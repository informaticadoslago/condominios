<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes del sistema que se tocan desde una pantalla, no desde el .env: clave y
     * valor, ambos texto. El primero es la caducidad por defecto de los tokens de API
     * (`tokens_api.caducidad`), pero la tabla es para todos los que vengan.
     *
     * El .env sigue siendo el sitio de lo que no se cambia en caliente (credenciales,
     * interruptores de despliegue); esto es lo contrario: lo que decide el administrador
     * mientras la aplicación está corriendo.
     */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            // Texto siempre: cada ajuste sabe leer lo suyo. Nulo = puesto a nada a
            // propósito, que no es lo mismo que no estar la fila.
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
