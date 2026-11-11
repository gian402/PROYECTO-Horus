<?php

use App\Enums\EnrollmentRole;
use App\Enums\EnrollmentStatus;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Registros históricos: ediciones sin fechas, matrículas aprobadas sin nota
| y ponentes con matrícula. Cada caso provoca a lo sumo una violación.
*/

describe('domain', function () {
    it('approves historical enrollments without a grade', function () {
        $enrollment = Enrollment::factory()->create(['historical' => true]);

        expect($enrollment->status)->toBe(EnrollmentStatus::Aprobado)->and($enrollment->grade)->toBeNull();
    });

    it('still derives the status from the grade on historical enrollments', function () {
        expect(Enrollment::factory()->create(['historical' => true, 'grade' => 10])->status)->toBe(EnrollmentStatus::Desaprobado);
    });

    it('approves speakers without a grade', function () {
        $enrollment = Enrollment::factory()->create(['role' => EnrollmentRole::Ponente]);

        expect($enrollment->status)->toBe(EnrollmentStatus::Aprobado)->and($enrollment->isSpeaker())->toBeTrue();
    });

    it('keeps regular participants without a grade as cursando', function () {
        expect(Enrollment::factory()->create()->status)->toBe(EnrollmentStatus::Cursando);
    });

    it('labels editions without dates by year', function () {
        $edition = CourseEdition::factory()->create(['start_date' => null, 'end_date' => null, 'year' => 2024]);

        expect($edition->label())->toEndWith('· Año 2024')
            ->and($edition->periodText())->toBe('en el año 2024');
    });
});

describe('constraints', function () {
    it('rejects approved without grade for a regular participant', function () {
        $enrollment = Enrollment::factory()->create();

        expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['status' => 'aprobado']))
            ->toThrow(QueryException::class, 'enrollments_grade_status_consistency');
    });

    it('allows approved without grade for historical enrollments and speakers', function () {
        $historical = Enrollment::factory()->create();
        $speaker = Enrollment::factory()->create();

        DB::table('enrollments')->where('id', $historical->id)->update(['status' => 'aprobado', 'historical' => true]);
        DB::table('enrollments')->where('id', $speaker->id)->update(['status' => 'aprobado', 'role' => 'ponente']);

        expect(DB::table('enrollments')->where('status', 'aprobado')->whereNull('grade')->count())->toBe(2);
    });

    it('rejects an unknown enrollment role', function () {
        $enrollment = Enrollment::factory()->create();

        expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['role' => 'oyente']))
            ->toThrow(QueryException::class, 'enrollments_role_values');
    });

    it('rejects an edition with only one date', function () {
        expect(fn () => CourseEdition::factory()->create(['start_date' => '2024-01-10', 'end_date' => null]))
            ->toThrow(QueryException::class, 'course_editions_dates_check');
    });
});
