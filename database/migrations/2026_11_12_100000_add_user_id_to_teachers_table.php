<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo opcional entre un ponente y la cuenta de usuario con la que
     * inicia sesión (rol Ponente). Permite limitar al ponente a sus ediciones.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // UNIQUE: una cuenta corresponde a un solo ponente (también sirve de índice).
            // nullOnDelete: si se elimina la cuenta, el ponente y su historial se conservan.
            $table->foreignId('user_id')->nullable()->unique()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
