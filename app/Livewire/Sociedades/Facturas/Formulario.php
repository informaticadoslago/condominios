<?php

namespace App\Livewire\Sociedades\Facturas;

use App\Exceptions\FacturaDuplicadaException;
use App\Models\Documento;
use App\Models\Pais;
use App\Models\PersonaSociedad;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Services\Facturas\AltaProveedorDesdeFacturaSociedad;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta manual de una factura de proveedor de sociedad (modal, como el resto de altas de la
 * app): sin PDF que analizar, el fichero adjunto —si lo hay— se guarda tal cual, como
 * soporte. El proveedor se resuelve por CIF/NIF/NIE al salir del campo (comprobarDocumento);
 * si existe se enseña su razón social y el foco pasa al número de factura, si no existe se
 * piden razón social/nombre comercial ahí mismo (el resto de datos, mínimos: guardar() crea
 * el proveedor con AltaProveedorDesdeFacturaSociedad, igual que hace el resto del alta).
 */
class Formulario extends Component
{
    use WithFileUploads;

    protected const TOLERANCIA_CENTIMOS = 0.01;

    public bool $abrir = false;

    public ?int $documento_pais_id = null;

    public ?int $tipo_documento_id = null;

    public string $documento = '';

    /** Documento con formato válido ya comprobado (da igual si el proveedor existe o no). */
    public bool $documentoComprobado = false;

    /** Si ya es proveedor de esta sociedad (se enseña su razón social) o hay que darlo de alta (se pide). */
    public bool $proveedorExistente = false;

    public ?string $proveedorNombre = null;

    /** Solo cuando el proveedor no existe todavía: persona jurídica (CIF). */
    public string $razon_social = '';
    public string $nombre_comercial = '';

    /** Solo cuando el proveedor no existe todavía: persona física (NIF/NIE). */
    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public ?int $genero_id = null;
    public ?string $fecha_nacimiento = null;

    /** Si el tipo de documento actual es de persona jurídica (CIF) o física (NIF/NIE/pasaporte). */
    public bool $esTipoDocumentoCif = false;

    public string $numero_factura = '';

    public ?string $fecha = null;

    public string $importe_base = '';

    /** [['tipo_iva' => '21', 'importe' => '10,50'], ...]. */
    public array $cuotas = [];

    public string $importe_total = '';

    public $fichero = null;

    protected function cuotaVacia(): array
    {
        return ['tipo_iva' => '', 'importe' => ''];
    }

    protected function messages()
    {
        return [
            'required'      => 'Debe rellenar :attribute',
            'max'           => 'Máxima longitud de :attribute = :max',
            'numeric'       => 'El importe tiene que ser un número',
            'date'          => 'La fecha no es válida',
            'fichero.mimes' => 'El fichero tiene que ser un PDF o una imagen',
            'fichero.max'   => 'El fichero no puede pasar de 10 MB',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'documento'         => __('el documento'),
            'razon_social'      => __('la razón social'),
            'numero_factura'    => __('el número de factura'),
            'fecha'             => __('la fecha'),
            'importe_base'      => __('la base imponible'),
            'importe_total'     => __('el importe total'),
            'cuotas.*.tipo_iva' => __('el % de IVA'),
            'cuotas.*.importe'  => __('el importe de la cuota'),
            'fichero'           => __('el fichero'),
        ];
    }

    #[On('abrir-nueva-factura-sociedad')]
    public function crear()
    {
        $this->documento_pais_id  = Pais::porDefecto();
        $this->tipo_documento_id  = $this->tipoPorDefecto($this->documento_pais_id);
        $this->esTipoDocumentoCif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);
        $this->documento          = '';
        $this->documentoComprobado = false;
        $this->proveedorExistente = false;
        $this->proveedorNombre    = null;
        $this->razon_social       = '';
        $this->nombre_comercial   = '';
        $this->nombre             = '';
        $this->apellido1          = '';
        $this->apellido2          = '';
        $this->genero_id          = null;
        $this->fecha_nacimiento   = null;
        $this->numero_factura     = '';
        $this->fecha              = null;
        $this->importe_base       = '';
        $this->importe_total      = '';
        $this->cuotas             = [$this->cuotaVacia()];
        $this->fichero            = null;
        $this->resetValidation();

