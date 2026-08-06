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
        Schema::create('mandatos_sepa', function (Blueprint $table) {
            $table->id();
            // El acreedor es la comunidad: su identificador de acreedor y su sufijo son
            // los que van en el adeudo, así que el mismo titular con la misma cuenta en
            // dos comunidades necesita dos mandatos.
            $table->unsignedBigInteger('comunidad_id')->index('mandatos_sepa_comunidad_id_foreign');
            $table->unsignedBigInteger('cuenta_bancaria_id')->index('mandatos_sepa_cuenta_bancaria_id_foreign');

            // RUM, tal y como se escribió a mano: P19 + NIF del titular de la cuenta +
            // contador. No se genera: lo teclea quien registra el mandato firmado.
            $table->string('referencia', 35);
            $table->date('fecha_firma');
            $table->timestamps();

            // Un RUM no se repite dentro de la misma comunidad...
            $table->unique(['comunidad_id', 'referencia'], 'mandatos_sepa_comunidad_referencia_unique');
            // ...y una cuenta tiene un único mandato: el RUM va casado con esa cuenta de
            // por vida. Si la cuenta se deja de usar, ese RUM muere con ella y no se
            // recicla para otra — para eso el contador avanza.
            $table->unique(['comunidad_id', 'cuenta_bancaria_id'], 'mandatos_sepa_comunidad_cuenta_unique');

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandatos_sepa');
    }
};
