<?php

namespace App\Livewire\Examenes;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Horario;
use App\Models\Jornada;
use App\Services\Horarios\ColocacionSesiones;
use App\Support\DiaSemana;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    const HORA_PERSONALIZADA = 'personalizada';

    public bool $abrir = false;
    public ?int $itemId = null;

    /** Al modificar, la asignatura que ya tenía: se deja elegir aunque esté dada de baja. */
    #[Locked]
    public ?int $asignaturaOriginalId = null;

    public $asignatura_id = '';
    public string $titulo = '';
    public string $fecha = '';
    public string $hora = '';
    public string $hora_fin = '';

    /** Minutos antes del inicio (ver Examen::opcionesAlarma()), o '' para sin alarma. */
    public $alarma_minutos = Examen::ALARMA_POR_DEFECTO;

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
                    ->where(fn ($q) => $this->soloActivasOOriginal($q)),
            ],
            'titulo'   => ['required', 'string', 'max:150'],
            // Un examen nuevo no puede caer en un día que ya ha pasado (hoy sí vale). Al
            // modificar sí, para poder corregir uno que ya se hizo.
            'fecha'    => $this->itemId ? ['required', 'date'] : ['required', 'date', 'after_or_equal:today'],
            'hora'     => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora'],
            'alarma_minutos' => ['nullable', Rule::in(array_keys(Examen::opcionesAlarma()))],
        ];
    }

    protected function messages()
    {
        return [
            'required'       => 'Debe rellenar :attribute',
            'max'            => 'Máxima longitud de :attribute = :max',
            'exists'         => 'La asignatura no es válida',
            'date_format'    => 'La hora no es válida',
            'hora_fin.after' => 'La hora de fin tiene que ser posterior a la de inicio',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a hoy',
            'alarma_minutos.in' => 'La alarma no es válida',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'asignatura_id' => __('asignatura'),
            'titulo'        => __('texto del evento'),
            'fecha'         => __('fecha'),
            'hora'          => __('hora'),
            'hora_fin'      => __('hora de fin'),
        ];
    }

    /**
     * A una asignatura dada de baja no se le ponen exámenes nuevos; al modificar se deja
     * también la que ya tenía el examen, aunque se haya dado de baja después.
     */
    private function soloActivasOOriginal($query)
    {
        return $query->where('estado_id', Asignatura::ESTADO_ACTIVO)
            ->when($this->asignaturaOriginalId, fn ($q, $id) => $q->orWhere('id', $id));
    }

    #[On('abrir-crear-examen')]
    public function crear()
    {
        $this->reset();
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('examen-editar')]
    public function editar($id)
    {
        $item = Examen::where('horario_id', session('horario_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->reset();
        $this->itemId               = $item->id;
        $this->asignaturaOriginalId = $item->asignatura_id;
        $this->asignatura_id        = $item->asignatura_id;
        $this->titulo               = $item->titulo;
        $this->fecha                = $item->fecha->format('Y-m-d');
        $this->hora                 = substr($item->hora, 0, 5);
        $this->hora_fin             = substr($item->hora_fin, 0, 5);
        $this->alarma_minutos       = $item->alarma_minutos ?? '';
        // Si empieza a la hora de una de sus sesiones de ese día, sale esa elegida; si no,
        // es una hora personalizada.
        $this->eleccionHora = in_array($this->hora, array_column($this->sesionesDelDia(), 'hora'), true)
            ? $this->hora
            : self::HORA_PERSONALIZADA;
        $this->resetValidation();
        $this->abrir = true;
    }

    /**
     * Al elegir la asignatura se propone el texto del evento; se puede cambiar (y si luego
     * se cambia de asignatura, se vuelve a proponer).
     */
    public function updatedAsignaturaId()
    {
        $asignatura = Asignatura::with('modulo')->where('horario_id', session('horario_actual_id'))->find($this->asignatura_id);

        $this->titulo = $asignatura ? __('Examen de :asignatura', ['asignatura' => $asignatura->nombreConModulo()]) : '';

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
            $this->hora         = '';
            $this->hora_fin     = '';
        }
    }

    /**
     * Elegida una sesión, el examen empieza a su hora. Al pasar a personalizada se deja la
     * que hubiera, como punto de partida para cambiarla.
     */
    public function updatedEleccionHora()
    {
        if ($this->eleccionHora !== self::HORA_PERSONALIZADA) {
            $this->hora = $this->eleccionHora;
        }

        $this->proponerHoraFin();
    }

    public function updatedHora()
    {
        $this->proponerHoraFin();
    }

    /** De fábrica el examen dura una sesión del horario (una hora si no la tiene); luego se puede cambiar. */
    private function proponerHoraFin()
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $this->hora)) {
            $this->hora_fin = '';

            return;
        }

        $duracion = Horario::find(session('horario_actual_id'))?->duracion_sesion_minutos ?: 60;

        $this->hora_fin = Carbon::createFromFormat('H:i', $this->hora)->addMinutes($duracion)->format('H:i');
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

        $horario  = Horario::with(['jornadas.recreos', 'sesiones'])->find(session('horario_actual_id'));
        $jornadas = $horario?->jornadasPorDia()->get($dia->value);

        if (! $jornadas) {
            return [];
        }

        $deAsignatura = $horario->sesiones
            ->where('dia_semana', $dia->value)
            ->where('asignatura_id', $this->asignatura_id)
            ->pluck('sesion_numero')
            ->all();

        // En el de profesor, a la hora real de cada sesión (puede estar movida a mano).
        if ($horario->esDeProfesor()) {
            return collect((new ColocacionSesiones($horario))->sesiones($dia->value))
                ->whereIn('numero', $deAsignatura)
                ->map(fn (array $sesion) => ['numero' => $sesion['numero'], 'hora' => ColocacionSesiones::hora($sesion['inicio'])])
                ->values()
                ->all();
        }

        $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0);

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

        $this->titulo = trim($this->titulo);

        $data = $this->validate();

        $data['alarma_minutos'] = $this->alarma_minutos === '' || $this->alarma_minutos === null ? null : (int) $this->alarma_minutos;

        if ($this->itemId) {
            Examen::where('horario_id', session('horario_actual_id'))->findOrFail($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Examen modificado')]);
        } else {
            $data['horario_id'] = session('horario_actual_id');
            Examen::create($data);
            $this->dispatch('toast-success', ['title' => __('Examen creado')]);
        }

        $this->dispatch('examen-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        $asignaturas = Asignatura::with('modulo')
            ->where('horario_id', session('horario_actual_id'))
            ->where(fn ($q) => $this->soloActivasOOriginal($q))
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Asignatura $asignatura) => [$asignatura->id => $asignatura->nombreConModulo()]);

        return view('livewire.examenes.formulario', [
            'asignaturas' => $asignaturas,
            'sesiones'    => $this->sesionesDelDia(),
        ]);
    }
}
