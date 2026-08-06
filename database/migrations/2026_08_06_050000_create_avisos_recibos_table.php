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
        Schema::create('avisos_recibos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recibo_id')->index('avisos_recibos_recibo_id_foreign');

            // Solo el motivo, no el cuerpo: interesa saber QUÉ se le avisó y cuándo, no
            // reproducir el correo. Guardar el contenido multiplicaría la tabla sin que
            // nadie lo vaya a leer.
            $table->string('motivo', 150);

            // La dirección a la que se escribió, copiada tal cual: si el propietario la
            // cambia después, el rastro tiene que seguir diciendo a dónde se mandó.
            $table->string('destinatario', 150);

            $table->timestamp('enviado_at');

            // Quién lo lanzó. Nulo si lo mandó un proceso automático y no una persona.
            $table->unsignedBigInteger('user_id')->nullable()->index('avisos_recibos_user_id_foreign');

            $table->timestamps();

            $table->index(['recibo_id', 'enviado_at']);

            $table->foreign('recibo_id')->references('id')->on('recibos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avisos_recibos');
    }
};
