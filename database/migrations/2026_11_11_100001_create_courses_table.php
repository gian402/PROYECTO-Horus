<?php

use App\Support\Sql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // El código forma parte del código de registro (HGC-{code}-NN-AAA):
        // solo mayúsculas y dígitos, sin guiones ni espacios.
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_code_format CHECK ('.Sql::regexCheck('code', '^[A-Z0-9]+$').')');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
