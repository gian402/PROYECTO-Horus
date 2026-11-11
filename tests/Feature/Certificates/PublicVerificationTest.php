<?php

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('verification');
});

it('shows a valid certificate without authentication', function () {
    $student = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Quispe', 'dni' => '70123456', 'email' => 'ana@example.com', 'phone' => '987654321']);
    $enrollment = Enrollment::factory()->for($student)->approved()->create();
    $certificate = Certificate::factory()->for($enrollment)->create();

    $response = $this->get(route('certificates.verify', $certificate->unique_hash));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('public/VerifyCertificate')
            ->where('status', 'valid')
            ->where('certificate.participant', 'Ana Quispe')
            ->where('certificate.registration_code', $enrollment->registration_code)
            ->missing('certificate.dni')
            ->missing('certificate.email'));

    // Ningún dato personal sensible en la respuesta.
    expect($response->getContent())->not->toContain('70123456')
        ->not->toContain('ana@example.com')
        ->not->toContain('987654321');
});

it('marks a revoked certificate as revoked', function () {
    $certificate = Certificate::factory()->revoked()->create();

    $this->get(route('certificates.verify', $certificate->unique_hash))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('status', 'revoked'));
});

it('returns 404 for an unknown hash', function () {
    $this->get(route('certificates.verify', str_repeat('a', 64)))
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->where('status', 'not_found')->where('certificate', null));
});

it('rejects malformed hashes and sequential ids', function (string $hash) {
    $this->get("/validar/{$hash}")->assertNotFound();
})->with(['1', 'abc', str_repeat('g', 64), str_repeat('A', 64)]);

it('only allows reading', function () {
    $certificate = Certificate::factory()->create();

    $this->post(route('certificates.verify', $certificate->unique_hash))->assertMethodNotAllowed();
    $this->delete(route('certificates.verify', $certificate->unique_hash))->assertMethodNotAllowed();
});

it('rate limits the public verification', function () {
    $hash = str_repeat('b', 64);

    for ($i = 0; $i < 30; $i++) {
        $this->get(route('certificates.verify', $hash))->assertNotFound();
    }

    $this->get(route('certificates.verify', $hash))->assertTooManyRequests();
});
