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

    /** Los horarios de alumno son fijos, con un solo recreo por jornada. */
    public bool $unSoloRecreo = false;

    /**
     * Lista de jornadas: cada una es ['nombre' => string, 'hora_inicio' => 'H:i'|null,
     * 'num_sesiones' => int|null, 'recreos' => [['hora_inicio' => 'H:i'|null,
     * 'duracion_minutos' => int|null], ...], 'dias' => [dia_semana => bool]].
     */
    public array $jornadas = [];

    protected function jornadaVacia(): array
    {
        return [
            'nombre' => '',
            'hora_inicio' => null,
            'num_sesiones' => null,
            'recreos' => [],
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

    public function agregarRecreo(int $indice): void
    {
        if ($this->unSoloRecreo && count($this->jornadas[$indice]['recreos']) >= 1) {
            return;
        }

        $this->jornadas[$indice]['recreos'][] = ['hora_inicio' => null, 'duracion_minutos' => null];
    }

    public function quitarRecreo(int $indice, int $recreo): void
    {
        unset($this->jornadas[$indice]['recreos'][$recreo]);
        $this->jornadas[$indice]['recreos'] = array_values($this->jornadas[$indice]['recreos']);
    }

    #[On('abrir-editar-dias-sesiones')]
    public function abrirModal(): void
    {
        $this->horarioId = (int) session('horario_actual_id');

        $horario = Horario::with('jornadas.recreos')->find($this->horarioId);

        $this->duracionSesionMinutos = $horario->duracion_sesion_minutos;
        $this->alinearHoras = $horario->alinear_horas;
        $this->unSoloRecreo = ! $horario->esDeProfesor();

        $this->jornadas = $horario->jornadas->sortBy('hora_inicio')->values()->map(fn (Jornada $jornada) => [
            'nombre' => $jornada->nombre,
            'hora_inicio' => substr($jornada->hora_inicio, 0, 5),
            'num_sesiones' => $jornada->num_sesiones,
            'recreos' => $jornada->recreos->map(fn ($recreo) => [
                'hora_inicio' => substr($recreo->hora_inicio, 0, 5),
                'duracion_minutos' => $recreo->duracion_minutos,
            ])->all(),
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

            // Un recreo no puede empezar antes que la propia jornada.
            foreach (array_keys($jornada['recreos'] ?? []) as $r) {
                $rules["$prefijo.recreos.$r.hora_inicio"] = ['required', 'date_format:H:i', "after_or_equal:$prefijo.hora_inicio"];
                $rules["$prefijo.recreos.$r.duracion_minutos"] = ['required', 'integer', 'min:1'];
            }
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
            'after_or_equal' => ':attribute no puede ser antes del inicio de la jornada',
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

            foreach (array_keys($this->jornadas[$indice]['recreos'] ?? []) as $r) {
                $attrs["$prefijo.recreos.$r.hora_inicio"] = __('jornada :n — recreo :r — hora de inicio', ['n' => $numero, 'r' => $r + 1]);
                $attrs["$prefijo.recreos.$r.duracion_minutos"] = __('jornada :n — recreo :r — duración', ['n' => $numero, 'r' => $r + 1]);
            }
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

        if (! Horario::find($this->horarioId)?->esDeProfesor()) {
            foreach ($this->jornadas as $indice => $jornada) {
                if (count($jornada['recreos']) > 1) {
                    $this->addError("jornadas.$indice.nombre", __('En un horario de alumno cada jornada tiene como mucho un recreo.'));

                    return;
                }
            }
        }

        // Los recreos de una jornada no pueden pisarse entre sí.
        foreach ($this->jornadas as $indice => $jornada) {
            $finAnterior = null;

            foreach (collect($jornada['recreos'])->sortBy('hora_inicio') as $r => $recreo) {
                $inicio = Jornada::minutosDesdeMedianoche($recreo['hora_inicio']);

                if ($finAnterior !== null && $inicio < $finAnterior) {
                    $this->addError("jornadas.$indice.recreos.$r.hora_inicio", __('Este recreo se solapa con el anterior.'));

                    return;
                }

                $finAnterior = $inicio + (int) $recreo['duracion_minutos'];
            }
        }

        DB::transaction(function () {
            Horario::whereKey($this->horarioId)->update([
                'duracion_sesion_minutos' => $this->duracionSesionMinutos,
                'alinear_horas' => $this->alinearHoras,
            ]);

            Jornada::where('horario_id', $this->horarioId)->delete();

            foreach ($this->jornadas as $jornada) {
                $diasSemana = collect($jornada['dias'])->filter()->keys()->values()->all();

                $nueva = Jornada::create([
                    'horario_id' => $this->horarioId,
                    'nombre' => $jornada['nombre'],
                    'hora_inicio' => $jornada['hora_inicio'],
                    'num_sesiones' => $jornada['num_sesiones'],
                    'dias_semana' => $diasSemana,
                ]);

                foreach ($jornada['recreos'] as $recreo) {
                    $nueva->recreos()->create([
                        'hora_inicio' => $recreo['hora_inicio'],
                        'duracion_minutos' => $recreo['duracion_minutos'],
                    ]);
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
