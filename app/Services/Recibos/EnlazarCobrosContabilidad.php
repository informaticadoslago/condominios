<?php

namespace App\Services\Recibos;

use App\Models\Cobro;
use App\Models\CuentaBancaria;
use App\Models\EjercicioContable;
use App\Models\TipoComisionBancaria;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manda a la contabilidad el dinero que ha entrado por los recibos: los cobros que
 * todavía no han entrado en ningún asiento.
 *
 * Se agrupa como lo ve el banco en el extracto, que es lo que luego hay que conciliar:
 * los cobros de una remesa son un asiento —el banco abona el total de una vez— y cada
 * transferencia suelta es el suyo. Al debe la cuenta corriente por el total, al haber la
 * de cada propietario por lo suyo, que es lo que cancela la deuda que dejó la emisión.
 *
 * Una devolución es un cobro en negativo y va sola en su asiento. Lo devuelto vuelve al
 * debe del propietario con el banco de contrapartida, que es el único movimiento real
 * que hace el banco. La comisión que la comunidad decide repercutir es otra cosa: no la
 * carga el banco en ese momento (ver EnlazarComisionesBancariasContabilidad para el
 * cargo real, con su factura), así que su contrapartida es la cuenta de gastos bancarios,
 * no el banco: se debe al propietario y se abona esa cuenta de gasto, para que cuando
 * entre el cargo real de la comisión quede neteado con lo ya repercutido.
 *
 * Se puede volver a lanzar sin miedo: los cobros ya enlazados se saltan, y si aun así
 * llegara dos veces el mismo grupo, la contabilidad reconoce la referencia y devuelve el
 * asiento que ya hizo en vez de duplicarlo.
 */
final class EnlazarCobrosContabilidad
{
    public function __construct(private readonly RegistrarAsientoService $asientos)
    {
    }

    /**
     * @param  array<int|string>  $reciboIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function ejecutar(array $reciboIds): array
    {
        $cobros = Cobro::with([
            'recibo.presupuesto.comunidad.cuentasBancarias',
            'recibo.propietario',
            'recibo.inmueble',
            'formaDePago',
            'lineaRemesa.remesa.cuentaBancaria',
        ])
            ->whereIn('recibo_id', $reciboIds)
            ->whereNull('asiento_contable')
            ->get();

        $enlazables = $cobros->filter(fn (Cobro $cobro) => $this->esEnlazable($cobro));

        $enlazados = 0;

        foreach ($enlazables->groupBy(fn (Cobro $cobro) => $this->clave($cobro)) as $grupo) {
            $enlazados += $this->enlazarGrupo($grupo);
        }

        return [
            'enlazados' => $enlazados,
            'omitidos'  => $cobros->count() - $enlazados,
        ];
    }

    /**
     * Solo se enlaza lo que la contabilidad puede recibir sin dejar el saldo torcido:
     *
     * - El recibo tiene que estar emitido en contabilidad. Cobrar cancela una deuda, y si
     *   la deuda no está registrada el propietario quedaría con saldo a favor.
     * - Tiene que haber cuenta corriente con subcuenta por donde pase el dinero. El
     *   efectivo se queda fuera mientras no exista la cuenta de caja.
     *
     * Lo que no cumple se cuenta como omitido, no se rompe la tanda por ello.
     */
    private function esEnlazable(Cobro $cobro): bool
    {
        $empresaId = $cobro->recibo?->presupuesto?->comunidad?->empresa_contable_id;

        if ((float) $cobro->importe == 0
            || $cobro->recibo?->asiento_contable === null
            || $cobro->recibo?->propietario?->cuenta_contable === null
            || $empresaId === null
            || $this->cuentaTesoreria($cobro)?->cuenta_contable === null) {
            return false;
        }

        $comision = (float) ($cobro->lineaRemesa?->gastos_devolucion ?? 0);

        // Sin cuenta de gastos bancarios configurada no se puede repercutir: se omite en
        // vez de reventar la tanda entera por una comisión que no tiene dónde ir.
        if ($cobro->esDevolucion() && $comision > 0 && $this->cuentaGastosBancarios($empresaId) === null) {
            return false;
        }

        return true;
    }

    /**
     * La cuenta donde va la comisión de devolución que la comunidad repercute, y donde
     * más tarde entra el cargo real del banco (ver EnlazarComisionesBancariasContabilidad):
     * la misma que ya usa la comisión de liquidar una remesa, no una nueva.
     */
    private function cuentaGastosBancarios(int $empresaId): ?string
    {
        return TipoComisionBancaria::with('cuentaContable')
            ->where('empresa_contable_id', $empresaId)
            ->where('codigo', TipoComisionBancaria::REMESA)
            ->first()
            ?->cuentaContable
            ?->codigo;
    }

