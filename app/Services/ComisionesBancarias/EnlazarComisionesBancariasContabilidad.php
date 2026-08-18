<?php

namespace App\Services\ComisionesBancarias;

use App\Models\Comunidad;
use App\Models\ComisionBancaria;
use App\Models\EjercicioContable;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Facades\DB;

/**
 * Manda a la contabilidad las comisiones bancarias que todavía no han entrado en ningún
 * asiento.
 *
 * Al debe la cuenta de gasto que le toque según su tipo (remesa o mantenimiento, ver
 * tipo_comisiones_bancarias — cada empresa tiene la suya, ya resuelta), una línea por
 * cada importe que cargó el banco (comisión e IVA por separado, tal y como los carga
 * él, para poder casarlos luego con el extracto); al haber la cuenta corriente por el
 * total.
 *
 * Se puede volver a lanzar sin miedo: las ya enlazadas se saltan, y si aun así llegara
 * dos veces la misma, la contabilidad reconoce la referencia y devuelve el asiento que
 * ya hizo en vez de duplicarlo.
 */
final class EnlazarComisionesBancariasContabilidad
{
    public function __construct(private readonly RegistrarAsientoService $asientos)
    {
    }

    /**
     * @param  array<int|string>  $comisionIds
     * @return array{enlazadas: int, omitidas: int}
     */
    public function ejecutar(array $comisionIds): array
    {
        $comisiones = ComisionBancaria::with(['cuentaBancaria.titular', 'lineas', 'tipoComisionBancaria.cuentaContable'])
            ->whereIn('id', $comisionIds)
            ->whereNull('asiento_contable')
            ->get();

        $enlazadas = 0;

        foreach ($comisiones as $comision) {
            if ($this->esEnlazable($comision)) {
                $this->enlazar($comision);
                $enlazadas++;
            }
        }

        return [
            'enlazadas' => $enlazadas,
            'omitidas'  => $comisiones->count() - $enlazadas,
        ];
    }

    private function esEnlazable(ComisionBancaria $comision): bool
    {
        return $comision->lineas->isNotEmpty()
            && $comision->cuentaBancaria?->cuenta_contable !== null
            && $this->empresaContableId($comision) !== null
            && $comision->tipoComisionBancaria?->cuentaContable?->codigo !== null;
    }

    private function empresaContableId(ComisionBancaria $comision): ?int
    {
        $titular = $comision->cuentaBancaria?->titular;

        return $titular instanceof Comunidad ? $titular->empresa_contable_id : null;
    }

    private function enlazar(ComisionBancaria $comision): void
    {
        $empresaId   = $this->empresaContableId($comision);
        $cuentaGasto = $comision->tipoComisionBancaria->cuentaContable->codigo;
        $fecha       = $comision->fecha->toDateString();

        $lineas = [];
        $total  = 0;

        foreach ($comision->lineas as $linea) {
            // La contabilidad trabaja en céntimos enteros; la línea, en euros con dos
            // decimales. La conversión se hace aquí, en la frontera.
            $centimos = (int) round((float) $linea->importe * 100);
            $total += $centimos;

            $lineas[] = new DatosApunte(debe: $centimos, cuenta: $cuentaGasto, concepto: $linea->concepto);
        }

        $lineas[] = new DatosApunte(haber: $total, cuenta: $comision->cuentaBancaria->cuenta_contable);

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: $comision->concepto ?: __('Comisión bancaria'),
            lineas: $lineas,
            diario: 'BAN',
            referenciaTipo: 'comisiones_bancarias',
            referenciaId: (string) $comision->id,
            evento: 'registro',
        )));

        $comision->update(['asiento_contable' => $asiento->id]);
    }
}
