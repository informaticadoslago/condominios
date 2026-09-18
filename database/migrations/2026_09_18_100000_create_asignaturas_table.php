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
        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('asignaturas_horario_id_foreign');
            $table->string('nombre', 100);
            $table->unsignedBigInteger('estado_id')->default(1)->index('asignaturas_estado_id_foreign');
            $table->timestamps();

            $table->unique(['horario_id', 'nombre']);

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaturas');
    }
};
