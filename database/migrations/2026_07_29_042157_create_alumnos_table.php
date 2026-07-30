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
        Schema::create('alumnos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('fechaalta')->default('2020-06-05 10:36:52');
            $table->dateTime('fecha_alta')->nullable();
            $table->unsignedBigInteger('persona_id')->nullable()->index('alumnos_persona_id_foreign');
            $table->unsignedBigInteger('socio_id')->nullable()->index('alumnos_tutor_id_foreign');
            $table->unsignedBigInteger('titularcb_id')->nullable()->index('alumnos_titularcb_id_foreign');
            $table->string('relacionsocio', 50)->nullable();
            $table->unsignedInteger('tipo_formadepago_id')->default(1)->index('alumnos_tipo_formadepago_id_foreign');
            $table->unsignedBigInteger('forma_de_pago_id')->nullable()->index('alumnos_forma_de_pago_id_foreign');
            $table->unsignedInteger('tipo_entidadfinanciera_id')->nullable()->index('alumnos_tipo_entidadfinanciera_id_foreign');
            $table->unsignedBigInteger('entidad_bancaria_id')->nullable()->index('alumnos_entidad_bancaria_id_foreign');
            $table->string('iban', 30)->nullable();
            $table->text('comentarios')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(1)->index('alumnos_estado_id_foreign');
            $table->timestamps();
            $table->unsignedBigInteger('preferido_contacto_id')->nullable()->index('alumnos_preferido_contacto_id_foreign');
            $table->dateTime('fechabaja')->nullable();
            $table->dateTime('fecha_baja')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
