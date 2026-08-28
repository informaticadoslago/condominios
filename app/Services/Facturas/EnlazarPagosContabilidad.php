<?php

namespace App\Services\Facturas;

use App\Models\EjercicioContable;
use App\Models\PagoFactura;
use App\Models\PersonaComunidad;
use App\Services\Comunidades\EnlaceContableComunidad;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\DatosTercero;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manda a la contabilidad los pagos de facturas que todavía no han entrado en ningún
 * asiento: al debe el acreedor, al haber la cuenta corriente de la que salió el dinero.
 *
 * Es el otro lado de EnlazarFacturasContabilidad: allí nació la deuda con el acreedor,
 * aquí se cancela. Por eso el pago solo se enlaza si su factura ya está contabilizada.
 *
 * Se puede volver a lanzar sin miedo: los pagos ya enlazados se saltan, y la referencia
 * hace que reenviar el mismo pago devuelva su asiento en vez de duplicarlo.
 */
final class EnlazarPagosContabilidad
{
    public function __construct(
        private readonly RegistrarAsientoService $asientos,
        private readonly EnlaceContableComunidad $enlace,
    ) {
    }

    /**
     * Igual que ejecutar(), pero manda todos los pagos del grupo en un único asiento: al
     * debe el acreedor de cada factura, al haber una sola línea con el total en la cuenta
     * corriente. Lo usa el pago en lote con "un único apunte bancario" marcado, porque en
     * el extracto real esas facturas salen como una sola transferencia.
     *
     * @param  array<int|string>  $pagoIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function ejecutarAgrupado(array $pagoIds): array
    {
        $pagos = PagoFactura::with([
            'cuentaBancaria',
            'factura.proveedor.persona' => fn ($query) => $query->morphWith([PersonaComunidad::class => ['comunidad']]),
        ])
            ->whereIn('id', $pagoIds)
            ->whereNull('asiento_contable')
            ->get()
            ->filter(fn (PagoFactura $pago) => $this->esEnlazable($pago));

        if ($pagos->isEmpty()) {
            return ['enlazados' => 0, 'omitidos' => count($pagoIds)];
        }

        $this->enlazarGrupo($pagos);

        return ['enlazados' => $pagos->count(), 'omitidos' => count($pagoIds) - $pagos->count()];
    }

    /**
     * @param  array<int|string>  $pagoIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function ejecutar(array $pagoIds): array
    {
        $pagos = PagoFactura::with([
            'cuentaBancaria',
            'factura.proveedor.persona' => fn ($query) => $query->morphWith([PersonaComunidad::class => ['comunidad']]),
            'factura.actividad',
        ])
            ->whereIn('id', $pagoIds)
            ->whereNull('asiento_contable')
            ->get();

        $enlazados = 0;

        foreach ($pagos as $pago) {
            if ($this->esEnlazable($pago)) {
                $this->enlazar($pago);
                $enlazados++;
            }
        }

        return [
            'enlazados' => $enlazados,
            'omitidos'  => $pagos->count() - $enlazados,
        ];
    }

    /**
     * Solo se enlaza lo que la contabilidad puede recibir: comunidad con empresa contable
     * y factura ya asentada. Una comunidad que no lleva contabilidad paga igual, solo que
     * su pago se queda en la gestión.
     */
    private function esEnlazable(PagoFactura $pago): bool
    {
        return $pago->factura?->proveedor?->persona?->comunidad?->empresa_contable_id !== null
            && $pago->factura?->asiento_contable !== null;
    }

