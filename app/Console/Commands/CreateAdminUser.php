<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('horus:create-admin
    {--name= : Nombre del administrador}
    {--email= : Correo de inicio de sesión}
    {--generate-password : Genera una contraseña aleatoria y la muestra una sola vez}')]
#[Description('Crea un usuario del personal con el rol Super Admin')]
class CreateAdminUser extends Command
{
    public const ROLE = 'Super Admin';

    public function handle(): int
    {
        if (! Role::where('name', self::ROLE)->where('guard_name', 'web')->exists()) {
            $this->error('El rol "'.self::ROLE.'" no existe. Ejecuta primero: php artisan db:seed --class=RoleSeeder');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text(label: 'Nombre', required: true);
        $email = mb_strtolower(trim((string) ($this->option('email') ?: text(label: 'Correo', required: true))));

        $generated = (bool) $this->option('generate-password');
        $plainPassword = $generated
            ? Str::password(20)
            : password(label: 'Contraseña', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plainPassword],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $plainPassword): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $plainPassword,
            ]);

            // Cuenta creada por un responsable: no requiere verificación por correo.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(self::ROLE);

            return $user;
        });

        $this->info("Super Admin creado: {$user->email} (id {$user->id}).");

        if ($generated) {
            $this->warn('Contraseña generada (se muestra UNA sola vez, cámbiala al iniciar sesión):');
            $this->line($plainPassword);
        }

        return self::SUCCESS;
    }
}
