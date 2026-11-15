<?php

namespace App\Livewire\Pruebas;

use App\Models\Evaluacion;
use App\Models\Prueba;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    #[Locked]
    public int $asignaturaId;

    public bool $abrir = false;
    public ?int $itemId = null;

    /** Si la prueba que se modifica ya tiene notas: cambiar la puntuación máxima las recalcula. */
    public bool $tieneNotas = false;

    public $evaluacion_id = '';
    public string $nombre = '';
    public string $fecha = '';
    public string $puntuacion_maxima = '';
    public string $peso = '';

    /** Las evaluaciones de los cursos del horario abierto. */
    private function evaluaciones()
    {
        return Evaluacion::with('curso')
            ->whereHas('curso', fn ($q) => $q->where('horario_id', session('horario_actual_id')))
            ->get()
            ->sortBy(fn (Evaluacion $e) => $e->curso->fecha_inicio->toDateString().$e->fecha_inicio->toDateString())
            ->values();
    }

    protected function rules()
    {
        return [
            'evaluacion_id' => ['required', Rule::in($this->evaluaciones()->pluck('id')->all())],
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('pruebas', 'nombre')
                    ->where('asignatura_id', $this->asignaturaId)
                    ->where('evaluacion_id', $this->evaluacion_id ?: 0)
                    ->ignore($this->itemId),
            ],
            'fecha'             => ['required', 'date'],
            'puntuacion_maxima' => ['required', 'numeric', 'gt:0', 'max:9999.99'],
            'peso'              => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => ':attribute no puede pasar de :max',
            'unique'   => 'Ya existe una prueba con ese nombre en la evaluación',
            'date'     => 'La :attribute no es válida',
            'numeric'  => ':attribute debe ser un número',
            'gt'       => ':attribute debe ser mayor que cero',
            'in'       => 'La evaluación no es válida',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'evaluacion_id'     => __('evaluación'),
            'nombre'            => __('nombre'),
            'fecha'             => __('fecha'),
            'puntuacion_maxima' => __('la puntuación máxima'),
            'peso'              => __('el peso'),
        ];
    }

    /** Se abre con la evaluación que la lista tenga en el filtro, si tiene alguna. */
    #[On('abrir-crear-prueba')]
    public function crear($evaluacionId = null)
    {
        $this->reset(['itemId', 'tieneNotas', 'evaluacion_id', 'nombre', 'fecha', 'puntuacion_maxima', 'peso']);
        $this->evaluacion_id = $evaluacionId ?: '';
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('prueba-editar')]
    public function editar($id)
    {
        $item = Prueba::where('asignatura_id', $this->asignaturaId)->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->tieneNotas = $item->notas()->exists();
        $this->evaluacion_id = $item->evaluacion_id;
        $this->nombre = $item->nombre;
        $this->fecha = $item->fecha->toDateString();
        $this->puntuacion_maxima = str_replace(',', '.', Prueba::numero($item->puntuacion_maxima));
        $this->peso = str_replace(',', '.', Prueba::numero($item->peso));
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        // Se admite la coma decimal.
        $this->puntuacion_maxima = str_replace(',', '.', trim($this->puntuacion_maxima));
        $this->peso = str_replace(',', '.', trim($this->peso));

        $data = $this->validate();
        $data['nombre'] = trim($data['nombre']);

        $evaluacion = $this->evaluaciones()->firstWhere('id', (int) $data['evaluacion_id']);
        if ($data['fecha'] < $evaluacion->fecha_inicio->toDateString() || $data['fecha'] > $evaluacion->fecha_fin->toDateString()) {
            $this->addError('fecha', __('La fecha debe estar dentro de la evaluación (:inicio – :fin).', [
                'inicio' => $evaluacion->fecha_inicio->format('d/m/Y'),
                'fin'    => $evaluacion->fecha_fin->format('d/m/Y'),
            ]));

            return;
        }

        if ($this->itemId) {
            $prueba = Prueba::where('asignatura_id', $this->asignaturaId)->find($this->itemId);
            if (! $prueba) {
                return;
            }

            $notaMasAlta = (float) $prueba->notas()->max('nota');
            if ($notaMasAlta > (float) $data['puntuacion_maxima']) {
                $this->addError('puntuacion_maxima', __('Hay notas más altas que esa puntuación (hasta :nota).', ['nota' => Prueba::numero($notaMasAlta)]));

                return;
            }

            DB::transaction(function () use ($prueba, $data) {
                $cambiaMaxima = (float) $data['puntuacion_maxima'] !== $prueba->puntuacion_maxima;
                $prueba->update($data);

                // Con otra puntuación máxima, las notas sobre 10 se recalculan todas: los
                // retoques a mano se pierden (el formulario lo avisa).
                if ($cambiaMaxima) {
                    foreach ($prueba->notas()->whereNotNull('nota')->get() as $nota) {
                        $nota->update(['nota_sobre_10' => $prueba->sobre10($nota->nota)]);
                    }
                }
            });
            $this->dispatch('toast-success', ['title' => __('Prueba modificada')]);
        } else {
            Prueba::create($data + ['asignatura_id' => $this->asignaturaId, 'estado_id' => Prueba::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Prueba creada')]);
        }

        $this->dispatch('prueba-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.pruebas.formulario', [
            'evaluaciones' => $this->abrir ? $this->evaluaciones() : collect(),
        ]);
    }
}
