<?php

namespace App\Livewire\Forms;

use App\Models\Comunidad;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifComunidadRule;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Comunidad es, como Empresa, una Persona jurídica: siempre CIF, siempre España.
 * A diferencia de Proveedor/Empresa, aquí no se elige tipo de documento ni país
 * (no aplica: una comunidad de propietarios española es siempre CIF-España), así
 * que el formulario no enseña esos selectores aunque por debajo se guarde igual.
 */
class ComunidadForm extends Form
{
    public ?Comunidad $comunidad = null;
    public ?Persona $persona = null;

    public $nombre;
    public $cif;

    public int $persona_id = 0;

    public function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'cif'    => [
                'required', 'string', 'max:40',
                new IsCifComunidadRule(),
                Rule::unique('personas', 'documento_identificativo')->ignore($this->persona_id),
            ],
        ];
    }

    public function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima :attribute = :max',
            'unique'   => 'Ese CIF ya pertenece a otra persona registrada en el sistema.',
        ];
    }

    private function datosPersona(): array
    {
        return [
            'nombre'                   => '', // personas.nombre es NOT NULL; jurídica lo deja vacío (usa razon_social)
            'apellido1'                => null,
            'apellido2'                => null,
            'razon_social'             => $this->nombre,
            'nombre_comercial'         => null,
            'documento_pais_id'        => Pais::ESPAÑA,
            'tipo_documento_id'        => TipoDocumentoIdentificativo::DOCUMENTO_CIF,
            'documento_identificativo' => $this->cif,
            'fecha_nacimiento'         => null,
            'genero_id'                => TipoGenero::GENERO_OTRO,
        ];
    }

    public function setComunidad()
    {
        $persona = $this->comunidad->persona;

        $this->persona    = $persona;
        $this->persona_id = $persona->id;
        $this->nombre     = $persona->razon_social;
        $this->cif        = $persona->documento_identificativo;
    }

    public function store($validated): Comunidad
    {
        $persona   = Persona::create($this->datosPersona());
        $comunidad = Comunidad::create(['persona_id' => $persona->id]);
        $comunidad->setRelation('persona', $persona);

        $this->comunidad  = $comunidad;
        $this->persona    = $persona;
        $this->persona_id = $persona->id;

        return $comunidad;
    }

    public function update($validated): Comunidad
    {
        $this->persona->update($this->datosPersona());

        return $this->comunidad;
    }

    public function resetForm()
    {
        $this->nombre      = '';
        $this->cif         = '';
        $this->persona_id  = 0;
        $this->comunidad   = null;
        $this->persona     = null;

        $this->resetValidation();
    }
}
