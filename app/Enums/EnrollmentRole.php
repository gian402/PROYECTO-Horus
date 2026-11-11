<?php

namespace App\Enums;

/**
 * Rol de una matrícula. Los ponentes también reciben código de registro y
 * certificado (práctica heredada de los libros Excel), pero no se califican.
 */
enum EnrollmentRole: string
{
    case Participante = 'participante';
    case Ponente = 'ponente';

    public function label(): string
    {
        return match ($this) {
            self::Participante => 'Participante',
            self::Ponente => 'Ponente',
        };
    }
}
