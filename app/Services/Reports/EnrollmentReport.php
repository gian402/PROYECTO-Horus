<?php

namespace App\Services\Reports;

use App\Enums\EnrollmentRole;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reporte de matrículas, notas y certificados. La misma consulta alimenta la
 * vista paginada y la exportación a Excel, para que siempre coincidan.
 */
class EnrollmentReport
{
    /**
     * @return array{year: ?int, course_id: ?int, edition_id: ?int, status: ?string, certificate: ?string}
     */
    public static function filters(Request $request): array
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'course_id' => ['nullable', 'integer'],
            'edition_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
            'certificate' => ['nullable', Rule::in(['with', 'without'])],
        ]);

        return [
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'course_id' => isset($data['course_id']) ? (int) $data['course_id'] : null,
            'edition_id' => isset($data['edition_id']) ? (int) $data['edition_id'] : null,
            'status' => $data['status'] ?? null,
            'certificate' => $data['certificate'] ?? null,
        ];
    }

    /**
     * @param  array{year: ?int, course_id: ?int, edition_id: ?int, status: ?string, certificate: ?string}  $filters
     * @return Builder<Enrollment>
     */
    public static function query(array $filters): Builder
    {
        return Enrollment::query()
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['edition_id'], fn ($q, $id) => $q->where('course_edition_id', $id))
            ->when($filters['year'] || $filters['course_id'], fn ($q) => $q->whereHas('courseEdition', fn ($e) => $e
                ->when($filters['year'], fn ($e, $year) => $e->where('year', $year))
                ->when($filters['course_id'], fn ($e, $id) => $e->where('course_id', $id))))
            ->when($filters['certificate'] === 'with', fn ($q) => $q->whereHas('activeCertificate'))
            ->when($filters['certificate'] === 'without', fn ($q) => $q->whereDoesntHave('activeCertificate'));
    }

    /**
     * Totales del conjunto filtrado. Los estados (y el % de aprobación que se
     * calcula con ellos) cuentan solo participantes: los ponentes no se califican
     * y se informan aparte, igual que en el panel.
     *
     * @param  array{year: ?int, course_id: ?int, edition_id: ?int, status: ?string, certificate: ?string}  $filters
     * @return array{total: int, speakers: int, cursando: int, aprobado: int, desaprobado: int, certificates: int}
     */
    public static function totals(array $filters): array
    {
        $byStatus = self::query($filters)
            ->toBase()
            ->selectRaw('role, status, count(*) as total')
            ->groupBy('role', 'status')
            ->get();

        $participants = $byStatus->where('role', EnrollmentRole::Participante->value)->pluck('total', 'status');

        return [
            'total' => (int) $byStatus->sum('total'),
            'speakers' => (int) $byStatus->where('role', EnrollmentRole::Ponente->value)->sum('total'),
            'cursando' => (int) ($participants['cursando'] ?? 0),
            'aprobado' => (int) ($participants['aprobado'] ?? 0),
            'desaprobado' => (int) ($participants['desaprobado'] ?? 0),
            'certificates' => self::query($filters)->whereHas('activeCertificate')->count(),
        ];
    }
}
