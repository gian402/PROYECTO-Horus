<?php

use App\Models\Teacher;
use App\Models\User;

it('allows only the Super Admin to manage users', function (?string $role, int $status) {
    actingAsRole($role);

    $this->get(route('users.index'))->assertStatus($status);
})->with([
    ['Super Admin', 200],
    ['Coordinador Académico', 403],
    ['Ponente', 403],
    [null, 403],
]);

it('lists and searches users', function () {
    actingAsRole('Super Admin');
    User::factory()->create(['name' => 'María Quispe', 'email' => 'maria@horus.local']);
    User::factory()->create(['name' => 'Otro Usuario']);

    $this->get(route('users.index', ['search' => 'quispe']))
        ->assertInertia(fn ($page) => $page
            ->component('users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'maria@horus.local'));
});

it('assigns a role and links a teacher to a Ponente', function () {
    actingAsRole('Super Admin');
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create();

    $this->put(route('users.update', $user), ['role' => 'Ponente', 'teacher_id' => $teacher->id])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasRole('Ponente'))->toBeTrue()
        ->and($teacher->fresh()->user_id)->toBe($user->id);
});

it('unlinks the teacher when the role changes away from Ponente', function () {
    actingAsRole('Super Admin');
    $user = User::factory()->create();
    $user->assignRole('Ponente');
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    $this->put(route('users.update', $user), ['role' => 'Coordinador Académico'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->getRoleNames()->all())->toBe(['Coordinador Académico'])
        ->and($teacher->fresh()->user_id)->toBeNull();
});

it('rejects linking a teacher already linked to another account', function () {
    actingAsRole('Super Admin');
    $teacher = Teacher::factory()->create(['user_id' => User::factory()->create()->id]);
    $user = User::factory()->create();

    $this->put(route('users.update', $user), ['role' => 'Ponente', 'teacher_id' => $teacher->id])
        ->assertSessionHasErrors('teacher_id');
});

it('rejects unknown roles', function () {
    actingAsRole('Super Admin');

    $this->put(route('users.update', User::factory()->create()), ['role' => 'Estudiante'])
        ->assertSessionHasErrors('role');
});

it('prevents a Super Admin from removing their own role', function () {
    $admin = actingAsRole('Super Admin');
    User::factory()->create()->assignRole('Super Admin');

    $this->put(route('users.update', $admin), ['role' => 'Ponente'])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole('Super Admin'))->toBeTrue();
});

it('prevents leaving the system without a Super Admin', function () {
    actingAsRole('Super Admin');
    // El actuante pierde su rol por otra vía: queda un único Super Admin (el objetivo).
    $target = User::factory()->create();
    $target->assignRole('Super Admin');
    auth()->user()->syncRoles(['Super Admin']);
    auth()->user()->removeRole('Super Admin');
    auth()->user()->givePermissionTo('users.manage');

    $this->put(route('users.update', $target), ['role' => 'Ponente'])
        ->assertSessionHasErrors('role');

    expect($target->fresh()->hasRole('Super Admin'))->toBeTrue();
});

it('forbids non admins from updating roles', function () {
    actingAsRole('Coordinador Académico');

    $this->put(route('users.update', User::factory()->create()), ['role' => 'Super Admin'])
        ->assertForbidden();
});
