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
        Schema::create('remesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('remesas_comunidad_id_foreign');
            // Cuenta de la comunidad en la que se abona el cargo (la del acreedor).
            $table->unsignedBigInteger('cuenta_bancaria_id')->index('remesas_cuenta_bancaria_id_foreign');
            $table->string('referencia', 35)->unique('remesas_referencia_unique');
            $table->date('fecha_cargo');
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remesas');
    }
};
