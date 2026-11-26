<?php

namespace RoleAccess\Acl\Services;

use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the web and API ACL controllers.
 */
final class AclRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function group(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('acl_groups', 'name')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function route(?int $ignoreId = null): array
    {
        return [
            'pattern' => [
                'required',
                'string',
                'max:191',
                // "*", "users.index" or a prefix wildcard "users.*"
                'regex:/^(\*|[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*(\.\*)?)$/',
                Rule::unique('acl_routes', 'pattern')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            // Font Awesome class; empty = not shown in the sidebar
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^fa-[a-z0-9-]+$/'],
            'order_index' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
