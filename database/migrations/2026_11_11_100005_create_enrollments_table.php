<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_edition_id')->constrained()->restrictOnDelete();
            $table->string('registration_code', 30)->unique();
            $table->decimal('grade', 4, 2)->nullable();
            $table->string('status', 15)->default('cursando');
            $table->timestamps();

            // Evita matricular dos veces al mismo participante en la misma edición.
            // Su columna líder (student_id) cubre también el historial por participante.
            $table->unique(['student_id', 'course_edition_id']);
            // Listado de matriculados por edición (el índice anterior no lo cubre).
            $table->index('course_edition_id');
            // Reportes por estado académico.
            $table->index('status');
        });

        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_grade_range CHECK (grade IS NULL OR (grade >= 0 AND grade <= 20))');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_values CHECK (status IN ('cursando', 'aprobado', 'desaprobado'))");
        // Coherencia mínima independiente del umbral: sin nota <=> cursando.
        // El umbral aprobatorio (14) se aplica en el dominio para poder cambiarlo sin migración.
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_grade_status_consistency CHECK ((grade IS NULL) = (status = 'cursando'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
