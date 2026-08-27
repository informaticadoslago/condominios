<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable: la inmensa mayoría de comunidades tiene una sola actividad implícita y no
     * necesita marcar nada. Solo las que se dividen en varias (dos torres, dos negocios
     * bajo el mismo CIF) dan de alta sus actividades y las asignan aquí. Todos los
     * recibos de este presupuesto, y los apuntes que generan al cobrarse, heredan esta
     * misma actividad.
     */
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_id')->nullable()->after('comunidad_id')
                ->index('presupuestos_actividad_id_foreign');

            $table->foreign('actividad_id')->references('id')->on('actividades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropForeign(['actividad_id']);
            $table->dropColumn('actividad_id');
        });
    }
};
