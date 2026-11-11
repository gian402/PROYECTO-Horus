<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ponente. La cuenta de usuario es opcional (user_id): solo si el ponente
 * debe iniciar sesión para ver y calificar sus ediciones.
 */
#[Fillable(['dni', 'full_name', 'specialty', 'user_id'])]
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
