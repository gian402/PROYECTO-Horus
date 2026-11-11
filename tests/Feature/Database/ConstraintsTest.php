<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| En PostgreSQL una violación aborta la transacción de la prueba, por eso
| cada caso provoca una sola violación y la verifica por nombre de restricción.
*/

describe('unique constraints', function () {
    it('rejects a duplicated student DNI', function () {
        Student::factory()->create(['dni' => '70000001']);

        expect(fn () => Student::factory()->create(['dni' => '70000001']))
            ->toThrow(QueryException::class, 'students_dni_unique');
    });

    it('rejects a duplicated teacher DNI', function () {
        Teacher::factory()->create(['dni' => '40000001']);

        expect(fn () => Teacher::factory()->create(['dni' => '40000001']))
            ->toThrow(QueryException::class, 'teachers_dni_unique');
    });

    it('rejects a duplicated course code even with different casing', function () {
        Course::factory()->create(['code' => 'PA']);

        expect(fn () => Course::factory()->create(['code' => 'pa']))
            ->toThrow(QueryException::class, 'courses_code_unique');
    });

    it('rejects a duplicated registration code', function () {
        Enrollment::factory()->create(['registration_code' => 'HGC-PA-01-024']);

        expect(fn () => Enrollment::factory()->create(['registration_code' => 'HGC-PA-01-024']))
            ->toThrow(QueryException::class, 'enrollments_registration_code_unique');
    });

    it('rejects enrolling the same student twice in the same edition', function () {
        $enrollment = Enrollment::factory()->create();

        expect(fn () => Enrollment::factory()->create([
            'student_id' => $enrollment->student_id,
            'course_edition_id' => $enrollment->course_edition_id,
        ]))->toThrow(QueryException::class, 'enrollments_student_id_course_edition_id_unique');
    });

    it('rejects a duplicated certificate hash', function () {
        $first = Certificate::factory()->create();
        $second = Certificate::factory()->make();
        $second->unique_hash = $first->unique_hash;

        expect(fn () => $second->save())
            ->toThrow(QueryException::class, 'certificates_unique_hash_unique');
    });
});

describe('check constraints', function () {
    it('rejects grades outside the 0-20 scale', function (float $grade) {
        expect(fn () => Enrollment::factory()->create(['grade' => $grade]))
            ->toThrow(QueryException::class, 'enrollments_grade_range');
    })->with([20.01, -1.0]);

    it('rejects an invalid status written directly to the database', function () {
        $enrollment = Enrollment::factory()->create();

        // Con nota presente, la coherencia nota/estado se cumple y solo falla el dominio de estados.
        expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['grade' => 15, 'status' => 'retirado']))
            ->toThrow(QueryException::class, 'enrollments_status_values');
    });

    it('rejects a grade without a final status written directly to the database', function () {
        $enrollment = Enrollment::factory()->create();

        expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['grade' => 15]))
            ->toThrow(QueryException::class, 'enrollments_grade_status_consistency');
    });

    it('rejects an edition that ends before it starts', function () {
        expect(fn () => CourseEdition::factory()->create([
            'start_date' => '2024-05-10',
            'end_date' => '2024-05-09',
        ]))->toThrow(QueryException::class, 'course_editions_dates_check');
    });

    it('rejects an out of range academic year', function () {
        expect(fn () => CourseEdition::factory()->create(['year' => 1999]))
            ->toThrow(QueryException::class, 'course_editions_year_check');
    });

    it('rejects course codes with characters not allowed in registration codes', function (string $code) {
        expect(fn () => DB::table('courses')->insert(['code' => $code, 'name' => 'X']))
            ->toThrow(QueryException::class, 'courses_code_format');
    })->with(['P A', '-PA', 'PA-', 'NUT--DIET', 'pa']);

    it('accepts course codes with inner hyphens', function () {
        DB::table('courses')->insert(['code' => 'NUT-DIET', 'name' => 'X']);

        expect(DB::table('courses')->where('code', 'NUT-DIET')->exists())->toBeTrue();
    });

    it('rejects a blank student DNI', function () {
        expect(fn () => Student::factory()->create(['dni' => '   ']))
            ->toThrow(QueryException::class, 'students_dni_not_blank');
    });

    it('rejects a revocation dated before the issue date', function () {
        expect(fn () => Certificate::factory()->create([
            'issued_at' => '2024-06-10 10:00:00',
            'revoked_at' => '2024-06-09 10:00:00',
        ]))->toThrow(QueryException::class, 'certificates_revoked_after_issued');
    });
});

describe('foreign keys to missing records', function () {
    it('rejects an edition for a missing course', function () {
        expect(fn () => CourseEdition::factory()->create(['course_id' => 999999]))
            ->toThrow(QueryException::class, 'course_editions_course_id_foreign');
    });

    it('rejects an edition for a missing teacher', function () {
        expect(fn () => CourseEdition::factory()->create(['teacher_id' => 999999]))
            ->toThrow(QueryException::class, 'course_editions_teacher_id_foreign');
    });

    it('rejects an enrollment for a missing student', function () {
        expect(fn () => Enrollment::factory()->create(['student_id' => 999999]))
            ->toThrow(QueryException::class, 'enrollments_student_id_foreign');
    });

    it('rejects an enrollment for a missing edition', function () {
        expect(fn () => Enrollment::factory()->create(['course_edition_id' => 999999]))
            ->toThrow(QueryException::class, 'enrollments_course_edition_id_foreign');
    });

    it('rejects a certificate for a missing enrollment', function () {
        expect(fn () => Certificate::factory()->create(['enrollment_id' => 999999]))
            ->toThrow(QueryException::class, 'certificates_enrollment_id_foreign');
    });
});
