<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El calendario de exámenes pasa a ser de eventos: además de exámenes guarda juntas
     * de evaluación, claustros… (ver tipo_eventos). Los que ya había son exámenes. La
     * asignatura deja de ser obligatoria: solo la pide el examen.
     *
     * Las claves ajenas y sus índices se quitan antes de renombrar y se vuelven a crear
     * después, para que no se queden con el nombre de la tabla vieja.
     */
    public function up(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropForeign('examenes_horario_id_foreign');
            $table->dropForeign('examenes_asignatura_id_foreign');
            $table->dropIndex('examenes_horario_id_foreign');
            $table->dropIndex('examenes_asignatura_id_foreign');
        });

        Schema::rename('examenes', 'eventos');

        Schema::table('eventos', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_evento_id')->default(1)->after('horario_id')
                ->index('eventos_tipo_evento_id_foreign');
            $table->unsignedBigInteger('asignatura_id')->nullable()->change();

            $table->index('horario_id', 'eventos_horario_id_foreign');
            $table->index('asignatura_id', 'eventos_asignatura_id_foreign');

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_evento_id')->references('id')->on('tipo_eventos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * La vuelta atrás borra los eventos que no son exámenes o no tienen asignatura: en
     * la tabla de exámenes no caben.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropForeign('eventos_horario_id_foreign');
            $table->dropForeign('eventos_asignatura_id_foreign');
            $table->dropForeign('eventos_tipo_evento_id_foreign');
            $table->dropIndex('eventos_horario_id_foreign');
            $table->dropIndex('eventos_asignatura_id_foreign');
            $table->dropIndex('eventos_tipo_evento_id_foreign');
        });

        Schema::rename('eventos', 'examenes');

        \Illuminate\Support\Facades\DB::table('examenes')
            ->where('tipo_evento_id', '!=', 1)
            ->orWhereNull('asignatura_id')
            ->delete();

        Schema::table('examenes', function (Blueprint $table) {
            $table->dropColumn('tipo_evento_id');
            $table->unsignedBigInteger('asignatura_id')->nullable(false)->change();

            $table->index('horario_id', 'examenes_horario_id_foreign');
            $table->index('asignatura_id', 'examenes_asignatura_id_foreign');

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onUpdate('restrict')->onDelete('restrict');
        });
    }
};
