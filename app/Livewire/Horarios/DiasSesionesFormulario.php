<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\Jornada;
use App\Support\DiaSemana;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class DiasSesionesFormulario extends Component
{
    public bool $abrir = false;

    public int $horarioId;

    public ?int $duracionSesionMinutos = null;

    /**
     * Por defecto (false), cada día lista sus sesiones seguidas desde su propia fila 0.
     * Con esto a true, la rejilla alinea horizontalmente la misma hora real en los 7
     * días, dejando huecos donde ese día no hay clase a esa hora.
     */
    public bool $alinearHoras = false;

    /**
     * Lista de jornadas: cada una es ['nombre' => string, 'hora_inicio' => 'H:i'|null,
     * 'num_sesiones' => int|null, 'recreo_antes_de_sesion' => int|null,
     * 'recreo_duracion_minutos' => int|null, 'dias' => [dia_semana => bool]].
     * Los dos campos de recreo van juntos: los dos vacíos = esa jornada no tiene recreo.
     */
    public array $jornadas = [];

    protected function jornadaVacia(): array
    {
        return [
            'nombre' => '',
            'hora_inicio' => null,
            'num_sesiones' => null,
            'recreo_antes_de_sesion' => null,
            'recreo_duracion_minutos' => null,
            'dias' => collect(DiaSemana::cases())->mapWithKeys(fn (DiaSemana $dia) => [$dia->value => false])->all(),
        ];
    }

    public function agregarJornada(): void
    {
        $this->jornadas[] = $this->jornadaVacia();
    }

    public function quitarJornada(int $indice): void
    {
        unset($this->jornadas[$indice]);
        $this->jornadas = array_values($this->jornadas);
    }

    #[On('abrir-editar-dias-sesiones')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');

        $horario = Horario::with('jornadas')->find($this->horarioId);

        $this->duracionSesionMinutos = $horario->duracion_sesion_minutos;
        $this->alinearHoras = $horario->alinear_horas;

        $this->jornadas = $horario->jornadas->sortBy('hora_inicio')->values()->map(fn (Jornada $jornada) => [
            'nombre' => $jornada->nombre,
            'hora_inicio' => substr($jornada->hora_inicio, 0, 5),
            'num_sesiones' => $jornada->num_sesiones,
            'recreo_antes_de_sesion' => $jornada->recreo_antes_de_sesion,
            'recreo_duracion_minutos' => $jornada->recreo_duracion_minutos,
            'dias' => collect(DiaSemana::cases())->mapWithKeys(
                fn (DiaSemana $dia) => [$dia->value => in_array($dia->value, $jornada->dias_semana, true)]
            )->all(),
        ])->all();

        if (empty($this->jornadas)) {
            // Horario recién creado, sin configurar todavía: arrancamos con una jornada
            // de lunes a viernes en vez de una lista vacía.
            $jornada = $this->jornadaVacia();
            $jornada['nombre'] = __('Mañana');

            foreach ($jornada['dias'] as $dia => &$activo) {
                $activo = $dia <= 5;
            }
            unset($activo);

            $this->jornadas = [$jornada];
        }

        $this->resetValidation();
        $this->abrir = true;
    }

    protected function rules(): array
    {
        $rules = [
            'duracionSesionMinutos' => ['required', 'integer', 'min:1'],
        ];

        foreach ($this->jornadas as $indice => $jornada) {
            $prefijo = "jornadas.$indice";

            $rules["$prefijo.nombre"] = ['required', 'string', 'max:100'];
            $rules["$prefijo.hora_inicio"] = ['required', 'date_format:H:i'];
            $rules["$prefijo.num_sesiones"] = ['required', 'integer', 'min:1', 'max:20'];

            // El recreo es opcional, pero si se pone uno de los dos campos hace falta el
            // otro. "Antes de la sesión X" solo tiene sentido con sesiones a los dos
            // lados: al menos la 1ª y 2ª antes (min:2) y como mucho la última de la
            // jornada (max: num_sesiones de esa jornada).
            $rules["$prefijo.recreo_antes_de_sesion"] = [
                'nullable', 'integer', 'min:2', 'max:'.($jornada['num_sesiones'] ?: 20),
                "required_with:$prefijo.recreo_duracion_minutos",
            ];
            $rules["$prefijo.recreo_duracion_minutos"] = [
                'nullable', 'integer', 'min:1',
                "required_with:$prefijo.recreo_antes_de_sesion",
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'string' => ':attribute debe ser texto',
            'integer' => ':attribute debe ser un número entero',
            'min' => ':attribute debe ser como mínimo :min',
            'max' => ':attribute debe ser como máximo :max',
            'date_format' => ':attribute no es una hora válida',
        ];
    }

    protected function validationAttributes(): array
    {
        $attrs = [
            'duracionSesionMinutos' => __('duración de la sesión'),
        ];

        foreach (array_keys($this->jornadas) as $indice) {
            $prefijo = "jornadas.$indice";
            $numero = $indice + 1;

            $attrs["$prefijo.nombre"] = __('jornada :n — nombre', ['n' => $numero]);
            $attrs["$prefijo.hora_inicio"] = __('jornada :n — hora de inicio', ['n' => $numero]);
            $attrs["$prefijo.num_sesiones"] = __('jornada :n — número de sesiones', ['n' => $numero]);
            $attrs["$prefijo.recreo_antes_de_sesion"] = __('jornada :n — recreo antes de la sesión', ['n' => $numero]);
            $attrs["$prefijo.recreo_duracion_minutos"] = __('jornada :n — duración del recreo', ['n' => $numero]);
        }

        return $attrs;
    }

    public function guardar()
    {
        if (empty($this->jornadas)) {
            $this->addError('jornadas', __('Añade al menos una jornada.'));

            return;
        }

        foreach ($this->jornadas as $indice => $jornada) {
            if (! in_array(true, $jornada['dias'], true)) {
                $this->addError("jornadas.$indice.nombre", __('Selecciona al menos un día para esta jornada.'));

                return;
            }
        }

        $this->validate();

        DB::transaction(function () {
            Horario::whereKey($this->horarioId)->update([
                'duracion_sesion_minutos' => $this->duracionSesionMinutos,
                'alinear_horas' => $this->alinearHoras,
            ]);

            Jornada::where('horario_id', $this->horarioId)->delete();

            foreach ($this->jornadas as $jornada) {
                $diasSemana = collect($jornada['dias'])->filter()->keys()->values()->all();

                Jornada::create([
                    'horario_id' => $this->horarioId,
                    'nombre' => $jornada['nombre'],
                    'hora_inicio' => $jornada['hora_inicio'],
                    'num_sesiones' => $jornada['num_sesiones'],
                    'recreo_antes_de_sesion' => $jornada['recreo_antes_de_sesion'] ?: null,
                    'recreo_duracion_minutos' => $jornada['recreo_duracion_minutos'] ?: null,
                    'dias_semana' => $diasSemana,
                ]);
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
