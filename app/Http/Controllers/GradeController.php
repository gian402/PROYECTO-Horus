<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Http\Requests\Editions\UpdateGradesRequest;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    use InteractsWithToasts;

    /**
     * Guarda las notas de la edición en una sola transacción.
     * El estado (cursando / aprobado / desaprobado) lo deriva Enrollment al guardar.
     */
    public function update(UpdateGradesRequest $request, CourseEdition $edition): RedirectResponse
    {
        /** @var array<int|string, string|null> $grades */
        $grades = $request->validated('grades');

        $changed = DB::transaction(function () use ($edition, $grades): int {
            $changed = 0;

            $edition->enrollments()
                ->whereIn('id', array_keys($grades))
                ->lockForUpdate()
                ->get()
                ->each(function (Enrollment $enrollment) use ($grades, &$changed): void {
                    $enrollment->grade = $grades[$enrollment->id];

                    if ($enrollment->isDirty('grade')) {
                        $enrollment->save();
                        $changed++;
                    }
                });

            return $changed;
        });

        $this->toast($changed === 0 ? 'No había cambios en las notas.' : "Notas guardadas ({$changed}).");

        return back();
    }
}
