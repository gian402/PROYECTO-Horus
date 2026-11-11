<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Cursando = 'cursando';
    case Aprobado = 'aprobado';
    case Desaprobado = 'desaprobado';

    /**
     * Determina el estado académico a partir de la nota.
     *
     * Regla única del sistema: sin nota => cursando; nota >= mínima => aprobado;
     * en otro caso => desaprobado. La nota se redondea a 2 decimales para
     * coincidir con la precisión almacenada en PostgreSQL (numeric(4,2)).
     */
    public static function fromGrade(int|float|string|null $grade): self
    {
        if ($grade === null || $grade === '') {
            return self::Cursando;
        }

        $passing = (float) config('academic.passing_grade', 14);

        return round((float) $grade, 2) >= $passing
            ? self::Aprobado
            : self::Desaprobado;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cursando => 'Cursando',
            self::Aprobado => 'Aprobado',
            self::Desaprobado => 'Desaprobado',
        };
    }
}
