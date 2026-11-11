<?php

namespace App\Services\Import;

use App\Enums\EnrollmentRole;

/**
 * Fila normalizada de un archivo de importación (plantilla del CRM o libro histórico).
 */
final class ImportRow
{
    /**
     * Código de registro, incluidos los formatos heredados:
     * HGC-PA-01-024, HGC-NUT-DIET-01-2024, HGC-SM-010-2024.
     */
    public const CODE_PATTERN = '/^(?<prefix>[A-Z]+)-(?<course>[A-Z0-9]+(?:-[A-Z0-9]+)*)-(?<number>\d{2,})-(?<year>\d{3,4})$/';

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $line,
        public readonly string $dni,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $courseCode,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?int $year,
        public readonly ?string $registrationCode,
        public readonly ?string $grade,
        public array $errors = [],
        public readonly ?string $sheet = null,
        public readonly ?string $courseName = null,
        public readonly EnrollmentRole $role = EnrollmentRole::Participante,
        public readonly bool $historical = false,
        public array $warnings = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function isSpeaker(): bool
    {
        return $this->role === EnrollmentRole::Ponente;
    }

    /**
     * @return array{prefix: string, course: string, number: int, year: int}|null
     */
    public static function parseCode(?string $code): ?array
    {
        if ($code === null || ! preg_match(self::CODE_PATTERN, $code, $m)) {
            return null;
        }

        $year = (int) $m['year'];

        return [
            'prefix' => $m['prefix'],
            'course' => $m['course'],
            'number' => (int) $m['number'],
            'year' => strlen($m['year']) === 3 ? 2000 + $year : $year,
        ];
    }

    /**
     * Número correlativo (NN) del código, p. ej. HGC-NUT-DIET-07-2024 → 7.
     */
    public function registrationNumber(): ?int
    {
        return self::parseCode($this->registrationCode)['number'] ?? null;
    }

    /**
     * Origen legible de la fila: "Hoja X, fila N" o "Fila N".
     */
    public function origin(): string
    {
        return $this->sheet !== null ? "Hoja {$this->sheet}, fila {$this->line}" : "Fila {$this->line}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'sheet' => $this->sheet,
            'origin' => $this->origin(),
            'dni' => $this->dni,
            'name' => trim("{$this->lastName}, {$this->firstName}", ', '),
            'course_code' => $this->courseCode,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'year' => $this->year,
            'registration_code' => $this->registrationCode,
            'grade' => $this->grade,
            'role' => $this->role->value,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
