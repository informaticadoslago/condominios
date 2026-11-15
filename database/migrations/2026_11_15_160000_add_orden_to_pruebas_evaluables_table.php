<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orden de las pruebas dentro de su asignatura, el que se elige arrastrándolas en la
     * lista: es el orden en que se imprimen, aunque se hayan creado por fecha. Las que ya
     * existen se numeran por fecha.
     */
    public function up(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->unsignedInteger('orden')->default(0)->after('peso');
        });

        $posicion = [];
        foreach (DB::table('pruebas_evaluables')->orderBy('asignatura_id')->orderBy('fecha')->orderBy('id')->get(['id', 'asignatura_id']) as $prueba) {
            $posicion[$prueba->asignatura_id] = ($posicion[$prueba->asignatura_id] ?? 0) + 1;
            DB::table('pruebas_evaluables')->where('id', $prueba->id)->update(['orden' => $posicion[$prueba->asignatura_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
