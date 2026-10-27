<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombres de los ficheros que iban adjuntos al correo (["convocatoria-….pdf",
     * "cuentas-2025.pdf"]). Solo los nombres: el contenido no se guarda, que serían
     * copias del mismo fichero por cada destinatario. Nulo = sin adjuntos.
     */
    public function up(): void
    {
        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->json('adjuntos')->nullable()->after('asunto');
        });
    }

    public function down(): void
    {
        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->dropColumn('adjuntos');
        });
    }
};
