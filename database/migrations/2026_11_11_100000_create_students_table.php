<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 15)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            // Búsquedas y ordenamiento por apellidos/nombres en listados.
            $table->index(['last_name', 'first_name']);
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_dni_not_blank CHECK (btrim(dni) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
