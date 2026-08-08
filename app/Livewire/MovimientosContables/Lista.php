<?php

namespace App\Livewire\MovimientosContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Livewire\Traits\ConRangoContable;
use App\Services\Contabilidad\InformeMovimientos;

/**
 * Informe de movimientos entre dos fechas: los ingresos y los gastos mes a mes, el
 * resumen de dónde se sale y dónde se llega, y la justificación del saldo final.
 *
 * Es el informe que se le da a la comunidad. El rango es libre —de mayo a julio, o a
 * caballo entre dos años—, no el ejercicio: el saldo con el que arranca sale de sumar
 * todo lo anterior, no de ningún asiento de apertura.
 *
 * Lo que decide en qué bloque cae cada cuenta es su TIPO, no su código: ingresos y
 * gastos arriba, activo y pasivo abajo justificando el saldo. Por eso el informe vale
 * igual para un plan de cuentas que no sea el de comunidades.
 *
 * Los números los saca InformeMovimientos, que es el mismo que arma el PDF: la pantalla
 * y el papel tienen que contar lo mismo.
 */
class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;
    use ConRangoContable;

    /** Las fechas no filtran filas: definen el informe entero. */
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

    public function render(InformeMovimientos $informe)
    {
        $desde = $this->desde();
        $hasta = $this->hasta();

        // Sin las dos fechas no hay informe: ni meses que pintar ni saldo del que partir.
        if (! $desde || ! $hasta || $hasta < $desde) {
            return view('livewire.movimientos-contables.lista', ['rango' => false]);
        }

        return view('livewire.movimientos-contables.lista', [
            'rango' => true,
        ] + $informe->generar($this->empresaContableActual()?->id ?? 0, $desde, $hasta));
    }
}
