<?php

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Services\CertificateIssuer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(CertificateIssuer::DISK);
    $this->issuer = app(CertificateIssuer::class);
});

describe('service', function () {
    it('issues a certificate with a stored PDF for an approved enrollment', function () {
        $enrollment = Enrollment::factory()->approved(16)->create();

        $certificate = $this->issuer->issue($enrollment);

        expect($certificate->pdf_path)->toBe("certificates/{$certificate->unique_hash}.pdf");
        Storage::disk(CertificateIssuer::DISK)->assertExists($certificate->pdf_path);
        expect(Storage::disk(CertificateIssuer::DISK)->get($certificate->pdf_path))->toStartWith('%PDF');
    });

    it('refuses to issue for enrollments that are not approved', function (?float $grade) {
        $enrollment = Enrollment::factory()->create(['grade' => $grade]);

        expect(fn () => $this->issuer->issue($enrollment))->toThrow(DomainException::class);
        expect(Certificate::count())->toBe(0);
    })->with([null, 10.0, 13.99]);

    it('is idempotent', function () {
        $enrollment = Enrollment::factory()->approved()->create();

        $first = $this->issuer->issue($enrollment);
        $second = $this->issuer->issue($enrollment);

        expect($second->id)->toBe($first->id)
            ->and(Certificate::count())->toBe(1);
    });

    it('reissues keeping the history', function () {
        $enrollment = Enrollment::factory()->approved()->create();
        $original = $this->issuer->issue($enrollment);

        $new = $this->issuer->reissue($enrollment, 'Corrección de nombre');

        expect($original->fresh()->isRevoked())->toBeTrue()
            ->and($original->fresh()->revocation_reason)->toBe('Corrección de nombre')
            ->and($new->isRevoked())->toBeFalse()
            ->and($new->unique_hash)->not->toBe($original->unique_hash)
            ->and($enrollment->certificates()->count())->toBe(2);
    });

    it('revokes a certificate only once', function () {
        $certificate = $this->issuer->issue(Enrollment::factory()->approved()->create());

        $this->issuer->revoke($certificate, 'Error de emisión');

        expect($certificate->fresh()->isRevoked())->toBeTrue()
            ->and(fn () => $this->issuer->revoke($certificate, 'Otra vez'))->toThrow(DomainException::class);
    });

    it('embeds the registration code, the verification URL and a QR in the PDF template', function () {
        $enrollment = Enrollment::factory()->approved(17)->create();
        $certificate = $enrollment->certificates()->create();
        $certificate->load('enrollment.student', 'enrollment.courseEdition.course');

        $html = view('pdf.certificate', [
            'certificate' => $certificate,
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'edition' => $enrollment->courseEdition,
            'course' => $enrollment->courseEdition->course,
            'verifyUrl' => route('certificates.verify', $certificate->unique_hash),
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($this->issuer->qrSvg('x')),
        ])->render();

        expect($html)->toContain($enrollment->registration_code)
            ->toContain(route('certificates.verify', $certificate->unique_hash))
            ->toContain('data:image/svg+xml;base64,')
            ->toContain(e($enrollment->student->full_name));
    });

    it('regenerates a missing PDF on download', function () {
        $certificate = $this->issuer->issue(Enrollment::factory()->approved()->create());
        Storage::disk(CertificateIssuer::DISK)->delete($certificate->pdf_path);

        $this->issuer->pdfPath($certificate);

        Storage::disk(CertificateIssuer::DISK)->assertExists($certificate->pdf_path);
    });
});

describe('routes', function () {
    it('lets the coordination issue, reissue and revoke', function () {
        actingAsRole('Coordinador Académico');
        $enrollment = Enrollment::factory()->approved()->create();

        $this->post(route('certificates.store', $enrollment))->assertInertiaFlash('toast.type', 'success');
        $this->post(route('certificates.reissue', $enrollment), ['reason' => 'Corrección'])->assertSessionHasNoErrors();

        $active = $enrollment->activeCertificate()->first();
        $this->post(route('certificates.revoke', $active), ['reason' => 'Anulado por error'])->assertSessionHasNoErrors();

        expect($enrollment->certificates()->count())->toBe(2)
            ->and($enrollment->activeCertificate()->exists())->toBeFalse();
    });

    it('requires a reason to reissue or revoke', function () {
        actingAsRole('Coordinador Académico');
        $enrollment = Enrollment::factory()->approved()->create();
        $certificate = $this->issuer->issue($enrollment);

        $this->post(route('certificates.reissue', $enrollment), [])->assertSessionHasErrors('reason');
        $this->post(route('certificates.revoke', $certificate), [])->assertSessionHasErrors('reason');
    });

    it('shows an error when issuing for a non approved enrollment', function () {
        actingAsRole('Coordinador Académico');
        $enrollment = Enrollment::factory()->failed()->create();

        $this->post(route('certificates.store', $enrollment))->assertInertiaFlash('toast.type', 'error');

        expect(Certificate::count())->toBe(0);
    });

    it('forbids a Ponente from issuing', function () {
        actingAsRole('Ponente');

        $this->post(route('certificates.store', Enrollment::factory()->approved()->create()))->assertForbidden();
    });

    it('controls who can download the PDF', function () {
        $certificate = $this->issuer->issue(Enrollment::factory()->approved()->create());

        actingAsRole('Coordinador Académico');
        $this->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    it('lets a Ponente download only certificates of their editions', function () {
        $user = actingAsRole('Ponente');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $own = Enrollment::factory()->approved()->create();
        $own->courseEdition->update(['teacher_id' => $teacher->id]);
        $ownCertificate = $this->issuer->issue($own);
        $otherCertificate = $this->issuer->issue(Enrollment::factory()->approved()->create());

        $this->get(route('certificates.download', $ownCertificate))->assertOk();
        $this->get(route('certificates.download', $otherCertificate))->assertForbidden();
    });

    it('does not expose the PDF publicly', function () {
        $certificate = $this->issuer->issue(Enrollment::factory()->approved()->create());

        $this->get(route('certificates.download', $certificate))->assertRedirect(route('login'));

        // Disco privado: sin URL firmada, Laravel responde 403 (o 404 si no hay ruta de servicio).
        expect($this->get('/storage/'.$certificate->pdf_path)->status())->toBeIn([403, 404]);
        expect($this->get('/storage/private/'.$certificate->pdf_path)->status())->toBeIn([403, 404]);
    });
});
