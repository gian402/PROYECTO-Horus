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
     * Guarda las notas y la asistencia de la edición en una sola transacción.
     * El estado (cursando / aprobado / desaprobado) lo deriva Enrollment al guardar.
     */
    public function update(UpdateGradesRequest $request, CourseEdition $edition): RedirectResponse
    {
        /** @var array<int|string, string|null> $grades */
        $grades = $request->validated('grades');
        /** @var array<int|string, bool|null> $attendance */
        $attendance = $request->validated('attendance') ?? [];

        $ids = array_unique([...array_keys($grades), ...array_keys($attendance)]);

        $changed = DB::transaction(function () use ($edition, $grades, $attendance, $ids): int {
            $changed = 0;

            $edition->enrollments()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->each(function (Enrollment $enrollment) use ($grades, $attendance, &$changed): void {
                    if (array_key_exists($enrollment->id, $grades)) {
                        $enrollment->grade = $grades[$enrollment->id];
                    }

                    if (array_key_exists($enrollment->id, $attendance)) {
                        $enrollment->attended = $attendance[$enrollment->id];
                    }

                    if ($enrollment->isDirty(['grade', 'attended'])) {
                        $enrollment->save();
                        $changed++;
                    }
                });

            return $changed;
        });

        $this->toast($changed === 0 ? 'No había cambios.' : "Cambios guardados ({$changed}).");

        return back();
    }
}
