<?php

namespace App\Services\Horarios;

use App\Models\Horario;
use App\Models\Jornada;

/**
 * Dónde cae cada sesión con asignatura de un horario de profesor, en minutos desde
 * medianoche. Cada una está a la hora que le toca por su jornada (Jornada::calcularSlots)
 * salvo que tenga una hora de inicio puesta a mano (horario_sesiones.hora_inicio).
 *
 * Las sesiones se mueven de una en una: pueden pisar los recreos, pero no a otra sesión
 * ni salirse de su jornada, y ninguna empuja a las demás. Los huecos sin asignatura no
 * son nada: el tiempo libre que queda entre dos sesiones, del tamaño que sea.
 *
 * Requiere cargadas las relaciones 'jornadas.recreos' y 'sesiones'.
 */
class ColocacionSesiones
{
    private int $duracion;

    /**
     * [dia => ['jornadas' => [['inicio' => m, 'fin' => m]], 'recreos' => [['inicio' => m,
     * 'fin' => m]], 'posiciones' => [numero => ['inicio' => m, 'jornada' => índice]]]]
     */
    private array $dias = [];

    /** [dia => [numero => minutos|null]]: la hora a mano de cada sesión con asignatura. */
    private array $manuales = [];

    public function __construct(Horario $horario)
    {
        $this->duracion = (int) $horario->duracion_sesion_minutos;

        foreach ($horario->jornadasPorDia() as $dia => $jornadas) {
            $this->dias[$dia] = ['jornadas' => [], 'recreos' => [], 'posiciones' => []];
            $numero = 1;

            foreach ($jornadas->sortBy('hora_inicio') as $jornada) {
                $indice = count($this->dias[$dia]['jornadas']);
                $inicioJornada = Jornada::minutosDesdeMedianoche($jornada->hora_inicio);
                $finJornada = $inicioJornada;

                foreach ($jornada->calcularSlots($this->duracion, $numero) as $slot) {
                    $inicio = Jornada::minutosDesdeMedianoche($slot['hora']);

                    if ($slot['tipo'] === 'recreo') {
                        $this->dias[$dia]['recreos'][] = ['inicio' => $inicio, 'fin' => $inicio + $slot['duracion']];

                        continue;
                    }

                    $this->dias[$dia]['posiciones'][$slot['numero']] = ['inicio' => $inicio, 'jornada' => $indice];
                    $finJornada = max($finJornada, $inicio + $this->duracion);
                    $numero = $slot['numero'] + 1;
                }

                $this->dias[$dia]['jornadas'][] = ['inicio' => $inicioJornada, 'fin' => $finJornada];
            }
        }

        foreach ($horario->sesiones as $sesion) {
            if (isset($this->dias[$sesion->dia_semana]['posiciones'][$sesion->sesion_numero])) {
                $this->manuales[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->hora_inicio
                    ? Jornada::minutosDesdeMedianoche($sesion->hora_inicio)
                    : null;
            }
        }
    }

    public function duracion(): int
    {
        return $this->duracion;
    }

    /** @return array<int, int> los días con alguna jornada */
    public function dias(): array
    {
        return array_keys($this->dias);
    }

    /** @return array<int, array{inicio: int, fin: int}> */
    public function jornadas(int $dia): array
    {
        return $this->dias[$dia]['jornadas'] ?? [];
    }

    /** @return array<int, array{inicio: int, fin: int}> */
    public function recreos(int $dia): array
    {
        return $this->dias[$dia]['recreos'] ?? [];
    }

    /** La hora que le toca a la sesión por su jornada, sin contar la puesta a mano. */
    public function inicioPorDefecto(int $dia, int $numero): ?int
    {
        return $this->dias[$dia]['posiciones'][$numero]['inicio'] ?? null;
    }

    /** @return array<int, int|null> [numero => minutos|null] de las sesiones con asignatura */
    public function manuales(int $dia): array
    {
        return $this->manuales[$dia] ?? [];
    }

    /**
     * Las sesiones con asignatura del día, ordenadas por hora. $manuales permite probar
     * otras horas a mano antes de guardarlas ([numero => minutos|null]; las claves son
     * las sesiones con asignatura). Cada una lleva 'min' y 'max': entre qué horas puede
     * empezar sin pisar a sus vecinas ni salirse de la jornada (para arrastrarla).
     *
     * @return array<int, array{numero: int, inicio: int, fin: int, manual: bool, jornada: array{inicio: int, fin: int}, min: int, max: int}>
     */
    public function sesiones(int $dia, ?array $manuales = null): array
    {
        $sesiones = [];

        foreach ($manuales ?? $this->manuales($dia) as $numero => $manual) {
            $posicion = $this->dias[$dia]['posiciones'][$numero] ?? null;

            if (! $posicion) {
                continue;
            }

            $inicio = $manual ?? $posicion['inicio'];

            $sesiones[] = [
                'numero' => $numero,
                'inicio' => $inicio,
                'fin' => $inicio + $this->duracion,
                'manual' => $manual !== null,
                'jornada' => $this->dias[$dia]['jornadas'][$posicion['jornada']],
            ];
        }

        usort($sesiones, fn ($a, $b) => [$a['inicio'], $a['numero']] <=> [$b['inicio'], $b['numero']]);

        foreach ($sesiones as $i => &$sesion) {
            $finAnterior = $i > 0 ? $sesiones[$i - 1]['fin'] : PHP_INT_MIN;
            $inicioSiguiente = isset($sesiones[$i + 1]) ? $sesiones[$i + 1]['inicio'] : PHP_INT_MAX;

            $sesion['min'] = max($sesion['jornada']['inicio'], $finAnterior);
            $sesion['max'] = min($sesion['jornada']['fin'], $inicioSiguiente) - $this->duracion;
        }
        unset($sesion);

        return $sesiones;
    }

    /**
     * Por qué no vale cómo quedan las sesiones del día: [numero => mensaje] de las que se
     * salen de su jornada o se pisan con otra (las dos de cada pareja). Vacío si todo cabe.
     *
     * @return array<int, string>
     */
    public function errores(int $dia, ?array $manuales = null): array
    {
        $errores = [];
        $sesiones = $this->sesiones($dia, $manuales);

        foreach ($sesiones as $i => $sesion) {
            if ($sesion['inicio'] < $sesion['jornada']['inicio'] || $sesion['fin'] > $sesion['jornada']['fin']) {
                $errores[$sesion['numero']] = __('Se sale de la jornada.');
            }

            $anterior = $sesiones[$i - 1] ?? null;

            if ($anterior && $sesion['inicio'] < $anterior['fin']) {
                $errores[$sesion['numero']] = __('Se pisa con otra sesión.');
                $errores[$anterior['numero']] ??= __('Se pisa con otra sesión.');
            }
        }

        return $errores;
    }

    /**
     * El día de $desde a $hasta en tramos seguidos de lo mismo: una sesión, un recreo (la
     * parte que no tapa ninguna sesión), un hueco libre dentro de la jornada o 'fuera' de
     * ella. Se mira cada $paso minutos, así que las horas deben caer en múltiplos de $paso
     * (ver paso()).
     *
     * @return array<int, array{tipo: string, inicio: int, fin: int, numero?: int}>
     */
    public function tramos(int $dia, int $desde, int $hasta, int $paso): array
    {
        $sesiones = $this->sesiones($dia);
        $recreos = $this->recreos($dia);
        $jornadas = $this->jornadas($dia);
        $dentro = fn (array $tramo, int $m) => $tramo['inicio'] <= $m && $m < $tramo['fin'];

        $tramos = [];

        for ($m = $desde; $m < $hasta; $m += $paso) {
            $actual = ['tipo' => 'fuera'];

            if ($sesion = collect($sesiones)->first(fn ($s) => $dentro($s, $m))) {
                $actual = ['tipo' => 'sesion', 'numero' => $sesion['numero']];
            } elseif (($r = collect($recreos)->search(fn ($r) => $dentro($r, $m))) !== false) {
                $actual = ['tipo' => 'recreo', 'recreo' => $r];
            } elseif (collect($jornadas)->contains(fn ($j) => $dentro($j, $m))) {
                $actual = ['tipo' => 'hueco'];
            }

            $ultimo = array_key_last($tramos);

            if ($ultimo !== null && array_diff_key($tramos[$ultimo], ['inicio' => 0, 'fin' => 0]) === $actual) {
                $tramos[$ultimo]['fin'] = $m + $paso;
            } else {
                $tramos[] = $actual + ['inicio' => $m, 'fin' => $m + $paso];
            }
        }

        return $tramos;
    }

    /**
     * Cada cuántos minutos hay que mirar para que todas las horas del horario caigan en
     * una línea: 5, salvo que alguna hora o duración no sea múltiplo de 5.
     */
    public function paso(): int
    {
        $paso = 5;

        foreach ($this->dias() as $dia) {
            foreach (array_merge($this->jornadas($dia), $this->recreos($dia), $this->sesiones($dia)) as $tramo) {
                $paso = $this->mcd($this->mcd($paso, $tramo['inicio']), $tramo['fin']);
            }
        }

        return max(1, $paso);
    }

    /** @return array{inicio: int, fin: int}|null de lo primero a lo último en todos los días, desde la media hora anterior */
    public function limitesSemana(): ?array
    {
        $inicios = [];
        $fines = [];

        foreach ($this->dias() as $dia) {
            foreach (array_merge($this->jornadas($dia), $this->recreos($dia)) as $tramo) {
                $inicios[] = $tramo['inicio'];
                $fines[] = $tramo['fin'];
            }
        }

        return $inicios ? ['inicio' => intdiv(min($inicios), 30) * 30, 'fin' => max($fines)] : null;
    }

    private function mcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->mcd($b, $a % $b);
    }

    public static function hora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
