<?php

namespace App\Http\Middleware;

use App\Console\Commands\CreateAdminUser;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Solo para mostrar u ocultar elementos: la autorización real está en el backend.
                'roles' => fn () => $request->user()?->getRoleNames()->values()->all() ?? [],
                'permissions' => fn () => $this->permissionsFor($request->user()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Permisos efectivos del usuario. El Super Admin los tiene todos (Gate::before).
     *
     * @return list<string>
     */
    private function permissionsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->hasRole(CreateAdminUser::ROLE)) {
            return Permission::values();
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
