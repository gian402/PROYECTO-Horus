<?php

use App\Enums\EnrollmentStatus;
use App\Services\RegistrationCodeGenerator;

describe('EnrollmentStatus::fromGrade', function () {
    it('derives the status from the grade', function (int|float|string|null $grade, EnrollmentStatus $expected) {
        expect(EnrollmentStatus::fromGrade($grade))->toBe($expected);
    })->with([
        'sin nota' => [null, EnrollmentStatus::Cursando],
        'cadena vacía' => ['', EnrollmentStatus::Cursando],
        'cero' => [0, EnrollmentStatus::Desaprobado],
        'justo debajo del mínimo' => [13.99, EnrollmentStatus::Desaprobado],
        'mínimo exacto' => [14, EnrollmentStatus::Aprobado],
        'mínimo como decimal de BD' => ['14.00', EnrollmentStatus::Aprobado],
        'nota máxima' => [20, EnrollmentStatus::Aprobado],
    ]);

    it('uses the centralized passing grade from config', function () {
        config(['academic.passing_grade' => 11]);

        expect(EnrollmentStatus::fromGrade(11))->toBe(EnrollmentStatus::Aprobado)
            ->and(EnrollmentStatus::fromGrade(10.99))->toBe(EnrollmentStatus::Desaprobado);
    });
});

describe('RegistrationCodeGenerator::format', function () {
    it('formats registration codes', function (string $code, int $number, int $year, string $expected) {
        expect(RegistrationCodeGenerator::format($code, $number, $year))->toBe($expected);
    })->with([
        ['PA', 1, 2024, 'HGC-PA-01-024'],
        ['INY', 4, 2024, 'HGC-INY-04-024'],
        ['NUT', 12, 2025, 'HGC-NUT-12-025'],
        ['PA', 100, 2024, 'HGC-PA-100-024'],
        ['SSO', 3, 2100, 'HGC-SSO-03-100'],
    ]);
});
