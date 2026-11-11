<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;

beforeEach(function () {
    $this->course = Course::factory()->create(['code' => 'PA']);
    $this->teacher = Teacher::factory()->create();
    $this->edition = CourseEdition::factory()->for($this->course)->for($this->teacher)->create();
    $this->student = Student::factory()->create();
    $this->enrollment = Enrollment::factory()
        ->for($this->student)
        ->for($this->edition, 'courseEdition')
        ->approved()
        ->create();
});

it('links a course to its editions', function () {
    expect($this->course->editions)->toHaveCount(1)
        ->and($this->edition->course->is($this->course))->toBeTrue();
});

it('links a teacher to its editions', function () {
    expect($this->teacher->editions)->toHaveCount(1)
        ->and($this->edition->teacher->is($this->teacher))->toBeTrue();
});

it('links an edition to its enrollments', function () {
    expect($this->edition->enrollments)->toHaveCount(1)
        ->and($this->enrollment->courseEdition->is($this->edition))->toBeTrue();
});

it('links a student to enrollments across different courses', function () {
    Enrollment::factory()->for($this->student)->create();

    expect($this->student->enrollments)->toHaveCount(2)
        ->and($this->enrollment->student->is($this->student))->toBeTrue();
});

it('keeps the certificate history and exposes only the active certificate', function () {
    $old = Certificate::factory()->for($this->enrollment)->revoked()->create();
    $current = Certificate::factory()->for($this->enrollment)->create();

    expect($this->enrollment->certificates)->toHaveCount(2)
        ->and($this->enrollment->activeCertificate->is($current))->toBeTrue()
        ->and($old->enrollment->is($this->enrollment))->toBeTrue()
        ->and(Certificate::valid()->pluck('id')->all())->toBe([$current->id]);
});
