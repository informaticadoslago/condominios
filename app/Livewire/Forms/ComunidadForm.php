<?php

namespace App\Livewire\Forms;

use App\Models\Comunidad;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDireccion;
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

    // --- Dirección del edificio: domicilio de la persona de la comunidad (tabla direcciones) ---
    public ?string $direccion1 = null;
    public ?string $numero = null;
    public ?string $codigo_postal = null;
    public ?int $provincia_id = null;
    public ?int $municipio_id = null;

    // --- Administrador: sale en las convocatorias tal cual se teclea ---
    public ?string $administrador_nombre = null;
    public ?string $administrador_oficina_reunion = null;
    public ?string $administrador_localidad = null;

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
            'direccion1'                  => ['nullable', 'string', 'max:150'],
            'numero'                      => ['nullable', 'string', 'max:10'],
            'codigo_postal'               => ['nullable', 'string', 'max:10'],
            'provincia_id'                => ['nullable', 'exists:provincias,id'],
            'municipio_id'                => ['nullable', 'exists:municipios,id', 'required_with:provincia_id'],
            'administrador_nombre'          => ['nullable', 'string', 'max:150'],
            'administrador_oficina_reunion' => ['nullable', 'string', 'max:255'],
            'administrador_localidad'       => ['nullable', 'string', 'max:100'],
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

        $domicilio = $persona->domicilio;
        $this->direccion1    = $domicilio?->direccion1;
        $this->numero        = $domicilio?->numero;
        $this->codigo_postal = $domicilio?->codigo_postal;
        $this->provincia_id  = $domicilio?->provincia_id;
        $this->municipio_id  = $domicilio?->municipio_id;

        $this->administrador_nombre          = $this->comunidad->administrador_nombre;
        $this->administrador_oficina_reunion = $this->comunidad->administrador_oficina_reunion;
        $this->administrador_localidad       = $this->comunidad->administrador_localidad;

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
            ] + $this->datosAdministrador());
            $comunidad->setRelation('persona', $persona);

            $this->comunidad  = $comunidad;
            $this->persona    = $persona;
            $this->persona_id = $persona->id;

            $this->guardarDireccion();
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
            ] + $this->datosAdministrador());

            $this->guardarDireccion();
            $this->guardarCuentaBancaria();
        });

        return $this->comunidad;
    }

    /** Municipios de la provincia elegida, para el desplegable. */
    public function municipios()
    {
        return $this->provincia_id ? Municipio::deProvincia($this->provincia_id)->ordenaPorNombre()->get() : collect();
    }

    /**
     * Domicilio de la persona de la comunidad, como el de los propietarios (ver
     * CuentaBancariaStep). Si no se ha rellenado nada y no había, no se crea una vacía.
     */
    private function guardarDireccion(): void
    {
        $direccion = [
            'direccion1'    => $this->direccion1,
            'numero'        => $this->numero,
            'codigo_postal' => $this->codigo_postal,
            'provincia_id'  => $this->provincia_id,
            'municipio_id'  => $this->municipio_id,
        ];

        if (! array_filter($direccion) && ! $this->persona->domicilio) {
            return;
        }

        $this->persona->direcciones()->updateOrCreate(
            ['tipo_direccion_id' => TipoDireccion::idDomicilio()],
            $direccion + ['pais_id' => Pais::ESPAÑA, 'estado_id' => Estado::ESTADO_ACTIVO],
        );
        $this->persona->unsetRelation('domicilio');
    }

    private function datosAdministrador(): array
    {
        return [
            'administrador_nombre'          => $this->administrador_nombre,
            'administrador_oficina_reunion' => $this->administrador_oficina_reunion,
            'administrador_localidad'       => $this->administrador_localidad,
        ];
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

        $this->direccion1    = null;
        $this->numero        = null;
        $this->codigo_postal = null;
        $this->provincia_id  = null;
        $this->municipio_id  = null;

        $this->administrador_nombre          = null;
        $this->administrador_oficina_reunion = null;
        $this->administrador_localidad       = null;
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
