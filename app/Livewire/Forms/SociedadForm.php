<?php

namespace App\Livewire\Forms;

use App\Models\CuentaBancaria;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Sociedad;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsIBANRule;
use App\Rules\IsNifCifNieRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Sociedad es, como Comunidad, una Persona jurídica: de momento siempre CIF,
 * siempre España (igual simplificación que ComunidadForm; ver esa clase).
 */
class SociedadForm extends Form
{
    public ?Sociedad $sociedad = null;
    public ?Persona $persona = null;

    public $nombre;
    public $cif;

    public int $persona_id = 0;

    /**
     * Cada fila: id (null si no está guardada), iban, entidad_bancaria_id,
     * entidad_bancaria_texto, alias, nombre_contable (con el que se lee en el mayor;
     * solo hace falta si la sociedad lleva contabilidad, ver "Enlace contabilidad").
     */
    public array $cuentas = [];

    /**
     * ids de cuentas_bancarias que el usuario ha quitado del formulario: no se borran,
     * se desactivan (estado_id => CANCELADA) al guardar.
     */
    public array $cuentas_canceladas = [];

    public function rules()
    {
        return [
            'nombre'                             => ['required', 'string', 'max:100'],
            'cif'                                 => [
                'required', 'string', 'max:40',
                new IsNifCifNieRule(),
                Rule::unique('personas', 'documento_identificativo')->ignore($this->persona_id),
            ],
            'cuentas.*.iban'                      => ['nullable', 'string', new IsIBANRule()],
            'cuentas.*.entidad_bancaria_id'        => ['nullable', 'exists:entidades_bancarias,id', 'required_with:cuentas.*.iban'],
            'cuentas.*.alias'                      => ['nullable', 'string', 'max:100'],
            'cuentas.*.nombre_contable'             => ['nullable', 'string', 'max:150'],
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

    public function setSociedad()
    {
        $persona = $this->sociedad->persona;

        $this->persona    = $persona;
        $this->persona_id = $persona->id;
        $this->nombre     = $persona->razon_social;
        $this->cif        = $persona->documento_identificativo;

        $this->cuentas = $this->sociedad->cuentasBancarias
            ->where('estado_id', CuentaBancaria::ESTADO_ACTIVA)
            ->map(fn ($cuenta) => [
            'id'                     => $cuenta->id,
            'iban'                   => $cuenta->iban,
            'entidad_bancaria_id'    => $cuenta->entidad_bancaria_id,
            'entidad_bancaria_texto' => $cuenta->entidadBancaria
                ? $cuenta->entidadBancaria->codigo.' - '.$cuenta->entidadBancaria->descripcion
                : null,
            'alias'                  => $cuenta->alias,
            'nombre_contable'        => $cuenta->nombre_contable,
            'cuenta_contable'        => $cuenta->cuenta_contable,
        ])->all();
    }

    public function store($validated): Sociedad
    {
        return DB::transaction(function () {
            $persona  = Persona::create($this->datosPersona());
            $sociedad = Sociedad::create(['persona_id' => $persona->id]);
            $sociedad->setRelation('persona', $persona);

            $this->sociedad   = $sociedad;
            $this->persona    = $persona;
            $this->persona_id = $persona->id;

            $this->guardarCuentasBancarias();

            return $sociedad;
        });
    }

    public function update($validated): Sociedad
    {
        DB::transaction(function () {
            $this->persona->update($this->datosPersona());

            $this->guardarCuentasBancarias();
        });

        return $this->sociedad;
    }

    private function guardarCuentasBancarias(): void
    {
        if ($this->cuentas_canceladas) {
            // No se borran: se desactivan. Modelo a modelo (no ->update() masivo) para que
            // ConHistorialEstado (en CuentaBancaria) dispare y deje rastro del cambio.
            $this->sociedad->cuentasBancarias()->whereIn('id', $this->cuentas_canceladas)->get()
                ->each(fn (CuentaBancaria $cuenta) => $cuenta->update(['estado_id' => CuentaBancaria::ESTADO_CANCELADA]));
        }

        foreach ($this->cuentas as $fila) {
            if (! $fila['iban']) {
                continue;
            }

            $datosCuenta = [
                'iban'                => $fila['iban'],
                'entidad_bancaria_id' => $fila['entidad_bancaria_id'],
                'alias'               => $fila['alias'],
                'nombre_contable'     => $fila['nombre_contable'],
            ];

            if (! empty($fila['id'])) {
                $this->sociedad->cuentasBancarias()->whereKey($fila['id'])->update($datosCuenta);
            } else {
                $this->sociedad->cuentasBancarias()->create($datosCuenta);
            }
        }
    }

    public function resetForm()
    {
        $this->nombre     = '';
        $this->cif        = '';
        $this->persona_id = 0;
        $this->sociedad   = null;
        $this->persona    = null;

        $this->cuentas             = [];
        $this->cuentas_canceladas  = [];

        $this->resetValidation();
    }
}
