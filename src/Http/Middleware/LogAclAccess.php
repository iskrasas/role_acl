<?php

namespace RoleAccess\Acl\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes an acl_logs row (+ acl_log_payloads with the input) for every authenticated request,
 * denied ones (403) included.
 *
 * The row is written in handle(), BEFORE the request is processed, so it exists even when the
 * request ends with a 500, a fatal error or a timeout. The response status is filled in later, in
 * terminate(); a row whose status_code stays null never got a response.
 *
 * Put it after the authentication middleware (the user must be known) and BEFORE roleAccess,
 * otherwise a denied request never reaches it. A logging failure is reported, never thrown.
 */
class LogAclAccess
{
    private const ATTRIBUTE = 'acl_log_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (config('acl_log.enabled')) {
            try {
                $logId = $this->write($request);

                if ($logId !== null) {
                    $request->attributes->set(self::ATTRIBUTE, $logId);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $logId = $request->attributes->get(self::ATTRIBUTE);

        if ($logId === null) {
            return;
        }

        try {
            DB::table('acl_logs')->where('id', $logId)->update([
                'status_code' => $response->getStatusCode(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function write(Request $request): ?int
    {
        $user = $request->user();
        $route = $request->route();

        if ($user === null || ! $route instanceof Route) {
            return null;
        }

        $name = $route->getName();
        $now = now();

        return DB::transaction(function () use ($request, $route, $user, $name, $now): int {
            $logId = DB::table('acl_logs')->insertGetId([
                'route_id' => $name ? DB::table('acl_routes')->where('pattern', $name)->value('id') : null,
                'staff_id' => DB::table('acl_staff')->where('user_id', $user->getAuthIdentifier())->value('id'),
                'method' => $request->method(),
                'uri' => mb_substr('/'.ltrim($request->path(), '/'), 0, 1024),
                'object_id' => $this->objectId($route),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $payload = $this->payload($request);

            if ($payload !== null) {
                DB::table('acl_log_payloads')->insert([
                    'acl_log_id' => $logId,
                    'payload' => $payload,
                ]);
            }

            return (int) $logId;
        });
    }

    /**
     * Value of the last route parameter: {action} in actions/{action}/edit.
     */
    private function objectId(Route $route): ?string
    {
        $last = Arr::last($route->parametersWithoutNulls());

        $value = match (true) {
            $last instanceof Model => $last->getRouteKey(),
            is_string($last), is_int($last) => $last,
            default => null,
        };

        return $value === null ? null : mb_substr((string) $value, 0, 191);
    }

    /**
     * JSON of the request input, or null when there is none.
     */
    private function payload(Request $request): ?string
    {
        $data = $this->sanitize($request->all());

        if ($data === []) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false) {
            return null;
        }

        if (strlen($json) > (int) config('acl_log.max_payload_bytes')) {
            return json_encode(['_truncated' => true, 'bytes' => strlen($json)]);
        }

        return $json;
    }

    /**
     * Masks hidden keys and replaces uploaded files with their real path (the original name if unavailable).
     *
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function sanitize(array $data): array
    {
        $hidden = array_map('strtolower', (array) config('acl_log.hidden'));
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $hidden, true)) {
                $clean[$key] = '***';

                continue;
            }

            $clean[$key] = match (true) {
                $value instanceof UploadedFile => $value->getRealPath() ?: $value->getClientOriginalName(),
                is_array($value) => $this->sanitize($value),
                default => $value,
            };
        }

        return $clean;
    }
}
