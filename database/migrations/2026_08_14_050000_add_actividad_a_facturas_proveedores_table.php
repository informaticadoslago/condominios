<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable a propósito, y por dos motivos distintos: la comunidad puede no tener
     * actividades, o el gasto puede ser del CIF entero sin base de reparto entre ellas
     * (ver [[project-proyecto-contable]] — el ejemplo del alquiler del patio).
     */
    public function up(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_id')->nullable()->after('proveedor_id')
                ->index('facturas_proveedores_actividad_id_foreign');

            $table->foreign('actividad_id')->references('id')->on('actividades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->dropForeign(['actividad_id']);
            $table->dropColumn('actividad_id');
        });
    }
};
