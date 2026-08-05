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
        Schema::create('tipo_estado_recibos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 50);
        });

        // Ciclo de vida del recibo, no de su cobro: «enviado» es que ya se le ha pedido
        // el dinero al propietario, por remesa o por correo según su forma de pago, y no
        // significa cobrado. Cuánto se debe lo dice el saldo, no el estado.
        DB::table('tipo_estado_recibos')->insert([
            ['id' => 1, 'descripcion' => 'Generado'],
            ['id' => 2, 'descripcion' => 'Enviado'],
            ['id' => 3, 'descripcion' => 'Cobrado'],
            ['id' => 4, 'descripcion' => 'Devuelto'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_estado_recibos');
    }
};
