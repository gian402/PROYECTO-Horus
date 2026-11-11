<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_editions', function (Blueprint $table) {
            $table->id();
            // restrict: un curso o ponente con ediciones no puede borrarse (historial).
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            // nullable: la convocatoria puede abrirse antes de confirmar al ponente.
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('year');
            $table->timestamps();

            // PostgreSQL no indexa automáticamente las FK: ediciones por curso / por ponente
            // y verificación rápida de RESTRICT al borrar el padre.
            $table->index('course_id');
            $table->index('teacher_id');
            // Filtros de reportes por año académico.
            $table->index('year');
        });

        DB::statement('ALTER TABLE course_editions ADD CONSTRAINT course_editions_dates_check CHECK (end_date >= start_date)');
        DB::statement('ALTER TABLE course_editions ADD CONSTRAINT course_editions_year_check CHECK (year BETWEEN 2000 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_editions');
    }
};
