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
        Schema::table('proveedores', function (Blueprint $table) {
            // Nula para los proveedores que ya estaban dados de alta; en el alta nueva
            // se pide siempre.
            $table->unsignedBigInteger('tipo_proveedor_id')->nullable()->after('persona_comunidad_id')
                ->index('proveedores_tipo_proveedor_id_foreign');

            $table->foreign('tipo_proveedor_id')->references('id')->on('tipo_proveedores')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropForeign(['tipo_proveedor_id']);
            $table->dropColumn('tipo_proveedor_id');
        });
    }
};
