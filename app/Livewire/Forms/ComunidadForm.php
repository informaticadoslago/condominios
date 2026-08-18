<?php

namespace App\Livewire\Forms;

use App\Models\Comunidad;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifComunidadRule;
use App\Rules\IsIBANRule;
use App\Services\Comunidades\EnlaceContableComunidad;
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
    public ?string $correo_contacto = null;

    public int $persona_id = 0;

    // --- Datos financieros (cuenta bancaria propia + acreedor SEPA para remesas) ---
    public ?string $iban = null;
    public ?int $entidad_bancaria_id = null;
    public ?string $entidad_bancaria_texto = null;
    // Nombre de la cuenta en el plan contable; solo lo usa la comunidad que lleva
    // contabilidad, y es el que estrena su subcuenta de tesorería.
    public ?string $nombre_contable = null;

    /**
     * Cuenta bancaria a la que le han cambiado el nombre contable teniendo ya subcuenta.
     * El Form no pregunta nada: solo lo deja anotado para que el componente lance la
     * confirmación, porque en el plan de cuentas manda el contable.
     */
    public ?int $renombrar_cuenta_bancaria_id = null;
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
            'correo_contacto'             => ['nullable', 'email', 'max:150'],
            'iban'                        => ['nullable', 'string', new IsIBANRule()],
            'entidad_bancaria_id'         => ['nullable', 'exists:entidades_bancarias,id', 'required_with:iban'],
            'nombre_contable'             => ['nullable', 'string', 'max:150'],
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
        $this->correo_contacto             = $this->comunidad->correo_contacto;

        $cuenta = $this->comunidad->cuentasBancarias->first();
        $this->iban                   = $cuenta?->iban;
        $this->nombre_contable        = $cuenta?->nombre_contable;
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
                'correo_contacto'             => $this->correo_contacto,
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
                'correo_contacto'             => $this->correo_contacto,
            ]);

            $this->guardarCuentaBancaria();
        });

        return $this->comunidad;
    }

    private function guardarCuentaBancaria(): void
    {
        $this->renombrar_cuenta_bancaria_id = null;

        if (! $this->iban) {
            return;
        }

        $datosCuenta = [
            'iban'                => $this->iban,
            'entidad_bancaria_id' => $this->entidad_bancaria_id,
            'nombre_contable'     => $this->nombre_contable,
        ];

        $cuenta = $this->comunidad->cuentasBancarias->first();

        if ($cuenta) {
            // Se compara antes de guardar: después ya no se sabría con qué nombre nació
            // la subcuenta.
            if ($cuenta->cuenta_contable && $this->nombre_contable && $cuenta->nombre_contable !== $this->nombre_contable) {
                $this->renombrar_cuenta_bancaria_id = $cuenta->id;
            }

            $cuenta->update($datosCuenta);
        } else {
            $cuenta = $this->comunidad->cuentasBancarias()->create($datosCuenta);
        }

        // Si la comunidad lleva contabilidad, la cuenta estrena aquí su subcuenta de
        // bancos: es la contrapartida de los recibos que se cobren. Si no la lleva, o
        // todavía no tiene nombre contable, esto no hace nada.
        $cuenta->setRelation('titular', $this->comunidad);
        app(EnlaceContableComunidad::class)->asignarCuentaBancaria($cuenta);
    }

    public function resetForm()
    {
        $this->nombre           = '';
        $this->cif              = '';
        $this->correo_contacto  = null;
        $this->persona_id       = 0;
        $this->comunidad        = null;
        $this->persona          = null;

        $this->iban                        = null;
        $this->entidad_bancaria_id         = null;
        $this->entidad_bancaria_texto      = null;
        $this->nombre_contable             = null;
        $this->sufijo                      = '000';
        $this->identificador_acreedor_sepa = null;

        $this->renombrar_cuenta_bancaria_id = null;

        $this->resetValidation();
    }
}
