<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable a propósito: un apunte del CIF sin base de reparto entre proyectos (un
     * gasto compartido que no se puede prorratear) se queda sin proyecto. El que sí
     * pertenece a una actividad lo lleva en cada línea, no en el asiento.
     */
    public function up(): void
    {
        Schema::table('apunte_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto_contable_id')->nullable()->after('cuenta_contable_id')
                ->index('apunte_contables_proyecto_contable_id_foreign');

            $table->foreign('proyecto_contable_id')->references('id')->on('proyecto_contables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apunte_contables', function (Blueprint $table) {
            $table->dropForeign(['proyecto_contable_id']);
            $table->dropColumn('proyecto_contable_id');
        });
    }
};
