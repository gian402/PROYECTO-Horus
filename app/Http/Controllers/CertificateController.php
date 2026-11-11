<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificateIssuer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    use InteractsWithToasts;

    public function __construct(private readonly CertificateIssuer $issuer) {}

    public function store(Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('issue', [Certificate::class, $enrollment]);

        try {
            $certificate = $this->issuer->issue($enrollment);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $this->toast($certificate->wasRecentlyCreated
            ? "Certificado emitido para {$enrollment->registration_code}."
            : 'La matrícula ya tenía un certificado vigente.');

        return back();
    }

    public function reissue(Request $request, Enrollment $enrollment): RedirectResponse
    {
        Gate::authorize('issue', [Certificate::class, $enrollment]);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motivo']);

        try {
            $this->issuer->reissue($enrollment, $data['reason']);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $this->toast("Certificado reemitido para {$enrollment->registration_code}.");

        return back();
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        Gate::authorize('revoke', $certificate);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motivo']);

        try {
            $this->issuer->revoke($certificate, $data['reason']);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $this->toast('Certificado anulado.');

        return back();
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        Gate::authorize('download', $certificate);

        $certificate->loadMissing('enrollment');

        return Storage::disk(CertificateIssuer::DISK)->download(
            $this->issuer->pdfPath($certificate),
            "certificado-{$certificate->enrollment->registration_code}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
