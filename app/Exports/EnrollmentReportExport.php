<?php

namespace App\Exports;

use App\Models\Enrollment;
use App\Services\Reports\EnrollmentReport;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exportación del reporte de matrículas. FromQuery procesa por bloques (chunks),
 * así no carga todas las filas en memoria.
 */
class EnrollmentReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array{year: ?int, course_id: ?int, edition_id: ?int, status: ?string, certificate: ?string}  $filters
     */
    public function __construct(private readonly array $filters) {}

    /**
     * @return Builder<Enrollment>
     */
    public function query(): Builder
    {
        return EnrollmentReport::query($this->filters)
            ->with([
                'student:id,dni,first_name,last_name',
                'courseEdition:id,course_id,start_date,end_date,year,teacher_id',
                'courseEdition.course:id,code,name',
                'courseEdition.teacher:id,full_name',
                'activeCertificate:id,enrollment_id,issued_at,unique_hash',
            ])
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Código de registro', 'DNI', 'Apellidos', 'Nombres', 'Curso', 'Nombre del curso',
            'Inicio', 'Fin', 'Año', 'Ponente', 'Rol', 'Nota', 'Estado', 'Certificado emitido', 'URL de verificación',
        ];
    }

    /**
     * @param  Enrollment  $enrollment
     * @return list<string|int|float|null>
     */
    public function map($enrollment): array
    {
        $edition = $enrollment->courseEdition;
        $certificate = $enrollment->activeCertificate;

        return [
            $enrollment->registration_code,
            $enrollment->student->dni,
            $enrollment->student->last_name,
            $enrollment->student->first_name,
            $edition->course->code,
            $edition->course->name,
            $edition->start_date?->format('d/m/Y'),
            $edition->end_date?->format('d/m/Y'),
            $edition->year,
            $edition->teacher?->full_name,
            $enrollment->role->label(),
            $enrollment->grade !== null ? (float) $enrollment->grade : null,
            $enrollment->status->label(),
            $certificate?->issued_at?->format('d/m/Y'),
            $certificate ? route('certificates.verify', $certificate->unique_hash) : null,
        ];
    }
}
