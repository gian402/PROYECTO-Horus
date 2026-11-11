<?php

use App\Models\Enrollment;
use App\Models\Student;

$valid = fn (array $overrides = []) => array_merge([
    'dni' => '70123456',
    'first_name' => 'Ana',
    'last_name' => 'Pérez',
    'email' => 'ana@example.com',
    'phone' => '987654321',
], $overrides);

describe('authorization', function () {
    it('controls access to the list', function (?string $role, int $status) {
        actingAsRole($role);

        $this->get(route('students.index'))->assertStatus($status);
    })->with([
        ['Super Admin', 200],
        ['Coordinador Académico', 200],
        ['Ponente', 200],
        [null, 403],
    ]);

    it('lets only managers create, edit and delete', function (string $role, int $status) {
        actingAsRole($role);
        $student = Student::factory()->create();

        $this->get(route('students.create'))->assertStatus($status);
        $this->get(route('students.edit', $student))->assertStatus($status);
        $this->delete(route('students.destroy', $student))->assertStatus($status === 200 ? 302 : 403);
    })->with([
        ['Coordinador Académico', 200],
        ['Ponente', 403],
    ]);

    it('redirects guests to login', function () {
        $this->get(route('students.index'))->assertRedirect(route('login'));
    });
});

it('lists and searches students by DNI, names and full name', function (string $term) {
    actingAsRole('Coordinador Académico');
    Student::factory()->create(['dni' => '70123456', 'first_name' => 'Ana María', 'last_name' => 'Quispe Rojas']);
    Student::factory()->create(['dni' => '40000000', 'first_name' => 'Luis', 'last_name' => 'Gómez']);

    $this->get(route('students.index', ['search' => $term]))
        ->assertInertia(fn ($page) => $page
            ->component('students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.dni', '70123456'));
})->with(['7012', 'quispe', 'ANA MARÍA', 'maría quispe']);

it('paginates the list', function () {
    actingAsRole('Coordinador Académico');
    Student::factory()->count(20)->create();

    $this->get(route('students.index'))
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 15)
            ->where('students.total', 20));
});

it('creates a student normalizing the DNI and email', function () use ($valid) {
    actingAsRole('Coordinador Académico');

    $response = $this->post(route('students.store'), $valid(['dni' => ' ab 123456 ', 'email' => 'Ana@Example.COM']));

    $student = Student::firstWhere('dni', 'AB123456');
    expect($student)->not->toBeNull()
        ->and($student->email)->toBe('ana@example.com');
    $response->assertRedirect(route('students.show', $student));
});

it('validates the student data in Spanish', function (array $overrides, string $field) use ($valid) {
    actingAsRole('Coordinador Académico');
    Student::factory()->create(['dni' => '11111111']);

    $this->post(route('students.store'), $valid($overrides))->assertSessionHasErrors($field);
})->with([
    'dni vacío' => [['dni' => ''], 'dni'],
    'dni duplicado' => [['dni' => '11111111'], 'dni'],
    'dni con guiones' => [['dni' => '7012-3456'], 'dni'],
    'dni corto' => [['dni' => '123'], 'dni'],
    'sin nombres' => [['first_name' => ''], 'first_name'],
    'correo inválido' => [['email' => 'no-es-correo'], 'email'],
    'teléfono inválido' => [['phone' => 'abc'], 'phone'],
]);

it('shows validation messages in Spanish', function () use ($valid) {
    actingAsRole('Coordinador Académico');

    $this->post(route('students.store'), $valid(['first_name' => '']))
        ->assertSessionHasErrors(['first_name' => 'El campo nombres es obligatorio.']);
});

it('updates a student keeping its own DNI valid', function () use ($valid) {
    actingAsRole('Coordinador Académico');
    $student = Student::factory()->create(['dni' => '70123456']);

    $this->put(route('students.update', $student), $valid(['first_name' => 'Ana Lucía']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('students.show', $student));

    expect($student->fresh()->first_name)->toBe('Ana Lucía');
});

it('shows the academic history', function () {
    actingAsRole('Ponente');
    $enrollment = Enrollment::factory()->approved(17)->create();

    $this->get(route('students.show', $enrollment->student))
        ->assertInertia(fn ($page) => $page
            ->component('students/Show')
            ->has('enrollments', 1)
            ->where('enrollments.0.status', 'aprobado')
            ->where('enrollments.0.registration_code', $enrollment->registration_code)
            ->where('can.update', false));
});

it('deletes a student without enrollments', function () {
    actingAsRole('Coordinador Académico');
    $student = Student::factory()->create();

    $this->delete(route('students.destroy', $student))->assertRedirect(route('students.index'));

    expect(Student::find($student->id))->toBeNull();
});

it('refuses to delete a student with enrollments and explains why', function () {
    actingAsRole('Coordinador Académico');
    $enrollment = Enrollment::factory()->create();

    $this->from(route('students.show', $enrollment->student))
        ->delete(route('students.destroy', $enrollment->student))
        ->assertRedirect(route('students.show', $enrollment->student))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Student::find($enrollment->student_id))->not->toBeNull();
});
