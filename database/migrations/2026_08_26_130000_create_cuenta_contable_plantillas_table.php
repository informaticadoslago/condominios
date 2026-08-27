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
        Schema::create('cuenta_contable_plantillas', function (Blueprint $table) {
            $table->id();
            // Null = común (se copia siempre); si no, el origen que la añade encima de la
            // común (ver App\Models\CuentaContable::PLANTILLA_*).
            $table->string('plantilla', 30)->nullable();
            $table->unsignedBigInteger('tipo_cuenta_contable_id')->nullable()->index('cuenta_contable_plantillas_tipo_cuenta_contable_id_foreign');
            $table->unsignedBigInteger('cuenta_padre_id')->nullable()->index('cuenta_contable_plantillas_cuenta_padre_id_foreign');
            $table->string('codigo', 8);
            $table->string('nombre', 150);
            $table->unsignedBigInteger('estado_id')->default(1)->index('cuenta_contable_plantillas_estado_id_foreign');
            $table->timestamps();

            // Un mismo código puede repetirse en dos plantillas distintas a propósito (la
            // de comunidad pisa el nombre de la común en la 430): el único va con
            // plantilla, no solo con código.
            $table->unique(['plantilla', 'codigo']);

            $table->foreign('tipo_cuenta_contable_id')->references('id')->on('tipo_cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_padre_id')->references('id')->on('cuenta_contable_plantillas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuenta_contable_plantillas');
    }
};
