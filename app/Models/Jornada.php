<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Jornada extends Model
{
    protected $table = 'jornadas';

    protected $fillable = [
        'horario_id',
        'nombre',
        'hora_inicio',
        'num_sesiones',
        'recreo_antes_de_sesion',
        'recreo_duracion_minutos',
        'dias_semana',
    ];

    protected $casts = [
        'num_sesiones' => 'integer',
        'recreo_antes_de_sesion' => 'integer',
        'recreo_duracion_minutos' => 'integer',
        'dias_semana' => 'array',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function tieneRecreo(): bool
    {
        return ! is_null($this->recreo_antes_de_sesion) && ! is_null($this->recreo_duracion_minutos);
    }

    /**
     * La secuencia cronológica de la jornada: una entrada por sesión más, si hay recreo,
     * una entrada de recreo justo antes de la sesión que le corresponda. Es la base para
     * pintar la rejilla (pantalla, modal de edición y PDF): la fila ya no es "la sesión
     * N" (el recreo no cuenta como sesión y puede caer en un sitio distinto cada día),
     * sino "la posición N en el orden de la jornada".
     *
     * $sesionInicial numera las sesiones a partir de ese valor: cuando un día tiene
     * varias jornadas, la 2ª continúa la numeración donde la dejó la 1ª en vez de volver
     * a empezar por la 1, ver combinarSlots().
     *
     * @return array<int, array{tipo: 'sesion', numero: int, hora: string}|array{tipo: 'recreo', hora: string, duracion: int}>
     */
    public function calcularSlots(int $duracionSesionMinutos, int $sesionInicial = 1): array
    {
        $slots = [];
        $hora = Carbon::createFromFormat('H:i:s', $this->hora_inicio);
        $numero = $sesionInicial;

        for ($s = 1; $s <= $this->num_sesiones; $s++) {
            if ($this->tieneRecreo() && $this->recreo_antes_de_sesion === $s) {
                $slots[] = [
                    'tipo' => 'recreo',
                    'hora' => $hora->format('H:i'),
                    'duracion' => $this->recreo_duracion_minutos,
                ];

                $hora = $hora->copy()->addMinutes($this->recreo_duracion_minutos);
            }

            $slots[] = [
                'tipo' => 'sesion',
                'numero' => $numero,
                'hora' => $hora->format('H:i'),
            ];

            $numero++;
            $hora = $hora->copy()->addMinutes($duracionSesionMinutos);
        }

        return $slots;
    }

    /** Minutos desde medianoche de una hora 'H:i' o 'H:i:s', para comparar horas entre sí. */
    public static function minutosDesdeMedianoche(string $hora): int
    {
        [$horas, $minutos] = explode(':', substr($hora, 0, 5));

        return ((int) $horas) * 60 + (int) $minutos;
    }

    /**
     * Combina las jornadas de un mismo día (p.ej. mañana y tarde) en una sola lista,
     * ordenada por hora de inicio (no deberían solaparse, pero si pasa, quedan en el
     * orden en que empiezan). La numeración de sesión es continua a lo largo del día
     * completo: la 2ª jornada sigue donde la dejó la 1ª, no vuelve a la sesión 1.
     *
     * Sin $minutosReferencia, cada día empieza en su propia fila 0 (comportamiento por
     * defecto). Con $minutosReferencia (los minutos desde medianoche de la hora más
     * temprana de todo el horario), cada sesión se coloca en la fila que le toca por
     * hora real en vez de por orden: así la misma hora cae en la misma fila en los 7
     * días, dejando huecos (filas sin sesión) donde ese día no hay clase a esa hora.
     *
     * @param  Collection<int, self>  $jornadas  del mismo día, en cualquier orden
     * @return array{slots: array<int, array>, num_sesiones: int, total_filas: int}
     */
    public static function combinarSlots($jornadas, int $duracionSesionMinutos, ?int $minutosReferencia = null): array
    {
        $slots = [];
        $sesion = 1;

        foreach ($jornadas->sortBy('hora_inicio') as $jornada) {
            foreach ($jornada->calcularSlots($duracionSesionMinutos, $sesion) as $slot) {
                if ($minutosReferencia === null || $duracionSesionMinutos <= 0) {
                    $slots[] = $slot;
                } else {
                    $fila = (int) round((self::minutosDesdeMedianoche($slot['hora']) - $minutosReferencia) / $duracionSesionMinutos);
                    $slots[max(0, $fila)] = $slot;
                }

                if ($slot['tipo'] === 'sesion') {
                    $sesion++;
                }
            }
        }

        $totalFilas = empty($slots) ? 0 : max(array_keys($slots)) + 1;

        return ['slots' => $slots, 'num_sesiones' => $sesion - 1, 'total_filas' => $totalFilas];
    }
}
