<?php

namespace App\Services\Recibos;

use App\Exceptions\RemesaNoGenerableException;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\MandatoSepa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa en una remesa los recibos domiciliados de un vencimiento y los pasa a Enviado.
 *
 * Solo entran los que se pueden cobrar de verdad: domiciliados, con saldo pendiente, con
 * mandato firmado para su cuenta y que no estén ya en vuelo en otra remesa. Un recibo
 * devuelto sí vuelve a entrar — se presenta otra vez, con una línea nueva.
 *
 * El importe de cada línea es el SALDO, no el importe del recibo: si ya se cobró algo a
 * cuenta por otra vía, al banco solo se le pide lo que falta.
 */
final class GeneradorRemesa
{
    public function generar(Comunidad $comunidad, string $fechaVencimiento, string $fechaCargo): Remesa
    {
        $cuentaAbono = $comunidad->cuentasBancarias()->first();

        if (! $comunidad->identificador_acreedor_sepa) {
            throw new RemesaNoGenerableException(
                'La comunidad no tiene identificador de acreedor SEPA, y va en cada adeudo del fichero.'
            );
        }

        if (! $cuentaAbono) {
            throw new RemesaNoGenerableException(
                'La comunidad no tiene cuenta bancaria: hace falta el IBAN donde el banco abona la remesa.'
            );
        }

        $recibos = $this->recibosRemesables($comunidad, $fechaVencimiento);

        if ($recibos->isEmpty()) {
            throw new RemesaNoGenerableException(
                'No hay recibos domiciliados pendientes con ese vencimiento.'
            );
        }

        $mandatos = $this->mandatosPorCuenta($comunidad, $recibos);

        $this->comprobarMandatos($recibos, $mandatos);

        return DB::transaction(function () use ($comunidad, $cuentaAbono, $recibos, $mandatos, $fechaCargo): Remesa {
            $remesa = Remesa::create([
                'comunidad_id'       => $comunidad->id,
                'cuenta_bancaria_id' => $cuentaAbono->id,
                'referencia'         => $this->referencia($comunidad, $fechaCargo),
                'fecha_cargo'        => $fechaCargo,
            ]);

            foreach ($recibos as $recibo) {
                $remesa->lineas()->create([
                    'recibo_id' => $recibo->id,
                    'importe'   => $recibo->saldo,
                    // Se copia el IBAN con el que se presenta: mañana el propietario
                    // puede cambiar de cuenta, y esta línea tiene que seguir contando
                    // con cuál se pidió el dinero.
                    'iban'      => $mandatos[$recibo->cuenta_bancaria_id]->cuentaBancaria->iban,
                ]);

                $recibo->update(['estado_id' => TipoEstadoRecibo::ENVIADO]);
            }

            return $remesa;
        });
    }

    /**
     * Recibos que pueden ir en la remesa de ese vencimiento.
     *
     * @return Collection<int, Recibo>
     */
    public function recibosRemesables(Comunidad $comunidad, string $fechaVencimiento): Collection
    {
        return Recibo::query()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', $comunidad->id)->select('id'))
            ->where('forma_de_pago_id', FormaDePago::RECIBO_BANCARIO)
            ->whereNotNull('cuenta_bancaria_id')
            ->whereDate('fecha_vencimiento', $fechaVencimiento)
            ->where('saldo', '>', 0)
            // Ya presentado y sin devolver: está en vuelo, no se presenta dos veces.
            ->whereDoesntHave('lineasRemesas', fn ($q) => $q->whereNull('fecha_devolucion'))
            ->with(['cuentaBancaria.entidadBancaria', 'inmueble'])
            ->orderBy('inmueble_id')
            ->get();
    }

    /**
     * Mandatos de las cuentas implicadas, indexados por cuenta.
     *
     * @param  Collection<int, Recibo>  $recibos
     * @return array<int, MandatoSepa>
     */
    private function mandatosPorCuenta(Comunidad $comunidad, Collection $recibos): array
    {
        return MandatoSepa::where('comunidad_id', $comunidad->id)
            ->whereIn('cuenta_bancaria_id', $recibos->pluck('cuenta_bancaria_id')->unique())
            ->with('cuentaBancaria.entidadBancaria')
            ->get()
            ->keyBy('cuenta_bancaria_id')
            ->all();
    }

    /**
     * Sin mandato firmado no se puede adeudar: se paran TODOS y se dice de quién falta,
     * en vez de mandar una remesa coja que el banco devolvería en parte.
     *
     * @param  Collection<int, Recibo>  $recibos
     * @param  array<int, MandatoSepa>  $mandatos
     */
    private function comprobarMandatos(Collection $recibos, array $mandatos): void
    {
        $sinMandato = $recibos
            ->filter(fn (Recibo $recibo) => ! isset($mandatos[$recibo->cuenta_bancaria_id]))
            ->map(fn (Recibo $recibo) => trim(($recibo->inmueble?->planta ?? '').' '.($recibo->inmueble?->puerta ?? '')) ?: "#{$recibo->inmueble_id}")
            ->unique()
            ->values();

        if ($sinMandato->isNotEmpty()) {
            throw new RemesaNoGenerableException(
                'Faltan mandatos SEPA firmados: '.$sinMandato->implode(', ').'.'
            );
        }
    }

    /** Identificador de la remesa, único: el banco lo usa para no procesar dos veces el mismo envío. */
    private function referencia(Comunidad $comunidad, string $fechaCargo): string
    {
        $base = 'REM'.$comunidad->id.'-'.str_replace('-', '', $fechaCargo);

        $enviadas = Remesa::where('comunidad_id', $comunidad->id)
            ->where('referencia', 'like', $base.'%')
            ->count();

        return $base.'-'.($enviadas + 1);
    }
}
