<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador del correlativo de códigos de registro por curso y año.
     * Se bloquea con SELECT ... FOR UPDATE al asignar cada código, de modo
     * que dos matrículas simultáneas nunca obtengan el mismo número.
     */
    public function up(): void
    {
        Schema::create('registration_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->smallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            // Una sola fila por (curso, año); también sirve de índice para el bloqueo.
            $table->unique(['course_id', 'year']);
        });

        DB::statement('ALTER TABLE registration_sequences ADD CONSTRAINT registration_sequences_last_number_check CHECK (last_number >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_sequences');
    }
};
