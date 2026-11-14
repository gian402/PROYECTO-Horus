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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 15)->unique();
            $table->string('full_name', 150);
            $table->string('specialty', 150)->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE teachers ADD CONSTRAINT teachers_dni_not_blank CHECK ('.Sql::notBlankCheck('dni').')');
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
