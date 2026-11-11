<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory()->approved(),
            'pdf_path' => null,
            'issued_at' => now(),
        ];
    }

    public function revoked(string $reason = 'Reemisión por corrección de datos'): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }
}
