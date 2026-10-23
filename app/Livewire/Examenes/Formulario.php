<?php

namespace App\Livewire\Examenes;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Horario;
use App\Models\Jornada;
use App\Support\DiaSemana;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    const HORA_PERSONALIZADA = 'personalizada';

    public bool $abrir = false;

    public $asignatura_id = '';
    public string $fecha = '';
    public string $hora = '';

    /**
     * Lo elegido en el desplegable de hora: la hora 'H:i' de inicio de una de las
     * sesiones de ese día, HORA_PERSONALIZADA (se escribe a mano en $hora) o '' (nada).
     */
    public string $eleccionHora = '';

    protected function rules()
    {
        return [
            'asignatura_id' => [
                'required',
                Rule::exists('asignaturas', 'id')
                    ->where('horario_id', session('horario_actual_id'))
                    ->where('estado_id', Asignatura::ESTADO_ACTIVO),
            ],
            'fecha' => ['required', 'date'],
            'hora'  => ['required', 'date_format:H:i'],
        ];
    }

    protected function messages()
    {
        return [
            'required'    => 'Debe rellenar :attribute',
            'exists'      => 'La asignatura no es válida',
            'date_format' => 'La hora no es válida',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'asignatura_id' => __('asignatura'),
            'fecha'         => __('fecha'),
            'hora'          => __('hora'),
        ];
    }

    #[On('abrir-crear-examen')]
    public function crear()
    {
        $this->reset(['asignatura_id', 'fecha', 'hora', 'eleccionHora']);
        $this->resetValidation();
        $this->abrir = true;
    }

    public function updatedAsignaturaId()
    {
        $this->descartarEleccionInvalida();
    }

    public function updatedFecha()
    {
        $this->descartarEleccionInvalida();
    }

    /** Si la sesión elegida no es de la nueva asignatura o del nuevo día, hay que volver a elegir. */
    private function descartarEleccionInvalida()
    {
        if ($this->eleccionHora === self::HORA_PERSONALIZADA) {
            return;
        }

        if (! in_array($this->eleccionHora, array_column($this->sesionesDelDia(), 'hora'), true)) {
            $this->eleccionHora = '';
        }
    }

    /**
     * Las sesiones de la asignatura elegida el día de la semana de $fecha, según la
     * rejilla, en orden: [['numero' => 4, 'hora' => '17:30'], …]. Vacío sin asignatura,
     * sin fecha o si ese día no tiene clase.
     */
    private function sesionesDelDia(): array
    {
        if (! $this->fecha || ! $this->asignatura_id) {
            return [];
        }

        try {
            $dia = DiaSemana::deFecha(Carbon::parse($this->fecha));
        } catch (\Exception) {
            return [];
        }

        $horario  = Horario::with('jornadas')->find(session('horario_actual_id'));
        $jornadas = $horario?->jornadasPorDia()->get($dia->value);

        if (! $jornadas) {
            return [];
        }

        $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0);
        $deAsignatura = $horario->sesiones()
            ->where('dia_semana', $dia->value)
            ->where('asignatura_id', $this->asignatura_id)
            ->pluck('sesion_numero')
            ->all();

        return collect($combinado['slots'])
            ->where('tipo', 'sesion')
            ->whereIn('numero', $deAsignatura)
            ->map(fn (array $slot) => ['numero' => $slot['numero'], 'hora' => $slot['hora']])
            ->values()
            ->all();
    }

    public function guardar()
    {
        if ($this->eleccionHora !== self::HORA_PERSONALIZADA) {
            $this->hora = $this->eleccionHora;
        }

        $data = $this->validate();

        $data['horario_id'] = session('horario_actual_id');
        Examen::create($data);
        $this->dispatch('toast-success', ['title' => __('Examen creado')]);

        $this->dispatch('examen-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        // Solo las asignaturas activas: a una dada de baja no se le ponen exámenes nuevos.
        $asignaturas = Asignatura::where('horario_id', session('horario_actual_id'))
            ->where('estado_id', Asignatura::ESTADO_ACTIVO)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');

        return view('livewire.examenes.formulario', [
            'asignaturas' => $asignaturas,
            'sesiones'    => $this->sesionesDelDia(),
        ]);
    }
}
