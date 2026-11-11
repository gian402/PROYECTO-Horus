<?php

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\QueryException;

/*
| Todas las FK usan ON DELETE RESTRICT para proteger el historial académico:
| ningún registro con dependientes puede eliminarse.
*/

beforeEach(function () {
    $this->certificate = Certificate::factory()->create();
    $this->enrollment = $this->certificate->enrollment;
    $this->edition = $this->enrollment->courseEdition;
});

it('prevents deleting a course that has editions', function () {
    expect(fn () => $this->edition->course->delete())
        ->toThrow(QueryException::class, 'course_editions_course_id_foreign');
});

it('prevents deleting a teacher assigned to editions', function () {
    expect(fn () => $this->edition->teacher->delete())
        ->toThrow(QueryException::class, 'course_editions_teacher_id_foreign');
});

it('prevents deleting a student with enrollments', function () {
    expect(fn () => $this->enrollment->student->delete())
        ->toThrow(QueryException::class, 'enrollments_student_id_foreign');
});

it('prevents deleting an edition with enrollments', function () {
    expect(fn () => $this->edition->delete())
        ->toThrow(QueryException::class, 'enrollments_course_edition_id_foreign');
});

it('prevents deleting an enrollment with certificates', function () {
    expect(fn () => $this->enrollment->delete())
        ->toThrow(QueryException::class, 'certificates_enrollment_id_foreign');
});

it('allows deleting records without dependents', function () {
    $lonely = Enrollment::factory()->create();

    $lonely->delete();

    expect(Enrollment::find($lonely->id))->toBeNull();
});
