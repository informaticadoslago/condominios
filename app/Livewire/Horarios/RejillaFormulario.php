<?php

namespace App\Livewire\Horarios;

use App\Models\Asignatura;
use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\HorarioSesionAviso;
use App\Models\Jornada;
use App\Services\Horarios\ColocacionSesiones;
use App\Support\DiaSemana;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class RejillaFormulario extends Component
{
    public bool $abrir = false;

    public int $horarioId;

    /** [dia_semana => ['nombre' => .., 'num_sesiones' => n, 'slots' => calcularSlots()]], solo días con clase. */
    public array $diasConfig = [];

    /** Fila más larga de la rejilla: el máximo de slots (sesiones + recreo) entre los días activos. */
    public int $maxSlots = 0;

    /** [dia_semana][sesion_numero] => asignatura_id ('' si ninguna), para el wire:model de cada select. */
    public array $asignaciones = [];

    /** Solo en horarios de profesor: cada sesión puede llevar su hora de inicio a mano. */
    public bool $esDeProfesor = false;

    /** [dia_semana][sesion_numero] => 'H:i' a mano ('' = la que le toca por la jornada). */
    public array $horas = [];

    /**
     * La campana de cada sesión solo sale si el usuario tiene las notificaciones push
     * activadas y algún dispositivo que las haya aceptado: sin él no le llegaría nada.
     */
    public bool $conAvisos = false;

    /** [dia_semana][sesion_numero] => ['inicio' => minutos, 'fin' => minutos]: los avisos del usuario (solo los que tiene). */
    public array $avisos = [];

    /**
     * [dia_semana][sesion_numero] => true de las sesiones guardadas con asignatura: el aviso
     * va colgado de la sesión, así que una asignatura recién elegida en el desplegable no
     * tiene campana hasta que se guarda la rejilla.
     */
    public array $sesionesGuardadas = [];

    /** El modal de avisos de una sesión, encima del de la rejilla. */
    public bool $avisoAbierto = false;

    public ?int $avisoDia = null;

    public ?int $avisoNumero = null;

    /** Día, hora y asignatura de la sesión, para el subtítulo del modal de avisos. */
    public string $avisoSesion = '';

    /** Minutos antes (ver HorarioSesionAviso::opciones()), o '' para ninguno. */
    public string $avisoInicio = '';

    public string $avisoFin = '';

    /**
     * El componente se pinta en la página aunque el modal esté cerrado (@livewire lo
     * incluye siempre), y render() usa $horarioId (consulta las asignaturas): hace
     * falta un valor desde el primer render, no solo cuando abrirModal() lo rellena.
     */
    public function mount(): void
    {
        $this->horarioId = (int) session('horario_actual_id');
    }

    #[On('abrir-editar-rejilla')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');

        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find($this->horarioId);

        $this->diasConfig = [];
        $this->maxSlots = 0;
        $this->asignaciones = [];
        $this->horas = [];
        $this->esDeProfesor = $horario->esDeProfesor();

        $minutosReferencia = $horario->alinear_horas ? $horario->minutosReferenciaJornadas() : null;

        foreach ($horario->jornadasPorDia() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0, $minutosReferencia);

            $this->diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'num_sesiones' => $combinado['num_sesiones'],
                'slots' => $combinado['slots'],
            ];

            $this->maxSlots = max($this->maxSlots, $combinado['total_filas']);

            $this->asignaciones[$diaSemana] = array_fill(1, $combinado['num_sesiones'], '');
            $this->horas[$diaSemana] = array_fill(1, $combinado['num_sesiones'], '');
        }

        $this->sesionesGuardadas = [];

        foreach ($horario->sesiones as $sesion) {
            $this->asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->asignatura_id;
            $this->horas[$sesion->dia_semana][$sesion->sesion_numero] = $sesion->hora_inicio ? substr($sesion->hora_inicio, 0, 5) : '';
            $this->sesionesGuardadas[$sesion->dia_semana][$sesion->sesion_numero] = true;
        }

        $this->conAvisos = Auth::user()->notificaciones_push && Auth::user()->pushSubscriptions()->exists();
        $this->avisos = [];

        $avisos = HorarioSesionAviso::with('sesion')->where('user_id', Auth::id())->where('horario_id', $this->horarioId)->get();

        foreach ($avisos as $aviso) {
            $this->avisos[$aviso->dia_semana][$aviso->sesion->sesion_numero][$aviso->momento] = $aviso->minutos_antes;
        }

        $this->resetValidation();
        $this->abrir = true;
    }

    /** La sesión guardada (con asignatura) de ese día y número, o null. */
    private function sesionGuardada(int $dia, int $numero): ?HorarioSesion
    {
        return HorarioSesion::with('asignatura')
            ->where('horario_id', $this->horarioId)
            ->where('dia_semana', $dia)
            ->where('sesion_numero', $numero)
            ->first();
    }

    /**
     * La campana de una sesión: abre encima el modal con sus dos avisos. Sin asignatura
     * guardada no hay clase de la que avisar.
     */
    public function abrirAviso(int $dia, int $numero): void
    {
        $slot = collect($this->diasConfig[$dia]['slots'] ?? [])->firstWhere('numero', $numero);
        $sesion = $this->sesionGuardada($dia, $numero);

        if (! $this->conAvisos || ! $slot || ! $sesion) {
            return;
        }

        $hora = $sesion->hora_inicio ? substr($sesion->hora_inicio, 0, 5) : $slot['hora'];

        $this->avisoDia = $dia;
        $this->avisoNumero = $numero;
        $this->avisoSesion = $this->diasConfig[$dia]['nombre'].', '.$hora.' · '.$sesion->asignatura->nombre;
        $this->avisoInicio = (string) ($this->avisos[$dia][$numero][HorarioSesionAviso::MOMENTO_INICIO] ?? '');
        $this->avisoFin = (string) ($this->avisos[$dia][$numero][HorarioSesionAviso::MOMENTO_FIN] ?? '');

        $this->resetValidation(['avisoInicio', 'avisoFin']);
        $this->avisoAbierto = true;
    }

    /**
     * Los avisos son del usuario, no del horario: se guardan ya, sin esperar al Guardar
     * de la rejilla. Uno por momento; en "ninguno" se borra. La hora a la que salta cada
     * uno se calcula aquí (HorarioSesionAviso::recalcular()), no en el cron.
     */
    public function guardarAviso(): void
    {
        $opciones = Rule::in(array_keys(HorarioSesionAviso::opciones()));

        $this->validate(
            ['avisoInicio' => ['nullable', $opciones], 'avisoFin' => ['nullable', $opciones]],
            ['in' => __('No es un aviso válido.')],
        );

        $sesion = $this->sesionGuardada((int) $this->avisoDia, (int) $this->avisoNumero);

        if (! $sesion) {
            $this->avisoAbierto = false;

            return;
        }

        $elegidos = [
            HorarioSesionAviso::MOMENTO_INICIO => $this->avisoInicio,
            HorarioSesionAviso::MOMENTO_FIN => $this->avisoFin,
        ];

        DB::transaction(function () use ($sesion, $elegidos) {
            foreach ($elegidos as $momento => $minutos) {
                $clave = ['user_id' => Auth::id(), 'horario_sesion_id' => $sesion->id, 'momento' => $momento];

                if ($minutos === '') {
                    HorarioSesionAviso::where($clave)->delete();
                } else {
                    HorarioSesionAviso::updateOrCreate($clave, [
                        'horario_id' => $sesion->horario_id,
                        'dia_semana' => $sesion->dia_semana,
                        'minutos_antes' => (int) $minutos,
                    ]);
                }
            }

            HorarioSesionAviso::recalcular($sesion->horario_id);
        });

        $elegidos = array_map('intval', array_filter($elegidos, fn ($minutos) => $minutos !== ''));

        if ($elegidos) {
            $this->avisos[$this->avisoDia][$this->avisoNumero] = $elegidos;
        } else {
            unset($this->avisos[$this->avisoDia][$this->avisoNumero]);
        }

        $this->dispatch('toast-success', ['title' => __('Avisos guardados')]);
        $this->avisoAbierto = false;
    }

    public function guardar()
    {
        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find($this->horarioId);
        $this->esDeProfesor = $horario->esDeProfesor();
        $colocacion = $this->esDeProfesor ? new ColocacionSesiones($horario) : null;
        $manuales = [];

        if ($colocacion) {
            $this->validate(
                ['horas.*.*' => ['nullable', 'date_format:H:i']],
                ['date_format' => __('No es una hora válida.')],
            );

            // Cómo quedarían las sesiones con asignatura de cada día con las horas del
            // formulario: ninguna puede pisar a otra ni salirse de su jornada.
            foreach ($this->diasConfig as $dia => $config) {
                $manuales[$dia] = [];

                for ($s = 1; $s <= $config['num_sesiones']; $s++) {
                    if (($this->asignaciones[$dia][$s] ?? '') !== '') {
                        $hora = $this->horas[$dia][$s] ?? '';
                        $manuales[$dia][$s] = $hora !== '' ? Jornada::minutosDesdeMedianoche($hora) : null;
                    }
                }

                foreach ($colocacion->errores($dia, $manuales[$dia]) as $numero => $mensaje) {
                    $this->addError("horas.$dia.$numero", $mensaje);
                }
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        DB::transaction(function () use ($colocacion, $manuales) {
            foreach ($this->diasConfig as $dia => $config) {
                for ($s = 1; $s <= $config['num_sesiones']; $s++) {
                    $asignaturaId = $this->asignaciones[$dia][$s] ?? '';

                    if ($asignaturaId !== '') {
                        $valores = ['asignatura_id' => $asignaturaId];

                        if ($colocacion) {
                            // A su hora de siempre, la sesión vuelve a "automática".
                            $manual = $manuales[$dia][$s] ?? null;
                            $valores['hora_inicio'] = $manual !== null && $manual !== $colocacion->inicioPorDefecto($dia, $s)
                                ? ColocacionSesiones::hora($manual)
                                : null;
                        }

                        HorarioSesion::updateOrCreate(
                            ['horario_id' => $this->horarioId, 'dia_semana' => $dia, 'sesion_numero' => $s],
                            $valores,
                        );
                    } else {
                        HorarioSesion::where('horario_id', $this->horarioId)
                            ->where('dia_semana', $dia)
                            ->where('sesion_numero', $s)
                            ->delete();
                    }
                }
            }

            // Una sesión que se queda sin asignatura se lleva sus avisos (ON DELETE CASCADE);
            // las demás pueden haber cambiado de hora a mano.
            HorarioSesionAviso::recalcular($this->horarioId);
        });

        $this->dispatch('toast-success', ['title' => __('Horario guardado')]);
        $this->dispatch('rejilla-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.rejilla-formulario', [
            'asignaturas' => Asignatura::with('modulo')->where('horario_id', $this->horarioId)->orderBy('nombre')->get(),
        ]);
    }
}
