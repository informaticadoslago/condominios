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
        Schema::create('personas_comunidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('personas_comunidad_comunidad_id_foreign');
            $table->string('nombre', 100);
            $table->string('apellido1', 100)->nullable();
            $table->string('apellido2', 100)->nullable();
            $table->string('razon_social', 100)->nullable();
            $table->string('nombre_comercial', 100)->nullable();
            $table->unsignedBigInteger('documento_pais_id')->nullable()->index('personas_comunidad_documento_pais_id_foreign');
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->index('personas_comunidad_tipo_documento_id_foreign');
            $table->string('documento_identificativo', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('genero_id')->nullable()->index('personas_comunidad_genero_id_foreign');
            $table->unsignedBigInteger('nacionalidad_id')->default(67)->index('personas_comunidad_nacionalidad_id_foreign');
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->unique(['comunidad_id', 'documento_identificativo']);
            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('documento_pais_id')->references('id')->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_documento_id')->references('id')->on('tipo_documento_identificativos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('genero_id')->references('id')->on('tipo_generos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('nacionalidad_id')->references('id')->on('paises')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas_comunidad');
    }
};
