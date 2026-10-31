<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una jornada puede tener tantos recreos como haga falta, cada uno con su hora de
     * inicio y su duración. Sustituye a los dos recreos fijos de jornadas ("antes de la
     * sesión N"), que se pasan aquí a hora antes de quitar sus columnas.
     */
    public function up(): void
    {
        Schema::create('jornada_recreos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jornada_id')->index('jornada_recreos_jornada_id_foreign');
            $table->time('hora_inicio');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->timestamps();

            $table->foreign('jornada_id')->references('id')->on('jornadas')->onUpdate('restrict')->onDelete('cascade');
        });

        // La hora de cada recreo es la de inicio de la jornada más lo que dura lo que va
        // antes: las sesiones previas y, para el 2º, también el 1er recreo. La misma
        // cuenta hace ImportadorZipHorario con los ZIP antiguos.
        $jornadas = DB::table('jornadas')
            ->join('horarios', 'horarios.id', '=', 'jornadas.horario_id')
            ->select('jornadas.*', 'horarios.duracion_sesion_minutos')
            ->get();

        foreach ($jornadas as $jornada) {
            [$horas, $minutos] = explode(':', $jornada->hora_inicio);
            $inicio = (int) $horas * 60 + (int) $minutos;
            $desplazamiento = 0;

            foreach ([
                [$jornada->recreo_antes_de_sesion, $jornada->recreo_duracion_minutos],
                [$jornada->recreo2_antes_de_sesion, $jornada->recreo2_duracion_minutos],
            ] as [$antesDeSesion, $duracion]) {
                if (is_null($antesDeSesion) || is_null($duracion)) {
                    continue;
                }

                $hora = $inicio + ($antesDeSesion - 1) * (int) $jornada->duracion_sesion_minutos + $desplazamiento;

                DB::table('jornada_recreos')->insert([
                    'jornada_id'       => $jornada->id,
                    'hora_inicio'      => sprintf('%02d:%02d:00', intdiv($hora, 60), $hora % 60),
                    'duracion_minutos' => $duracion,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $desplazamiento += $duracion;
            }
        }

        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropColumn([
                'recreo_antes_de_sesion',
                'recreo_duracion_minutos',
                'recreo2_antes_de_sesion',
                'recreo2_duracion_minutos',
            ]);
        });
    }

    /**
     * Reverse the migrations. Vuelven las columnas, pero vacías: los recreos por hora no
     * siempre caben en "antes de la sesión N".
     */
    public function down(): void
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->unsignedTinyInteger('recreo_antes_de_sesion')->nullable()->after('num_sesiones');
            $table->unsignedSmallInteger('recreo_duracion_minutos')->nullable()->after('recreo_antes_de_sesion');
            $table->unsignedTinyInteger('recreo2_antes_de_sesion')->nullable()->after('recreo_duracion_minutos');
            $table->unsignedSmallInteger('recreo2_duracion_minutos')->nullable()->after('recreo2_antes_de_sesion');
        });

        Schema::dropIfExists('jornada_recreos');
    }
};
