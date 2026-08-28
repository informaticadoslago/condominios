<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropForeign('proveedores_tipo_proveedor_id_foreign');
            $table->dropColumn('tipo_proveedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_proveedor_id')->nullable()
                ->after('persona_id')->index('proveedores_tipo_proveedor_id_foreign');
        });

        DB::table('proveedores')
            ->where('tipo_type', \App\Models\TipoProveedor::class)
            ->update(['tipo_proveedor_id' => DB::raw('tipo_id')]);

        Schema::table('proveedores', function (Blueprint $table) {
            $table->foreign('tipo_proveedor_id')->references('id')->on('tipo_proveedores')->onUpdate('restrict')->onDelete('restrict');
        });
    }
};
