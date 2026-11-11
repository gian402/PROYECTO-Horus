<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles iniciales del personal interno. No existe rol de estudiante.
 * Idempotente: puede ejecutarse varias veces sin duplicar registros.
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

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
