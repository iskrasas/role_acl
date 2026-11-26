<?php

namespace RoleAccess\Acl\Http\Controllers\Web;

use RoleAccess\Acl\Http\Controllers\Controller;
use RoleAccess\Acl\Models\AclRoute;
use RoleAccess\Acl\Services\AclResolver;
use RoleAccess\Acl\Services\AclRules;
use RoleAccess\Acl\Services\AclService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AclRouteController extends Controller
{
    public function index(): View
    {
        return view('acl::routes.index', [
            'routes' => AclRoute::query()
                ->withCount(['groupPermissions', 'staffPermissions'])
                ->orderBy('pattern')
                ->get(),
            'matches' => AclResolver::matchCounts(),
        ]);
    }

    public function create(): View
    {
        return view('acl::routes.form', ['aclRoute' => new AclRoute()]);
    }

    public function store(Request $request, AclService $acl): RedirectResponse
    {
        $acl->saveRoute(new AclRoute(), $this->validated($request), $request->user());

        return redirect()->route('acl.routes.index')->with('success', 'Шаблон добавлен');
    }

    public function edit(AclRoute $aclRoute): View
    {
        return view('acl::routes.form', ['aclRoute' => $aclRoute]);
    }

    public function update(Request $request, AclRoute $aclRoute, AclService $acl): RedirectResponse
    {
        $acl->saveRoute($aclRoute, $this->validated($request, $aclRoute), $request->user());

        return redirect()->route('acl.routes.index')->with('success', 'Шаблон сохранён');
    }

    public function destroy(Request $request, AclRoute $aclRoute, AclService $acl): RedirectResponse
    {
        $acl->deleteRoute($aclRoute, $request->user());

        return redirect()->route('acl.routes.index')->with('success', 'Шаблон удалён вместе с его правилами');
    }

    /**
     * @return array{pattern: string, description: string|null, icon: string|null, order_index: int}
     */
    private function validated(Request $request, ?AclRoute $current = null): array
    {
        $data = $request->validate(AclRules::route($current?->id));

        $data['description'] ??= null;
        $data['icon'] ??= null;
        $data['order_index'] ??= 0;

        return $data;
    }
}
