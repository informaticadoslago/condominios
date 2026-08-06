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
        Schema::table('comunidades', function (Blueprint $table) {
            // Empresa contable en la que esta comunidad lleva sus libros. Se rellena con
            // el botón "Enlace contabilidad".
            //
            // A propósito SIN clave ajena: la gestión es un cliente más de la
            // contabilidad, igual que lo sería un sistema ajeno entrando por la API, y
            // un cliente no ata la base de datos del servicio del que tira. La
            // integridad la garantiza el servicio que rellena la columna, no el motor.
            $table->unsignedBigInteger('empresa_contable_id')->nullable()->after('sufijo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comunidades', function (Blueprint $table) {
            $table->dropColumn('empresa_contable_id');
        });
    }
};
