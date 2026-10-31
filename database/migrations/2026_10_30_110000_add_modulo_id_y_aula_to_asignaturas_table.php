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
        Schema::table('asignaturas', function (Blueprint $table) {
            // Nulos: las asignaturas que ya existen no tienen ni módulo ni aula.
            $table->unsignedBigInteger('modulo_id')->nullable()->after('horario_id')->index('asignaturas_modulo_id_foreign');
            $table->string('aula', 10)->nullable()->after('nombre');

            $table->foreign('modulo_id')->references('id')->on('modulos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asignaturas', function (Blueprint $table) {
            $table->dropForeign(['modulo_id']);
            $table->dropIndex('asignaturas_modulo_id_foreign');
            $table->dropColumn(['modulo_id', 'aula']);
        });
    }
};
