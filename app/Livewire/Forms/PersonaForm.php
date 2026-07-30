<?php
namespace App\Livewire\Forms;

use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Rules\IsCifRule;
use App\Rules\IsMayorEdad;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Models\TipoGenero;
use App\Rules\ProhibidoSi;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PersonaForm extends Form
{
    public ?Persona $persona;

    public $fecha_alta;
    public $fecha_nacimiento;
    public ?string $razon_social;
    public ?string $nombre_comercial;
    public ?string $nombre = null;
    public ?string $apellido1;
    public ?string $apellido2;

    #[Validate]
    public ?int $tipo_documento_id = null;

    public $documento_pais_id;

    #[Validate]
    public ?string $documento_identificativo = null;
    public $genero_id;

    public int $persona_id = 0;

    // public ?string $correo_e;
    public ?string $alergias_alimentos;
    public ?string $observaciones;

    public $tipo_documento_identificativos;
    public $paises;
    public bool $es_tipo_documento_cif = false;

    /** Algunos formularios (usuarios, profesores) solo admiten persona física. */
    public bool $soloDocumentosFisica = false;

    /** Algunos formularios (socios, usuarios, profesores) exigen que la persona sea mayor de edad. */
    public bool $exigeMayorEdad = false;

    // Al cambiar el país, recalcular los tipos de documento permitidos por su grupo.
    public function updatedDocumentoPaisId()
    {
        $this->refrescarTiposDocumento();
    }

    /**
     * Refresca la lista de tipos de documento permitidos según el grupo del país
     * elegido (y, si procede, solo persona física). Si el tipo actual deja de
     * estar permitido, selecciona el primero disponible. Reutilizable: lo llaman
     * todos los formularios con documento+país. La regla está en
     * TipoDocumentoIdentificativo::idsPorGrupoPais().
     */
    public function refrescarTiposDocumento(): void
    {
        // Sin país aún → asumimos España (país por defecto de la app).
        // Sin país aún → asumimos el país por defecto (configurable).
        $paisId = $this->documento_pais_id ?: Pais::porDefecto();

        $query = TipoDocumentoIdentificativo::porPais($paisId);
        if ($this->soloDocumentosFisica) {
            $query->persona_fisica();
        }
        $this->tipo_documento_identificativos = $query->get();

        $permitidos = $this->tipo_documento_identificativos->pluck('id');
        if ($permitidos->isNotEmpty() && ! $permitidos->contains($this->tipo_documento_id)) {
            $this->tipo_documento_id     = $permitidos->first();
            $this->es_tipo_documento_cif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);
        }
    }

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
            //'fecha_alta'               => ['required', 'date'],
            'tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
            'documento_pais_id'        => ['required', 'exists:paises,id'],
            'documento_identificativo' => ['required', 'string', 'max:40'],

            'nombre'                   => ['required_unless:tipo_documento_id,6', 'max:100'],
            'apellido1'                => ['required_with:nombre', 'max:100'],
            'apellido2'                => [new ProhibidoSi(empty($this->nombre) || empty($this->apellido1)), 'max:100'],
            // Toda persona tiene fecha: nacimiento (física) o creación (jurídica).
            'fecha_nacimiento'         => ['required', 'date', 'before_or_equal:today'],
            // El género solo aplica a personas físicas (la jurídica no tiene).
            'genero_id'                => $this->es_tipo_documento_cif ? ['nullable'] : ['required', 'exists:tipo_generos,id'],

            'razon_social'             => ['required_without:nombre', 'max:100'],
            'nombre_comercial'         => ['nullable', 'string', 'max:100'],
            'alergias_alimentos'       => ['nullable', 'string', 'max:500'],
            'observaciones'            => ['nullable', 'string', 'max:500'],
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

        //$rules['fecha_nacimiento'] = ['date', 'before_or_equal:' . $fechaminima];

        $rules['documento_identificativo'][] = Rule::unique('personas')->ignore($this->persona_id);

        // Socios, profesores y usuarios deben ser mayores de edad (los alumnos no). En la
        // persona jurídica la fecha no es de nacimiento sino de constitución: no aplica.
        if ($this->exigeMayorEdad && ! $this->es_tipo_documento_cif) {
            $rules['fecha_nacimiento'][] = new IsMayorEdad();
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'max'                              => 'Máxima :attribute = :max',
            'min'                              => 'Mínima :attribute = :min',
            'required'                         => 'Debe rellenar :attribute',
            'required_unless'                  => 'Se requiere :attribute.',
            'required_with'                    => 'Se requiere :attribute cuando :values tiene valor.',
            'before_or_equal'                  => 'La fecha no puede ser futura.',
            'documento_identificativo.unique'  => 'Ese documento ya pertenece a otra persona registrada en el sistema.',
        ];

    }

    public function setPersona()
    {
        $this->persona_id               = $this->persona->id;
        $this->fecha_alta               = $this->persona->fecha_alta;
        // Formato Y-m-d para el <input type="date"> (el cast 'date' devuelve un Carbon).
        $this->fecha_nacimiento         = $this->persona->fecha_nacimiento?->format('Y-m-d');
        $this->razon_social             = $this->persona->razon_social;
        $this->nombre_comercial         = $this->persona->nombre_comercial;
        $this->nombre                   = $this->persona->nombre;
        $this->apellido1                = $this->persona->apellido1;
        $this->apellido2                = $this->persona->apellido2;
        $this->tipo_documento_id        = $this->persona->tipo_documento_id;
        $this->documento_pais_id        = $this->persona->documento_pais_id;
        $this->documento_identificativo = $this->persona->documento_identificativo;
        $this->genero_id                = $this->persona->genero_id;
        $this->observaciones            = $this->persona->comentarios;
    }

    public function store($validated): Persona
    {
        $validated = $this->generoJuridica($validated);
        $this->persona = Persona::create($validated);

        return $this->persona;
    }

    public function update($validated): Persona
    {
        $validated = $this->generoJuridica($validated);
        $this->persona->update($validated);
        return $this->persona;
    }

    /** La persona jurídica no tiene género: se le asigna "Otro". */
    private function generoJuridica(array $validated): array
    {
        if ($this->es_tipo_documento_cif) {
            $validated['genero_id'] = TipoGenero::GENERO_OTRO;
        }

        return $validated;
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
        $this->alergias_alimentos       = '';
        $this->observaciones            = '';

        $this->resetValidation();
    }

}
