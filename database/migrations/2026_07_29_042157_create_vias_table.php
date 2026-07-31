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
        Schema::create('vias', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('municipio_id')->nullable()->index('vias_municipio_id_foreign');
            $table->string('codigo', 5);
            $table->tinyInteger('posiciontvia');
            $table->string('nombre', 50);
            $table->string('nombrecorto', 25);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vias');
    }
};
