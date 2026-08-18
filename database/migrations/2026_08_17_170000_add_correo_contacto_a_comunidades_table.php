<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correo al que el propietario debe escribir si algo no cuadra (cuenta ya no es
     * suya, datos incorrectos...). Antes iba literal en la plantilla de aviso de
     * cargo; cada comunidad tiene el suyo, así que hace falta guardarlo aquí.
     */
    public function up(): void
    {
        Schema::table('comunidades', function (Blueprint $table) {
            $table->string('correo_contacto', 150)->nullable()->after('identificador_acreedor_sepa');
        });
    }

    public function down(): void
    {
        Schema::table('comunidades', function (Blueprint $table) {
            $table->dropColumn('correo_contacto');
        });
    }
};
