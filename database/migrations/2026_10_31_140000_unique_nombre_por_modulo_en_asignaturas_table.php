<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dos asignaturas del mismo horario pueden llamarse igual si son de módulos distintos.
     * Ojo: MySQL no compara los NULL en un índice único, así que dos asignaturas sin módulo
     * con el mismo nombre no las para la base de datos; lo impide el formulario
     * (App\Livewire\Asignaturas\Formulario).
     */
    public function up(): void
    {
        Schema::table('asignaturas', function (Blueprint $table) {
            $table->dropUnique(['horario_id', 'nombre']);
            $table->unique(['horario_id', 'modulo_id', 'nombre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asignaturas', function (Blueprint $table) {
            $table->dropUnique(['horario_id', 'modulo_id', 'nombre']);
            $table->unique(['horario_id', 'nombre']);
        });
    }
};
