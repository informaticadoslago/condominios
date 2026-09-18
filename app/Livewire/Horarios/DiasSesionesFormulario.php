<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\HorarioDia;
use App\Support\DiaSemana;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class DiasSesionesFormulario extends Component
{
    public bool $abrir = false;

    public int $horarioId;

    public string $tipoDias = 'lunes_viernes';

    /**
     * [dia_semana => ['activo' => bool, 'hora_primera_sesion' => 'H:i'|null, 'num_sesiones' => int|null,
     * 'recreo_antes_de_sesion' => int|null, 'recreo_duracion_minutos' => int|null]].
     * Los dos campos de recreo van juntos: los dos vacíos = ese día no tiene recreo.
     */
    public array $dias = [];

    public ?int $duracionSesionMinutos = null;

    /**
     * El componente se pinta en la página aunque el modal esté cerrado (@livewire lo
     * incluye siempre), así que el blade necesita las 7 claves de $dias desde el primer
     * render, no solo cuando abrirModal() las rellena de verdad.
     */
    public function mount(): void
    {
        $this->dias = collect(DiaSemana::cases())->mapWithKeys(fn (DiaSemana $dia) => [
            $dia->value => $this->diaVacio(),
        ])->all();
    }

    protected function diaVacio(): array
    {
        return [
            'activo'                  => false,
            'hora_primera_sesion'     => null,
            'num_sesiones'            => null,
            'recreo_antes_de_sesion'  => null,
            'recreo_duracion_minutos' => null,
        ];
    }

    #[On('abrir-editar-dias-sesiones')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');

        $horario = Horario::with('dias')->find($this->horarioId);

        $this->duracionSesionMinutos = $horario->duracion_sesion_minutos;

        $this->dias = collect(DiaSemana::cases())->mapWithKeys(fn (DiaSemana $dia) => [
            $dia->value => $this->diaVacio(),
        ])->all();

        foreach ($horario->dias as $horarioDia) {
            $this->dias[$horarioDia->dia_semana] = [
                'activo'                  => true,
                'hora_primera_sesion'     => substr($horarioDia->hora_primera_sesion, 0, 5),
                'num_sesiones'            => $horarioDia->num_sesiones,
                'recreo_antes_de_sesion'  => $horarioDia->recreo_antes_de_sesion,
                'recreo_duracion_minutos' => $horarioDia->recreo_duracion_minutos,
            ];
        }

        if ($horario->dias->isEmpty()) {
            // Horario recién creado, sin configurar todavía: arrancamos con un preset
            // razonable en vez de dejar los siete días desmarcados.
            $this->tipoDias = 'lunes_viernes';
            $this->aplicarPreset('lunes_viernes');
        } else {
            $this->tipoDias = $this->detectarTipoDias();
        }

        $this->resetValidation();
        $this->abrir = true;
    }

    protected function detectarTipoDias(): string
    {
        $activos = collect($this->dias)->filter(fn ($config) => $config['activo'])->keys()->sort()->values()->all();

        if ($activos === [1, 2, 3, 4, 5]) {
            return 'lunes_viernes';
        }

        if ($activos === [1, 2, 3, 4, 5, 6, 7]) {
            return 'lunes_domingo';
        }

        return 'personalizado';
    }

    public function updatedTipoDias($valor): void
    {
        $this->aplicarPreset($valor);
    }

    /** 'personalizado' no toca nada: el usuario marca los checkboxes él mismo. */
    protected function aplicarPreset(string $tipo): void
    {
        if ($tipo === 'lunes_viernes') {
            foreach ($this->dias as $dia => &$config) {
                $config['activo'] = $dia <= 5;
            }
            unset($config);
        } elseif ($tipo === 'lunes_domingo') {
            foreach ($this->dias as $dia => &$config) {
                $config['activo'] = true;
            }
            unset($config);
        }
    }

    protected function rules(): array
    {
        $rules = [
            'duracionSesionMinutos' => ['required', 'integer', 'min:1'],
            'tipoDias'              => ['required', Rule::in(['lunes_viernes', 'lunes_domingo', 'personalizado'])],
        ];

        foreach ($this->dias as $dia => $config) {
            if ($config['activo']) {
                $rules["dias.$dia.hora_primera_sesion"] = ['required', 'date_format:H:i'];
                $rules["dias.$dia.num_sesiones"]         = ['required', 'integer', 'min:1', 'max:20'];

                // El recreo es opcional, pero si se pone uno de los dos campos hace
                // falta el otro. "Antes de la sesión X" solo tiene sentido con sesiones
                // a los dos lados: al menos la 1ª y 2ª antes (min:2) y como mucho la
                // última del día (max: num_sesiones de ESE día).
                $rules["dias.$dia.recreo_antes_de_sesion"] = [
                    'nullable', 'integer', 'min:2', 'max:'.($config['num_sesiones'] ?: 20),
                    'required_with:dias.'.$dia.'.recreo_duracion_minutos',
                ];
                $rules["dias.$dia.recreo_duracion_minutos"] = [
                    'nullable', 'integer', 'min:1',
                    'required_with:dias.'.$dia.'.recreo_antes_de_sesion',
                ];
            }
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required'    => 'Debe rellenar :attribute',
            'integer'     => ':attribute debe ser un número entero',
            'min'         => ':attribute debe ser como mínimo :min',
            'max'         => ':attribute debe ser como máximo :max',
            'date_format' => ':attribute no es una hora válida',
        ];
    }

    protected function validationAttributes(): array
    {
        $attrs = [
            'duracionSesionMinutos' => __('duración de la sesión'),
        ];

        foreach (DiaSemana::cases() as $dia) {
            $attrs["dias.{$dia->value}.hora_primera_sesion"]     = $dia->nombre().' — '.__('hora de la primera sesión');
            $attrs["dias.{$dia->value}.num_sesiones"]            = $dia->nombre().' — '.__('número de sesiones');
            $attrs["dias.{$dia->value}.recreo_antes_de_sesion"]  = $dia->nombre().' — '.__('recreo antes de la sesión');
            $attrs["dias.{$dia->value}.recreo_duracion_minutos"] = $dia->nombre().' — '.__('duración del recreo');
        }

        return $attrs;
    }

    public function guardar()
    {
        if (! collect($this->dias)->contains(fn ($config) => $config['activo'])) {
            $this->addError('tipoDias', __('Selecciona al menos un día con clase.'));

            return;
        }

        $this->validate();

        DB::transaction(function () {
            Horario::whereKey($this->horarioId)->update(['duracion_sesion_minutos' => $this->duracionSesionMinutos]);

            foreach ($this->dias as $dia => $config) {
                if ($config['activo']) {
                    HorarioDia::updateOrCreate(
                        ['horario_id' => $this->horarioId, 'dia_semana' => $dia],
                        [
                            'hora_primera_sesion'     => $config['hora_primera_sesion'],
                            'num_sesiones'            => $config['num_sesiones'],
                            'recreo_antes_de_sesion'  => $config['recreo_antes_de_sesion'] ?: null,
                            'recreo_duracion_minutos' => $config['recreo_duracion_minutos'] ?: null,
                        ],
                    );
                } else {
                    HorarioDia::where('horario_id', $this->horarioId)->where('dia_semana', $dia)->delete();
                }
            }
        });

        $this->dispatch('toast-success', ['title' => __('Días y sesiones guardados')]);
        $this->dispatch('dias-sesiones-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.dias-sesiones-formulario', [
            'diasSemana' => DiaSemana::cases(),
        ]);
    }
}
