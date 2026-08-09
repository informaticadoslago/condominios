<?php

namespace App\Livewire\Inmuebles;

use App\Livewire\ListaComponent;
use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\TipoInmueble;
use App\Models\Titularidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar inmueble?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarInmueble',
            'cancelCallback'     => 'eliminarInmuebleCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarInmueble')]
    public function ejecutarEliminar($id)
    {
        $inmueble = Inmueble::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($inmueble) {
            // Las FK son restrict: hay que soltar las relaciones antes de borrar. Ojo:
            // propietarios() solo ve las titularidades VIGENTES, así que para no dejar
            // huérfanas las cerradas (histórico), se borran todas por inmueble_id directo.
            Titularidad::where('inmueble_id', $inmueble->id)->delete();
            $inmueble->gruposDeReparto()->detach();
            $inmueble->delete();
            $this->dispatch('toast-success', ['title' => __('Inmueble eliminado')]);
        }
    }

    #[On('eliminarInmuebleCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    /**
     * Copia el inmueble (datos, propietarios y forma de pago) a un borrador de ALTA nueva
     * — nunca de edición: 'inmueble_id' se deja vacío para que Terminar cree un inmueble
     * real distinto. Planta y puerta se dejan en blanco porque son justo lo que suele
     * cambiar entre inmuebles "iguales" (mismo tipo, coeficiente...); si al final coinciden
     * con las de otro inmueble ya existente, DatosStep lo rechaza al validar.
     */
    public function duplicar($id)
    {
        $inmueble = Inmueble::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $inmueble) {
            return;
        }

        $datos = $inmueble->only(['comunidad_id', 'ocupacion_id', 'tipo_inmueble_id', 'coeficiente', 'referencia_catastral']);
        $datos['planta'] = null;
        $datos['puerta'] = null;

        $propietarios = Titularidad::vigente()
            ->where('inmueble_id', $inmueble->id)
            ->with('propietario.persona')
            ->get()
            ->values()
            ->map(fn (Titularidad $t, $i) => [
                'ref'                  => $i,
                // Sin titularidad_id: son líneas NUEVAS para el inmueble nuevo, no hay que
                // tocar la titularidad real del inmueble original.
                'titularidad_id'       => null,
                'persona_comunidad_id' => $t->propietario->persona_comunidad_id,
                'persona_nueva'        => null,
                'nombre'               => ($t->propietario->persona->documento_identificativo ?? '').' — '.$t->propietario->persona->nombreCompleto,
                'cuota_percent'        => (float) $t->cuota_percent,
                'causa'                => $t->causa,
                'fecha_inicio'         => $t->fecha_inicio?->toDateString(),
            ])->all();

        $vigentePago = $inmueble->formaPagoVigente()->with('propietario')->first();
        $financiero  = $vigentePago ? [
            'forma_de_pago_id'          => $vigentePago->forma_de_pago_id,
            'persona_comunidad_id_pago' => $vigentePago->propietario?->persona_comunidad_id,
            'cuenta_bancaria_id'        => $vigentePago->cuenta_bancaria_id,
        ] : null;

        $borrador = Borrador::create([
            'user_id' => auth()->id(),
            'tipo'    => Borrador::TIPO_INMUEBLE,
            'payload' => [
                'inmueble_id'  => null,
                'datos'        => $datos,
                'propietarios' => $propietarios,
                'financiero'   => $financiero,
            ],
        ]);

        $this->redirect(route('inmuebles.crear', ['borrador' => $borrador->id]), navigate: true);
    }

    public function confirmarDescartarBorrador($borradorId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Descartar este inmueble sin terminar?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, descartar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDescartarBorrador',
            'cancelCallback'     => 'descartarBorradorCancelado',
            'id'                 => $borradorId,
        ]);
    }

    #[On('ejecutarDescartarBorrador')]
    public function ejecutarDescartarBorrador($id)
    {
        // Nada real llega a existir en un borrador de alta (ver DatosStep/PropietariosStep):
        // descartar es solo borrar la fila, no hay ningún Inmueble/Propietario que limpiar.
        Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->whereKey($id)->delete();
    }

    #[On('descartarBorradorCancelado')]
    public function descartarBorradorCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $borradores = Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Borrador $borrador) => ($borrador->payload['datos']['comunidad_id'] ?? null) == session('comunidad_actual_id'))
            ->map(function (Borrador $borrador) {
                $datos = $borrador->payload['datos'] ?? [];
                $borrador->tipoInmuebleDescripcion = TipoInmueble::find($datos['tipo_inmueble_id'] ?? null)?->descripcion;
                $borrador->planta = $datos['planta'] ?? null;
                $borrador->puerta = $datos['puerta'] ?? null;

                return $borrador;
            })
            ->values();

        $items = $this->aplicarFiltros(
            Inmueble::with(['ocupacion', 'tipoInmueble', 'propietarios.persona', 'formaPagoVigente.formaDePago'])
                ->where('comunidad_id', session('comunidad_actual_id'))
                ->when($search, function ($q) use ($search) {
                    $q->where('puerta', 'like', "%{$search}%")
                        ->orWhere('referencia_catastral', 'like', "%{$search}%");
                })
        )
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        // Suma sobre TODOS los inmuebles de la comunidad (no solo la página o el
        // filtro actuales): lo que se comprueba es si la comunidad está
        // completamente repartida al 100%, no el resultado de la búsqueda.
        // El round a 3 es el mismo número de decimales que admite el coeficiente: evita
        // que la coma flotante deje la suma en 99.99999999 y el 100% exacto no se detecte.
        $sumaCoeficientes = round((float) Inmueble::where('comunidad_id', session('comunidad_actual_id'))->sum('coeficiente'), 3);

        return view('livewire.inmuebles.lista', compact('items', 'borradores', 'sumaCoeficientes'));
    }
}
