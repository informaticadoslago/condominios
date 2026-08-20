<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Volcado en bruto del extracto del banco (CSV o Q43), sin clasificar: todo lo
     * que trae el fichero, línea a línea, tal cual. `hash` es la línea entera
     * (fechas+tipo+descripción+referencia+importe+saldo+divisa): si se borra un
     * movimiento y se reimporta el mismo fichero, vuelve a entrar; si NO se borra,
     * el hash ya existe y esa línea se salta.
     */
    public function up(): void
    {
        Schema::create('movimientos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_bancaria_id')->index('movimientos_bancarios_cuenta_bancaria_id_foreign');
            $table->date('fecha_valor');
            $table->date('fecha_contable')->nullable();
            $table->date('fecha_operacion')->nullable();
            $table->string('tipo_operacion', 60);
            $table->string('descripcion', 255)->nullable();
            $table->string('referencia', 60)->nullable();
            $table->decimal('importe', 10, 2);
            $table->decimal('saldo', 10, 2)->nullable();
            $table->string('divisa', 3)->nullable();
            $table->string('hash', 64);
            $table->timestamps();

            $table->unique(['cuenta_bancaria_id', 'hash'], 'movimientos_bancarios_unico');

            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};
