<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un presupuesto es de cuotas o de derrama, entero: no mezcla conceptos de los dos.
     *
     * `codigo_ingreso` es la palabra que se le manda a la contabilidad para que sepa de
     * qué grupo colgar la cuenta de ingresos. Se guarda aquí, y no una FK a
     * tipo_ingreso_contables, porque la gestión no conoce las tablas de la contabilidad.
     */
    public function up(): void
    {
        Schema::create('tipo_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
            $table->string('codigo_ingreso', 20);
        });

        DB::table('tipo_presupuestos')->insert([
            ['id' => 1, 'descripcion' => 'Cuotas', 'codigo_ingreso' => 'cuotas'],
            ['id' => 2, 'descripcion' => 'Derrama', 'codigo_ingreso' => 'derramas'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_presupuestos');
    }
};
