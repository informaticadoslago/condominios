<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generaliza avisos_recibos para que sirva de rastro de cualquier correo enviado (o
 * encolado), no solo los ligados a un recibo: bienvenida de usuario, verificación de
 * dirección de propietario, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('avisos_recibos', 'correos_enviados');

        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->renameColumn('motivo', 'asunto');
        });

        Schema::table('correos_enviados', function (Blueprint $table) {
            // Clase Mailable usada: identifica el tipo de correo (aviso de remesa,
            // bienvenida, verificación de dirección...) sin necesitar un catálogo aparte.
            $table->string('tipo', 150)->after('id');
            $table->unsignedBigInteger('recibo_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->unsignedBigInteger('recibo_id')->nullable(false)->change();
            $table->dropColumn('tipo');
        });

        Schema::table('correos_enviados', function (Blueprint $table) {
            $table->renameColumn('asunto', 'motivo');
        });

        Schema::rename('correos_enviados', 'avisos_recibos');
    }
};
