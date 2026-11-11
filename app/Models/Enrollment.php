<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Matrícula de un participante en una edición.
 *
 * `status` NO es asignable: se deriva siempre de `grade` en el evento saving,
 * de modo que el backend es la única fuente del estado académico.
 * `registration_code` debe asignarse mediante App\Actions\Enrollments\EnrollStudent.
 *
 * @property EnrollmentStatus $status
 */
#[Fillable(['student_id', 'course_edition_id', 'registration_code', 'grade'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'cursando',
    ];

    protected static function booted(): void
    {
        static::saving(function (Enrollment $enrollment): void {
            $enrollment->status = EnrollmentStatus::fromGrade($enrollment->grade);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<CourseEdition, $this>
     */
    public function courseEdition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class);
    }

    /**
     * Historial completo de certificados (emisiones y reemisiones).
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Certificado vigente (a lo sumo uno, garantizado por índice único parcial).
     *
     * @return HasOne<Certificate, $this>
     */
    public function activeCertificate(): HasOne
    {
        return $this->hasOne(Certificate::class)->whereNull('revoked_at');
    }

    public function isApproved(): bool
    {
        return $this->status === EnrollmentStatus::Aprobado;
    }
}
