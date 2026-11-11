<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificados con historial de reemisiones: una matrícula puede tener
     * varios certificados, pero solo uno vigente (revoked_at IS NULL).
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            // Identificador público de verificación (/validar/{hash}): 64 hex aleatorios.
            $table->string('unique_hash', 64)->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();

            // Historial de certificados de una matrícula.
            $table->index('enrollment_id');
        });

        DB::statement('CREATE UNIQUE INDEX certificates_one_active_per_enrollment ON certificates (enrollment_id) WHERE revoked_at IS NULL');
        DB::statement('ALTER TABLE certificates ADD CONSTRAINT certificates_revoked_after_issued CHECK (revoked_at IS NULL OR revoked_at >= issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
