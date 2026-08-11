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
        Schema::create('historial_importaciones_comunidades', function (Blueprint $table) {
            $table->id();

            // Nulo si la comunidad importada se acaba borrando después: el rastro de la
            // importación tiene que sobrevivir aunque la comunidad ya no exista.
            $table->unsignedBigInteger('comunidad_id')->nullable()->index('historial_importaciones_comunidades_comunidad_id_foreign');

            // CIF y nombre copiados tal cual en el momento de importar, no un join a
            // personas: si la comunidad se borra y se reimporta, cada fila cuenta lo que
            // pasó en su momento.
            $table->string('cif', 30);
            $table->string('nombre_comunidad', 150)->nullable();

            $table->string('nombre_fichero', 255)->nullable();

            $table->boolean('enlazado_contabilidad')->default(false);

            // Avisos de saneo contable ocurridos durante la importación (motivo, ids
            // implicados). Null si no hubo ninguno.
            $table->json('avisos')->nullable();

            $table->unsignedBigInteger('user_id')->nullable()->index('historial_importaciones_comunidades_user_id_foreign');

            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_importaciones_comunidades');
    }
};
