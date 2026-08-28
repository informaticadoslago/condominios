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
            $table->string('tipo_type')->nullable()->after('tipo_proveedor_id');
            $table->unsignedBigInteger('tipo_id')->nullable()->after('tipo_type');
            $table->index(['tipo_type', 'tipo_id']);
        });

        // Todos los proveedores existentes son, hoy, de comunidad. tipo_proveedor_id ya
        // era nullable (proveedores antiguos sin tipo asignado): se deja igual aquí, sin
        // forzar NOT NULL, para no reventar esas filas.
        DB::table('proveedores')->whereNotNull('tipo_proveedor_id')->update([
            'tipo_type' => \App\Models\TipoProveedor::class,
            'tipo_id'   => DB::raw('tipo_proveedor_id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex(['tipo_type', 'tipo_id']);
            $table->dropColumn(['tipo_type', 'tipo_id']);
        });
    }
};
