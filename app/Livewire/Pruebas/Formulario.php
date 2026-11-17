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

    /** Si la prueba que se modifica lleva partes dentro: ya no puede dejar de ser «con partes» ni cambiar de evaluación. */
    public bool $tienePartes = false;

    /** La prueba de la que es parte (o va a serlo, si es nueva); null si es una prueba suelta. */
    #[Locked]
    public ?int $contenedoraId = null;

    public string $contenedoraNombre = '';

    public $evaluacion_id = '';
    public string $nombre = '';
    public string $fecha = '';
    public string $puntuacion_maxima = '';
    public string $peso = '';

    /** Cómo se califica (ver Prueba::CALIFICACION_*): con un número o con palabras. Con notas ya puestas no se cambia. */
    public $tipo_calificacion = Prueba::CALIFICACION_NUMERICA;

    /** Prueba con partes: sin puntuación máxima ni notas propias; su nota es la media de sus partes. */
    public bool $con_partes = false;

    /** La nota sobre 10 que hay que sacar en cada parte para que la media valga; 0 = sin mínimo. */
    public string $nota_minima = '5';

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
                Rule::unique('pruebas_evaluables', 'nombre')
                    ->where('asignatura_id', $this->asignaturaId)
                    ->where('evaluacion_id', $this->evaluacion_id ?: 0)
                    ->ignore($this->itemId),
            ],
            'fecha'             => ['required', 'date'],
            // La prueba con partes no tiene puntuación máxima; a cambio, tiene nota mínima por parte.
            // Tampoco la escribe la que se califica con palabras: es siempre sobre 1.
            'puntuacion_maxima' => $this->con_partes || ! $this->numerica() ? ['nullable'] : ['required', 'numeric', 'gt:0', 'max:9999.99'],
            'tipo_calificacion' => ['required', Rule::in(array_keys(Prueba::tiposCalificacion()))],
            'nota_minima'       => $this->con_partes ? ['required', 'numeric', 'min:0', 'max:10'] : ['nullable'],
            'peso'              => ['required', 'numeric', 'gt:0', 'max:100'],
            'con_partes'        => ['boolean'],
        ];
    }

    /** Si lo elegido en el formulario es calificar con un número (una prueba con partes no elige). */
    private function numerica(): bool
    {
        return $this->con_partes || (int) $this->tipo_calificacion === Prueba::CALIFICACION_NUMERICA;
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => ':attribute no puede pasar de :max',
            'min'      => ':attribute no puede ser menor que :min',
            'unique'   => 'Ya existe una prueba con ese nombre en la evaluación',
            'date'     => 'La :attribute no es válida',
            'numeric'  => ':attribute debe ser un número',
            'gt'       => ':attribute debe ser mayor que cero',
            'evaluacion_id.in'     => 'La evaluación no es válida',
            'tipo_calificacion.in' => 'El tipo de calificación no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'evaluacion_id'     => __('evaluación'),
            'nombre'            => __('nombre'),
            'fecha'             => __('fecha'),
            'puntuacion_maxima' => __('la puntuación máxima'),
            'tipo_calificacion' => __('cómo se califica'),
            'nota_minima'       => __('la nota mínima'),
            'peso'              => __('el peso'),
        ];
    }

    /**
     * Se abre con la evaluación que la lista tenga en el filtro, si tiene alguna. Con
     * $pruebaId, lo que se crea es una parte de esa prueba (y en su evaluación).
     */
    #[On('abrir-crear-prueba')]
    public function crear($evaluacionId = null, $pruebaId = null)
    {
        $this->reset(['itemId', 'tieneNotas', 'tienePartes', 'contenedoraId', 'contenedoraNombre', 'evaluacion_id', 'nombre', 'fecha', 'puntuacion_maxima', 'tipo_calificacion', 'peso', 'con_partes', 'nota_minima']);
        $this->evaluacion_id = $evaluacionId ?: '';

        if ($pruebaId) {
            $contenedora = $this->contenedoras()->find($pruebaId);
            if (! $contenedora) {
                return;
            }
            $this->deContenedora($contenedora);
        }

        $this->resetValidation();
        $this->abrir = true;
    }

    /** Las pruebas de la asignatura que pueden llevar partes dentro. */
    private function contenedoras()
    {
        return Prueba::where('asignatura_id', $this->asignaturaId)->where('con_partes', true)->whereNull('prueba_id');
    }

    /** La parte es de la evaluación de su prueba. */
    private function deContenedora(Prueba $contenedora): void
    {
        $this->contenedoraId = $contenedora->id;
        $this->contenedoraNombre = $contenedora->nombre;
        $this->evaluacion_id = $contenedora->evaluacion_id;
    }

    #[On('prueba-editar')]
    public function editar($id)
    {
        $item = Prueba::where('asignatura_id', $this->asignaturaId)->find($id);
        if (! $item) {
            return;
        }
        $this->reset(['contenedoraId', 'contenedoraNombre']);
        $this->itemId = $item->id;
        $this->tieneNotas = ! $item->con_partes && $item->notas()->exists();
        $this->tienePartes = $item->partes()->exists();
        $this->evaluacion_id = $item->evaluacion_id;
        $this->nombre = $item->nombre;
        $this->fecha = $item->fecha->toDateString();
        // El «sobre 1» de la que se califica con palabras no se enseña: no lo ha escrito nadie.
        $this->puntuacion_maxima = $item->esNumerica() ? str_replace(',', '.', Prueba::numero($item->puntuacion_maxima)) : '';
        $this->tipo_calificacion = $item->tipo_calificacion;
        $this->peso = str_replace(',', '.', Prueba::numero($item->peso));
        $this->con_partes = $item->con_partes;
        $this->nota_minima = str_replace(',', '.', Prueba::numero($item->nota_minima));
        if ($item->contenedora) {
            $this->deContenedora($item->contenedora);
        }
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        // Se admite la coma decimal.
        $this->puntuacion_maxima = str_replace(',', '.', trim($this->puntuacion_maxima));
        $this->peso = str_replace(',', '.', trim($this->peso));
        $this->nota_minima = str_replace(',', '.', trim($this->nota_minima));

        // Una parte no lleva partes, y va en la evaluación de su prueba.
        $contenedora = $this->contenedoraId ? $this->contenedoras()->find($this->contenedoraId) : null;
        if ($this->contenedoraId && ! $contenedora) {
            return;
        }
        if ($contenedora) {
            $this->con_partes = false;
            $this->evaluacion_id = $contenedora->evaluacion_id;
        }

        $data = $this->validate();
        $data['nombre'] = trim($data['nombre']);
        // Cada tipo de prueba guarda solo lo suyo.
        $data['tipo_calificacion'] = $data['con_partes'] ? Prueba::CALIFICACION_NUMERICA : (int) $data['tipo_calificacion'];
        $data['puntuacion_maxima'] = match (true) {
            $data['con_partes']                                             => null,
            $data['tipo_calificacion'] !== Prueba::CALIFICACION_NUMERICA => 1,
            default                                                         => $data['puntuacion_maxima'],
        };
        $data['nota_minima'] = $data['con_partes'] ? $data['nota_minima'] : 5;

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

            // Lo que ya cuelga de la prueba manda: con notas propias no pasa a tener partes, y
            // con partes dentro ni deja de tenerlas ni cambia de evaluación (son de la misma).
            if ($data['con_partes'] && ! $prueba->con_partes && $prueba->notas()->exists()) {
                $this->addError('con_partes', __('Esta prueba ya tiene notas: no puede pasar a tener partes.'));

                return;
            }
            if ($prueba->partes()->exists() && (! $data['con_partes'] || (int) $data['evaluacion_id'] !== $prueba->evaluacion_id)) {
                $this->addError($data['con_partes'] ? 'evaluacion_id' : 'con_partes', __('Esta prueba tiene partes dentro: antes hay que sacarlas.'));

                return;
            }

            // Un 7 no se convierte en Apto ni un Apto en un número: con notas, el tipo se queda.
            if (! $prueba->con_partes && $data['tipo_calificacion'] !== $prueba->tipo_calificacion && $prueba->notas()->exists()) {
                $this->addError('tipo_calificacion', __('Esta prueba ya tiene notas: no se puede cambiar cómo se califica.'));

                return;
            }

            $notaMasAlta = $data['con_partes'] ? 0 : (float) $prueba->notas()->max('nota');
            if ($notaMasAlta > (float) $data['puntuacion_maxima']) {
                $this->addError('puntuacion_maxima', __('Hay notas más altas que esa puntuación (hasta :nota).', ['nota' => Prueba::numero($notaMasAlta)]));

                return;
            }

            DB::transaction(function () use ($prueba, $data) {
                $cambiaMaxima = ! $data['con_partes'] && (float) $data['puntuacion_maxima'] !== $prueba->puntuacion_maxima;
                // Al dejar de ser «con partes», los retoques de la nota final ya no son de nada.
                if ($prueba->con_partes && ! $data['con_partes']) {
                    $prueba->notas()->delete();
                }
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
            Prueba::create($data + [
                'asignatura_id' => $this->asignaturaId,
                'prueba_id'     => $contenedora?->id,
                'estado_id'     => Prueba::ESTADO_ACTIVO,
            ]);
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
            'numerica'     => $this->numerica(),
            'evaluaciones' => $this->abrir ? $this->evaluaciones() : collect(),
        ]);
    }
}
