<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificación pública de certificados (/validar/{hash}).
 *
 * Solo lectura, sin autenticación y con límite de consultas. Expone únicamente
 * lo necesario para comprobar la autenticidad: nunca DNI, correo ni teléfono.
 */
class CertificateVerificationController extends Controller
{
    public function __invoke(Request $request, string $hash): Response
    {
        $certificate = Certificate::query()
            ->with(['enrollment.student:id,first_name,last_name', 'enrollment.courseEdition.course:id,code,name'])
            ->where('unique_hash', $hash)
            ->first();

        $status = match (true) {
            $certificate === null => 'not_found',
            $certificate->isRevoked() => 'revoked',
            default => 'valid',
        };

        $response = Inertia::render('public/VerifyCertificate', [
            'status' => $status,
            'certificate' => $certificate ? [
                'participant' => $certificate->enrollment->student->full_name,
                'course' => $certificate->enrollment->courseEdition->course->name,
                'start_date' => $certificate->enrollment->courseEdition->start_date?->toDateString(),
                'end_date' => $certificate->enrollment->courseEdition->end_date?->toDateString(),
                'year' => $certificate->enrollment->courseEdition->year,
                'role' => $certificate->enrollment->role->value,
                'registration_code' => $certificate->enrollment->registration_code,
                'issued_at' => $certificate->issued_at->toDateString(),
                'revoked_at' => $certificate->revoked_at?->toDateString(),
            ] : null,
        ])->toResponse($request);

        $response->setStatusCode($status === 'not_found' ? 404 : 200);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
