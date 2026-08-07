<?php

namespace App\Livewire\Facturas;

use App\Exceptions\FacturaDuplicadaException;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta de facturas en serie, sin papel: las que llegan de una en una ya tienen la
 * importación de PDFs; esto es para cuando hay un taco encima de la mesa.
 *
 * Cada vuelta empieza en el documento del proveedor. Si es correcto pero no es de nadie,
 * se ofrece darlo de alta en el modal de proveedores de siempre; si se acepta, la vuelta
 * sigue, y si no, el cursor vuelve al documento. Con el proveedor resuelto se teclean
 * número, fecha e importe, y al añadir la factura queda guardada y empieza otra fila.
 */
class Crear extends Component
{
    public ?int $documento_pais_id = null;

    public ?int $tipo_documento_id = null;

    public string $documento = '';

    public bool $documentoValido = false;

    public ?string $proveedorNombre = null;

    public ?int $proveedorId = null;

    public string $numero_factura = '';

    public ?string $fecha = null;

    public string $importe = '';

    /** Lo metido en esta sesión de trabajo, lo último arriba. */
    public array $metidas = [];

    public function mount()
    {
        $this->documento_pais_id = Pais::porDefecto();
        $this->tipo_documento_id = $this->tipoPorDefecto($this->documento_pais_id);
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'numeric'  => 'El importe tiene que ser un número',
            'date'     => 'La fecha no es válida',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'documento'      => __('el documento'),
            'numero_factura' => __('el número de factura'),
            'fecha'          => __('la fecha'),
            'importe'        => __('el importe'),
        ];
    }

    /** Cambiar de país cambia los documentos posibles (España tiene los suyos). */
    public function updatedDocumentoPaisId($valor)
    {
        $tipos = TipoDocumentoIdentificativo::idsPorPais((int) $valor);

        if (! in_array($this->tipo_documento_id, $tipos, true)) {
            $this->tipo_documento_id = $this->tipoPorDefecto((int) $valor);
        }

        $this->cambiarDocumento();
    }

    /** CIF de salida: aquí el proveedor es una empresa casi siempre. */
    private function tipoPorDefecto(?int $paisId): int
    {
        $tipos = TipoDocumentoIdentificativo::idsPorPais($paisId);

        return in_array(TipoDocumentoIdentificativo::DOCUMENTO_CIF, $tipos, true)
            ? TipoDocumentoIdentificativo::DOCUMENTO_CIF
            : $tipos[0];
    }

    public function comprobarDocumento()
    {
        $this->documentoValido = false;
        $this->proveedorNombre = null;
        $this->proveedorId     = null;
        $this->resetValidation();

        // La letra solo se comprueba en los documentos españoles; de un NIF europeo o un
        // pasaporte no se sabe el dígito de control (misma regla que el resto de altas).
        $reglas = ['documento' => ['required', 'string', 'max:40']];

        if ($this->documento_pais_id == Pais::ESPAÑA) {
            $reglas['documento'][] = match ((int) $this->tipo_documento_id) {
                TipoDocumentoIdentificativo::DOCUMENTO_NIF => new IsNifRule(),
                TipoDocumentoIdentificativo::DOCUMENTO_NIE => new IsNieRule(),
                TipoDocumentoIdentificativo::DOCUMENTO_CIF => new IsCifRule(),
                default                                    => 'string', // pasaporte: sin dígito de control
            };
        }

        try {
            $this->validate($reglas);
        } catch (ValidationException $e) {
            $this->dispatch('foco-documento');

            throw $e;
        }

        $persona = PersonaComunidad::where('comunidad_id', session('comunidad_actual_id'))
            ->where('documento_identificativo', $this->documentoNormalizado())
            ->first();

        $proveedor = $persona
            ? Proveedor::where('persona_comunidad_id', $persona->id)->first()
            : null;

        // Documento correcto pero de nadie: se pregunta con SweetAlert y, si dice que sí,
        // se abre el alta de proveedores con el documento ya puesto. El botón de aceptar
        // nace con el foco, así que basta con darle a Enter.
        if (! $proveedor) {
            $this->dispatch('swalConfirm', [
                'title'             => __('El proveedor no existe'),
                'text'              => __('¿Desea darlo de alta?'),
                'icon'              => 'question',
                'showCancelButton'  => true,
                'focusConfirm'      => true,
                'confirmButtonColor' => '#16a34a',
                'cancelButtonColor'  => '#f1c40f',
                'confirmButtonText' => __('Sí, darlo de alta'),
                'cancelButtonText'  => __('No'),
                'confirmCallback'   => 'alta-proveedor-si',
                'cancelCallback'    => 'alta-proveedor-no',
                'id'                => null,
            ]);

            return;
        }

        $this->documentoValido = true;
        $this->proveedorId     = $proveedor->id;
        $this->proveedorNombre = $persona->razon_social ?: trim($persona->nombre.' '.$persona->apellido1);

        $this->dispatch('foco-numero');
    }

    /** Sí al «¿desea darlo de alta?»: el modal de proveedores, con el documento hecho. */
    #[On('alta-proveedor-si')]
    public function altaProveedor($id = null)
    {
        $this->dispatch(
            'abrir-crear-proveedor-con-documento',
            documento: $this->documentoNormalizado(),
            paisId: $this->documento_pais_id,
            tipoId: $this->tipo_documento_id,
        );
    }

    /** El alta terminó bien: se vuelve a comprobar, que ahora ya lo encuentra. */
    #[On('proveedor-guardado')]
    public function trasAltaProveedor()
    {
        if ($this->documento !== '') {
            $this->comprobarDocumento();
        }
    }

    /** No al «¿desea darlo de alta?», o cerró el modal: el cursor vuelve al documento. */
    #[On('alta-proveedor-no')]
    #[On('alta-proveedor-cancelada')]
    public function altaProveedorCancelada($id = null)
    {
        $this->cambiarDocumento();
    }

    public function cambiarDocumento()
    {
        $this->documentoValido = false;
        $this->proveedorNombre = null;
        $this->proveedorId     = null;
        $this->resetValidation();

        $this->dispatch('foco-documento');
    }

    /** Guarda la factura de esta fila y deja la siguiente lista para escribir. */
    public function anadir(AltaProveedorDesdeFactura $alta)
    {
        if (! $this->documentoValido) {
            $this->comprobarDocumento();

            return;
        }

        $this->validate([
            'numero_factura' => ['required', 'string', 'max:60'],
            'fecha'          => ['required', 'date'],
            'importe'        => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $alta->ejecutar(
                comunidadId: (int) session('comunidad_actual_id'),
                documento: $this->documentoNormalizado(),
                razonSocial: $this->proveedorNombre,
                metadatosFichero: [], // sin papel: la factura queda «sin soporte»
                numeroFactura: trim($this->numero_factura),
                fecha: $this->fecha,
                importe: $this->importe,
                documentoPaisId: $this->documento_pais_id,
                tipoDocumentoId: $this->tipo_documento_id,
            );
        } catch (FacturaDuplicadaException $e) {
            $this->addError('numero_factura', $e->getMessage());

            return;
        }

        // La línea se queda insertada encima y debajo nace otra vacía.
        $this->metidas[] = [
            'pais'      => Pais::find($this->documento_pais_id)?->nombre,
            'tipo'      => TipoDocumentoIdentificativo::find($this->tipo_documento_id)?->nombre,
            'proveedor' => $this->proveedorNombre,
            'documento' => $this->documentoNormalizado(),
            'numero'    => trim($this->numero_factura),
            'fecha'     => $this->fecha,
            'importe'   => (float) $this->importe,
        ];

        $this->reset(['documento', 'documentoValido', 'proveedorNombre', 'proveedorId',
            'numero_factura', 'fecha', 'importe']);
        $this->resetValidation();

        $this->dispatch('foco-documento');
    }

    public function cerrar()
    {
        return redirect()->route('facturas.index');
    }

    private function documentoNormalizado(): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->documento));
    }

    public function render()
    {
        return view('livewire.facturas.crear', [
            'paises' => Pais::activo()->ordenGrupo()->get(),
            'tipos'  => TipoDocumentoIdentificativo::porPais($this->documento_pais_id)->get(),
        ]);
    }
}
