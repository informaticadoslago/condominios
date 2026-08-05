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
        Schema::create('apunte_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asiento_contable_id')->index('apunte_contables_asiento_contable_id_foreign');
            $table->unsignedBigInteger('cuenta_contable_id')->index('apunte_contables_cuenta_contable_id_foreign');
            // Importes en céntimos, enteros. Con signo a propósito: con columnas UNSIGNED,
            // MariaDB revienta en cualquier resta a nivel de fila — SUM(debe - haber),
            // debe - haber, o el saldo acumulado del mayor con una window function.
            // El no-negativo se valida en la aplicación.
            $table->bigInteger('debe')->default(0);
            $table->bigInteger('haber')->default(0);
            $table->string('concepto', 255)->nullable();
            $table->timestamps();

            $table->foreign('asiento_contable_id')->references('id')->on('asiento_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_contable_id')->references('id')->on('cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apunte_contables');
    }
};
