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
        Schema::create('formas_pago_inmuebles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inmueble_id')->index('formas_pago_inmuebles_inmueble_id_foreign');
            $table->unsignedBigInteger('forma_de_pago_id')->index('formas_pago_inmuebles_forma_de_pago_id_foreign');
            $table->unsignedBigInteger('cuenta_bancaria_id')->nullable()->index('formas_pago_inmuebles_cuenta_bancaria_id_foreign');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->foreign('inmueble_id')->references('id')->on('inmuebles')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('forma_de_pago_id')->references('id')->on('formas_de_pago')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formas_pago_inmuebles');
    }
};
