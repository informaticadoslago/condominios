<?php

namespace App\Livewire\MayorContable;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Livewire\Traits\ConRangoContable;
use App\Models\ApunteContable;
use App\Models\CuentaContable;
use App\Services\Contabilidad\SaldosContablesService;

/**
 * El mayor de una cuenta entre dos fechas: con qué saldo llega al primer día, cada
 * apunte con su acumulado, y con qué saldo se queda al último.
 *
 * El rango manda sobre el ejercicio —se puede pedir de mayo a julio, o a caballo entre
 * dos años—, y por eso el saldo inicial se calcula sumando todo lo anterior en vez de
 * leerlo de ninguna parte (ver SaldosContablesService).
 *
 * El orden es fijo: fecha, número de asiento y línea. Es lo que hace que el acumulado
 * signifique algo, así que aquí no se ordena por columnas como en las demás listas.
 */
class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;
    use ConRangoContable;

    public function mount(): void
    {
        $this->sort      = 'fecha';
        $this->direction = 'asc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha'         => __('Fecha'),
            'asiento'       => __('Asiento'),
            'concepto'      => __('Concepto'),
            'contrapartida' => __('Contrapartida'),
            'debe'          => __('Debe'),
            'haber'         => __('Haber'),
            'saldo'         => __('Saldo'),
        ];
    }

    /**
     * Misma defensa que la lista de asientos: si la cuenta guardada en preferencias es de
     * otra empresa contable (se cambió de empresa con el filtro puesto), se descarta en
     * vez de enseñar el mayor de una cuenta ajena.
     */
    protected function cargarPreferencias(): void
    {
        parent::cargarPreferencias();

        $empresaContableId = $this->empresaContableActual()?->id ?? 0;
        $cuentaId          = (int) ($this->filtros['cuenta_contable_id'] ?? 0);

        if ($cuentaId && ! CuentaContable::where('id', $cuentaId)->where('empresa_contable_id', $empresaContableId)->exists()) {
            $this->filtros['cuenta_contable_id'] = 0;
            $this->guardarPreferencias();
        }
    }

    /** Solo las cuentas donde se apunta: los grupos y subgrupos del PGC no tienen mayor. */
    protected function filtroCuenta(): array
    {
        $cuentas = CuentaContable::where('empresa_contable_id', $this->empresaContableActual()?->id ?? 0)
            ->whereRaw('CHAR_LENGTH(codigo) > ?', [CuentaContable::CIFRAS_AGRUPACION])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return [
            'clave'    => 'cuenta_contable_id',
            'etiqueta' => __('Cuenta'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Elige una cuenta')] + $cuentas
                ->mapWithKeys(fn ($cuenta) => [$cuenta->id => $cuenta->codigo.' - '.$cuenta->nombre])
                ->all(),
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => $query->where('apunte_contables.cuenta_contable_id', $valor),
        ];
    }

    protected function filtroDesde(): array
    {
        return [
            'clave'    => 'desde',
            'etiqueta' => __('Desde'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->whereDate('asiento_contables.fecha', '>=', $valor),
        ];
    }

    protected function filtroHasta(): array
    {
        return [
            'clave'    => 'hasta',
            'etiqueta' => __('Hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->whereDate('asiento_contables.fecha', '<=', $valor),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroCuenta(),
            $this->filtroDesde(),
            $this->filtroHasta(),
        ];
    }

    public function render()
    {
        $saldos    = app(SaldosContablesService::class);
        $cuentaId  = (int) ($this->filtros['cuenta_contable_id'] ?? 0);
        $desde     = $this->desde();
        $hasta     = $this->hasta();
        $cuenta    = $cuentaId ? CuentaContable::find($cuentaId) : null;

        // Sin cuenta no hay mayor: se pinta la pantalla vacía pidiendo que se elija una.
        if (! $cuenta) {
            return view('livewire.mayor-contable.lista', [
                'items'        => ApunteContable::whereRaw('1 = 0')->paginate($this->lineasXPagina),
                'cuenta'       => null,
                'saldoInicial' => 0,
                'sumas'        => ['debe' => 0, 'haber' => 0],
                'saldoFinal'   => 0,
            ]);
        }

        $saldoInicial = $saldos->saldoAnteriorA($cuenta->id, $desde);
        $sumas        = $saldos->sumas($cuenta->id, $desde, $hasta);

        $items = $this->aplicarFiltros(
            ApunteContable::query()
                ->join('asiento_contables', 'asiento_contables.id', '=', 'apunte_contables.asiento_contable_id')
                ->with('asientoContable.apuntesContables.cuentaContable')
                ->select('apunte_contables.*')
                // El acumulado se calcula sobre TODO el rango antes de paginar, así que la
                // segunda página sigue la cuenta donde la dejó la primera. Le falta el
                // saldo inicial, que se le suma al pintar.
                ->selectRaw('SUM(apunte_contables.debe - apunte_contables.haber) OVER ('
                    .'ORDER BY asiento_contables.fecha, asiento_contables.numero, apunte_contables.id'
                    .') AS acumulado')
        )
            ->orderBy('asiento_contables.fecha')
            ->orderBy('asiento_contables.numero')
            ->orderBy('apunte_contables.id')
            ->paginate($this->lineasXPagina);

        return view('livewire.mayor-contable.lista', [
            'items'        => $items,
            'cuenta'       => $cuenta,
            'saldoInicial' => $saldoInicial,
            'sumas'        => $sumas,
            'saldoFinal'   => $saldoInicial + $sumas['debe'] - $sumas['haber'],
        ]);
    }
}
