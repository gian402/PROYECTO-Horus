<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Solo datos estructurales. No se crean usuarios con contraseñas
     * predeterminadas: el primer Super Admin se crea manualmente.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}
