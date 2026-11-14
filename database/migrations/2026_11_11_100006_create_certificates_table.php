<?php

use App\Support\Sql;
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

        if (Sql::isPgsql()) {
            // Índice único parcial: solo un certificado vigente (no anulado) por matrícula.
            DB::statement('CREATE UNIQUE INDEX certificates_one_active_per_enrollment ON certificates (enrollment_id) WHERE revoked_at IS NULL');
        } else {
            // MySQL no soporta índices únicos parciales. Se simula con una columna generada
            // que vale enrollment_id solo mientras el certificado está vigente (o NULL si no):
            // el índice único permite muchos NULL, así que solo se compite por el valor vigente.
            DB::statement('ALTER TABLE certificates ADD COLUMN active_enrollment_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN revoked_at IS NULL THEN enrollment_id END) VIRTUAL');
            DB::statement('ALTER TABLE certificates ADD UNIQUE INDEX certificates_one_active_per_enrollment (active_enrollment_id)');
        }

        DB::statement('ALTER TABLE certificates ADD CONSTRAINT certificates_revoked_after_issued CHECK (revoked_at IS NULL OR revoked_at >= issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
