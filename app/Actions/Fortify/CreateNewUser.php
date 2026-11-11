<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Console\Commands\CreateAdminUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

/**
 * Registro del personal mediante /register.
 *
 * Con fortify.register_as_super_admin activo (por defecto solo en APP_ENV=local,
 * el entorno de desarrollo de cada integrante) la cuenta registrada recibe el
 * rol Super Admin. En producción nunca se aplica. En otros casos la cuenta
 * queda sin rol y un Super Admin debe asignárselo.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            if (self::registersAsSuperAdmin()) {
                // findOrCreate: funciona aunque aún no se haya ejecutado RoleSeeder.
                $user->assignRole(Role::findOrCreate(CreateAdminUser::ROLE, 'web'));
            }

            return $user;
        });
    }

    /**
     * Activo por configuración y jamás en producción (salvaguarda fija).
     */
    public static function registersAsSuperAdmin(): bool
    {
        return (bool) config('fortify.register_as_super_admin') && ! app()->isProduction();
    }
}
