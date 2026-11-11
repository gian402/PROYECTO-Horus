<?php

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;

beforeEach(function () {
    $this->edition = CourseEdition::factory()
        ->for(Course::factory()->create(['code' => 'PA']))
        ->create(['start_date' => '2024-02-01', 'end_date' => '2024-02-20', 'year' => 2024]);
});

describe('enrollment', function () {
    it('enrolls a student assigning the correlative code', function () {
        actingAsRole('Coordinador Académico');
        $student = Student::factory()->create();

        $this->post(route('editions.enrollments.store', $this->edition), ['student_id' => $student->id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Matrícula registrada con el código HGC-PA-01-024.');

        expect(Enrollment::sole()->registration_code)->toBe('HGC-PA-01-024');
    });

    it('rejects a duplicate enrollment with a friendly error', function () {
        actingAsRole('Coordinador Académico');
        $student = Student::factory()->create();
        $this->post(route('editions.enrollments.store', $this->edition), ['student_id' => $student->id]);

        $this->post(route('editions.enrollments.store', $this->edition), ['student_id' => $student->id])
            ->assertSessionHasErrors(['student_id' => 'El participante ya está matriculado en esta edición.']);

        expect(Enrollment::count())->toBe(1);
    });

    it('forbids a Ponente from enrolling', function () {
        $user = actingAsRole('Ponente');
        $this->edition->update(['teacher_id' => Teacher::factory()->create(['user_id' => $user->id])->id]);

        $this->post(route('editions.enrollments.store', $this->edition), ['student_id' => Student::factory()->create()->id])
            ->assertForbidden();
    });

    it('searches students not yet enrolled', function () {
        actingAsRole('Coordinador Académico');
        $enrolled = Student::factory()->create(['dni' => '70000001', 'last_name' => 'Quispe']);
        Student::factory()->create(['dni' => '70000002', 'last_name' => 'Quispe']);
        Enrollment::factory()->for($enrolled)->for($this->edition, 'courseEdition')->create();

        $this->getJson(route('editions.students.search', [$this->edition, 'q' => 'quispe']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.dni', '70000002');
    });

    it('cancels an enrollment without certificates but not one with certificates', function () {
        actingAsRole('Coordinador Académico');
        $free = Enrollment::factory()->for($this->edition, 'courseEdition')->create();
        $certified = Enrollment::factory()->for($this->edition, 'courseEdition')->approved()->create();
        Certificate::factory()->for($certified)->create();

        $this->delete(route('enrollments.destroy', $free))->assertSessionHasNoErrors();
        $this->delete(route('enrollments.destroy', $certified))->assertInertiaFlash('toast.type', 'error');

        expect(Enrollment::find($free->id))->toBeNull()
            ->and(Enrollment::find($certified->id))->not->toBeNull();
    });
});

describe('grades', function () {
    it('saves grades in bulk and derives the status on the backend', function () {
        actingAsRole('Coordinador Académico');
        [$a, $b, $c] = Enrollment::factory()->count(3)->for($this->edition, 'courseEdition')->create();

        $this->put(route('editions.grades.update', $this->edition), [
            'grades' => [$a->id => '14', $b->id => '13,5', $c->id => ''],
        ])->assertSessionHasNoErrors();

        expect($a->fresh()->status)->toBe(EnrollmentStatus::Aprobado)
            ->and($b->fresh()->grade)->toBe('13.50')
            ->and($b->fresh()->status)->toBe(EnrollmentStatus::Desaprobado)
            ->and($c->fresh()->status)->toBe(EnrollmentStatus::Cursando);
    });

    it('validates the grade scale', function (string $grade) {
        actingAsRole('Coordinador Académico');
        $e = Enrollment::factory()->for($this->edition, 'courseEdition')->create();

        $this->put(route('editions.grades.update', $this->edition), ['grades' => [$e->id => $grade]])
            ->assertSessionHasErrors("grades.{$e->id}");
    })->with(['21', '-1', 'abc', '15.555']);

    it('rejects enrollments from another edition', function () {
        actingAsRole('Coordinador Académico');
        $foreign = Enrollment::factory()->create();

        $this->put(route('editions.grades.update', $this->edition), ['grades' => [$foreign->id => '15']])
            ->assertSessionHasErrors('grades');

        expect($foreign->fresh()->grade)->toBeNull();
    });

    it('lets a Ponente grade only their own editions', function () {
        $user = actingAsRole('Ponente');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $this->edition->update(['teacher_id' => $teacher->id]);
        $own = Enrollment::factory()->for($this->edition, 'courseEdition')->create();
        $other = Enrollment::factory()->create();

        $this->put(route('editions.grades.update', $this->edition), ['grades' => [$own->id => '18']])
            ->assertSessionHasNoErrors();
        $this->put(route('editions.grades.update', $other->courseEdition), ['grades' => [$other->id => '18']])
            ->assertForbidden();

        expect($own->fresh()->grade)->toBe('18.00')
            ->and($other->fresh()->grade)->toBeNull();
    });
});
