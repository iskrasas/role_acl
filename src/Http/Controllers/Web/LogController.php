<?php

namespace RoleAccess\Acl\Http\Controllers\Web;

use RoleAccess\Acl\Http\Controllers\Controller;
use RoleAccess\Acl\Models\AclLog;
use RoleAccess\Acl\Models\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Read-only view of the access log (acl_logs + acl_log_payloads) with filters.
 * Newest first by id: ids grow with time, and no index on created_at is needed.
 */
class LogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'staff_id' => ['nullable', 'integer'],
            'route' => ['nullable', 'string', 'max:191'],
            'method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'status' => ['nullable', 'integer', 'between:100,599'],
            'object_id' => ['nullable', 'string', 'max:191'],
            'uri' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AclLog::query()
            ->with(['route:id,pattern', 'staff.user:id,name'])
            ->when($filters['staff_id'] ?? null, fn (Builder $query, $id) => $query->where('staff_id', $id))
            ->when($filters['route'] ?? null, fn (Builder $query, string $route) => $query->whereHas(
                'route',
                fn (Builder $query) => $this->contains($query, 'pattern', $route),
            ))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status_code', $status))
            ->when($filters['object_id'] ?? null, fn (Builder $query, string $id) => $query->where('object_id', $id))
            ->when($filters['uri'] ?? null, fn (Builder $query, string $uri) => $this->contains($query, 'uri', $uri))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $this->localDate($from, 'startOfDay')))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', $this->localDate($to, 'endOfDay')))
            ->when($filters['q'] ?? null, fn (Builder $query, string $text) => $query->whereHas(
                'payload',
                fn (Builder $query) => $this->contains($query, 'payload', $text),
            ))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('acl::logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'staff' => Staff::query()
                ->with('user:id,name')
                ->get()
                ->sortBy(fn (Staff $member): string => mb_strtolower((string) $member->user?->name))
                ->values(),
        ]);
    }

    public function show(AclLog $aclLog): View
    {
        $aclLog->load(['route:id,pattern', 'staff.user:id,name,email', 'payload']);

        return view('acl::logs.show', ['log' => $aclLog]);
    }

    /**
     * Start or end of a day typed by the user (in the display time zone), converted to the app time zone for the query.
     */
    private function localDate(string $date, string $bound): Carbon
    {
        return Carbon::parse($date, config('acl_log.timezone'))->{$bound}()->setTimezone(config('app.timezone'));
    }

    /**
     * "column contains value", with the wildcards of the value taken literally
     * (an explicit ESCAPE works the same on MySQL, PostgreSQL and SQLite).
     *
     * @param  Builder<*>  $query
     */
    private function contains(Builder $query, string $column, string $value): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';

        return $query->whereRaw("{$column} like ? escape '!'", [$pattern]);
    }
}
