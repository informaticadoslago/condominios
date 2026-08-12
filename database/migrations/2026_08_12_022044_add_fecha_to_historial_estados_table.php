<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('historial_estados', function (Blueprint $table) {
            // Fecha de negocio del cambio (p. ej. la fecha de cobro), distinta de
            // created_at que es cuándo se ejecutó la acción en el sistema.
            $table->date('fecha')->nullable()->after('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historial_estados', function (Blueprint $table) {
            $table->dropColumn('fecha');
        });
    }
};
