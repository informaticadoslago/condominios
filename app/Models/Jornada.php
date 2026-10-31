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
        'dias_semana',
    ];

    protected $casts = [
        'num_sesiones' => 'integer',
        'dias_semana' => 'array',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function recreos()
    {
        return $this->hasMany(JornadaRecreo::class)->orderBy('hora_inicio');
    }

    /**
     * La secuencia cronológica de la jornada: una entrada por sesión y otra por recreo.
     * Las sesiones van seguidas desde la hora de inicio; si un recreo empieza justo cuando
     * acaba una sesión, entra el recreo y la siguiente empieza al acabar él; si empieza a
     * mitad de una sesión, la sesión se lo come (ver abajo). Un recreo que empiece
     * después de la última sesión no sale. Es la base para
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
        $pendientes = $this->recreos->sortBy('hora_inicio')->values();

        for ($s = 1; $s <= $this->num_sesiones; $s++) {
            while ($pendientes->isNotEmpty()
                && self::minutosDesdeMedianoche($pendientes->first()->hora_inicio) <= self::minutosDesdeMedianoche($hora->format('H:i'))) {
                $recreo = $pendientes->shift();
                $inicioRecreo = Carbon::createFromFormat('H:i', substr($recreo->hora_inicio, 0, 5));

                $slots[] = [
                    'tipo' => 'recreo',
                    'hora' => $inicioRecreo->format('H:i'),
                    'duracion' => $recreo->duracion_minutos,
                ];

                $hora = $hora->max($inicioRecreo->copy()->addMinutes($recreo->duracion_minutos));
            }

            $slots[] = [
                'tipo' => 'sesion',
                'numero' => $numero,
                'hora' => $hora->format('H:i'),
            ];

            $numero++;
            $hora = $hora->copy()->addMinutes($duracionSesionMinutos);

            // Un recreo que empieza a mitad de esta sesión no la parte: la sesión se lo
            // come (ese grupo tendrá su recreo a otra hora) y la siguiente va pegada a ella.
            while ($pendientes->isNotEmpty()
                && self::minutosDesdeMedianoche($pendientes->first()->hora_inicio) < self::minutosDesdeMedianoche($hora->format('H:i'))) {
                $recreo = $pendientes->shift();

                $slots[] = [
                    'tipo' => 'recreo',
                    'hora' => substr($recreo->hora_inicio, 0, 5),
                    'duracion' => $recreo->duracion_minutos,
                ];
            }
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
                    $fila = max(0, $fila);

                    // El recreo suele durar menos que una sesión, así que puede redondear
                    // a la misma fila que la sesión siguiente: en vez de sobrescribirlo,
                    // desplazamos a la primera fila libre.
                    while (isset($slots[$fila])) {
                        $fila++;
                    }

                    $slots[$fila] = $slot;
                }

                if ($slot['tipo'] === 'sesion') {
                    $sesion++;
                }
            }
        }

        $totalFilas = empty($slots) ? 0 : max(array_keys($slots)) + 1;

        return ['slots' => $slots, 'num_sesiones' => $sesion - 1, 'total_filas' => $totalFilas];
    }

    /**
     * Para cada fila de la rejilla, la hora de inicio y fin del primer slot que haya en
     * esa fila en cualquiera de los días: con alinear_horas todos los días coinciden en
     * esa hora, así que es exacto; sin alinear_horas es solo orientativo (la hora del
     * primer día que tenga algo en esa fila, que puede no coincidir con los demás).
     *
     * @param  array<int, array{nombre: string, slots: array<int, array>}>  $diasConfig
     * @return array<int, array{inicio: string, fin: string}|null>
     */
    public static function horasPorFila(array $diasConfig, int $totalFilas, int $duracionSesionMinutos): array
    {
        $horas = [];

        for ($fila = 0; $fila < $totalFilas; $fila++) {
            $horas[$fila] = null;

            foreach ($diasConfig as $config) {
                $slot = $config['slots'][$fila] ?? null;

                if (! $slot) {
                    continue;
                }

                $duracion = $slot['tipo'] === 'recreo' ? $slot['duracion'] : $duracionSesionMinutos;
                $fin = Carbon::createFromFormat('H:i', $slot['hora'])->addMinutes($duracion)->format('H:i');

                $horas[$fila] = ['inicio' => $slot['hora'], 'fin' => $fin];

                break;
            }
        }

        return $horas;
    }
}
