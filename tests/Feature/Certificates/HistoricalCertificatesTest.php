<?php

use App\Enums\EnrollmentRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\CertificateIssuer;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/*
| Certificados, notas y verificación para registros históricos (sin fechas,
| aprobados sin nota) y para ponentes.
*/

beforeEach(function () {
    Storage::fake(CertificateIssuer::DISK);
    RateLimiter::clear('verification');
    $this->undated = CourseEdition::factory()->for(Course::factory()->create(['code' => 'SM']))
        ->create(['start_date' => null, 'end_date' => null, 'year' => 2024]);
});

function certificateHtml(Enrollment $enrollment): string
{
    $certificate = $enrollment->certificates()->create();

    return view('pdf.certificate', [
        'certificate' => $certificate,
        'enrollment' => $enrollment,
        'student' => $enrollment->student,
        'edition' => $enrollment->courseEdition,
        'course' => $enrollment->courseEdition->course,
        'verifyUrl' => route('certificates.verify', $certificate->unique_hash),
        'qrDataUri' => 'data:image/svg+xml;base64,',
    ])->render();
}

it('issues certificates for historical enrollments without grade and for speakers', function () {
    $historical = Enrollment::factory()->for($this->undated)->create(['historical' => true]);
    $speaker = Enrollment::factory()->for($this->undated)->create(['role' => EnrollmentRole::Ponente]);

    $issuer = app(CertificateIssuer::class);

    expect($issuer->issue($historical)->exists)->toBeTrue()
        ->and($issuer->issue($speaker)->exists)->toBeTrue()
        ->and(Certificate::count())->toBe(2);
});

it('prints the year when the edition has no dates and the speaker wording', function () {
    $speaker = Enrollment::factory()->for($this->undated)->create(['role' => EnrollmentRole::Ponente]);
    $participant = Enrollment::factory()->for($this->undated)->create(['historical' => true]);

    expect(certificateHtml($speaker))->toContain('por su participación como ponente')->toContain('Realizada en el año 2024')
        ->and(certificateHtml($participant))->toContain('por haber aprobado satisfactoriamente')->not->toContain('Nota final');
});

it('renders a real PDF for an undated edition', function () {
    $certificate = app(CertificateIssuer::class)->issue(Enrollment::factory()->for($this->undated)->create(['historical' => true]));

    expect(Storage::disk(CertificateIssuer::DISK)->get($certificate->pdf_path))->toStartWith('%PDF');
});

it('verifies a certificate of an undated edition showing the year and role', function () {
    $enrollment = Enrollment::factory()->for($this->undated)->create(['role' => EnrollmentRole::Ponente]);
    $certificate = Certificate::factory()->for($enrollment)->create();

    $this->get(route('certificates.verify', $certificate->unique_hash))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('status', 'valid')
            ->where('certificate.start_date', null)
            ->where('certificate.year', 2024)
            ->where('certificate.role', 'ponente'));
});

it('does not allow grading speakers', function () {
    actingAsRole('Coordinador Académico');
    $speaker = Enrollment::factory()->for($this->undated)->create(['role' => EnrollmentRole::Ponente]);

    $this->put(route('editions.grades.update', $this->undated), ['grades' => [$speaker->id => '18']])
        ->assertSessionHasErrors('grades');

    expect($speaker->fresh()->grade)->toBeNull();
});

it('enrolls a speaker from the edition page', function () {
    actingAsRole('Coordinador Académico');
    $student = Student::factory()->create();

    $this->post(route('editions.enrollments.store', $this->undated), ['student_id' => $student->id, 'as_speaker' => true])
        ->assertSessionHasNoErrors();

    expect(Enrollment::sole())
        ->role->toBe(EnrollmentRole::Ponente)
        ->status->value->toBe('aprobado')
        ->registration_code->toBe('HGC-SM-01-024');
});

it('shows undated editions in the list and detail pages', function () {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->for($this->undated)->create(['historical' => true]);

    $this->get(route('editions.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('editions.data.0.start_date', null));

    $this->get(route('editions.show', $this->undated))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('edition.start_date', null)
            ->where('enrollments.0.historical', true)
            ->where('enrollments.0.status', 'aprobado'));
});

it('lets an undated edition stay undated or receive both dates when edited', function () {
    actingAsRole('Coordinador Académico');
    $data = ['course_id' => $this->undated->course_id, 'teacher_id' => null, 'year' => 2024];

    $this->put(route('editions.update', $this->undated), [...$data, 'start_date' => null, 'end_date' => null])
        ->assertSessionHasNoErrors();

    $this->put(route('editions.update', $this->undated), [...$data, 'start_date' => '2024-03-01', 'end_date' => null])
        ->assertSessionHasErrors('end_date');

    $this->put(route('editions.update', $this->undated), [...$data, 'start_date' => '2024-03-01', 'end_date' => '2024-03-20'])
        ->assertSessionHasNoErrors();

    expect($this->undated->fresh()->start_date->toDateString())->toBe('2024-03-01');
});

it('still requires dates when creating a new edition', function () {
    actingAsRole('Coordinador Académico');

    $this->post(route('editions.store'), ['course_id' => $this->undated->course_id, 'year' => 2026])
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

it('exports and reports undated editions without errors', function () {
    actingAsRole('Coordinador Académico');
    Enrollment::factory()->for($this->undated)->create(['role' => EnrollmentRole::Ponente]);

    $this->get(route('reports.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.data.0.role', 'ponente'));
    $this->get(route('reports.export'))->assertOk();
    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.enrollments', 0));
});
