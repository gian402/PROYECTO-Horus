<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PortalLoginRequest;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\CertificateIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal público del participante: consulta de solo lectura de sus propias
 * matrículas, notas y certificados. Sin cuenta ni contraseña.
 *
 * Identificación: DNI + un código de registro suyo (cualquiera de los que
 * tenga). Una vez validada esa pareja, la sesión guarda el student_id y da
 * acceso a TODAS sus matrículas, no solo a la del código usado para entrar.
 *
 * El mensaje de error es siempre el mismo (DNI no existe o código no
 * corresponde): así nadie puede confirmar si un DNI está en el sistema
 * solo tanteando códigos.
 */
class ParticipantPortalController extends Controller
{
    public const SESSION_KEY = 'portal.student_id';

    public function create(): Response
    {
        return Inertia::render('public/PortalLogin');
    }

    public function store(PortalLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $student = Student::where('dni', $data['dni'])->first();

        $matches = $student !== null && $student->enrollments()
            ->where('registration_code', $data['registration_code'])
            ->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'dni' => 'DNI o código de registro incorrectos.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $student->id);

        return to_route('portal.dashboard');
    }

    public function dashboard(Request $request): Response
    {
        $student = Student::findOrFail($request->session()->get(self::SESSION_KEY));

        $student->load([
            'enrollments' => fn ($q) => $q->latest('id'),
            'enrollments.courseEdition.course:id,code,name',
            'enrollments.courseEdition.teacher:id,full_name',
            'enrollments.activeCertificate:id,enrollment_id,issued_at',
        ]);

        return Inertia::render('public/PortalDashboard', [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'dni' => $student->dni,
            ],
            'enrollments' => $student->enrollments->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'registration_code' => $e->registration_code,
                'grade' => $e->grade,
                'status' => $e->status->value,
                'role' => $e->role->value,
                'edition' => [
                    'id' => $e->courseEdition->id,
                    'label' => $e->courseEdition->label(),
                    'year' => $e->courseEdition->year,
                    'course' => $e->courseEdition->course->only(['id', 'code', 'name']),
                    'teacher' => $e->courseEdition->teacher?->only(['id', 'full_name']),
                ],
                'certificate' => $e->activeCertificate ? [
                    'id' => $e->activeCertificate->id,
                    'issued_at' => $e->activeCertificate->issued_at?->toDateString(),
                ] : null,
            ])->values(),
        ]);
    }

    public function downloadCertificate(Request $request, Certificate $certificate, CertificateIssuer $issuer): StreamedResponse
    {
        $certificate->loadMissing('enrollment');

        abort_unless($certificate->enrollment->student_id === $request->session()->get(self::SESSION_KEY), 404);
        abort_if($certificate->isRevoked(), 404);

        return Storage::disk(CertificateIssuer::DISK)->download(
            $issuer->pdfPath($certificate),
            "certificado-{$certificate->enrollment->registration_code}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return to_route('portal.login');
    }
}
