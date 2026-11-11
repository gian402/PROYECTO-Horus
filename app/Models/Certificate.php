<?php

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Certificado emitido. `unique_hash` es el identificador público de verificación:
 * se genera aquí con un CSPRNG y no es asignable masivamente.
 */
#[Fillable(['enrollment_id', 'pdf_path', 'issued_at', 'revoked_at', 'revocation_reason'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            if (empty($certificate->unique_hash)) {
                $certificate->unique_hash = self::generateHash();
            }

            $certificate->issued_at ??= now();
        });
    }

    public static function generateHash(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
