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
        Schema::create('socios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('numsocio');
            $table->dateTime('fechaalta')->default('2020-06-05 10:36:49');
            $table->dateTime('fecha_alta')->nullable();
            $table->unsignedBigInteger('persona_id')->nullable()->index('socios_persona_id_foreign');
            $table->unsignedBigInteger('contactoateneo_id')->nullable()->index('socios_contactoateneo_id_foreign');
            $table->unsignedInteger('tipo_formadepago_id')->default(154)->index('socios_tipo_formadepago_id_foreign');
            $table->unsignedBigInteger('forma_de_pago_id')->nullable()->index('socios_forma_de_pago_id_foreign');
            $table->unsignedBigInteger('titularcb_id')->nullable()->index('socios_titularcb_id_foreign');
            $table->unsignedInteger('tipo_entidadfinanciera_id')->nullable()->index('socios_tipo_entidadfinanciera_id_foreign');
            $table->unsignedBigInteger('entidad_bancaria_id')->nullable()->index('socios_entidad_bancaria_id_foreign');
            $table->string('iban', 30)->nullable();
            $table->text('comentarios')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(1)->index('socios_estado_id_foreign');
            $table->timestamps();
            $table->dateTime('fechabaja')->default('1900-01-01 00:00:00');
            $table->dateTime('fecha_baja')->nullable();
            $table->tinyInteger('colaborador')->default(0);
            $table->unsignedTinyInteger('mes_ciclo_factura')->nullable();
            $table->unsignedBigInteger('anualidad_socio_id')->nullable()->index('socios_anualidad_socio_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('socios');
    }
};
