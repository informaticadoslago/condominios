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
        Schema::create('tipo_tercero_contables', function (Blueprint $table) {
            $table->id();
            // Referencia estable desde la API: quien llama manda 'cliente', no un id.
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 50);
            // Grupo de 4 dígitos del que cuelgan las subcuentas de este tipo de tercero.
            $table->char('prefijo_cuenta', 4);
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_tercero_contables_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        DB::table('tipo_tercero_contables')->insert([
            ['id' => 1, 'codigo' => 'proveedor', 'descripcion' => 'Proveedores', 'prefijo_cuenta' => '4000', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'codigo' => 'acreedor', 'descripcion' => 'Acreedores por prestaciones de servicios', 'prefijo_cuenta' => '4100', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'codigo' => 'cliente', 'descripcion' => 'Clientes', 'prefijo_cuenta' => '4300', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'codigo' => 'deudor', 'descripcion' => 'Deudores varios', 'prefijo_cuenta' => '4400', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_tercero_contables');
    }
};
