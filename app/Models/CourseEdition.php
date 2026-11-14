<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\CourseEditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'teacher_id', 'start_date', 'end_date', 'year'])]
class CourseEdition extends Model
{
    /** @use HasFactory<CourseEditionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function hasDates(): bool
    {
        return $this->start_date !== null;
    }

    /**
     * Finalizada: su fecha de fin ya pasó, o es una edición histórica sin
     * fechas (importada del Excel, siempre de un año anterior ya cerrado).
     */
    public function isFinished(): bool
    {
        // Comparación por fecha, no por instante: debe coincidir exactamente
        // con scopeWhereFinished (una edición que termina hoy sigue "en curso").
        return $this->end_date === null || $this->end_date->isBefore(today());
    }

    /**
     * @param  Builder<CourseEdition>  $query
     */
    public function scopeWhereFinished(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '<', now()->toDateString()));
    }

    /**
     * @param  Builder<CourseEdition>  $query
     */
    public function scopeWhereNotFinished(Builder $query): void
    {
        $query->whereNotNull('end_date')->where('end_date', '>=', now()->toDateString());
    }

    /**
     * Periodo legible: "del 01/02/2024 al 20/02/2024" o "en el año 2024".
     */
    public function periodText(): string
    {
        return $this->hasDates()
            ? sprintf('del %s al %s', $this->start_date->format('d/m/Y'), $this->end_date->format('d/m/Y'))
            : sprintf('en el año %d', $this->year);
    }

    /**
     * Ediciones que el usuario puede ver: todas con editions.manage;
     * con solo editions.view, únicamente las que dicta como ponente.
     *
     * @param  Builder<CourseEdition>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::EditionsManage->value)) {
            return;
        }

        $query->whereHas('teacher', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    /**
     * Etiqueta legible de la edición, p. ej. "PA · 01/02/2024 – 20/02/2024"
     * o "PA · Año 2024" para las ediciones históricas sin fechas.
     */
    public function label(): string
    {
        if ($this->start_date === null) {
            return sprintf('%s · Año %d', $this->course?->code ?? '—', $this->year);
        }

        return sprintf(
            '%s · %s – %s',
            $this->course?->code ?? '—',
            $this->start_date?->format('d/m/Y'),
            $this->end_date?->format('d/m/Y'),
        );
    }
}
