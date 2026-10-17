<?php

namespace App\Livewire\Directivos;

use App\Models\ComunidadDirectivo;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\TipoDirectivoComunidad;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta y modificación de un miembro de la directiva. Los directivos son personas físicas
 * mayores de edad de la comunidad (personas_comunidad), así que el alta empieza como la
 * de un propietario: primero el documento. Si ya hay alguien con él en la comunidad (un
 * propietario, por ejemplo) se reutiliza; si no, se piden sus datos. Al modificar, la
 * persona no cambia: solo el puesto y las fechas.
 */
class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public $documento_pais_id;
    public $tipo_documento_id;
    public string $documento_identificativo = '';
    public bool $documentoComprobado = false;

    /** La persona de la comunidad que ya tenía ese documento; null = persona nueva. */
    #[Locked]
    public ?int $personaComunidadId = null;

    // Persona nueva. De una que ya existía solo se pide la fecha de nacimiento, y solo si
    // no la tiene: sin ella no se puede saber si es mayor de edad.
    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public $genero_id = '';
    public string $fecha_nacimiento = '';

    public $tipo_directivo_comunidad_id = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';

    private function reglasDocumento(): array
    {
        $rules = [
            'documento_pais_id'        => ['required', 'exists:paises,id'],
            'tipo_documento_id'        => ['required', Rule::in(TipoDocumentoIdentificativo::persona_fisica()->pluck('id')->all())],
            'documento_identificativo' => ['required', 'string', 'max:30'],
        ];

        if ($this->documento_pais_id == Pais::ESPAÑA) {
            if ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                $rules['documento_identificativo'][] = new IsNifRule();
            } elseif ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                $rules['documento_identificativo'][] = new IsNieRule();
            }
        }

        return $rules;
    }

    protected function rules()
    {
        $rules = [
            'tipo_directivo_comunidad_id' => ['required', 'exists:tipo_directivo_comunidades,id'],
            'fecha_inicio'                => ['required', 'date'],
            'fecha_fin'                   => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];

        if ($this->itemId) {
            return $rules;
        }

        $persona = $this->personaExistente();

        if (! $persona) {
            $rules += $this->reglasDocumento();
            $rules['documento_identificativo'][] = Rule::unique('personas_comunidad', 'documento_identificativo')
                ->where('comunidad_id', session('comunidad_actual_id'));
            $rules['nombre']    = ['required', 'string', 'max:100'];
            $rules['apellido1'] = ['required', 'string', 'max:100'];
            $rules['apellido2'] = ['nullable', 'string', 'max:100'];
            $rules['genero_id'] = ['required', 'exists:tipo_generos,id'];
        }

        if (! $persona?->fecha_nacimiento) {
            $rules['fecha_nacimiento'] = ['required', 'date', 'before_or_equal:'.PersonaComunidad::fechaMayoriaDeEdad()];
        }

        return $rules;
    }

    protected function messages()
    {
        return [
            'required'                         => 'Debe rellenar :attribute',
            'max'                              => 'Máxima longitud de :attribute = :max',
            'tipo_documento_id.in'             => 'La directiva solo admite personas físicas',
            'documento_identificativo.unique'  => 'Ya hay una persona con ese documento en la comunidad',
            'fecha_nacimiento.before_or_equal' => 'Tiene que ser mayor de edad',
            'fecha_fin.after_or_equal'         => 'La fecha de fin no puede ser anterior a la de inicio',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'documento_pais_id'           => __('país'),
            'tipo_documento_id'           => __('tipo de documento'),
            'documento_identificativo'    => __('documento'),
            'nombre'                      => __('nombre'),
            'apellido1'                   => __('apellido 1'),
            'apellido2'                   => __('apellido 2'),
            'genero_id'                   => __('género'),
            'fecha_nacimiento'            => __('fecha de nacimiento'),
            'tipo_directivo_comunidad_id' => __('puesto'),
            'fecha_inicio'                => __('fecha de inicio'),
            'fecha_fin'                   => __('fecha de fin'),
        ];
    }

    /** Siempre se vuelve a buscar en la comunidad activa: el id solo no basta. */
    private function personaExistente(): ?PersonaComunidad
    {
        return $this->personaComunidadId
            ? PersonaComunidad::where('comunidad_id', session('comunidad_actual_id'))->find($this->personaComunidadId)
            : null;
    }

    #[On('abrir-crear-directivo')]
    public function crear()
    {
        $this->reset();
        $this->documento_pais_id = Pais::porDefecto();
        $this->tipo_documento_id = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->fecha_inicio      = now()->toDateString();
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('directivo-editar')]
    public function editar($id)
    {
        $item = ComunidadDirectivo::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->reset();
        $this->itemId                      = $item->id;
        $this->personaComunidadId          = $item->persona_comunidad_id;
        $this->documentoComprobado         = true;
        $this->tipo_directivo_comunidad_id = $item->tipo_directivo_comunidad_id;
        $this->fecha_inicio                = $item->fecha_inicio->format('Y-m-d');
        $this->fecha_fin                   = $item->fecha_fin?->format('Y-m-d') ?? '';
        $this->resetValidation();
        $this->abrir = true;
    }

    /** Primer paso del alta: ¿hay ya alguien con ese documento en la comunidad? */
    public function comprobarDocumento()
    {
        $this->documento_identificativo = mb_strtoupper(trim($this->documento_identificativo));
        $this->validate($this->reglasDocumento());

        $persona = PersonaComunidad::where('comunidad_id', session('comunidad_actual_id'))
            ->where('documento_identificativo', $this->documento_identificativo)
            ->first();

        if ($persona && TipoDocumentoIdentificativo::isTipoDocumento($persona->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA)) {
            $this->addError('documento_identificativo', __('Ese documento es de una persona jurídica: la directiva solo admite personas físicas'));

            return;
        }

        if ($persona?->fecha_nacimiento && $persona->fecha_nacimiento->toDateString() > PersonaComunidad::fechaMayoriaDeEdad()) {
            $this->addError('documento_identificativo', __('Esa persona no es mayor de edad'));

            return;
        }

        $this->personaComunidadId  = $persona?->id;
        $this->documentoComprobado = true;
    }

    /** Vuelve a pedir el documento (por si el comprobado no era el que tocaba). */
    public function cambiarDocumento()
    {
        $this->reset(['documentoComprobado', 'personaComunidadId', 'nombre', 'apellido1', 'apellido2', 'genero_id', 'fecha_nacimiento']);
        $this->resetValidation();
    }

    public function guardar()
    {
        if (! $this->documentoComprobado) {
            $this->comprobarDocumento();

            return;
        }

        $this->validate();
        $comunidadId = session('comunidad_actual_id');
        $datos = [
            'tipo_directivo_comunidad_id' => $this->tipo_directivo_comunidad_id,
            'fecha_inicio'                => $this->fecha_inicio,
            'fecha_fin'                   => $this->fecha_fin ?: null,
        ];

        DB::transaction(function () use ($comunidadId, $datos) {
            if ($this->itemId) {
                ComunidadDirectivo::where('comunidad_id', $comunidadId)->findOrFail($this->itemId)->update($datos);

                return;
            }

            $persona = $this->personaExistente();

            if (! $persona) {
                $persona = PersonaComunidad::create([
                    'comunidad_id'             => $comunidadId,
                    'nombre'                   => $this->nombre,
                    'apellido1'                => $this->apellido1,
                    'apellido2'                => $this->apellido2 ?: null,
                    'documento_pais_id'        => $this->documento_pais_id,
                    'tipo_documento_id'        => $this->tipo_documento_id,
                    'documento_identificativo' => $this->documento_identificativo,
                    'fecha_nacimiento'         => $this->fecha_nacimiento,
                    'genero_id'                => $this->genero_id,
                ]);
            } elseif (! $persona->fecha_nacimiento) {
                $persona->update(['fecha_nacimiento' => $this->fecha_nacimiento]);
            }

            ComunidadDirectivo::create($datos + ['comunidad_id' => $comunidadId, 'persona_comunidad_id' => $persona->id]);
        });

        $this->dispatch('toast-success', ['title' => $this->itemId ? __('Directivo modificado') : __('Directivo creado')]);

        $this->dispatch('directivo-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        // Al modificar se deja también el puesto que ya tenía, aunque esté dado de baja.
        $puestos = TipoDirectivoComunidad::activo()
            ->when($this->tipo_directivo_comunidad_id, fn ($q) => $q->orWhere('id', $this->tipo_directivo_comunidad_id))
            ->orderBy('id')
            ->pluck('descripcion', 'id');

        return view('livewire.directivos.formulario', [
            'persona'        => $this->personaExistente(),
            'puestos'        => $puestos,
            'paises'         => Pais::activo()->ordenGrupo()->get(),
            'tiposDocumento' => TipoDocumentoIdentificativo::persona_fisica()->get(),
            'generos'        => TipoGenero::orderBy('nombre')->get(),
        ]);
    }
}
