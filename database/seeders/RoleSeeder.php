<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos del personal interno. No existe rol de estudiante.
 * Idempotente: puede ejecutarse varias veces; sincroniza los permisos de cada rol.
 */
class RoleSeeder extends Seeder
{
    public const ROLES = [
        'Super Admin',
        'Coordinador Académico',
        'Ponente',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (PermissionName::byRole() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
