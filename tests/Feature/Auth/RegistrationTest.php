<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the registration screen', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});

it('registers new staff and logs them in', function () {
    $this->post(route('register.store'), [
        'name' => 'Compañero',
        'email' => 'companero@horusgroup.local',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('grants Super Admin to self-registered accounts when enabled', function () {
    config(['fortify.register_as_super_admin' => true]);

    $this->post(route('register.store'), [
        'name' => 'Compañero',
        'email' => 'companero@horusgroup.local',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    // Sin ejecutar RoleSeeder: el rol se crea si no existe.
    expect(User::firstWhere('email', 'companero@horusgroup.local')->hasRole('Super Admin'))->toBeTrue();
});

it('does not grant any role when disabled', function () {
    config(['fortify.register_as_super_admin' => false]);
    $this->seed(RoleSeeder::class);

    $this->post(route('register.store'), [
        'name' => 'Compañero',
        'email' => 'companero@horusgroup.local',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(User::firstWhere('email', 'companero@horusgroup.local')->roles)->toBeEmpty();
});

it('never grants Super Admin in production even if enabled', function () {
    config(['fortify.register_as_super_admin' => true]);
    expect(CreateNewUser::registersAsSuperAdmin())->toBeTrue();

    // Simula producción solo para la decisión (sin crear usuario: en producción
    // la política de contraseñas consulta un servicio externo).
    $this->app['env'] = 'production';

    expect(CreateNewUser::registersAsSuperAdmin())->toBeFalse();
});

it('rejects a duplicated email regardless of case', function () {
    User::factory()->create(['email' => 'admin@horusgroup.local']);

    $this->post(route('register.store'), [
        'name' => 'Otro',
        'email' => 'Admin@HorusGroup.local',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires matching password confirmation', function () {
    $this->post(route('register.store'), [
        'name' => 'Compañero',
        'email' => 'companero@horusgroup.local',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});
