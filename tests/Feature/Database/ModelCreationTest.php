<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;

it('creates a student with optional contact data', function () {
    $student = Student::create([
        'dni' => '70000001',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
    ]);

    expect($student->fresh())
        ->dni->toBe('70000001')
        ->email->toBeNull()
        ->phone->toBeNull()
        ->full_name->toBe('Ana Pérez');
});

it('keeps leading zeros in the DNI because it is stored as text', function () {
    Student::factory()->create(['dni' => '00123456']);

    expect(Student::firstWhere('dni', '00123456'))->not->toBeNull();
});

it('creates a course and normalizes its code to uppercase', function () {
    $course = Course::create(['code' => ' pa ', 'name' => 'Primeros Auxilios']);

    expect($course->fresh()->code)->toBe('PA');
});

it('creates a teacher', function () {
    $teacher = Teacher::create(['dni' => '40000001', 'full_name' => 'Luis Gómez', 'specialty' => 'Enfermería']);

    expect($teacher->fresh()->full_name)->toBe('Luis Gómez');
});

it('creates a course edition with date casts', function () {
    $edition = CourseEdition::factory()->create([
        'start_date' => '2024-03-01',
        'end_date' => '2024-03-15',
        'year' => 2024,
    ]);

    $fresh = $edition->fresh();

    expect($fresh->start_date->toDateString())->toBe('2024-03-01')
        ->and($fresh->end_date->toDateString())->toBe('2024-03-15')
        ->and($fresh->year)->toBe(2024);
});

it('allows an edition without an assigned teacher', function () {
    expect(CourseEdition::factory()->withoutTeacher()->create()->teacher_id)->toBeNull();
});

it('creates an enrollment that starts as cursando', function () {
    $enrollment = Enrollment::factory()->create();

    expect($enrollment->fresh())
        ->grade->toBeNull()
        ->status->toBe(EnrollmentStatus::Cursando);
});

it('derives the enrollment status from the grade on the backend', function (float $grade, EnrollmentStatus $expected) {
    $enrollment = Enrollment::factory()->create(['grade' => $grade]);

    expect($enrollment->fresh())
        ->grade->toBe(number_format($grade, 2, '.', ''))
        ->status->toBe($expected);
})->with([
    [14.0, EnrollmentStatus::Aprobado],
    [13.5, EnrollmentStatus::Desaprobado],
    [20.0, EnrollmentStatus::Aprobado],
]);

it('ignores a status sent by the client', function () {
    $enrollment = Enrollment::factory()->create();

    // status no es fillable: se descarta.
    $enrollment->fill(['status' => 'aprobado'])->save();
    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Cursando);

    // Aunque se asigne directamente, saving lo recalcula desde la nota.
    $enrollment->status = EnrollmentStatus::Aprobado;
    $enrollment->grade = 9;
    $enrollment->save();
    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Desaprobado);
});
