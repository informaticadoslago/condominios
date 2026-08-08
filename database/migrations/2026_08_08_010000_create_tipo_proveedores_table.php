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
        Schema::create('tipo_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            // Código de la cuenta de gasto a la que van sus facturas. Es una referencia
            // opaca: aquí no hay FK a cuentas_contables, que vive en el módulo contable.
            $table->char('cuenta_gasto', 8);
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_proveedores_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_proveedores')->insert([
            ['id' => 1, 'descripcion' => 'Reparación y conservación', 'cuenta_gasto' => '62200000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'descripcion' => 'Profesionales', 'cuenta_gasto' => '62300000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'descripcion' => 'Suministros', 'cuenta_gasto' => '62800000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'descripcion' => 'Limpieza', 'cuenta_gasto' => '62900000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_proveedores');
    }
};
