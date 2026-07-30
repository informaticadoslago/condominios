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
        Schema::create('personas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 100);
            $table->string('apellido1', 100)->nullable();
            $table->string('apellido2', 100)->nullable();
            $table->unsignedBigInteger('nif_pais_id')->nullable()->index('personas_nif_pais_id_foreign');
            $table->unsignedBigInteger('documento_pais_id')->nullable()->index('personas_documento_pais_id_foreign');
            $table->unsignedInteger('tipo_nif_id')->default(1);
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->index('personas_tipo_documento_id_foreign');
            $table->string('nif', 30)->nullable()->unique();
            $table->string('documento_identificativo', 30)->nullable();
            $table->text('comentarios')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->nullable()->index('personas_estado_id_foreign');
            $table->json('estados_previos')->nullable();
            $table->timestamps();
            $table->date('fechanacimiento')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('alergiasalimentos')->nullable();
            $table->string('alergias_alimentos')->nullable();
            $table->string('razonsocial', 100)->nullable();
            $table->string('razon_social', 100)->nullable();
            $table->string('nombrecomercial', 100)->nullable();
            $table->string('nombre_comercial', 100)->nullable();
            $table->unsignedBigInteger('nacionalidad_id')->default(67)->index('personas_nacionalidad_id_foreign');
            $table->unsignedTinyInteger('genero')->default(0);
            $table->unsignedBigInteger('genero_id')->nullable()->index('personas_genero_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
