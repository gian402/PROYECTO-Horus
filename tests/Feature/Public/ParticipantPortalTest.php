<?php

use App\Http\Controllers\Public\ParticipantPortalController;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('portal-login');
});

it('shows the portal login form', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/PortalLogin'));
});

it('logs in with a matching DNI and registration code and lists all of its enrollments', function () {
    $student = Student::factory()->create(['dni' => '70123456']);
    $old = Enrollment::factory()->for($student)->approved()->create(['registration_code' => 'HGC-PA-01-023']);
    $recent = Enrollment::factory()->for($student)->create(['registration_code' => 'HGC-SM-05-024']);

    $this->post(route('portal.authenticate'), [
        'dni' => '70123456',
        'registration_code' => 'HGC-PA-01-023',
    ])->assertRedirect(route('portal.dashboard'));

    expect(session()->get(ParticipantPortalController::SESSION_KEY))->toBe($student->id);

    $this->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/PortalDashboard')
            ->where('student.id', $student->id)
            ->has('enrollments', 2)
            ->where('enrollments.0.id', $recent->id)
            ->where('enrollments.1.id', $old->id));
});

it('rejects a DNI that does not exist with a generic message', function () {
    $this->from(route('portal.login'))
        ->post(route('portal.authenticate'), [
            'dni' => '99999999',
            'registration_code' => 'HGC-PA-01-024',
        ])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHasErrors(['dni' => 'DNI o código de registro incorrectos.']);

    expect(session()->has(ParticipantPortalController::SESSION_KEY))->toBeFalse();
});

it('rejects a registration code that does not belong to that DNI with the same generic message', function () {
    $student = Student::factory()->create(['dni' => '70123456']);
    Enrollment::factory()->for($student)->create(['registration_code' => 'HGC-PA-01-024']);

    $this->from(route('portal.login'))
        ->post(route('portal.authenticate'), [
            'dni' => '70123456',
            'registration_code' => 'HGC-OTRO-99-024',
        ])
        ->assertSessionHasErrors(['dni' => 'DNI o código de registro incorrectos.']);
});

it('rate limits repeated attempts against the same DNI', function () {
    $student = Student::factory()->create(['dni' => '70123456']);
    Enrollment::factory()->for($student)->create(['registration_code' => 'HGC-PA-01-024']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('portal.authenticate'), [
            'dni' => '70123456',
            'registration_code' => 'HGC-WRONG-'.$i,
        ])->assertSessionHasErrors('dni');
    }

    $this->post(route('portal.authenticate'), [
        'dni' => '70123456',
        'registration_code' => 'HGC-PA-01-024',
    ])->assertStatus(429);
});

it('redirects to login when visiting the dashboard without a portal session', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
});

it('lets a participant download their own active certificate', function () {
    $student = Student::factory()->create(['dni' => '70123456']);
    $enrollment = Enrollment::factory()->for($student)->approved()->create(['registration_code' => 'HGC-PA-01-024']);
    $certificate = Certificate::factory()->for($enrollment)->create();

    $this->post(route('portal.authenticate'), [
        'dni' => '70123456',
        'registration_code' => 'HGC-PA-01-024',
    ]);

    $this->get(route('portal.certificates.download', $certificate))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('never lets a participant download another participant\'s certificate', function () {
    $me = Student::factory()->create(['dni' => '70123456']);
    Enrollment::factory()->for($me)->create(['registration_code' => 'HGC-PA-01-024']);

    $other = Student::factory()->create(['dni' => '80000000']);
    $otherEnrollment = Enrollment::factory()->for($other)->approved()->create(['registration_code' => 'HGC-SM-02-024']);
    $otherCertificate = Certificate::factory()->for($otherEnrollment)->create();

    $this->post(route('portal.authenticate'), [
        'dni' => '70123456',
        'registration_code' => 'HGC-PA-01-024',
    ]);

    $this->get(route('portal.certificates.download', $otherCertificate))->assertNotFound();
});

it('clears the portal session on logout', function () {
    $student = Student::factory()->create(['dni' => '70123456']);
    Enrollment::factory()->for($student)->create(['registration_code' => 'HGC-PA-01-024']);

    $this->post(route('portal.authenticate'), [
        'dni' => '70123456',
        'registration_code' => 'HGC-PA-01-024',
    ]);

    $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));

    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
});
