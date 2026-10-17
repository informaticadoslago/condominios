<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pestaña "Administrador" de la comunidad: quién la administra y dónde se reúne la
     * junta. Texto libre, tal cual se teclea (sin pasar a mayúsculas), porque sale así en
     * las convocatorias: "Oficinas de Fincas Lar (Av. García Barbón, 22 – 1º)".
     */
    public function up(): void
    {
        Schema::table('comunidades', function (Blueprint $table) {
            $table->string('administrador_nombre', 150)->nullable()->after('correo_contacto');
            $table->string('administrador_oficina_reunion', 255)->nullable()->after('administrador_nombre');
            $table->string('administrador_localidad', 100)->nullable()->after('administrador_oficina_reunion');
        });
    }

    public function down(): void
    {
        Schema::table('comunidades', function (Blueprint $table) {
            $table->dropColumn(['administrador_nombre', 'administrador_oficina_reunion', 'administrador_localidad']);
        });
    }
};
