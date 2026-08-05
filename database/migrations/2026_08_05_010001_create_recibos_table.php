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
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presupuesto_id')->index('recibos_presupuesto_id_foreign');
            $table->unsignedBigInteger('inmueble_id')->index('recibos_inmueble_id_foreign');
            $table->unsignedBigInteger('propietario_id')->index('recibos_propietario_id_foreign');
            $table->unsignedTinyInteger('numero_pago');
            $table->date('fecha_vencimiento')->index('recibos_fecha_vencimiento_index');
            $table->decimal('importe', 12, 2);
            $table->decimal('importe_pagado', 12, 2)->default(0);

            // Columna generada: el motor la mantiene, nadie la escribe. Persistente e
            // indexable (un moroso es un `where saldo > 0`), pero imposible de
            // desincronizar del importe y de lo cobrado.
            $table->decimal('saldo', 12, 2)->storedAs('importe - importe_pagado')
                ->index('recibos_saldo_index');

            // Copia congelada de la forma de pago vigente del inmueble al aprobarse el
            // presupuesto: así se sabe quién paga y cómo sin consultar la contabilidad
            // ni recalcular nada. Al remesar manda el IBAN vigente en ese momento, no
            // este; ver GeneradorRecibos.
            $table->unsignedBigInteger('forma_de_pago_id')->index('recibos_forma_de_pago_id_foreign');
            $table->unsignedBigInteger('cuenta_bancaria_id')->nullable()->index('recibos_cuenta_bancaria_id_foreign');

            $table->unsignedBigInteger('estado_id')->default(1)->index('recibos_estado_id_foreign');
            $table->timestamps();

            // Aprobar dos veces el mismo presupuesto no duplica los recibos.
            $table->unique(['presupuesto_id', 'inmueble_id', 'numero_pago'], 'recibos_presupuesto_inmueble_pago_unique');

            $table->foreign('presupuesto_id')->references('id')->on('presupuestos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('inmueble_id')->references('id')->on('inmuebles')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('propietario_id')->references('id')->on('propietarios')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('forma_de_pago_id')->references('id')->on('formas_de_pago')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('tipo_estado_recibos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
