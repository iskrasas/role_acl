<?php

namespace RoleAccess\Acl\Http\Middleware;

use RoleAccess\Acl\Services\AclService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only members of the configured admin group (config('acl.admin_group')) through.
 * Works on top of RoleAccess, it does not replace it.
 */
class AdminOnly
{
    public function __construct(private readonly AclService $acl)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $this->acl->isAdmin($user), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
