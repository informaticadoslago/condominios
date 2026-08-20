<?php

namespace App\Services\Recibos;

use App\Models\Cobro;
use App\Models\Comunidad;
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
 * los cobros de una remesa son un asiento —el banco abona el total de una vez—, y los
 * sueltos de un mismo propietario el mismo día también, sea uno o varios recibos a la
 * vez. Al debe la cuenta corriente por el total, al haber la de cada propietario por lo
 * suyo, que es lo que cancela la deuda que dejó la emisión.
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
 * llegara dos veces el mismo grupo exacto, la contabilidad reconoce la referencia y
 * devuelve el asiento que ya hizo en vez de duplicarlo. La referencia lleva metida una
 * huella del contenido real del grupo (qué cobros son, no solo de qué remesa/propietario
 * y fecha) para que esto sea verdad también con grupos parciales: enlazar hoy 2 cobros de
 * un propietario y mañana otro más del mismo día son dos hechos distintos, y cada uno
 * saca su propio asiento en vez de intentar completar uno ya escrito.
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
        // El sobrante de un pago no tiene recibo, así que no lo pilla el whereIn de
        // abajo: se busca aparte, por si algún propietario de estos recibos tiene alguno
        // suelto sin enlazar todavía.
        $propietarioIds = Cobro::whereIn('recibo_id', $reciboIds)
            ->join('recibos', 'recibos.id', '=', 'cobros.recibo_id')
            ->pluck('recibos.propietario_id')
            ->unique();

        $cobros = Cobro::with([
            'recibo.presupuesto.comunidad.cuentasBancarias',
            'recibo.propietario',
            'recibo.inmueble',
            'propietario.persona.comunidad.cuentasBancarias',
            'formaDePago',
            'lineaRemesa.remesa.cuentaBancaria',
        ])
            ->whereNull('asiento_contable')
            ->where(function ($query) use ($reciboIds, $propietarioIds) {
                $query->whereIn('recibo_id', $reciboIds);

                if ($propietarioIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($propietarioIds) {
                        $query->whereNull('recibo_id')->whereIn('propietario_id', $propietarioIds);
                    });
                }
            })
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
        $empresaId = $this->comunidad($cobro)?->empresa_contable_id;

        if ((float) $cobro->importe == 0
            || $empresaId === null
            || $this->cuentaContablePropietario($cobro) === null
            || $this->cuentaTesoreria($cobro)?->cuenta_contable === null) {
            return false;
        }

        // El sobrante no cancela la deuda de ningún recibo —es dinero suelto a favor del
        // propietario—, así que no le aplica esta comprobación: solo el cobro de un
        // recibo necesita que ese recibo esté ya emitido en contabilidad.
        if ($cobro->recibo_id !== null && $cobro->recibo?->asiento_contable === null) {
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
            ?? $this->comunidad($cobro)?->cuentasBancarias->first();
    }

    /**
     * La comunidad del cobro: por el recibo normalmente, y por el propietario cuando no
     * hay recibo —el sobrante—, que llega a la misma comunidad por su persona.
     */
    private function comunidad(Cobro $cobro): ?Comunidad
    {
        return $cobro->recibo?->presupuesto?->comunidad
            ?? $cobro->propietario?->persona?->comunidad;
    }

    /** La subcuenta de cliente del propietario, venga el cobro de un recibo o suelto. */
    private function cuentaContablePropietario(Cobro $cobro): ?string
    {
        return $cobro->recibo?->propietario?->cuenta_contable
            ?? $cobro->propietario?->cuenta_contable;
    }

    /**
     * Un asiento por remesa y fecha de abono; los cobros sueltos de un mismo propietario
     * el mismo día también van juntos —sea un recibo o varios cobrados de golpe, es el
     * mismo dinero entrando esa fecha en su cuenta—. Cada devolución va sola: lleva su
     * comisión y su motivo, y el banco las carga una a una.
     */
    private function clave(Cobro $cobro): string
    {
        if ($cobro->esDevolucion()) {
            return 'devolucion|'.$cobro->id;
        }

        if ($cobro->lineaRemesa) {
            return 'remesa|'.$cobro->lineaRemesa->remesa_id.'|'.$cobro->fecha->toDateString();
        }

        $propietarioId = $cobro->recibo?->propietario_id ?? $cobro->propietario_id;

        return 'cobro|'.$propietarioId.'|'.$cobro->fecha->toDateString();
    }

    /** @param  Collection<int, Cobro>  $grupo */
    private function enlazarGrupo(Collection $grupo): int
    {
        $primero = $grupo->first();
        $remesa  = $primero->lineaRemesa?->remesa;

        // El de referencia para lo que no lleva el sobrante (recibo, presupuesto):
        // cualquier cobro del grupo que sí venga de un recibo. Solo faltará si el grupo
        // es el sobrante solo, sin ningún recibo cobrado a su lado.
        $conRecibo = $grupo->first(fn (Cobro $cobro) => $cobro->recibo_id !== null) ?? $primero;

        $empresaId = $this->comunidad($primero)?->empresa_contable_id;
        $fecha     = $primero->fecha->toDateString();
        $propietarioId = $conRecibo->recibo?->propietario_id ?? $primero->propietario_id;

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
                cuenta: $this->cuentaContablePropietario($cobro),
                concepto: $cobro->recibo
                    ? (trim(sprintf('%s %s', $cobro->recibo->inmueble?->planta, $cobro->recibo->inmueble?->puerta)) ?: null)
                    : __('Sobrante a cuenta'),
            );
        }

        $lineas[] = new DatosApunte(debe: $total, cuenta: $this->cuentaTesoreria($primero)->cuenta_contable);

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: $remesa
                ? sprintf('Cobro remesa %s', $remesa->referencia)
                : sprintf('Cobro %s · %s', $primero->formaDePago?->descripcion, $conRecibo->recibo?->presupuesto?->nombre ?? __('saldo a favor')),
            lineas: $lineas,
            diario: 'BAN',
            // El hecho es el abono del banco de ESTOS cobros: reenviar el mismo grupo
            // devuelve el mismo asiento, pero un grupo distinto —aunque comparta remesa o
            // propietario y fecha— es un hecho distinto y saca un asiento propio.
            referenciaTipo: $remesa ? 'remesas' : 'cobros',
            referenciaId: ($remesa ? $remesa->id.':'.$fecha : $propietarioId.':'.$fecha).':'.$this->huella($grupo),
            evento: 'cobro',
        )));

        Cobro::whereIn('id', $grupo->pluck('id'))->update(['asiento_contable' => $asiento->id]);

        return $grupo->count();
    }

    /** Identifica el grupo por su contenido: mismos cobros → mismo hecho contable. */
    private function huella(Collection $grupo): string
    {
        return (string) crc32($grupo->pluck('id')->sort()->implode(','));
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
