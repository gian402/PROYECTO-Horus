<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the confirm password screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ConfirmPassword'));
});

it('requires authentication to confirm the password', function () {
    $this->get(route('password.confirm'))->assertRedirect(route('login'));
});
