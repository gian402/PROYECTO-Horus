<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return HasMany<CourseEdition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(CourseEdition::class);
    }

    /**
     * @return HasMany<RegistrationSequence, $this>
     */
    public function registrationSequences(): HasMany
    {
        return $this->hasMany(RegistrationSequence::class);
    }

    /**
     * Normaliza el código a mayúsculas (la BD exige ^[A-Z0-9]+$).
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value): string => mb_strtoupper(trim($value)));
    }
}
