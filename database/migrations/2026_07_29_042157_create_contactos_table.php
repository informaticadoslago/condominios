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
            $table->unsignedBigInteger('persona_id')->nullable()->index('contactos_persona_id_foreign');
            $table->unsignedInteger('tipo_contacto_id')->default(1);
            $table->string('descripcion', 50)->nullable();
            $table->string('valor', 100)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->timestamp('verified_at')->nullable();
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
