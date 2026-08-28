<?php

namespace App\Livewire\Sociedades\Forms;

use App\Models\Pais;
use App\Models\PersonaSociedad;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoProveedorSociedad;
use App\Models\TipoGenero;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Rules\ProhibidoSi;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProveedorForm extends Form
{
    public ?Proveedor $proveedor = null;
    public ?PersonaSociedad $persona = null;

    public ?int $sociedad_id = null;

    // --- Datos de persona (fiscales) ---
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

    public int $persona_id = 0;

    // --- Datos del proveedor ---
    // A qué se dedica: de ahí sale la cuenta de gasto de sus facturas.
    public $tipo_proveedor_id = null;

    // Auxiliares para la vista
    public $tipo_documento_identificativos;
    public bool $es_tipo_documento_cif = false;

    // Alta: primero se comprueba el documento. Si la persona ya existe en esta
    // sociedad (y no es proveedor todavía) se reutiliza tal cual; si no existe,
    // se piden sus datos.
    public bool $documentoComprobado = false;
    public bool $personaExistente    = false;

    public function updatedTipoDocumentoId($value)
    {
        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($value, TipoDocumentoIdentificativo::TIPO_JURIDICA);
        if ($this->es_tipo_documento_cif) {
            // limpiar campos de persona física
            $this->nombre    = '';
            $this->apellido1 = '';
            $this->apellido2 = '';
        } else {
            // limpiar campos de persona jurídica
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

    /** Al editar un proveedor ya existente, o al dar de alta uno con una persona
     *  que no existía todavía, hacen falta todos los datos. Si la persona YA existía
     *  (comprobada por documento) y no era proveedor, se reutiliza tal cual. */
    private function requiereDatosPersona(): bool
    {
        return $this->proveedor?->exists || ! $this->personaExistente;
    }

    public function rules()
    {
        $rules = $this->reglasDocumento();

        if ($this->requiereDatosPersona()) {
            $rules['nombre']           = ['required_unless:tipo_documento_id,6', 'max:100'];
            $rules['apellido1']        = ['required_with:nombre', 'max:100'];
            $rules['apellido2']        = [new ProhibidoSi(empty($this->nombre) || empty($this->apellido1)), 'max:100'];
            $rules['fecha_nacimiento'] = ['nullable', 'required_with:nombre', 'date'];
            $rules['genero_id']        = ['required_with:nombre', 'nullable', 'exists:tipo_generos,id'];
            $rules['razon_social']     = ['required_without:nombre', 'max:100'];
            $rules['nombre_comercial'] = ['nullable', 'string', 'max:100'];
        }

        // Es del proveedor, no de la persona: se pide aunque la persona ya existiera.
        $rules['tipo_proveedor_id'] = ['required', 'exists:tipo_proveedores_sociedad,id'];

        $rules['documento_identificativo'][] = Rule::unique('personas_sociedad', 'documento_identificativo')
            ->where(fn ($q) => $q->where('sociedad_id', $this->sociedad_id))
            ->ignore($this->persona_id);

        return $rules;
    }

    public function messages()
    {
        return [
            'max'             => 'Máxima :attribute = :max',
            'min'             => 'Mínima :attribute = :min',
            'required'        => 'Debe rellenar :attribute',
            'required_unless' => 'Se requiere :attribute.',
            'required_with'   => 'Se requiere :attribute cuando :values tiene valor.',
        ];
    }

    /** Paso 1 del alta: mira si el documento ya pertenece a una persona de esta sociedad. */
    public function comprobarDocumento()
    {
        $this->validate($this->reglasDocumento());

        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);

        $persona = PersonaSociedad::where('sociedad_id', $this->sociedad_id)
            ->where('documento_identificativo', $this->documento_identificativo)
            ->first();

        if ($persona) {
            if (Proveedor::where('persona_type', PersonaSociedad::class)->where('persona_id', $persona->id)->exists()) {
                $this->addError('documento_identificativo', __('Esta persona ya está dada de alta como proveedor.'));

                return;
            }

            $this->persona_id            = $persona->id;
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

    /** Vuelve al paso 1 (por si el documento comprobado no era el que tocaba). */
    public function cambiarDocumento()
    {
        $this->documentoComprobado = false;
        $this->personaExistente    = false;
        $this->persona_id          = 0;
        $this->resetErrorBag();
    }

    private function datosPersona(): array
    {
        return [
            'sociedad_id'              => $this->sociedad_id,
            'nombre'                   => $this->nombre ?? '', // personas_sociedad.nombre es NOT NULL
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

    public function setProveedor()
    {
        $persona = $this->proveedor->persona;

        $this->persona                  = $persona;
        $this->persona_id               = $persona->id;
        $this->sociedad_id              = $persona->sociedad_id;
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
        $this->tipo_proveedor_id        = $this->proveedor->tipo_id;

        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);
        $this->documentoComprobado   = true;
    }

    public function store($validated): Proveedor
    {
        if ($this->personaExistente && $this->persona_id) {
            $persona = PersonaSociedad::findOrFail($this->persona_id);
        } else {
            $persona = PersonaSociedad::create($this->datosPersona());
        }

        $proveedor = Proveedor::create([
            'persona_type' => PersonaSociedad::class,
            'persona_id'   => $persona->id,
            'tipo_type'    => TipoProveedorSociedad::class,
            'tipo_id'      => $this->tipo_proveedor_id,
        ]);
        $proveedor->setRelation('persona', $persona);

        $this->proveedor  = $proveedor;
        $this->persona    = $persona;
        $this->persona_id = $persona->id;

        return $proveedor;
    }

    public function update($validated): Proveedor
    {
        $this->persona->update($this->datosPersona());
        $this->proveedor->update([
            'tipo_type' => TipoProveedorSociedad::class,
            'tipo_id'   => $this->tipo_proveedor_id,
        ]);

        return $this->proveedor;
    }

    public function resetForm()
    {
        $this->nombre                   = '';
        $this->apellido1                = '';
        $this->apellido2                = '';
        $this->razon_social             = '';
        $this->nombre_comercial         = '';
        $this->fecha_nacimiento         = null;
        $this->documento_identificativo = '';
        $this->genero_id                = null;
        $this->persona_id               = 0;
        $this->tipo_proveedor_id        = null;
        $this->proveedor                = null;
        $this->persona                  = null;
        $this->documentoComprobado      = false;
        $this->personaExistente         = false;

        $this->tipo_documento_id     = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->documento_pais_id     = Pais::porDefecto();
        $this->es_tipo_documento_cif = false;

        $this->resetValidation();
    }
}
