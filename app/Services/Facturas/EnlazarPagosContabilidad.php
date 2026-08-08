<?php

namespace App\Services\Facturas;

use App\Models\EjercicioContable;
use App\Models\PagoFactura;
use App\Services\Comunidades\EnlaceContableComunidad;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\DatosTercero;
use App\Services\Contabilidad\RegistrarAsientoService;
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
     * @param  array<int|string>  $pagoIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function ejecutar(array $pagoIds): array
    {
        $pagos = PagoFactura::with([
            'cuentaBancaria',
            'factura.proveedor.persona.comunidad',
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

        $lineas = [
            new DatosApunte(debe: $centimos, tercero: new DatosTercero(
                tipo: 'proveedor',
                id: (string) $proveedor->id,
                clase: 'acreedor',
                nif: $persona->documento_identificativo,
                razonSocial: $persona->razon_social ?: $persona->nombre_completo,
            )),
            new DatosApunte(haber: $centimos, cuenta: $cuentaTesoreria),
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
}
