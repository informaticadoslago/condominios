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
        Schema::create('personas_sociedad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sociedad_id')->index('personas_sociedad_sociedad_id_foreign');
            $table->string('nombre', 100);
            $table->string('apellido1', 100)->nullable();
            $table->string('apellido2', 100)->nullable();
            $table->string('razon_social', 100)->nullable();
            $table->string('nombre_comercial', 100)->nullable();
            $table->unsignedBigInteger('documento_pais_id')->nullable()->index('personas_sociedad_documento_pais_id_foreign');
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->index('personas_sociedad_tipo_documento_id_foreign');
            $table->string('documento_identificativo', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('genero_id')->nullable()->index('personas_sociedad_genero_id_foreign');
            $table->unsignedBigInteger('nacionalidad_id')->default(67)->index('personas_sociedad_nacionalidad_id_foreign');
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->unique(['sociedad_id', 'documento_identificativo']);
            $table->foreign('sociedad_id')->references('id')->on('sociedades')->onUpdate('restrict')->onDelete('restrict');
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
        Schema::dropIfExists('personas_sociedad');
    }
};
