<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convocatoria de una junta de la comunidad. Lleva siempre dos convocatorias el
     * mismo día: la segunda, por si en la primera no hay quórum. Por eso una sola fecha
     * y dos horas; de partida la segunda va 30 minutos después de la primera (ver
     * ConvocatoriaJunta::MINUTOS_ENTRE_CONVOCATORIAS), pero se puede cambiar.
     */
    public function up(): void
    {
        Schema::create('convocatorias_juntas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('convocatorias_juntas_comunidad_id_foreign');
            $table->unsignedBigInteger('tipo_junta_id')->index('convocatorias_juntas_tipo_junta_id_foreign');
            $table->date('fecha');
            $table->time('hora_primera_convocatoria');
            $table->time('hora_segunda_convocatoria');
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_junta_id')->references('id')->on('tipo_juntas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatorias_juntas');
    }
};
