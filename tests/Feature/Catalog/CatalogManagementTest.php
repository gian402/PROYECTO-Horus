<?php

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Teacher;

describe('courses', function () {
    it('allows only the coordination to manage courses', function (?string $role, int $status) {
        actingAsRole($role);

        $this->get(route('courses.index'))->assertStatus($status);
        $this->get(route('courses.create'))->assertStatus($status);
    })->with([
        ['Super Admin', 200],
        ['Coordinador Académico', 200],
        ['Ponente', 403],
        [null, 403],
    ]);

    it('creates a course normalizing the code', function () {
        actingAsRole('Coordinador Académico');

        $this->post(route('courses.store'), ['code' => ' pa ', 'name' => 'Primeros Auxilios'])
            ->assertRedirect(route('courses.index'));

        expect(Course::firstWhere('code', 'PA')?->name)->toBe('Primeros Auxilios');
    });

    it('accepts hyphenated codes like the historical NUT-DIET', function () {
        actingAsRole('Coordinador Académico');

        $this->post(route('courses.store'), ['code' => 'nut-diet', 'name' => 'Nutrición y dietas'])
            ->assertSessionHasNoErrors();

        expect(Course::where('code', 'NUT-DIET')->exists())->toBeTrue();
    });

    it('validates the course data', function (array $data, string $field) {
        actingAsRole('Coordinador Académico');
        Course::factory()->create(['code' => 'INY']);

        $this->post(route('courses.store'), array_merge(['code' => 'NUT', 'name' => 'Nutrición'], $data))
            ->assertSessionHasErrors($field);
    })->with([
        'código vacío' => [['code' => ''], 'code'],
        'código duplicado' => [['code' => 'iny'], 'code'],
        'código con guion al borde' => [['code' => '-PA'], 'code'],
        'código con guion doble' => [['code' => 'NUT--DIET'], 'code'],
        'código con espacio' => [['code' => 'NUT DIET'], 'code'],
        'código largo' => [['code' => 'ABCDEFGHIJK'], 'code'],
        'sin nombre' => [['name' => ''], 'name'],
    ]);

    it('locks the code once the course has editions', function () {
        actingAsRole('Coordinador Académico');
        $course = Course::factory()->create(['code' => 'PA']);
        CourseEdition::factory()->for($course)->create();

        $this->put(route('courses.update', $course), ['code' => 'PAX', 'name' => 'Otro'])
            ->assertSessionHasErrors('code');

        $this->put(route('courses.update', $course), ['code' => 'PA', 'name' => 'Primeros Auxilios II'])
            ->assertSessionHasNoErrors();

        expect($course->fresh())->code->toBe('PA')->name->toBe('Primeros Auxilios II');
    });

    it('refuses to delete a course with editions', function () {
        actingAsRole('Coordinador Académico');
        $course = Course::factory()->create();
        CourseEdition::factory()->for($course)->create();

        $this->delete(route('courses.destroy', $course))->assertInertiaFlash('toast.type', 'error');

        expect($course->fresh())->not->toBeNull();
    });

    it('deletes an unused course', function () {
        actingAsRole('Coordinador Académico');
        $course = Course::factory()->create();

        $this->delete(route('courses.destroy', $course))->assertRedirect(route('courses.index'));

        expect(Course::find($course->id))->toBeNull();
    });
});

describe('teachers', function () {
    it('allows only the coordination to manage teachers', function (?string $role, int $status) {
        actingAsRole($role);

        $this->get(route('teachers.index'))->assertStatus($status);
    })->with([
        ['Coordinador Académico', 200],
        ['Ponente', 403],
    ]);

    it('creates and updates a teacher', function () {
        actingAsRole('Coordinador Académico');

        $this->post(route('teachers.store'), ['dni' => '40000001', 'full_name' => 'Luis Gómez', 'specialty' => 'Enfermería'])
            ->assertRedirect(route('teachers.index'));

        $teacher = Teacher::firstWhere('dni', '40000001');

        $this->put(route('teachers.update', $teacher), ['dni' => '40000001', 'full_name' => 'Luis Gómez Ruiz'])
            ->assertSessionHasNoErrors();

        expect($teacher->fresh())->full_name->toBe('Luis Gómez Ruiz')->specialty->toBeNull();
    });

    it('rejects a duplicated teacher DNI', function () {
        actingAsRole('Coordinador Académico');
        Teacher::factory()->create(['dni' => '40000001']);

        $this->post(route('teachers.store'), ['dni' => '40000001', 'full_name' => 'Otro'])
            ->assertSessionHasErrors('dni');
    });

    it('refuses to delete a teacher assigned to editions', function () {
        actingAsRole('Coordinador Académico');
        $edition = CourseEdition::factory()->create();

        $this->delete(route('teachers.destroy', $edition->teacher))->assertInertiaFlash('toast.type', 'error');

        expect($edition->teacher->fresh())->not->toBeNull();
    });
});
