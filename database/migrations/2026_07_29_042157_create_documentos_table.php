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
        Schema::create('documentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('fechaalta')->default('2020-06-05 10:37:03');
            $table->string('documentable_type', 191);
            $table->unsignedBigInteger('documentable_id');
            $table->unsignedBigInteger('tipo_documento_id')->default(1)->index('documentos_tipo_documento_id_foreign');
            $table->string('descripcion', 30)->nullable();
            $table->string('nombrefichero', 100)->nullable();
            $table->string('camino')->nullable();
            $table->string('nombrelocal', 100)->nullable();
            $table->string('extension', 30)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(1)->index('documentos_estado_id_foreign');
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
