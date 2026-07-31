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
        Schema::create('fotos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('fechaalta')->default('2020-06-05 10:37:02');
            $table->unsignedBigInteger('persona_id')->nullable()->index('fotos_persona_id_foreign');
            $table->string('nombrefichero', 30)->nullable();
            $table->string('extension', 30)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fotos');
    }
};
