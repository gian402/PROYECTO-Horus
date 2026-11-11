<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ponente. No requiere cuenta de usuario propia.
 */
#[Fillable(['dni', 'full_name', 'specialty'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /**
     * @return HasMany<CourseEdition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(CourseEdition::class);
    }
}
