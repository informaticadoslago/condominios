<?php
namespace App\Livewire\Forms;

use App\Models\Empresa;
use App\Models\EstadoEmpresa;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Rules\ProhibidoSi;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EmpresaForm extends Form
{
    public ?Empresa $empresa = null;
    public ?Persona $persona = null;

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

    // --- Datos propios de empresa ---
    public $fecha_alta;
    public $comentarios;

    // Auxiliares para la vista / parciales
    public $tipo_documento_identificativos;
    public bool $es_tipo_documento_cif = false;

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

    public function rules()
    {
        $rules = [
            'tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
            'documento_pais_id'        => ['required', 'exists:paises,id'],
            'documento_identificativo' => ['required', 'string', 'max:40'],

            'nombre'                   => ['required_unless:tipo_documento_id,6', 'max:100'],
            'apellido1'                => ['required_with:nombre', 'max:100'],
            'apellido2'                => [new ProhibidoSi(empty($this->nombre) || empty($this->apellido1)), 'max:100'],
            'fecha_nacimiento'         => ['nullable', 'required_with:nombre', 'date'],
            'genero_id'                => ['required_with:nombre', 'nullable', 'exists:tipo_generos,id'],

            'razon_social'             => ['required_without:nombre', 'max:100'],
            'nombre_comercial'         => ['nullable', 'string', 'max:100'],

            'fecha_alta'               => ['required', 'date'],
            'comentarios'              => ['nullable', 'string', 'max:1000'],
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

        $rules['documento_identificativo'][] = Rule::unique('personas')->ignore($this->persona_id);

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

    private function datosPersona(): array
    {
        return [
            'nombre'                   => $this->nombre ?? '', // personas.nombre es NOT NULL
            'apellido1'                => $this->apellido1,
            'apellido2'                => $this->apellido2,
            'razon_social'             => $this->razon_social,
            'nombre_comercial'         => $this->nombre_comercial,
            'documento_pais_id'        => $this->documento_pais_id,
            'tipo_documento_id'        => $this->tipo_documento_id,
            'documento_identificativo' => $this->documento_identificativo,
            'fecha_nacimiento'         => $this->fecha_nacimiento,
            // Las jurídicas no tienen género: por defecto "Otro" (el trigger de la BD
            // sincroniza la columna legacy 'genero' NOT NULL a partir de genero_id).
            'genero_id'                => $this->genero_id ?: TipoGenero::GENERO_OTRO,
        ];
    }

    private function datosEmpresa(int $personaId): array
    {
        // Los campos legacy (estado, fechabaja) usan el DEFAULT de la BD.
        return [
            'fechaalta'   => $this->fecha_alta,
            'persona_id'  => $personaId,
            'comentarios' => $this->comentarios,
            'estado_id'   => EstadoEmpresa::EMPRESA_ACTIVO,
        ];
    }

    public function setEmpresa()
    {
        $persona = $this->empresa->persona;

        $this->persona                  = $persona;
        $this->persona_id               = $persona->id;
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

        $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);

        $this->fecha_alta   = $this->empresa->fechaalta?->format('Y-m-d');
        $this->comentarios  = $this->empresa->comentarios;
    }

    public function store($validated): Empresa
    {
        $persona = Persona::create($this->datosPersona());
        $empresa = Empresa::create($this->datosEmpresa($persona->id));
        $empresa->setRelation('persona', $persona);

        // Dejar el form en modo edición para guardados posteriores.
        $this->empresa    = $empresa;
        $this->persona    = $persona;
        $this->persona_id = $persona->id;

        return $empresa;
    }

    public function update($validated): Empresa
    {
        $this->persona->update($this->datosPersona());

        $this->empresa->update([
            'fechaalta'   => $this->fecha_alta,
            'comentarios' => $this->comentarios,
        ]);

        return $this->empresa;
    }

    public function resetForm()
    {
        $this->fecha_alta               = date('Y-m-d');
        $this->nombre                   = '';
        $this->apellido1                = '';
        $this->apellido2                = '';
        $this->razon_social             = '';
        $this->nombre_comercial         = '';
        $this->fecha_nacimiento         = null;
        $this->documento_identificativo = '';
        $this->genero_id                = null;
        $this->comentarios              = '';
        $this->persona_id               = 0;

        $this->resetValidation();
    }
}
