<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            // Asiento de la contabilidad en el que entró este recibo. Los 300 recibos de
            // un mismo vencimiento comparten asiento, así que comparten este número: no
            // es suyo, es del hecho contable en el que salieron todos.
            //
            // Sin FK a propósito: es una referencia opaca a un módulo que no conoce a la
            // gestión, igual que la cuenta contable del propietario. Nulo = todavía no
            // enlazado, que es lo normal en una comunidad que no lleva contabilidad.
            $table->unsignedBigInteger('asiento_contable')->nullable()->after('estado_id')
                ->index('recibos_asiento_contable_index');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropIndex('recibos_asiento_contable_index');
            $table->dropColumn('asiento_contable');
        });
    }
};
