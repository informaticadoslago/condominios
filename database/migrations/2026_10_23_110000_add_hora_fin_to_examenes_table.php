<?php

use Carbon\Carbon;
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
        Schema::table('examenes', function (Blueprint $table) {
            $table->time('hora_fin')->nullable()->after('hora');
        });

        // Los exámenes que ya había duran una sesión de su horario (una hora si no la tiene),
        // lo mismo que propone el formulario para los nuevos.
        DB::table('examenes')
            ->join('horarios', 'horarios.id', '=', 'examenes.horario_id')
            ->select('examenes.id', 'examenes.hora', 'horarios.duracion_sesion_minutos')
            ->get()
            ->each(fn ($examen) => DB::table('examenes')->where('id', $examen->id)->update([
                'hora_fin' => Carbon::createFromFormat('H:i:s', $examen->hora)
                    ->addMinutes($examen->duracion_sesion_minutos ?: 60)
                    ->format('H:i:s'),
            ]));

        Schema::table('examenes', function (Blueprint $table) {
            $table->time('hora_fin')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropColumn('hora_fin');
        });
    }
};
