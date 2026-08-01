<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Rules\ProhibidoSi;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class DatosStep extends CrearPropietarioStep
{
    // Alta: null durante todo el wizard. Edición: ya viene puesto desde la ruta.
    #[Locked]
    public ?int $propietarioId = null;

    // Fijada por sesión (ver Propietarios\Formulario), nunca por el cliente.
    #[Locked]
    public $comunidad_id;

    public $razon_social;
    public $nombre_comercial;
    public ?string $nombre = null;
    public $apellido1;
    public $apellido2;
    public $tipo_documento_id = null;
    public $documento_pais_id;
    public $documento_identificativo;
    public $fecha_nacimiento;
    public $genero_id;

    public int $persona_comunidad_id = 0;
    public bool $es_tipo_documento_cif = false;

    // Alta: primero se comprueba el documento. Si la persona ya existe en esta
    // comunidad (y no es propietario todavía) se reutiliza tal cual; si no existe,
    // se piden sus datos.
    public bool $documentoComprobado = false;
    public bool $personaExistente    = false;

    public function stepInfo(): array
    {
        return ['label' => __('Datos fiscales')];
    }

    public function mount()
    {
        if ($this->propietarioId && ! $this->documento_identificativo) {
            $persona = Propietario::find($this->propietarioId)?->persona;

            if ($persona) {
                $this->comunidad_id             = $persona->comunidad_id;
                $this->persona_comunidad_id     = $persona->id;
                $this->nombre                   = $persona->nombre;
                $this->apellido1                = $persona->apellido1;
                $this->apellido2                = $persona->apellido2;
                $this->razon_social             = $persona->razon_social;
                $this->nombre_comercial         = $persona->nombre_comercial;
                $this->tipo_documento_id        = $persona->tipo_documento_id;
                $this->documento_pais_id        = $persona->documento_pais_id;
                $this->documento_identificativo = $persona->documento_identificativo;
                $this->fecha_nacimiento         = $persona->fecha_nacimiento?->format('Y-m-d');
                $this->genero_id                = $persona->genero_id;
                $this->es_tipo_documento_cif    = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);
                $this->documentoComprobado      = true;
            }
        }

        if (! $this->documentoComprobado && ! $this->tipo_documento_id) {
            $this->tipo_documento_id = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
            $this->documento_pais_id = Pais::porDefecto();
        }
    }

    public function updatedTipoDocumentoId($value)
    {
        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($value, TipoDocumentoIdentificativo::TIPO_JURIDICA);
        if ($this->es_tipo_documento_cif) {
            $this->nombre    = '';
            $this->apellido1 = '';
            $this->apellido2 = '';
        } else {
            $this->razon_social     = '';
            $this->nombre_comercial = '';
        }
    }

    private function reglasDocumento(): array
    {
        $rules = [
            'tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
            'documento_pais_id'        => ['required', 'exists:paises,id'],
            'documento_identificativo' => ['required', 'string', 'max:40'],
        ];

        if ($this->documento_pais_id == Pais::ESPAÑA) {
            if ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                $rules['documento_identificativo'][] = new IsNifRule();
            } elseif ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                $rules['documento_identificativo'][] = new IsNieRule();
            } elseif ($this->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_CIF) {
                $rules['documento_identificativo'][] = new IsCifRule();
            }
        }

        return $rules;
    }

    /** Alta: solo hacen falta los datos completos si la persona no existía ya. Edición: siempre. */
    private function requiereDatosPersona(): bool
    {
        return (bool) $this->propietarioId || ! $this->personaExistente;
    }

    protected function rules()
    {
        $rules = $this->reglasDocumento();

        if ($this->requiereDatosPersona()) {
            $rules['nombre']           = ['required_unless:tipo_documento_id,'.TipoDocumentoIdentificativo::DOCUMENTO_CIF, 'max:100'];
            $rules['apellido1']        = ['required_with:nombre', 'max:100'];
            $rules['apellido2']        = [new ProhibidoSi(empty($this->nombre) || empty($this->apellido1)), 'max:100'];
            $rules['fecha_nacimiento'] = ['nullable', 'required_with:nombre', 'date'];
            $rules['genero_id']        = ['required_with:nombre', 'nullable', 'exists:tipo_generos,id'];
            $rules['razon_social']     = ['required_without:nombre', 'max:100'];
            $rules['nombre_comercial'] = ['nullable', 'string', 'max:100'];
        }

        $rules['documento_identificativo'][] = Rule::unique('personas_comunidad', 'documento_identificativo')
            ->where(fn ($q) => $q->where('comunidad_id', $this->comunidad_id))
            ->ignore($this->persona_comunidad_id);

        return $rules;
    }

    /** Paso 1 del alta: mira si el documento ya pertenece a una persona de esta comunidad. */
    public function comprobarDocumento()
    {
        $this->comunidad_id ??= session('comunidad_actual_id');

        $this->validate($this->reglasDocumento());

        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);

        $persona = PersonaComunidad::where('comunidad_id', $this->comunidad_id)
            ->where('documento_identificativo', $this->documento_identificativo)
            ->first();

        if ($persona) {
            if (Propietario::where('persona_comunidad_id', $persona->id)->exists()) {
                $this->addError('documento_identificativo', __('Esta persona ya está dada de alta como propietario.'));

                return;
            }

            $this->persona_comunidad_id = $persona->id;
            $this->nombre                = $persona->nombre;
            $this->apellido1             = $persona->apellido1;
            $this->apellido2             = $persona->apellido2;
            $this->razon_social          = $persona->razon_social;
            $this->nombre_comercial      = $persona->nombre_comercial;
            $this->fecha_nacimiento      = $persona->fecha_nacimiento?->format('Y-m-d');
            $this->genero_id             = $persona->genero_id;
            $this->personaExistente      = true;
        } else {
            $this->personaExistente = false;
        }

        $this->documentoComprobado = true;
    }

    /** Vuelve a pedir el documento (por si el comprobado no era el que tocaba). */
    public function cambiarDocumento()
    {
        $this->documentoComprobado  = false;
        $this->personaExistente     = false;
        $this->persona_comunidad_id = 0;
        $this->resetErrorBag();
    }

    private function datosPersona(): array
    {
        return [
            'comunidad_id'             => $this->comunidad_id,
            'nombre'                   => $this->nombre ?? '', // personas_comunidad.nombre es NOT NULL
            'apellido1'                => $this->apellido1,
            'apellido2'                => $this->apellido2,
            'razon_social'             => $this->razon_social,
            'nombre_comercial'         => $this->nombre_comercial,
            'documento_pais_id'        => $this->documento_pais_id,
            'tipo_documento_id'        => $this->tipo_documento_id,
            'documento_identificativo' => $this->documento_identificativo,
            'fecha_nacimiento'         => $this->fecha_nacimiento,
            // Las jurídicas no tienen género: por defecto "Otro".
            'genero_id'                => $this->genero_id ?: TipoGenero::GENERO_OTRO,
        ];
    }

    /**
     * "Siguiente" hace de doble botón: si el documento aún no está comprobado, lo
     * comprueba y se queda en el paso (revela el resto de campos); si ya lo está,
     * valida y guarda como cualquier otro paso.
     */
    public function submit()
    {
        if (! $this->documentoComprobado) {
            $this->comprobarDocumento();

            return;
        }

        $this->validarYGuardar();
        $this->nextStep();
    }

    protected function validarYGuardar(): void
    {
        $data = $this->validate();
        $data['comunidad_id']          = $this->comunidad_id;
        $data['persona_comunidad_id']  = $this->persona_comunidad_id;
        $data['personaExistente']      = $this->personaExistente;
        // Se guarda también el flag, no solo los campos: al reanudar un borrador, el
        // documento ya viene relleno, y sin este flag el paso "olvida" que ya se
        // comprobó y vuelve a intentarlo — si se está editando, la persona SIEMPRE
        // existe ya como propietario (es la que se está editando), así que ese
        // segundo intento fallaba con "ya está dada de alta".
        $data['documentoComprobado']   = $this->documentoComprobado;
        $data['es_tipo_documento_cif'] = $this->es_tipo_documento_cif;
        $data['datosPersona']          = $this->datosPersona();

        $borrador = $this->borradorActual();
        $payload  = $borrador?->payload ?? [];
        $payload['propietario_id'] = $this->propietarioId;
        $payload['datos']          = $data;

        if ($borrador) {
            $borrador->update(['payload' => $payload]);
        } else {
            $borrador = Borrador::create([
                'user_id' => auth()->id(),
                'tipo'    => Borrador::TIPO_PROPIETARIO,
                'payload' => $payload,
            ]);
            session([$this->claveBorrador() => $borrador->id]);
        }
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session($this->claveBorrador());

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;
    }

    public function render()
    {
        return view('livewire.propietarios.crear.steps.datos-step', [
            'paises'                       => Pais::activo()->ordenGrupo()->get(),
            'generos'                      => TipoGenero::orderBy('nombre')->get(),
            'tipoDocumentoIdentificativos' => TipoDocumentoIdentificativo::all(),
        ]);
    }
}
