<?php

namespace App\Http\Controllers;

use App\Exports\EnrollmentReportExport;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Services\Reports\EnrollmentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');

        $filters = EnrollmentReport::filters($request);

        $rows = EnrollmentReport::query($filters)
            ->with([
                'student:id,dni,first_name,last_name',
                'courseEdition:id,course_id,start_date,end_date,year',
                'courseEdition.course:id,code,name',
                'activeCertificate:id,enrollment_id,issued_at',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Enrollment $e) => [
                'id' => $e->id,
                'registration_code' => $e->registration_code,
                'student' => "{$e->student->last_name}, {$e->student->first_name}",
                'dni' => $e->student->dni,
                'course' => $e->courseEdition->course->code,
                'edition_id' => $e->courseEdition->id,
                'edition' => $e->courseEdition->label(),
                'year' => $e->courseEdition->year,
                'grade' => $e->grade,
                'status' => $e->status->value,
                'role' => $e->role->value,
                'certificate_issued_at' => $e->activeCertificate?->issued_at?->toDateString(),
            ]);

        return Inertia::render('reports/Index', [
            'rows' => $rows,
            'filters' => $filters,
            'totals' => EnrollmentReport::totals($filters),
            'options' => [
                'courses' => Course::orderBy('code')->get(['id', 'code', 'name']),
                'years' => CourseEdition::query()->distinct()->orderByDesc('year')->pluck('year'),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('reports.view');

        $filters = EnrollmentReport::filters($request);

        return Excel::download(
            new EnrollmentReportExport($filters),
            'reporte-matriculas-'.now()->format('Y-m-d').'.xlsx',
        );
    }
}
