<?php

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Teacher;

$valid = fn (array $overrides = []) => array_merge([
    'course_id' => Course::factory()->create()->id,
    'teacher_id' => Teacher::factory()->create()->id,
    'start_date' => '2024-03-01',
    'end_date' => '2024-03-15',
    'year' => 2024,
], $overrides);

it('creates an edition', function () use ($valid) {
    actingAsRole('Coordinador Académico');

    $this->post(route('editions.store'), $valid())->assertSessionHasNoErrors();

    expect(CourseEdition::count())->toBe(1);
});

it('validates the edition data', function (array $overrides, string $field) use ($valid) {
    actingAsRole('Coordinador Académico');

    $this->post(route('editions.store'), $valid($overrides))->assertSessionHasErrors($field);
})->with([
    'fin antes del inicio' => [['end_date' => '2024-02-01'], 'end_date'],
    'año fuera de rango' => [['year' => 1990], 'year'],
    'curso inexistente' => [['course_id' => 999999], 'course_id'],
    'ponente inexistente' => [['teacher_id' => 999999], 'teacher_id'],
]);

it('allows an edition without teacher', function () use ($valid) {
    actingAsRole('Coordinador Académico');

    $this->post(route('editions.store'), $valid(['teacher_id' => null]))->assertSessionHasNoErrors();
});

it('locks course and year once there are enrollments', function () use ($valid) {
    actingAsRole('Coordinador Académico');
    $enrollment = Enrollment::factory()->create();
    $edition = $enrollment->courseEdition;

    $this->put(route('editions.update', $edition), $valid([
        'course_id' => Course::factory()->create()->id,
        'year' => $edition->year + 1,
    ]))->assertSessionHasErrors(['course_id', 'year']);

    $this->put(route('editions.update', $edition), [
        'course_id' => $edition->course_id,
        'teacher_id' => null,
        'start_date' => $edition->start_date->toDateString(),
        'end_date' => $edition->end_date->toDateString(),
        'year' => $edition->year,
    ])->assertSessionHasNoErrors();
});

it('filters editions by course and year', function () {
    actingAsRole('Coordinador Académico');
    $pa = Course::factory()->create(['code' => 'PA']);
    CourseEdition::factory()->for($pa)->create(['start_date' => '2024-01-10', 'end_date' => '2024-01-20', 'year' => 2024]);
    CourseEdition::factory()->for($pa)->create(['start_date' => '2025-01-10', 'end_date' => '2025-01-20', 'year' => 2025]);
    CourseEdition::factory()->create();

    $this->get(route('editions.index', ['course_id' => $pa->id, 'year' => 2025]))
        ->assertInertia(fn ($page) => $page->has('editions.data', 1)->where('editions.data.0.year', 2025));
});

it('shows only their own editions to a Ponente', function () {
    $user = actingAsRole('Ponente');
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $own = CourseEdition::factory()->for($teacher)->create();
    $other = CourseEdition::factory()->create();

    $this->get(route('editions.index'))
        ->assertInertia(fn ($page) => $page->has('editions.data', 1)->where('editions.data.0.id', $own->id));

    $this->get(route('editions.show', $own))->assertOk();
    $this->get(route('editions.show', $other))->assertForbidden();
    $this->get(route('editions.create'))->assertForbidden();
});

it('separates finished editions from ongoing ones', function () {
    actingAsRole('Coordinador Académico');
    $finished = CourseEdition::factory()->create(['start_date' => '2024-01-10', 'end_date' => '2024-01-20', 'year' => 2024]);
    $ongoing = CourseEdition::factory()->create(['start_date' => now()->toDateString(), 'end_date' => now()->addDays(5)->toDateString(), 'year' => now()->year]);
    $historical = CourseEdition::factory()->create(['start_date' => null, 'end_date' => null, 'year' => 2023]);

    $this->get(route('editions.index', ['status' => 'finalizada']))
        ->assertInertia(fn ($page) => $page
            ->has('editions.data', 2)
            ->where('editions.data.0.finished', true)
            ->where('editions.data.1.finished', true));

    $this->get(route('editions.index', ['status' => 'en_curso']))
        ->assertInertia(fn ($page) => $page
            ->has('editions.data', 1)
            ->where('editions.data.0.id', $ongoing->id)
            ->where('editions.data.0.finished', false));

    $this->get(route('editions.index'))
        ->assertInertia(fn ($page) => $page->has('editions.data', 3));

    expect($finished->fresh()->isFinished())->toBeTrue()
        ->and($historical->fresh()->isFinished())->toBeTrue()
        ->and($ongoing->fresh()->isFinished())->toBeFalse();
});

it('counts finished editions per course, scoped to what the user can see', function () {
    $pa = Course::factory()->create(['code' => 'PA']);
    $inm = Course::factory()->create(['code' => 'INM']);

    CourseEdition::factory()->for($pa)->create(['start_date' => '2024-01-10', 'end_date' => '2024-01-20', 'year' => 2024]);
    CourseEdition::factory()->for($pa)->create(['start_date' => '2024-02-10', 'end_date' => '2024-02-20', 'year' => 2024]);
    CourseEdition::factory()->for($inm)->create(['start_date' => '2024-01-10', 'end_date' => '2024-01-20', 'year' => 2024]);
    CourseEdition::factory()->for($pa)->create(['start_date' => now()->toDateString(), 'end_date' => now()->addDays(5)->toDateString(), 'year' => now()->year]);

    actingAsRole('Coordinador Académico');
    $this->get(route('editions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('finishedByCourse', 2)
            ->where('finishedByCourse.0.code', 'PA')
            ->where('finishedByCourse.0.finished_count', 2)
            ->where('finishedByCourse.1.code', 'INM')
            ->where('finishedByCourse.1.finished_count', 1));

    // Un Ponente sin ediciones propias no ve el resumen de nadie más.
    actingAsRole('Ponente');
    $this->get(route('editions.index'))
        ->assertInertia(fn ($page) => $page->has('finishedByCourse', 0));
});

it('refuses to delete an edition with enrollments', function () {
    actingAsRole('Coordinador Académico');
    $enrollment = Enrollment::factory()->create();

    $this->delete(route('editions.destroy', $enrollment->courseEdition))->assertInertiaFlash('toast.type', 'error');

    expect($enrollment->courseEdition->fresh())->not->toBeNull();
});
