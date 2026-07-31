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
        Schema::create('contactos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contacto_l9_id')->nullable()->index();
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');
            $table->unsignedBigInteger('tipo_contacto_id');
            $table->string('descripcion', 50);
            $table->string('valor', 50);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('estado_id')->default(1);
            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id']);

            $table->foreign('tipo_contacto_id')->references('id')->on('tipo_contactos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
