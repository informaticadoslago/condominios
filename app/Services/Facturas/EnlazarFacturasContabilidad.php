<?php

namespace App\Services\Facturas;

use App\Models\EjercicioContable;
use App\Models\FacturaProveedor;
use App\Models\PersonaComunidad;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\DatosTercero;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Manda a la contabilidad las facturas de proveedor que todavía no han entrado en ningún
 * asiento.
 *
 * Un asiento por factura, al revés que en recibos: cada factura es un hecho contable
 * suyo, con su fecha y su acreedor. Al debe, la cuenta de gasto del tipo del proveedor;
 * al haber, la subcuenta del acreedor, por el importe total y sin desglosar IVA (la
 * comunidad no tiene actividad sujeta, así que no hay IVA soportado que separar).
 *
 * Es el gasto devengado, no el pago: el pago será otro asiento (acreedor a tesorería)
 * cuando se pague de verdad.
 *
 * Se puede volver a lanzar sin miedo: las ya enlazadas se saltan, y si aun así llegara
 * dos veces la misma factura, la contabilidad reconoce la referencia y devuelve el
 * asiento que ya hizo en vez de duplicarlo.
 */
final class EnlazarFacturasContabilidad
{
    public function __construct(private readonly RegistrarAsientoService $asientos)
    {
    }

    /**
     * @param  array<int|string>  $facturaIds
     * @return array{enlazadas: int, omitidas: int}
     */
    public function ejecutar(array $facturaIds): array
    {
        $facturas = FacturaProveedor::with([
            'proveedor.persona' => fn ($query) => $query->morphWith([PersonaComunidad::class => ['comunidad']]),
            'proveedor.tipo',
            'actividad',
        ])
            ->whereIn('id', $facturaIds)
            ->whereNull('asiento_contable')
            ->get();

        $enlazadas = 0;

        foreach ($facturas as $factura) {
            if ($this->esEnlazable($factura)) {
                $this->enlazar($factura);
                $enlazadas++;
            }
        }

        return [
            'enlazadas' => $enlazadas,
            'omitidas'  => $facturas->count() - $enlazadas,
        ];
    }

    private function esEnlazable(FacturaProveedor $factura): bool
    {
        return $this->motivoNoEnlazable($factura) === null;
    }

    /**
     * Solo se enlaza lo que la contabilidad puede recibir: comunidad enlazada con una
     * empresa contable, proveedor con tipo (de ahí sale la cuenta de gasto) y factura con
     * fecha e importe. Lo demás se cuenta como omitido, no se rompe la tanda por ello;
     * cuando se contabiliza una sola, este motivo es lo que se le enseña a quien la manda.
     */
    public function motivoNoEnlazable(FacturaProveedor $factura): ?string
    {
        if ($factura->asiento_contable !== null) {
            return __('Esta factura ya está contabilizada.');
        }

        if ($factura->proveedor?->persona?->comunidad?->empresa_contable_id === null) {
            return __('Esta comunidad no está enlazada con ninguna empresa contable.');
        }

        if ($factura->proveedor?->tipo?->cuenta_gasto === null) {
            return __('El proveedor no tiene tipo: sin él no se sabe a qué cuenta de gasto va.');
        }

        if ($factura->importe === null || $this->fecha($factura) === null) {
            return __('A la factura le falta la fecha o el importe.');
        }

        return null;
    }

    private function enlazar(FacturaProveedor $factura): void
    {
        $proveedor   = $factura->proveedor;
        $persona     = $proveedor->persona;
        $empresaId   = $persona->comunidad->empresa_contable_id;
        $cuentaGasto = $proveedor->tipo->cuenta_gasto;
        $fecha       = $this->fecha($factura);
        // Sin actividad (comunidad de una sola, o gasto compartido sin marcar), el
        // apunte queda sin proyecto: eso es lo correcto, no un descuido.
        $proyectoId  = $factura->actividad?->proyecto_contable_id;

        // La contabilidad trabaja en céntimos enteros; la factura, en euros con dos
        // decimales. La conversión se hace aquí, en la frontera.
        $centimos = (int) round((float) $factura->importe * 100);

        $concepto = trim(sprintf(
            '%s%s',
            $persona->razon_social ?: $persona->nombre_completo,
            $factura->numero_factura ? ' · factura '.$factura->numero_factura : '',
        ));

        $lineas = [
            new DatosApunte(debe: $centimos, cuenta: $cuentaGasto, proyecto: $proyectoId),
            // Por tercero, no por cuenta: el acreedor que no tenga subcuenta la estrena
            // aquí, que es lo normal la primera vez que se le contabiliza una factura.
            new DatosApunte(haber: $centimos, tercero: new DatosTercero(
                tipo: 'proveedor',
                id: (string) $proveedor->id,
                clase: 'acreedor',
                nif: $persona->documento_identificativo,
                razonSocial: $persona->razon_social ?: $persona->nombre_completo,
            ), proyecto: $proyectoId),
        ];

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: $concepto,
            lineas: $lineas,
            diario: 'FAC',
            referenciaTipo: 'facturas',
            referenciaId: (string) $factura->id,
            evento: 'registro',
            crearTercerosDesconocidos: true,
        )));

        $factura->update([
            'cuenta_gasto'     => $cuentaGasto,
            'asiento_contable' => $asiento->id,
        ]);
    }

    /** fecha_factura se guarda como texto dd/mm/aaaa (ver AltaProveedorDesdeFactura::normalizarFecha). */
    private function fecha(FacturaProveedor $factura): ?string
    {
        if (! $factura->fecha_factura) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $factura->fecha_factura)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }
}
