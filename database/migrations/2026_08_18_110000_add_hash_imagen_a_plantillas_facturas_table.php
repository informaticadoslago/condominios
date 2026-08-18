<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconocimiento de proveedor por la imagen de cabecera, para plantillas cuyo CIF/razón
 * social están "quemados" en una imagen (ver ExtractorPorCoordenadas y
 * LectorPdf::extraerImagenPrincipal): al no aparecer el CIF en el texto de las facturas
 * nuevas, no hay forma de encontrar la plantilla por el mecanismo de siempre. El hash
 * identifica al proveedor porque reutiliza siempre la misma imagen de fondo, byte a byte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantillas_facturas', function (Blueprint $table) {
            $table->string('hash_imagen', 64)->nullable()->after('razon_social')->index();
        });
    }

    public function down(): void
    {
        Schema::table('plantillas_facturas', function (Blueprint $table) {
            $table->dropColumn('hash_imagen');
        });
    }
};
