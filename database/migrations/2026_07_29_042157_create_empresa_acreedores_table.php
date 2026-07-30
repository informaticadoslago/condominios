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
        Schema::create('empresa_acreedores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombrecuenta', 30)->nullable();
            $table->unsignedBigInteger('empresa_id')->index('empresa_acreedores_empresa_id_foreign');
            $table->string('nombreacreedor', 30)->nullable();
            $table->string('ibanacreedor', 24)->nullable();
            $table->string('bicacreedor', 191)->nullable();
            $table->string('moneda', 191)->default('EUR');
            $table->string('idsimple', 5)->nullable();
            $table->string('idcompleto', 20)->nullable();
            $table->string('iso', 15)->nullable();
            $table->string('tipo', 20)->nullable();
            $table->string('plazo', 20)->nullable();
            $table->unsignedSmallInteger('mindiasejecucion')->default(3);
            $table->unsignedTinyInteger('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_acreedores');
    }
};
