<?php

namespace App\Livewire\Forms;

use App\Models\Comunidad;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifComunidadRule;
use App\Rules\IsIBANRule;
use Illuminate\Support\Facades\DB;
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

    // --- Datos financieros (cuenta bancaria propia + acreedor SEPA para remesas) ---
    public ?string $iban = null;
    public ?int $entidad_bancaria_id = null;
    public ?string $entidad_bancaria_texto = null;
    public ?string $sufijo = '000';
    public ?string $identificador_acreedor_sepa = null;

    public function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'cif'    => [
                'required', 'string', 'max:40',
                new IsCifComunidadRule(),
                Rule::unique('personas', 'documento_identificativo')->ignore($this->persona_id),
            ],
            'iban'                        => ['nullable', 'string', new IsIBANRule()],
            'entidad_bancaria_id'         => ['nullable', 'exists:entidades_bancarias,id', 'required_with:iban'],
            'sufijo'                      => ['required', 'digits:3'],
            'identificador_acreedor_sepa' => ['nullable', 'string', 'max:35'],
        ];
    }

    public function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima :attribute = :max',
            'unique'   => 'Ese CIF ya pertenece a otra persona registrada en el sistema.',
            'digits'   => ':attribute debe tener :digits dígitos',
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

        $this->sufijo                      = $this->comunidad->sufijo;
        $this->identificador_acreedor_sepa = $this->comunidad->identificador_acreedor_sepa;

        $cuenta = $this->comunidad->cuentasBancarias->first();
        $this->iban                   = $cuenta?->iban;
        $this->entidad_bancaria_id    = $cuenta?->entidad_bancaria_id;
        $this->entidad_bancaria_texto = $cuenta?->entidadBancaria
            ? $cuenta->entidadBancaria->codigo.' - '.$cuenta->entidadBancaria->descripcion
            : null;
    }

    public function store($validated): Comunidad
    {
        return DB::transaction(function () {
            $persona   = Persona::create($this->datosPersona());
            $comunidad = Comunidad::create([
                'persona_id'                  => $persona->id,
                'sufijo'                      => $this->sufijo,
                'identificador_acreedor_sepa' => $this->identificador_acreedor_sepa,
            ]);
            $comunidad->setRelation('persona', $persona);

            $this->comunidad  = $comunidad;
            $this->persona    = $persona;
            $this->persona_id = $persona->id;

            $this->guardarCuentaBancaria();

            return $comunidad;
        });
    }

    public function update($validated): Comunidad
    {
        DB::transaction(function () {
            $this->persona->update($this->datosPersona());
            $this->comunidad->update([
                'sufijo'                      => $this->sufijo,
                'identificador_acreedor_sepa' => $this->identificador_acreedor_sepa,
            ]);

            $this->guardarCuentaBancaria();
        });

        return $this->comunidad;
    }

    private function guardarCuentaBancaria(): void
    {
        if (! $this->iban) {
            return;
        }

        $datosCuenta = [
            'iban'                => $this->iban,
            'entidad_bancaria_id' => $this->entidad_bancaria_id,
        ];

        $cuenta = $this->comunidad->cuentasBancarias->first();

        $cuenta ? $cuenta->update($datosCuenta) : $this->comunidad->cuentasBancarias()->create($datosCuenta);
    }

    public function resetForm()
    {
        $this->nombre      = '';
        $this->cif         = '';
        $this->persona_id  = 0;
        $this->comunidad   = null;
        $this->persona     = null;

        $this->iban                        = null;
        $this->entidad_bancaria_id         = null;
        $this->entidad_bancaria_texto      = null;
        $this->sufijo                      = '000';
        $this->identificador_acreedor_sepa = null;

        $this->resetValidation();
    }
}
