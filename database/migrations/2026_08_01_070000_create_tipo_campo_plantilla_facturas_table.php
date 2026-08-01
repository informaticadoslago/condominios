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
        Schema::create('tipo_campo_plantilla_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        DB::table('tipo_campo_plantilla_facturas')->insert([
            ['id' => 1, 'descripcion' => 'Número de factura'],
            ['id' => 2, 'descripcion' => 'Fecha'],
            ['id' => 3, 'descripcion' => 'Importe'],
            ['id' => 4, 'descripcion' => 'CIF'],
            ['id' => 5, 'descripcion' => 'Razón social'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_campo_plantilla_facturas');
    }
};
