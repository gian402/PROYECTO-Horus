<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para los registros históricos (libros Excel previos al CRM).
 *
 *  - Códigos de curso con guion interno (NUT-DIET), como en los códigos ya emitidos.
 *  - Ediciones históricas sin fechas: ambas fechas nulas o ambas presentes.
 *  - Rol de la matrícula (participante / ponente): los ponentes también reciben
 *    código de registro y certificado.
 *  - Matrículas históricas: sin nota se consideran aprobadas (decisión del negocio).
 *
 * Aditiva: no borra datos; solo agrega columnas y relaja restricciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE courses DROP CONSTRAINT courses_code_format');
        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_code_format CHECK (code ~ '^[A-Z0-9]+(-[A-Z0-9]+)*$')");

        Schema::table('course_editions', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
        DB::statement('ALTER TABLE course_editions DROP CONSTRAINT course_editions_dates_check');
        DB::statement('ALTER TABLE course_editions ADD CONSTRAINT course_editions_dates_check CHECK ((start_date IS NULL) = (end_date IS NULL) AND (end_date IS NULL OR end_date >= start_date))');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('role', 15)->default('participante');
            $table->boolean('historical')->default(false);
        });
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_role_values CHECK (role IN ('participante', 'ponente'))");
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT enrollments_grade_status_consistency');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_grade_status_consistency CHECK (((grade IS NULL) = (status = 'cursando')) OR (grade IS NULL AND status = 'aprobado' AND (historical OR role = 'ponente')))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT enrollments_grade_status_consistency');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_grade_status_consistency CHECK ((grade IS NULL) = (status = 'cursando'))");
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT enrollments_role_values');
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['role', 'historical']);
        });

        DB::statement('ALTER TABLE course_editions DROP CONSTRAINT course_editions_dates_check');
        Schema::table('course_editions', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
        DB::statement('ALTER TABLE course_editions ADD CONSTRAINT course_editions_dates_check CHECK (end_date >= start_date)');

        DB::statement('ALTER TABLE courses DROP CONSTRAINT courses_code_format');
        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_code_format CHECK (code ~ '^[A-Z0-9]+$')");
    }
};
