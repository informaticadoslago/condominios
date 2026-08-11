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
        Schema::table('mandatos_sepa', function (Blueprint $table) {
            $table->unsignedBigInteger('estado_id')->default(1)->after('fecha_firma')->index('mandatos_sepa_estado_id_foreign');

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');

            // Un mandato cancelado deja el hueco libre para que esa misma cuenta pueda
            // tener uno nuevo (con RUM distinto, ver RegistrarMandatoSepa::cancelar()):
            // "una cuenta, un único mandato de por vida" pasa a ser "un único mandato
            // ACTIVO de por vida", comprobado en código, no ya con esta unique de BD.
            $table->dropUnique('mandatos_sepa_comunidad_cuenta_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mandatos_sepa', function (Blueprint $table) {
            $table->unique(['comunidad_id', 'cuenta_bancaria_id'], 'mandatos_sepa_comunidad_cuenta_unique');

            $table->dropForeign('mandatos_sepa_estado_id_foreign');
            $table->dropColumn('estado_id');
        });
    }
};
