<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Enrollment;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Emisión, reemisión y anulación de certificados.
 *
 * Reglas:
 *  - Solo se emite para matrículas aprobadas.
 *  - issue() es idempotente: si ya hay un certificado vigente, lo devuelve.
 *  - La fila de la matrícula se bloquea (FOR UPDATE) para que dos emisiones
 *    simultáneas no compitan; el índice único parcial de certificates es la
 *    última barrera (un solo vigente por matrícula).
 *  - Los PDF se guardan en el disco privado "local" (nunca en una URL pública).
 */
class CertificateIssuer
{
    public const DISK = 'local';

    public function issue(Enrollment $enrollment): Certificate
    {
        return DB::transaction(function () use ($enrollment): Certificate {
            $locked = $this->lockApproved($enrollment);

            return $locked->activeCertificate()->first() ?? $this->create($locked);
        });
    }

    /**
     * Anula el certificado vigente (si existe) y emite uno nuevo, conservando el historial.
     */
    public function reissue(Enrollment $enrollment, string $reason): Certificate
    {
        return DB::transaction(function () use ($enrollment, $reason): Certificate {
            $locked = $this->lockApproved($enrollment);

            $locked->activeCertificate()->first()?->update([
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);

            return $this->create($locked);
        });
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        return DB::transaction(function () use ($certificate, $reason): Certificate {
            $locked = Certificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRevoked()) {
                throw new DomainException('El certificado ya estaba anulado.');
            }

            $locked->update(['revoked_at' => now(), 'revocation_reason' => $reason]);

            return $locked;
        });
    }

    /**
     * Ruta del PDF en el disco privado; lo regenera si el archivo no existe.
     */
    public function pdfPath(Certificate $certificate): string
    {
        $path = $certificate->pdf_path ?: "certificates/{$certificate->unique_hash}.pdf";

        if (! Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->put($path, $this->render($certificate));

            if ($certificate->pdf_path !== $path) {
                $certificate->update(['pdf_path' => $path]);
            }
        }

        return $path;
    }

    public function render(Certificate $certificate): string
    {
        $certificate->loadMissing(['enrollment.student', 'enrollment.courseEdition.course']);

        $verifyUrl = route('certificates.verify', $certificate->unique_hash);

        return Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'enrollment' => $certificate->enrollment,
            'student' => $certificate->enrollment->student,
            'edition' => $certificate->enrollment->courseEdition,
            'course' => $certificate->enrollment->courseEdition->course,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($verifyUrl)),
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * QR en SVG con bacon/bacon-qr-code v3 (la misma librería que usa Fortify para la 2FA).
     */
    public function qrSvg(string $content): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(220, 0),
            new SvgImageBackEnd,
        ));

        return $writer->writeString($content, 'UTF-8', ErrorCorrectionLevel::M());
    }

    private function lockApproved(Enrollment $enrollment): Enrollment
    {
        $locked = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isApproved()) {
            throw new DomainException('Solo se emiten certificados para matrículas aprobadas.');
        }

        return $locked;
    }

    private function create(Enrollment $enrollment): Certificate
    {
        /** @var Certificate $certificate */
        $certificate = $enrollment->certificates()->create(['issued_at' => now()]);

        $path = "certificates/{$certificate->unique_hash}.pdf";
        Storage::disk(self::DISK)->put($path, $this->render($certificate));
        $certificate->update(['pdf_path' => $path]);

        return $certificate;
    }
}
