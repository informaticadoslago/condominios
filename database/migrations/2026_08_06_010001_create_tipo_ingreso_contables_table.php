<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clases de ingreso, lo mismo que tipo_tercero_contables pero para el grupo 7: dicen
     * de qué cuenta de cuatro dígitos cuelgan las subcuentas que se creen.
     *
     * Existe porque una derrama no es un tercero —nadie le debe nada, es un concepto de
     * ingreso—, así que no puede numerarse por el catálogo de terceros aunque la regla
     * del código (4 dígitos de grupo + 4 de correlativo) sea la misma.
     */
    public function up(): void
    {
        Schema::create('tipo_ingreso_contables', function (Blueprint $table) {
            $table->id();
            // Referencia estable desde la API: quien llama manda 'cuotas', no un id.
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 50);
            // Grupo de 4 dígitos del que cuelgan las subcuentas de esta clase de ingreso.
            $table->char('prefijo_cuenta', 4);
            $table->unsignedBigInteger('estado_id')->default(1)->index('tipo_ingreso_contables_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });

        // Los prefijos son los del plan de cuentas global: 7500 «Ingresos por cuotas de
        // comunidad» y 7501 «Ingresos por derramas».
        DB::table('tipo_ingreso_contables')->insert([
            ['id' => 1, 'codigo' => 'cuotas', 'descripcion' => 'Ingresos por cuotas de comunidad', 'prefijo_cuenta' => '7500', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'codigo' => 'derramas', 'descripcion' => 'Ingresos por derramas', 'prefijo_cuenta' => '7501', 'estado_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_ingreso_contables');
    }
};
