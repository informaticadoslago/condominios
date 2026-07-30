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
        Schema::create('empresas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('fechaalta')->default('2020-12-26 12:14:33');
            $table->unsignedBigInteger('persona_id')->nullable()->index('empresas_persona_id_foreign');
            $table->text('direccion')->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('iban', 30)->nullable();
            $table->text('comentarios')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(1)->index('empresas_estado_id_foreign');
            $table->dateTime('fechabaja')->default('1900-01-01 00:00:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
