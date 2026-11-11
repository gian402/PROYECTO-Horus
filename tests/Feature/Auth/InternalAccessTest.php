<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/*
| CRM interno: registro habilitado para el personal (las cuentas nuevas no
| reciben rol), sin verificación por correo, sin passkeys y sin
| autoeliminación de cuentas.
*/

it('enables only the agreed Fortify features', function () {
    expect(Features::enabled(Features::registration()))->toBeTrue()
        ->and(Features::enabled(Features::resetPasswords()))->toBeTrue()
        ->and(Features::enabled(Features::twoFactorAuthentication()))->toBeTrue()
        ->and(Features::enabled(Features::emailVerification()))->toBeFalse();
});

it('does not let staff delete their own account', function () {
    $user = User::factory()->create();

    expect(Route::has('profile.destroy'))->toBeFalse();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});

it('redirects the root to the login when there is no session', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('login'));
});
