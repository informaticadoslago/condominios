<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\Jornada;
use App\Services\Horarios\ColocacionSesiones;
use App\Support\DiaSemana;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class Rejilla extends Component
{
    public ?Horario $horario = null;

    /** Píxeles por minuto del horario de profesor (escala de tiempo). */
    private const PIXELES_MINUTO = 1.6;

    public function mount()
    {
        $this->cargar();
    }

    #[On('rejilla-guardada')]
    public function cargar(): void
    {
        $this->horario = Horario::with(['jornadas.recreos', 'sesiones.asignatura.modulo'])->find((int) session('horario_actual_id'));
    }

    /**
     * Solo en horarios de profesor: la sesión se ha soltado a otra hora en el horario
     * semanal. Se comprueba aquí otra vez que no pisa a otra ni se sale de la jornada.
     */
    public function moverSesion(int $dia, int $numero, int $minutos): void
    {
        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find((int) session('horario_actual_id'));
        $sesion = HorarioSesion::where('horario_id', $horario?->id)
            ->where('dia_semana', $dia)
            ->where('sesion_numero', $numero)
            ->first();

        if (! $horario?->esDeProfesor() || ! $sesion) {
            return;
        }

        $colocacion = new ColocacionSesiones($horario);
        $manuales = $colocacion->manuales($dia);
        $manuales[$numero] = $minutos;

        $error = $colocacion->errores($dia, $manuales)[$numero] ?? null;

        if ($error) {
            $this->dispatch('toast-error', ['title' => $error]);
        } else {
            // A su hora de siempre, la sesión vuelve a "automática".
            $sesion->update([
                'hora_inicio' => $minutos === $colocacion->inicioPorDefecto($dia, $numero) ? null : ColocacionSesiones::hora($minutos),
            ]);
        }

        $this->cargar();
    }

    /**
     * Para pintar el horario de profesor en escala de tiempo: por día, las jornadas, los
     * recreos y las sesiones con su posición (top/alto en píxeles) y, en cada sesión,
     * entre qué horas se puede arrastrar.
     */
    private function escalaProfesor(): array
    {
        $colocacion = new ColocacionSesiones($this->horario);
        $ppm = self::PIXELES_MINUTO;
        $limites = $colocacion->limitesSemana();

        if (! $limites) {
            return [];
        }

        // Desde la media hora anterior, para que las líneas caigan en :00 y :30.
        $inicioEscala = $limites['inicio'];
        $finEscala = $limites['fin'];
        $paso = $colocacion->paso();
        $posicion = fn (int $minutos) => round(($minutos - $inicioEscala) * $ppm, 1);

        $asignaturas = $this->horario->sesiones->keyBy(fn ($s) => $s->dia_semana.'-'.$s->sesion_numero);

        $columnas = [];
        foreach ($colocacion->dias() as $dia) {
            $columnas[$dia] = [
                'nombre' => DiaSemana::from($dia)->nombre(),
                'jornadas' => array_map(fn ($j) => ['top' => $posicion($j['inicio']), 'alto' => round(($j['fin'] - $j['inicio']) * $ppm, 1)], $colocacion->jornadas($dia)),
                'recreos' => array_map(fn ($r) => [
                    'top' => $posicion($r['inicio']),
                    'alto' => round(($r['fin'] - $r['inicio']) * $ppm, 1),
                    'horas' => ColocacionSesiones::hora($r['inicio']).'–'.ColocacionSesiones::hora($r['fin']),
                ], $colocacion->recreos($dia)),
                'sesiones' => array_map(function ($s) use ($dia, $asignaturas, $posicion, $ppm, $colocacion) {
                    $asignatura = $asignaturas->get($dia.'-'.$s['numero'])?->asignatura;

                    return $s + [
                        'top' => $posicion($s['inicio']),
                        'alto' => round($colocacion->duracion() * $ppm, 1),
                        'nombre' => $asignatura?->nombre,
                        'aula' => $asignatura?->aula,
                        'modulo' => $asignatura?->modulo?->nombre,
                        'fondo' => $asignatura?->colorFondo(),
                        'texto' => $asignatura?->colorTexto(),
                    ];
                }, $colocacion->sesiones($dia)),
                // Para la vista de un día del móvil: de la primera jornada a la última, sin
                // lo que queda entre dos jornadas (p. ej. la comida).
                'tramos' => $this->tramosMovil($colocacion, $dia, $paso, $asignaturas),
            ];
        }

        $marcas = [];
        for ($m = $inicioEscala; $m <= $finEscala; $m += 30) {
            $marcas[] = ['top' => $posicion($m), 'hora' => ColocacionSesiones::hora($m)];
        }

        return [
            'ppm' => $ppm,
            'alto' => $posicion($finEscala),
            'cinco' => round(5 * $ppm, 1),
            'media' => round(30 * $ppm, 1),
            'marcas' => $marcas,
            'columnas' => $columnas,
        ];
    }

    /** Lo que hay en el día, por orden, con sus horas: sesiones, recreos y huecos libres. */
    private function tramosMovil(ColocacionSesiones $colocacion, int $dia, int $paso, $asignaturas): array
    {
        $jornadas = $colocacion->jornadas($dia);

        if (! $jornadas) {
            return [];
        }

        $tramos = $colocacion->tramos($dia, min(array_column($jornadas, 'inicio')), max(array_column($jornadas, 'fin')), $paso);

        return collect($tramos)
            ->reject(fn ($t) => $t['tipo'] === 'fuera')
            ->map(function ($t) use ($dia, $asignaturas) {
                $asignatura = $t['tipo'] === 'sesion' ? $asignaturas->get($dia.'-'.$t['numero'])?->asignatura : null;

                return $t + [
                    'hora' => ColocacionSesiones::hora($t['inicio']),
                    'fin_hora' => ColocacionSesiones::hora($t['fin']),
                    'nombre' => $asignatura?->nombre,
                    'aula' => $asignatura?->aula,
                    'modulo' => $asignatura?->modulo?->nombre,
                    'fondo' => $asignatura?->colorFondo(),
                    'texto' => $asignatura?->colorTexto(),
                ];
            })
            ->values()
            ->all();
    }

    public function render()
    {
        $diasConfig = [];
        $maxSlots = 0;

        $minutosReferencia = $this->horario?->alinear_horas ? $this->horario->minutosReferenciaJornadas() : null;
        $duracionSesion = $this->horario?->duracion_sesion_minutos ?? 0;

        foreach ($this->horario?->jornadasPorDia() ?? collect() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $duracionSesion, $minutosReferencia);

            // 'fin' es para la vista del móvil (un día cada vez): ahí cada sesión lleva su
            // propia hora, no la de la fila, que sin alinear_horas puede ser de otro día.
            $slots = array_map(fn (array $slot) => $slot + [
                'fin' => Carbon::createFromFormat('H:i', $slot['hora'])
                    ->addMinutes($slot['tipo'] === 'recreo' ? $slot['duracion'] : $duracionSesion)
                    ->format('H:i'),
            ], $combinado['slots']);
            ksort($slots);

            $diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'abreviatura' => DiaSemana::from($diaSemana)->abreviatura(),
                'slots' => $slots,
            ];

            $maxSlots = max($maxSlots, $combinado['total_filas']);
        }

        $asignaciones = [];
        foreach ($this->horario?->sesiones ?? [] as $sesion) {
            if ($sesion->asignatura) {
                $asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = [
                    'nombre' => $sesion->asignatura->nombre,
                    'modulo' => $sesion->asignatura->modulo?->nombre,
                    'fondo' => $sesion->asignatura->colorFondo(),
                    'texto' => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        $horasFilas = Jornada::horasPorFila($diasConfig, $maxSlots, $duracionSesion);

        // En el móvil se abre en el día de hoy si tiene clase; si no, en el primero.
        $diaInicial = isset($diasConfig[now()->dayOfWeekIso]) ? now()->dayOfWeekIso : array_key_first($diasConfig);

        $escala = $this->horario?->esDeProfesor() ? $this->escalaProfesor() : [];

        return view('livewire.horarios.rejilla', compact('diasConfig', 'maxSlots', 'asignaciones', 'horasFilas', 'diaInicial', 'escala'));
    }
}
