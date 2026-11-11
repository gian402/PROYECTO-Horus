<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates a verified Super Admin with a generated password', function () {
    $this->seed(RoleSeeder::class);

    $exitCode = Artisan::call('horus:create-admin', [
        '--name' => 'Administrador',
        '--email' => 'ADMIN@horusgroup.local',
        '--generate-password' => true,
    ]);

    $output = Artisan::output();
    $user = User::firstWhere('email', 'admin@horusgroup.local');

    expect($exitCode)->toBe(0)
        ->and($user)->not->toBeNull()
        ->and($user->hasRole('Super Admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    // La contraseña impresa es la que quedó guardada (hasheada).
    $printed = collect(preg_split('/\R/', trim($output)))->last();
    expect(strlen($printed))->toBe(20)
        ->and(Hash::check($printed, $user->password))->toBeTrue();
});

it('rejects a duplicated email', function () {
    $this->seed(RoleSeeder::class);
    User::factory()->create(['email' => 'admin@horusgroup.local']);

    $this->artisan('horus:create-admin', [
        '--name' => 'Otro',
        '--email' => 'admin@horusgroup.local',
        '--generate-password' => true,
    ])->assertFailed();

    expect(User::where('email', 'admin@horusgroup.local')->count())->toBe(1);
});

it('fails when the Super Admin role has not been seeded', function () {
    $this->artisan('horus:create-admin', [
        '--name' => 'Administrador',
        '--email' => 'admin@horusgroup.local',
        '--generate-password' => true,
    ])->assertFailed();

    expect(User::count())->toBe(0);
});