    /**
     * Dónde ha entrado el dinero: la cuenta a la que el banco abonó la remesa, y para lo
     * que viene suelto la de la comunidad.
     */
    private function cuentaTesoreria(Cobro $cobro): ?CuentaBancaria
    {
        return $cobro->lineaRemesa?->remesa?->cuentaBancaria
            ?? $cobro->recibo?->presupuesto?->comunidad?->cuentasBancarias->first();
    }

    /**
     * Un asiento por remesa y fecha de abono; los cobros sueltos, uno cada uno. Cada
     * devolución va sola: lleva su comisión y su motivo, y el banco las carga una a una.
     */
    private function clave(Cobro $cobro): string
    {
        if ($cobro->esDevolucion()) {
            return 'devolucion|'.$cobro->id;
        }

        return $cobro->lineaRemesa
            ? 'remesa|'.$cobro->lineaRemesa->remesa_id.'|'.$cobro->fecha->toDateString()
            : 'cobro|'.$cobro->id;
    }

    /** @param  Collection<int, Cobro>  $grupo */
    private function enlazarGrupo(Collection $grupo): int
    {
        $primero   = $grupo->first();
        $remesa    = $primero->lineaRemesa?->remesa;
        $empresaId = $primero->recibo->presupuesto->comunidad->empresa_contable_id;
        $fecha     = $primero->fecha->toDateString();

        if ($primero->esDevolucion()) {
            return $this->enlazarDevolucion($primero, $empresaId, $fecha);
        }

        $lineas = [];
        $total  = 0;

        foreach ($grupo as $cobro) {
            // La contabilidad trabaja en céntimos enteros; el cobro, en euros con dos
            // decimales. La conversión se hace aquí, en la frontera.
            $centimos = (int) round((float) $cobro->importe * 100);
            $total += $centimos;

            $lineas[] = new DatosApunte(
                haber: $centimos,
                cuenta: $cobro->recibo->propietario->cuenta_contable,
                concepto: trim(sprintf('%s %s', $cobro->recibo->inmueble?->planta, $cobro->recibo->inmueble?->puerta)) ?: null,
            );
        }

        $lineas[] = new DatosApunte(debe: $total, cuenta: $this->cuentaTesoreria($primero)->cuenta_contable);

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: $remesa
                ? sprintf('Cobro remesa %s', $remesa->referencia)
                : sprintf('Cobro %s · %s', $primero->formaDePago?->descripcion, $primero->recibo->presupuesto->nombre),
            lineas: $lineas,
            diario: 'BAN',
            // El hecho es el abono del banco: el de la remesa entera, o el de esa
            // transferencia. Reenviarlo devuelve el mismo asiento en vez de duplicarlo.
            referenciaTipo: $remesa ? 'remesas' : 'cobros',
            referenciaId: $remesa ? $remesa->id.':'.$fecha : (string) $primero->id,
            evento: 'cobro',
        )));

        Cobro::whereIn('id', $grupo->pluck('id'))->update(['asiento_contable' => $asiento->id]);

        return $grupo->count();
    }

    /**
     * El banco devuelve el adeudo: se deshace el cobro y el propietario vuelve a deber lo
     * suyo, más la comisión que la comunidad decide repercutirle.
     *
     * Son dos hechos distintos con contrapartidas distintas: lo devuelto es dinero real
     * que el banco mueve ahora mismo (contrapartida el banco); la comisión es un importe
     * que la comunidad se inventa, no lo que cobra el banco en este momento, así que su
     * contrapartida es la cuenta de gastos bancarios, no el banco.
     */
    private function enlazarDevolucion(Cobro $cobro, int $empresaId, string $fecha): int
    {
        $cuenta   = $cobro->recibo->propietario->cuenta_contable;
        $devuelto = (int) round(abs((float) $cobro->importe) * 100);
        $comision = (int) round((float) ($cobro->lineaRemesa?->gastos_devolucion ?? 0) * 100);

        $lineas = [
            new DatosApunte(
                debe: $devuelto,
                cuenta: $cuenta,
                concepto: trim(sprintf('%s %s', $cobro->recibo->inmueble?->planta, $cobro->recibo->inmueble?->puerta)) ?: null,
            ),
            new DatosApunte(haber: $devuelto, cuenta: $this->cuentaTesoreria($cobro)->cuenta_contable),
        ];

        if ($comision > 0) {
            $lineas[] = new DatosApunte(debe: $comision, cuenta: $cuenta, concepto: __('Comisión de devolución'));
            $lineas[] = new DatosApunte(haber: $comision, cuenta: $this->cuentaGastosBancarios($empresaId));
        }

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: trim(sprintf(
                'Devolución %s %s',
                $cobro->lineaRemesa?->remesa?->referencia,
                $cobro->lineaRemesa?->motivo_devolucion,
            )),
            lineas: $lineas,
            diario: 'BAN',
            referenciaTipo: 'cobros',
            referenciaId: (string) $cobro->id,
            evento: 'devolucion',
        )));

        $cobro->update(['asiento_contable' => $asiento->id]);

        return 1;
    }
}
