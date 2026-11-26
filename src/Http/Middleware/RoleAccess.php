<?php

namespace RoleAccess\Acl\Http\Middleware;

use RoleAccess\Acl\Services\AclResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-name based access control (acl_* tables).
 *
 * Must run after the authentication middleware. Denies (403) when there is no
 * user, the route has no name, or AclResolver does not allow the route name.
 */
class RoleAccess
{
    public function __construct(private readonly AclResolver $acl)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        abort_unless(
            $user !== null && $this->acl->can($user, $routeName),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
