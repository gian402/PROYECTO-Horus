<?php

use App\Enums\Permission;
use App\Models\CourseEdition;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

it('seeds roles and permissions idempotently', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::count())->toBe(3)
        ->and(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(Role::findByName('Coordinador Académico')->hasPermissionTo('users.manage'))->toBeFalse()
        ->and(Role::findByName('Coordinador Académico')->hasPermissionTo('students.manage'))->toBeTrue()
        ->and(Role::findByName('Ponente')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['certificates.view', 'editions.view', 'grades.manage', 'students.view']);
});

it('lets the Super Admin pass every gate', function () {
    $admin = actingAsRole('Super Admin');

    expect($admin->can('users.manage'))->toBeTrue()
        ->and($admin->can('anything.not.defined'))->toBeTrue();
});

it('shares the effective permissions with the frontend', function (?string $role, array $expected, array $missing) {
    actingAsRole($role);

    $this->get(route('dashboard'))->assertInertia(function ($page) use ($expected, $missing) {
        $permissions = $page->toArray()['props']['auth']['permissions'];

        foreach ($expected as $p) {
            expect($permissions)->toContain($p);
        }
        foreach ($missing as $p) {
            expect($permissions)->not->toContain($p);
        }
    });
})->with([
    'super admin' => ['Super Admin', ['users.manage', 'students.manage'], []],
    'coordinador' => ['Coordinador Académico', ['students.manage', 'certificates.issue'], ['users.manage']],
    'ponente' => ['Ponente', ['students.view', 'grades.manage'], ['students.manage', 'users.manage']],
    'sin rol' => [null, [], ['students.view']],
]);

it('limits a Ponente to the editions they teach', function () {
    $user = actingAsRole('Ponente');
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $own = CourseEdition::factory()->for($teacher)->create();
    $other = CourseEdition::factory()->create();

    expect($user->can('view', $own))->toBeTrue()
        ->and($user->can('grade', $own))->toBeTrue()
        ->and($user->can('view', $other))->toBeFalse()
        ->and($user->can('grade', $other))->toBeFalse()
        ->and($user->can('update', $own))->toBeFalse()
        ->and(CourseEdition::visibleTo($user)->pluck('id')->all())->toBe([$own->id]);
});

it('lets the coordination see every edition', function () {
    $user = actingAsRole('Coordinador Académico');
    CourseEdition::factory()->count(2)->create();

    expect(CourseEdition::visibleTo($user)->count())->toBe(2);
});

it('keeps the teacher when its user account is deleted', function () {
    $user = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($teacher->fresh())->not->toBeNull()
        ->and($teacher->fresh()->user_id)->toBeNull();
});
