<?php

namespace App\Http\Controllers;

use App\Console\Commands\CreateAdminUser;
use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use InteractsWithToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['roles:id,name', 'teacher:id,user_id,full_name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'teacher' => $user->teacher ? ['id' => $user->teacher->id, 'full_name' => $user->teacher->full_name] : null,
                'created_at' => $user->created_at?->toDateString(),
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => RoleSeeder::ROLES,
            'teachers' => Teacher::query()->orderBy('full_name')->get(['id', 'full_name', 'user_id']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $role = $request->string('role')->toString();
        $teacherId = $role === 'Ponente' ? $request->integer('teacher_id') ?: null : null;

        $isSuperAdmin = $user->hasRole(CreateAdminUser::ROLE);
        $losesSuperAdmin = $isSuperAdmin && $role !== CreateAdminUser::ROLE;

        if ($losesSuperAdmin && $user->is($request->user())) {
            return back()->withErrors(['role' => 'No puedes quitarte tu propio rol de Super Admin.']);
        }

        if ($losesSuperAdmin && User::role(CreateAdminUser::ROLE)->count() <= 1) {
            return back()->withErrors(['role' => 'Debe existir al menos un Super Admin.']);
        }

        DB::transaction(function () use ($user, $role, $teacherId): void {
            $user->syncRoles([$role]);

            Teacher::where('user_id', $user->id)
                ->when($teacherId, fn ($q) => $q->where('id', '!=', $teacherId))
                ->update(['user_id' => null]);

            if ($teacherId) {
                Teacher::whereKey($teacherId)->update(['user_id' => $user->id]);
            }
        });

        $this->toast("Usuario {$user->name} actualizado.");

        return back();
    }
}
