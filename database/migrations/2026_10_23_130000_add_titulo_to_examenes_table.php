<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // El texto del evento en el calendario (.ics). El formulario propone
        // "Examen de <asignatura>", pero se puede cambiar.
        Schema::table('examenes', function (Blueprint $table) {
            $table->string('titulo', 150)->nullable()->after('asignatura_id');
        });

        // Los exámenes que ya había, con el texto que se proponía hasta ahora.
        DB::table('examenes')
            ->join('asignaturas', 'asignaturas.id', '=', 'examenes.asignatura_id')
            ->select('examenes.id', 'asignaturas.nombre')
            ->get()
            ->each(fn ($examen) => DB::table('examenes')->where('id', $examen->id)->update([
                'titulo' => __('Examen de :asignatura', ['asignatura' => $examen->nombre]),
            ]));

        Schema::table('examenes', function (Blueprint $table) {
            $table->string('titulo', 150)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropColumn('titulo');
        });
    }
};
