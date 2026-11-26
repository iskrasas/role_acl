<?php

namespace RoleAccess\Acl\Http\Controllers\Api;

use RoleAccess\Acl\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Base of the ACL JSON controllers. Responses use the envelope
 * {"meta": {"result": "data|empty|error", "time": ...}, "data": {...}, "error": [...]}.
 * Validation failures (also those thrown by AclService, e.g. "you would lose access")
 * become HTTP 422: "error" holds the flat message list, "meta.errors" the messages per field.
 */
abstract class AclApiController extends Controller
{
    /** @var list<string> */
    protected array $errors = [];

    /** @var array<string, mixed> */
    protected array $metaOut = [];

    public function callAction(string $method, array $parameters): mixed
    {
        try {
            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException $e) {
            $this->errors = collect($e->errors())->flatten()->values()->all();
            $this->metaOut['errors'] = $e->errors();

            return $this->answer(code: 422);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function answer(array $data = [], int $code = 200): JsonResponse
    {
        $out = ['meta' => ['result' => $data === [] ? 'empty' : 'data', 'time' => date('Y-m-d H:i:s P')] + $this->metaOut];
        $out['data'] = $data;

        if ($code !== 200) {
            $out['meta']['result'] = 'error';
            $out['error'] = $this->errors;
        }

        return response()->json($out, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE);
    }
}
