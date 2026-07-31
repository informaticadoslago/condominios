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
        Schema::create('titularidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inmueble_id')->index('titularidades_inmueble_id_foreign');
            $table->unsignedBigInteger('propietario_id')->index('titularidades_propietario_id_foreign');
            $table->decimal('cuota_percent', 5, 2);
            $table->string('causa', 20);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->foreign('inmueble_id')->references('id')->on('inmuebles')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('propietario_id')->references('id')->on('propietarios')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titularidades');
    }
};
