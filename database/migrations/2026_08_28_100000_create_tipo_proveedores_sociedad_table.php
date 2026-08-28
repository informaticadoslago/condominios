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
        Schema::create('tipo_proveedores_sociedad', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            // Código de la cuenta de gasto/compra a la que van sus facturas. Opaca, sin FK
            // (mismo criterio que tipo_proveedores): el módulo contable vive aparte.
            // Subgrupo 60 -> pasivo 400 (proveedor); cualquier otra -> pasivo 410 (acreedor).
            $table->char('cuenta_gasto', 8);
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_proveedores_sociedad_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_proveedores_sociedad')->insert([
            // Subgrupo 60: van contra 400 (proveedor).
            ['descripcion' => 'Compras de mercaderías', 'cuenta_gasto' => '60000000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Compras de materias primas', 'cuenta_gasto' => '60100000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Compras de otros aprovisionamientos', 'cuenta_gasto' => '60200000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Trabajos realizados por otras empresas', 'cuenta_gasto' => '60700000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Subgrupo 62: van contra 410 (acreedor).
            ['descripcion' => 'Arrendamientos y cánones', 'cuenta_gasto' => '62100000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Reparación y conservación', 'cuenta_gasto' => '62200000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Servicios de profesionales independientes', 'cuenta_gasto' => '62300000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Transportes', 'cuenta_gasto' => '62400000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Primas de seguros', 'cuenta_gasto' => '62500000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Servicios bancarios y similares', 'cuenta_gasto' => '62600000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Publicidad, propaganda y relaciones públicas', 'cuenta_gasto' => '62700000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Suministros', 'cuenta_gasto' => '62800000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Servicios de limpieza', 'cuenta_gasto' => '62900000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_proveedores_sociedad');
    }
};
