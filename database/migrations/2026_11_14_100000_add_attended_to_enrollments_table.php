<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asistencia por matrícula: null = sin registrar, true/false = asistió o no.
     * Un solo marcador por edición, no por sesión: los talleres de Horus son
     * de corta duración, no cursos con varias clases en fechas distintas.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('attended')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('attended');
        });
    }
};