        $this->abrir = true;
    }

    private function tipoPorDefecto(?int $paisId): int
    {
        $tipos = TipoDocumentoIdentificativo::idsPorPais($paisId);

        return in_array(TipoDocumentoIdentificativo::DOCUMENTO_CIF, $tipos, true)
            ? TipoDocumentoIdentificativo::DOCUMENTO_CIF
            : $tipos[0];
    }

    public function updatedDocumentoPaisId($valor)
    {
        $tipos = TipoDocumentoIdentificativo::idsPorPais((int) $valor);

        if (! in_array($this->tipo_documento_id, $tipos, true)) {
            $this->tipo_documento_id = $this->tipoPorDefecto((int) $valor);
        }

        $this->actualizarEsTipoDocumentoCif();
        $this->cambiarDocumento();
    }

    public function updatedTipoDocumentoId()
    {
        $this->actualizarEsTipoDocumentoCif();
    }

    private function actualizarEsTipoDocumentoCif(): void
    {
        $this->esTipoDocumentoCif = TipoDocumentoIdentificativo::isTipoDocumento($this->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA);

        if ($this->esTipoDocumentoCif) {
            $this->nombre    = '';
            $this->apellido1 = '';
            $this->apellido2 = '';
        } else {
            $this->razon_social     = '';
            $this->nombre_comercial = '';
        }
    }

    /** Se dispara al salir del campo documento (wire:blur): comprueba formato y si ya es proveedor. */
    public function comprobarDocumento()
    {
        $this->documentoComprobado = false;
        $this->proveedorExistente  = false;
        $this->proveedorNombre     = null;
        $this->resetValidation();

        if (trim($this->documento) === '') {
            return;
        }

        $reglas = ['documento' => ['required', 'string', 'max:40']];

        if ($this->documento_pais_id == Pais::ESPAÑA) {
            $reglas['documento'][] = match ((int) $this->tipo_documento_id) {
                TipoDocumentoIdentificativo::DOCUMENTO_NIF => new IsNifRule(),
                TipoDocumentoIdentificativo::DOCUMENTO_NIE => new IsNieRule(),
                TipoDocumentoIdentificativo::DOCUMENTO_CIF => new IsCifRule(),
                default                                    => 'string',
            };
        }

        $this->validate($reglas);

        $persona = PersonaSociedad::where('sociedad_id', session('sociedad_actual_id'))
            ->where('documento_identificativo', $this->documentoNormalizado())
            ->first();

        $proveedor = $persona
            ? Proveedor::where('persona_type', PersonaSociedad::class)->where('persona_id', $persona->id)->first()
            : null;

        $this->documentoComprobado = true;

        if ($proveedor) {
            $this->proveedorExistente = true;
            $this->proveedorNombre    = $persona->nombreCompleto;
            $this->dispatch('foco-numero-factura-sociedad');
        } else {
            $this->proveedorExistente = false;
            $this->dispatch($this->esTipoDocumentoCif ? 'foco-razon-social-sociedad' : 'foco-nombre-persona-sociedad');
        }
    }

    public function cambiarDocumento()
    {
        $this->documentoComprobado = false;
        $this->proveedorExistente  = false;
        $this->proveedorNombre     = null;
        $this->razon_social        = '';
        $this->nombre_comercial    = '';
        $this->nombre              = '';
        $this->apellido1           = '';
        $this->apellido2           = '';
        $this->genero_id           = null;
        $this->fecha_nacimiento    = null;
        $this->resetValidation();
    }

    public function addCuota()
    {
        $this->cuotas[] = $this->cuotaVacia();
    }

    public function quitarCuota($indice)
    {
        unset($this->cuotas[$indice]);
        $this->cuotas = array_values($this->cuotas);

        if (! $this->cuotas) {
            $this->cuotas = [$this->cuotaVacia()];
        }
    }

    /** Cuotas realmente rellenas (se ignoran filas vacías que el usuario no llegó a usar). */
    protected function cuotasRellenas(): array
    {
        return array_values(array_filter($this->cuotas, fn ($c) => trim((string) ($c['tipo_iva'] ?? '')) !== '' || trim((string) ($c['importe'] ?? '')) !== ''));
    }

    /** Total == base + suma de cuotas, con margen de céntimo por redondeo. */
    protected function cuadra(): bool
    {
        $base  = (float) str_replace(',', '.', $this->importe_base);
        $total = (float) str_replace(',', '.', $this->importe_total);

        $sumaCuotas = 0.0;
        foreach ($this->cuotasRellenas() as $cuota) {
            $sumaCuotas += (float) str_replace(',', '.', $cuota['importe'] ?? 0);
        }

        return abs($total - ($base + $sumaCuotas)) <= self::TOLERANCIA_CENTIMOS;
    }

    public function guardar()
    {
        if (! $this->documentoComprobado) {
            $this->comprobarDocumento();

            return;
        }

        $reglas = [
            'numero_factura'    => ['required', 'string', 'max:60'],
            'fecha'             => ['required', 'date'],
            'importe_base'      => ['required', 'numeric', 'min:0'],
            'importe_total'     => ['required', 'numeric', 'min:0'],
            'cuotas.*.tipo_iva' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cuotas.*.importe'  => ['nullable', 'numeric', 'min:0'],
            'fichero'           => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];

        if (! $this->proveedorExistente) {
            if ($this->esTipoDocumentoCif) {
                $reglas['razon_social']     = ['required', 'string', 'max:100'];
                $reglas['nombre_comercial'] = ['nullable', 'string', 'max:100'];
            } else {
                $reglas['nombre']            = ['required', 'string', 'max:100'];
                $reglas['apellido1']         = ['required', 'string', 'max:100'];
                $reglas['apellido2']         = ['nullable', 'string', 'max:100'];
                $reglas['genero_id']         = ['required', 'exists:tipo_generos,id'];
                $reglas['fecha_nacimiento']  = ['nullable', 'date'];
            }
        }

        $this->validate($reglas);

        if (! $this->cuadra()) {
            $this->addError('importe_total', __('El total no cuadra con la base más las cuotas de IVA.'));

            return;
        }

        $metadatosFichero = $this->fichero
            ? Documento::subirFichero($this->fichero, enBorrador: true)
            : [];

        $esAltaNueva = ! $this->proveedorExistente;

        try {
            (new AltaProveedorDesdeFacturaSociedad())->ejecutar(
                sociedadId: (int) session('sociedad_actual_id'),
                documento: $this->documentoNormalizado(),
                razonSocial: $esAltaNueva ? ($this->esTipoDocumentoCif ? trim($this->razon_social) : null) : $this->proveedorNombre,
                metadatosFichero: $metadatosFichero,
                numeroFactura: trim($this->numero_factura),
                fecha: $this->fecha,
                importeBase: $this->importe_base,
                importeTotal: $this->importe_total,
                cuotasIva: $this->cuotasRellenas(),
                documentoPaisId: $this->documento_pais_id,
                tipoDocumentoId: $this->tipo_documento_id,
                nombreComercial: $esAltaNueva && $this->esTipoDocumentoCif ? (trim($this->nombre_comercial) ?: null) : null,
                nombre: $esAltaNueva && ! $this->esTipoDocumentoCif ? trim($this->nombre) : null,
                apellido1: $esAltaNueva && ! $this->esTipoDocumentoCif ? trim($this->apellido1) : null,
                apellido2: $esAltaNueva && ! $this->esTipoDocumentoCif ? (trim($this->apellido2) ?: null) : null,
                generoId: $esAltaNueva && ! $this->esTipoDocumentoCif ? $this->genero_id : null,
                fechaNacimiento: $esAltaNueva && ! $this->esTipoDocumentoCif ? $this->fecha_nacimiento : null,
            );
        } catch (FacturaDuplicadaException $e) {
            if ($metadatosFichero) {
                Documento::disco()->delete(
                    ltrim(trim((string) $metadatosFichero['camino'], '/') . '/' . $metadatosFichero['nombrefichero'], '/')
                );
            }
            $this->addError('numero_factura', $e->getMessage());

            return;
        }

        $this->cerrar();
        $this->dispatch('toast-success', ['title' => __('Factura creada')]);
        $this->dispatch('proveedor-guardado');
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    private function documentoNormalizado(): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->documento));
    }

    public function render()
    {
        return view('livewire.sociedades.facturas.formulario', [
            'paises'  => Pais::activo()->ordenGrupo()->get(),
            'tipos'   => TipoDocumentoIdentificativo::porPais($this->documento_pais_id)->get(),
            'generos' => TipoGenero::query()->orderBy('nombre')->get(),
        ]);
    }
}
