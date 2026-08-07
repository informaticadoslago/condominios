<?php

namespace App\Livewire\SumasYSaldos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Livewire\Traits\ConRangoContable;
use App\Services\Contabilidad\SaldosContablesService;

/**
 * Balance de sumas y saldos entre dos fechas: todas las cuentas con movimiento, con el
 * saldo con el que llegaron al primer día, lo que movieron en el periodo y cómo quedan.
 *
 * De aquí sale la justificación del saldo de un informe de movimientos: los bancos, lo
 * que debe cada propietario y lo que queda pendiente de pagar están en estas líneas.
 *
 * El total de la columna de saldos finales tiene que dar cero. Si no lo da, hay un
 * asiento descuadrado en la base de datos, porque por la API no entra ninguno.
 */
class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;
    use ConRangoContable;

    public function mount(): void
    {
        $this->sort      = 'codigo';
        $this->direction = 'asc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['codigo', 'nombre', 'saldo_inicial', 'debe', 'haber', 'saldo_final'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'codigo'        => __('Código'),
            'nombre'        => __('Cuenta'),
            'saldo_inicial' => __('Saldo anterior'),
            'debe'          => __('Debe'),
            'haber'         => __('Haber'),
            'saldo_final'   => __('Saldo'),
        ];
    }

    /**
     * Las fechas no filtran filas, reparten importes: «desde» separa el arrastre de lo
     * del periodo y «hasta» corta el balance. Por eso van con un aplicar que no toca el
     * query — el trabajo lo hacen dentro de SaldosContablesService::balance().
     */
    protected function filtroDesde(): array
    {
        return [
            'clave'    => 'desde',
            'etiqueta' => __('Desde'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query,
        ];
    }

    protected function filtroHasta(): array
    {
        return [
            'clave'    => 'hasta',
            'etiqueta' => __('Hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query,
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroDesde(),
            $this->filtroHasta(),
        ];
    }

    public function render()
    {
        $saldos            = app(SaldosContablesService::class);
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $items = $saldos->balance($empresaContableId, $this->desde(), $this->hasta())
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.sumas-y-saldos.lista', [
            'items'   => $items,
            'totales' => $saldos->totalesBalance($empresaContableId, $this->desde(), $this->hasta()),
        ]);
    }
}
