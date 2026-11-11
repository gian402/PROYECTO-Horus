<?php

namespace App\Services;

use App\Models\CourseEdition;
use App\Models\RegistrationSequence;
use Illuminate\Support\Facades\DB;

/**
 * Genera códigos de registro HGC-{CURSO}-{NN}-{AAA} con correlativo por curso y año.
 *
 * Seguridad ante concurrencia (PostgreSQL):
 *  1. INSERT ... ON CONFLICT DO NOTHING garantiza que exista la fila contador
 *     sin carreras entre dos primeras matrículas simultáneas.
 *  2. SELECT ... FOR UPDATE bloquea esa fila hasta el COMMIT de la transacción;
 *     una segunda solicitud espera y luego lee el valor ya incrementado.
 *  3. El UNIQUE de enrollments.registration_code es la última barrera.
 *
 * Llamarlo dentro de la misma transacción que inserta la matrícula
 * (ver App\Actions\Enrollments\EnrollStudent) para que un fallo posterior
 * revierta también el incremento y no deje huecos en la numeración.
 */
class RegistrationCodeGenerator
{
    public function next(CourseEdition $edition): string
    {
        $edition->loadMissing('course');

        return DB::transaction(function () use ($edition): string {
            $now = now();

            RegistrationSequence::query()->insertOrIgnore([
                'course_id' => $edition->course_id,
                'year' => $edition->year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var RegistrationSequence $sequence */
            $sequence = RegistrationSequence::query()
                ->where('course_id', $edition->course_id)
                ->where('year', $edition->year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return self::format($edition->course->code, $sequence->last_number, $edition->year);
        });
    }

    public static function format(string $courseCode, int $number, int $year): string
    {
        $padding = (int) config('academic.registration_number_padding', 2);
        $yearDigits = (int) config('academic.registration_year_digits', 3);

        return sprintf(
            '%s-%s-%s-%s',
            config('academic.registration_prefix', 'HGC'),
            $courseCode,
            str_pad((string) $number, $padding, '0', STR_PAD_LEFT),
            str_pad((string) ($year % (10 ** $yearDigits)), $yearDigits, '0', STR_PAD_LEFT),
        );
    }
}
