<?php

namespace App\Services\Recibos;

use App\Models\EjercicioContable;
use App\Models\Recibo;
use App\Services\Comunidades\EnlaceContableComunidad;
use App\Services\Contabilidad\DatosApunte;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manda a la contabilidad los recibos que todavía no han entrado en ningún asiento.
 *
 * Un asiento por presupuesto y vencimiento, no uno por recibo: los 300 recibos de un
 * mismo vencimiento son un solo hecho contable —se emiten todos a la vez— y así se ve en
 * el diario. Al haber, la cuenta de ingresos del presupuesto por el total; al debe, la
 * cuenta de cada propietario por su parte. Los 300 se quedan con el mismo número de
 * asiento, que es lo que los marca como enlazados.
 *
 * Se puede volver a lanzar sin miedo: los recibos ya enlazados se saltan, y si aun así
 * llegara dos veces el mismo grupo, la contabilidad reconoce la referencia y devuelve el
 * asiento que ya hizo en vez de duplicarlo.
 */
final class EnlazarRecibosContabilidad
{
    public function __construct(
        private readonly RegistrarAsientoService $asientos,
        private readonly EnlaceContableComunidad $enlace,
    ) {
    }

    /**
     * @param  array<int|string>  $reciboIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function ejecutar(array $reciboIds): array
    {
        $recibos = Recibo::with(['presupuesto.comunidad', 'propietario.persona', 'inmueble'])
            ->whereIn('id', $reciboIds)
            ->whereNull('asiento_contable')
            ->get();

        $enlazables = $recibos->filter(fn (Recibo $recibo) => $this->esEnlazable($recibo));

        $enlazados = 0;

        // Un asiento por cada presupuesto y fecha de vencimiento.
        foreach ($enlazables->groupBy(fn (Recibo $r) => $r->presupuesto_id.'|'.$r->fecha_vencimiento->toDateString()) as $grupo) {
            $enlazados += $this->enlazarGrupo($grupo);
        }

        return [
            'enlazados' => $enlazados,
            'omitidos'  => $recibos->count() - $enlazados,
        ];
    }

    /**
     * Solo se enlaza lo que la contabilidad puede recibir: comunidad enlazada con una
     * empresa contable y presupuesto con su cuenta de ingresos (la estrena al aprobarse).
     * Lo demás se cuenta como omitido, no se rompe la tanda por ello.
     */
    private function esEnlazable(Recibo $recibo): bool
    {
        return $recibo->presupuesto?->comunidad?->empresa_contable_id !== null
            && $recibo->presupuesto?->cuenta_contable !== null;
    }

    /** @param  Collection<int, Recibo>  $grupo */
    private function enlazarGrupo(Collection $grupo): int
    {
        $primero     = $grupo->first();
        $presupuesto = $primero->presupuesto;
        $empresaId   = $presupuesto->comunidad->empresa_contable_id;
        $fecha       = $primero->fecha_vencimiento->toDateString();

        // El propietario que todavía no tenga subcuenta la estrena aquí: sin ella no hay
        // línea que ponerle al debe. Suele pasar con los que ya existían cuando se enlazó
        // la comunidad con la contabilidad.
        foreach ($grupo as $recibo) {
            if (! $recibo->propietario?->cuenta_contable) {
                $this->enlace->asignarCuentaPropietario($recibo->propietario);
                $recibo->propietario->refresh();
            }
        }

        $lineas = [];
        $total  = 0;

        foreach ($grupo as $recibo) {
            // La contabilidad trabaja en céntimos enteros; el recibo, en euros con dos
            // decimales. La conversión se hace aquí, en la frontera.
            $centimos = (int) round((float) $recibo->importe * 100);
            $total += $centimos;

            $lineas[] = new DatosApunte(
                debe: $centimos,
                cuenta: $recibo->propietario->cuenta_contable,
                concepto: trim(sprintf('%s %s', $recibo->inmueble?->planta, $recibo->inmueble?->puerta)) ?: null,
            );
        }

        $lineas[] = new DatosApunte(haber: $total, cuenta: $presupuesto->cuenta_contable);

        $asiento = DB::transaction(fn () => $this->asientos->ejecutar(new DatosAsiento(
            empresaContableId: $empresaId,
            ejercicio: EjercicioContable::nombrePara($empresaId, $fecha),
            fecha: $fecha,
            concepto: sprintf('%s · vencimiento %s', $presupuesto->nombre, $primero->fecha_vencimiento->format('d/m/Y')),
            lineas: $lineas,
            diario: 'REC',
            // El hecho es la emisión de ese vencimiento, no cada recibo suelto: reenviarlo
            // devuelve el mismo asiento en vez de duplicarlo.
            referenciaTipo: 'recibos',
            referenciaId: $presupuesto->id.':'.$fecha,
            evento: 'emision',
        )));

        Recibo::whereIn('id', $grupo->pluck('id'))->update(['asiento_contable' => $asiento->id]);

        return $grupo->count();
    }
}
