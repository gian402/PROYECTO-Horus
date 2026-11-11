<?php

use App\Actions\Enrollments\EnrollStudent;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\RegistrationSequence;
use App\Models\Student;
use App\Services\RegistrationCodeGenerator;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->generator = app(RegistrationCodeGenerator::class);
    $this->pa = Course::factory()->create(['code' => 'PA']);
    $this->edition2024 = CourseEdition::factory()->for($this->pa)->create([
        'start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024,
    ]);
});

it('generates the first code of a course and year', function () {
    expect($this->generator->next($this->edition2024))->toBe('HGC-PA-01-024');
});

it('shares the sequence between editions of the same course and year', function () {
    $otherEdition2024 = CourseEdition::factory()->for($this->pa)->create([
        'start_date' => '2024-08-01', 'end_date' => '2024-08-20', 'year' => 2024,
    ]);

    expect($this->generator->next($this->edition2024))->toBe('HGC-PA-01-024')
        ->and($this->generator->next($otherEdition2024))->toBe('HGC-PA-02-024')
        ->and($this->generator->next($this->edition2024))->toBe('HGC-PA-03-024');
});

it('restarts the sequence for a new year', function () {
    $edition2025 = CourseEdition::factory()->for($this->pa)->create([
        'start_date' => '2025-02-01', 'end_date' => '2025-02-20', 'year' => 2025,
    ]);

    $this->generator->next($this->edition2024);

    expect($this->generator->next($edition2025))->toBe('HGC-PA-01-025');
});

it('keeps independent sequences per course', function () {
    $iny = CourseEdition::factory()
        ->for(Course::factory()->create(['code' => 'INY']))
        ->create(['start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024]);

    $this->generator->next($this->edition2024);

    expect($this->generator->next($iny))->toBe('HGC-INY-01-024');
});

it('grows beyond two digits after 99', function () {
    RegistrationSequence::create(['course_id' => $this->pa->id, 'year' => 2024, 'last_number' => 99]);

    expect($this->generator->next($this->edition2024))->toBe('HGC-PA-100-024');
});

it('rolls back the consumed number when the surrounding transaction fails', function () {
    try {
        DB::transaction(function () {
            $this->generator->next($this->edition2024);

            throw new RuntimeException('fallo simulado al guardar la matrícula');
        });
    } catch (RuntimeException) {
        // esperado
    }

    expect($this->generator->next($this->edition2024))->toBe('HGC-PA-01-024');
});

it('enrolls a student assigning the code in one transaction', function () {
    $student = Student::factory()->create();

    $enrollment = app(EnrollStudent::class)->handle($student, $this->edition2024);

    expect($enrollment->fresh())
        ->registration_code->toBe('HGC-PA-01-024')
        ->student_id->toBe($student->id);
});

it('refuses a duplicate enrollment without consuming a number', function () {
    $student = Student::factory()->create();
    $action = app(EnrollStudent::class);
    $action->handle($student, $this->edition2024);

    expect(fn () => $action->handle($student, $this->edition2024))
        ->toThrow(DomainException::class);

    expect(Enrollment::count())->toBe(1)
        ->and($this->generator->next($this->edition2024))->toBe('HGC-PA-02-024');
});
