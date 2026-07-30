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
        Schema::create('dosl_contactos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contacto_l9_id')->nullable()->index();
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');
            $table->unsignedBigInteger('tipo_contacto_id')->index('dosl_contactos_tipo_contacto_id_foreign');
            $table->string('descripcion', 50);
            $table->string('valor', 50);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('estado_id')->default(1)->index('dosl_contactos_estado_id_foreign');
            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosl_contactos');
    }
};
