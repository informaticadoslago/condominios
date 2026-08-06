<?php

namespace App\Livewire\Remesas;

use App\Exceptions\RemesaNoGenerableException;
use App\Livewire\ListaComponent;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Services\Recibos\GeneradorRemesa;

/**
 * Remesas enviadas al banco. No se editan ni se borran: una remesa es constancia de un
 * envío, y una vez presentada al banco lo que pasa después son devoluciones, no
 * correcciones.
 *
 * Se crean desde aquí eligiendo el vencimiento que se cobra: GeneradorRemesa reúne los
 * recibos domiciliados pendientes de esa fecha y los pasa a Enviado.
 */
class Lista extends ListaComponent
{
    public bool $nuevaAbierta = false;

    public ?string $nuevaVencimiento = null;

    public ?string $nuevaFechaCargo = null;

    public function mount()
    {
        $this->sort      = 'fecha_cargo';
        $this->direction = 'desc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['referencia', 'fecha_cargo'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'referencia'  => __('Referencia'),
            'fecha_cargo' => __('Fecha de cargo'),
            'cuenta'      => __('Cuenta de abono'),
            'recibos'     => __('Recibos'),
            'importe'     => __('Importe'),
            'devueltos'   => __('Devueltos'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                'clave'    => 'cargo_desde',
                'etiqueta' => __('Cargo desde'),
                'tipo'     => 'fecha',
                'aplicar'  => fn ($query, $valor) => $query->where('fecha_cargo', '>=', $valor),
            ],
            [
                'clave'    => 'cargo_hasta',
                'etiqueta' => __('Cargo hasta'),
                'tipo'     => 'fecha',
                'aplicar'  => fn ($query, $valor) => $query->where('fecha_cargo', '<=', $valor),
            ],
        ];
    }

    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return Remesa::with(['cuentaBancaria'])
            ->withCount([
                'lineas',
                'lineas as lineas_devueltas_count' => fn ($q) => $q->whereNotNull('fecha_devolucion'),
            ])
            ->withSum('lineas', 'importe')
            ->where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, fn ($q) => $q->where('referencia', 'like', "%{$search}%"));
    }

    /**
     * Abre el modal proponiendo el vencimiento pendiente más antiguo: es el que toca
     * cobrar, y así no hay que ir a buscarlo a la pantalla de recibos.
     */
    public function abrirNueva(): void
    {
        $this->nuevaVencimiento = $this->primerVencimientoPendiente();
        $this->nuevaFechaCargo  = now()->addDays(3)->toDateString();
        $this->nuevaAbierta     = true;
    }

    public function generar(GeneradorRemesa $generador): void
    {
        $this->validate([
            'nuevaVencimiento' => ['required', 'date'],
            'nuevaFechaCargo'  => ['required', 'date'],
        ], attributes: [
            'nuevaVencimiento' => __('Vencimiento'),
            'nuevaFechaCargo'  => __('Fecha de cargo'),
        ]);

        $comunidad = Comunidad::find(session('comunidad_actual_id'));

        if (! $comunidad) {
            return;
        }

        try {
            $remesa = $generador->generar($comunidad, $this->nuevaVencimiento, $this->nuevaFechaCargo);
        } catch (RemesaNoGenerableException $e) {
            // Falta un mandato, o la comunidad no tiene identificador de acreedor: el
            // modal se queda abierto para poder corregir la fecha y reintentar.
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->nuevaAbierta = false;

        $this->dispatch('toast-success', [
            'title' => __('Remesa :referencia generada con :count recibos', [
                'referencia' => $remesa->referencia,
                'count'      => $remesa->lineas()->count(),
            ]),
        ]);
    }

    /**
     * El vencimiento más antiguo que todavía tiene recibos domiciliados sin cobrar y sin
     * presentar. Mismo criterio que GeneradorRemesa::recibosRemesables(), salvo el
     * mandato: aquí solo se propone una fecha, y si falta algún mandato ya lo dirá el
     * generador al intentarlo.
     */
    private function primerVencimientoPendiente(): ?string
    {
        $comunidadId = session('comunidad_actual_id');

        return Recibo::query()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', $comunidadId)->select('id'))
            ->where('forma_de_pago_id', FormaDePago::RECIBO_BANCARIO)
            ->where('saldo', '>', 0)
            ->whereDoesntHave('lineasRemesas', fn ($q) => $q->whereNull('fecha_devolucion'))
            ->min('fecha_vencimiento');
    }

    public function render()
    {
        $items = $this->aplicarFiltros($this->consultaBase())
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.remesas.lista', compact('items'));
    }
}