    private function enlazar(PagoFactura $pago): void
    {
        $factura   = $pago->factura;
        $proveedor = $factura->proveedor;
        $persona   = $proveedor->persona;
        $empresaId = $persona->comunidad->empresa_contable_id;
        $fecha     = $pago->fecha->toDateString();

        // La cuenta corriente que todavía no tenga subcuenta la estrena aquí; sin ella no
        // hay dónde poner el haber. Hace falta su nombre contable, así que si no lo tiene
        // el pago se queda sin enlazar hasta que se lo pongan.
        $cuentaTesoreria = $pago->cuentaBancaria->cuenta_contable
            ?? $this->enlace->asignarCuentaBancaria($pago->cuentaBancaria);

        if (! $cuentaTesoreria) {
            return;
        }

        // La contabilidad trabaja en céntimos enteros; el pago, en euros con dos decimales.
        $centimos = (int) round((float) $pago->importe * 100);

        // El pago no tiene actividad propia: hereda la de su factura, para que la deuda
        // y su cancelación queden en el mismo proyecto.
        $proyectoId = $factura->actividad?->proyecto_contable_id;

        $lineas = [
            new DatosApunte(debe: $centimos, tercero: new DatosTercero(
                tipo: 'proveedor',
                id: (string) $proveedor->id,
                clase: 'acreedor',
                nif: $persona->documento_identificativo,
                razonSocial: $persona->razon_social ?: $persona->nombre_completo,
            ), proyecto: $proyectoId),
            new DatosApunte(haber: $centimos, cuenta: $cuentaTesoreria, proyecto: $proyectoId),
        ];

        $concepto = trim(sprintf(
            '%s%s',
            __('Pago a ').($persona->razon_social ?: $persona->nombre_completo),
            $factura->numero_factura ? ' · factura '.$factura->numero_factura : '',
        ));

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: $concepto,
            lineas: $lineas,
            diario: 'PAG',
            referenciaTipo: 'pagos_facturas',
            referenciaId: (string) $pago->id,
            evento: 'pago',
        )));

        $pago->update(['asiento_contable' => $asiento->id]);
    }

    /** @param  Collection<int, PagoFactura>  $grupo */
    private function enlazarGrupo(Collection $grupo): void
    {
        $primero   = $grupo->first();
        $factura   = $primero->factura;
        $empresaId = $factura->proveedor->persona->comunidad->empresa_contable_id;
        $fecha     = $primero->fecha->toDateString();

        // La cuenta corriente que todavía no tenga subcuenta la estrena aquí; sin ella no
        // hay dónde poner el haber. Hace falta su nombre contable, así que si no lo tiene
        // el grupo se queda sin enlazar hasta que se lo pongan.
        $cuentaTesoreria = $primero->cuentaBancaria->cuenta_contable
            ?? $this->enlace->asignarCuentaBancaria($primero->cuentaBancaria);

        if (! $cuentaTesoreria) {
            return;
        }

        $lineas = [];
        $total  = 0;

        foreach ($grupo as $pago) {
            $proveedor = $pago->factura->proveedor;
            $persona   = $proveedor->persona;
            $centimos  = (int) round((float) $pago->importe * 100);
            $total += $centimos;

            $lineas[] = new DatosApunte(debe: $centimos, tercero: new DatosTercero(
                tipo: 'proveedor',
                id: (string) $proveedor->id,
                clase: 'acreedor',
                nif: $persona->documento_identificativo,
                razonSocial: $persona->razon_social ?: $persona->nombre_completo,
            ));
        }

        $lineas[] = new DatosApunte(haber: $total, cuenta: $cuentaTesoreria);

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: __('Pago a proveedores (:n facturas)', ['n' => $grupo->count()]),
            lineas: $lineas,
            diario: 'PAG',
            // El hecho es esta transferencia concreta: reenviar el mismo grupo devuelve el
            // mismo asiento, pero un grupo distinto —aunque comparta fecha— es otro hecho.
            referenciaTipo: 'pagos_facturas_lote',
            referenciaId: $primero->id.':'.$this->huella($grupo),
            evento: 'pago',
        )));

        PagoFactura::whereIn('id', $grupo->pluck('id'))->update(['asiento_contable' => $asiento->id]);
    }

    /** Identifica el grupo por su contenido: mismos pagos → mismo hecho contable. */
    private function huella(Collection $grupo): string
    {
        return (string) crc32($grupo->pluck('id')->sort()->implode(','));
    }
}
