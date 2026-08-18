<?php

namespace App\Services\Recibos;

use App\Exceptions\RemesaNoGenerableException;
use App\Models\Comunidad;
use App\Models\Inmueble;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta en nuestro sistema una remesa que se presentó al banco por otro programa
 * (no generada por nosotros), a partir del pain.008 que se mandó. Sirve para casar
 * después sus devoluciones: se guarda el EndToEndId tal cual venía, y una devolución
 * futura casa por comparación exacta contra `lineas_remesas.referencia_externa`.
 *
 * No genera ningún adeudo nuevo: los recibos ya existen en nuestro sistema (se generaron
 * al aprobar el presupuesto) y puede que ya estén cobrados a mano. Por eso no puede
 * reutilizar GeneradorRemesa, que exige recibos con saldo pendiente.
 *
 * Analizar y aplicar son dos pasos separados: `analizar()` no escribe nada, solo casa
 * cada línea del fichero con su recibo para que la pantalla lo enseñe antes de confirmar.
 */
final class ImportadorRemesaSepa
{
    /**
     * @return array{
     *     error: ?string,
     *     referenciaOriginal: ?string,
     *     fechaCargo: ?string,
     *     candidatas: array<int, array>,
     *     sinCasar: array<int, array>,
     *     yaImportadas: array<int, array>,
     * }
     */
    public function analizar(Comunidad $comunidad, string $contenido): array
    {
        // El pain.008 va con espacio de nombres; se quita para recorrerlo por nombre a
        // secas, igual que hace LeerDevolucionesSepa con el pain.002.
        $xml = @simplexml_load_string(preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $contenido));

        if ($xml === false) {
            return $this->error(__('El fichero no es un XML válido.'));
        }

        $transacciones = $xml->xpath('//DrctDbtTxInf') ?: [];

        if (empty($transacciones)) {
            return $this->error(__('El fichero no trae ninguna transacción (DrctDbtTxInf).'));
        }

        $referenciaOriginal = trim((string) ($xml->xpath('//GrpHdr/MsgId')[0] ?? ''));
        $fechaCargo          = trim((string) ($xml->xpath('//ReqdColltnDt')[0] ?? ''));

        $candidatas   = [];
        $sinCasar     = [];
        $yaImportadas = [];

        foreach ($transacciones as $tx) {
            $endToEndId = trim((string) $tx->PmtId->EndToEndId);
            $importe    = round((float) ($tx->InstdAmt ?? 0), 2);
            $iban       = trim((string) ($tx->DbtrAcct->Id->IBAN ?? ''));
            $deudor     = trim((string) ($tx->Dbtr->Nm ?? ''));
            $concepto   = trim((string) ($tx->RmtInf->Ustrd ?? ''));

            $fila = [
                'referencia_externa' => $endToEndId,
                'importe'            => $importe,
                'iban'               => $iban,
                'deudor'             => $deudor,
                'concepto'           => $concepto,
            ];

            if ($endToEndId !== '' && LineaRemesa::where('referencia_externa', $endToEndId)->exists()) {
                $yaImportadas[] = $fila;

                continue;
            }

            $recibos = $this->recibosCandidatos($comunidad, $iban, $importe);

            if ($recibos->count() === 1) {
                $recibo = $recibos->first();

                $candidatas[] = $fila + [
                    'recibo_id' => $recibo->id,
                    'inmueble'  => trim(($recibo->inmueble?->tipoInmueble?->descripcion ?? '').' '.($recibo->inmueble?->planta ?? '').' '.($recibo->inmueble?->puerta ?? '')),
                    'estado'    => $recibo->estado_id,
                ];

                continue;
            }

            $sinCasar[] = $fila + [
                'motivo' => $recibos->isEmpty()
                    ? __('Ningún recibo de la comunidad casa por IBAN e importe.')
                    : __(':count recibos casan por IBAN e importe: no se puede elegir solo.', ['count' => $recibos->count()]),
            ];
        }

        return [
            'error'              => null,
            'referenciaOriginal' => $referenciaOriginal ?: null,
            'fechaCargo'         => $fechaCargo ?: null,
            'candidatas'         => $candidatas,
            'sinCasar'           => $sinCasar,
            'yaImportadas'       => $yaImportadas,
        ];
    }

    /**
     * Recibos de la comunidad que casan por IBAN (cuenta con la que se domicilió) e
     * importe, y que todavía no están en ninguna remesa: si ya tuvieran una línea,
     * sería esta misma importación repetida o un caso que hay que mirar a mano, no
     * casar de nuevo a ciegas.
     */
    private function recibosCandidatos(Comunidad $comunidad, string $iban, float $importe)
    {
        // El IBAN del XML no lleva espacios; el guardado en cuentas_bancarias sí, de
        // formato para pantalla. Se comparan ambos sin espacios para que casen igual.
        $ibanNormalizado = $this->normalizarIban($iban);

        return Recibo::domiciliado()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', $comunidad->id)->select('id'))
            ->where('importe', $importe)
            ->whereHas('cuentaBancaria', fn ($q) => $q->whereRaw("REPLACE(iban, ' ', '') = ?", [$ibanNormalizado]))
            ->whereDoesntHave('lineasRemesas')
            ->with(['inmueble.tipoInmueble'])
            ->get();
    }

    private function normalizarIban(string $iban): string
    {
        return strtoupper(str_replace(' ', '', $iban));
    }

    /**
     * Da de alta la Remesa y sus líneas con las candidatas que el usuario confirmó.
     * Como los recibos pueden venir ya cobrados a mano (no se sabe con qué estado se
     * restauró la base), cada línea se trata según lo que encuentra:
     *
     *  - Generado  → pasa a Enviado, como si la remesa hubiera salido de aquí.
     *  - Cobrado   → se deja el estado, pero si hay un único cobro suelto (sin remesa)
     *                se engancha a la línea nueva, para que una devolución futura sepa
     *                qué deshacer. Con cero o varios cobros sueltos no se toca: se
     *                cuenta aparte para que el usuario lo revise a mano.
     *
     * @param  array<int, array{recibo_id:int, importe:float, iban:string, referencia_externa:string}>  $lineas
     * @return array{remesa: Remesa, enlazados: int, sinEnlazar: int}
     */
    public function importar(Comunidad $comunidad, array $lineas, string $fechaCargo, ?string $referenciaOriginal = null): array
    {
        if (empty($lineas)) {
            throw new RemesaNoGenerableException(__('No hay ninguna línea confirmada para importar.'));
        }

        $cuentaAbono = $comunidad->cuentasBancarias()->first();

        if (! $cuentaAbono) {
            throw new RemesaNoGenerableException(
                __('La comunidad no tiene cuenta bancaria: hace falta para registrar en qué cuenta se abonó la remesa.')
            );
        }

        return DB::transaction(function () use ($comunidad, $cuentaAbono, $lineas, $fechaCargo, $referenciaOriginal) {
            $remesa = Remesa::create([
                'comunidad_id'       => $comunidad->id,
                'cuenta_bancaria_id' => $cuentaAbono->id,
                'referencia'         => $this->referencia($comunidad, $fechaCargo),
                'fecha_cargo'        => $fechaCargo,
            ]);

            $enlazados  = 0;
            $sinEnlazar = 0;

            foreach ($lineas as $dato) {
                $recibo = Recibo::whereKey($dato['recibo_id'])->lockForUpdate()->first();

                if (! $recibo) {
                    continue;
                }

                $linea = $remesa->lineas()->create([
                    'recibo_id'          => $recibo->id,
                    'importe'            => $dato['importe'],
                    'iban'               => $dato['iban'],
                    'referencia_externa' => $dato['referencia_externa'],
                ]);

                if ($recibo->estado_id === TipoEstadoRecibo::GENERADO) {
                    $recibo->motivoCambioEstado = __('Remesa importada :referencia', ['referencia' => $remesa->referencia]);
                    $recibo->update(['estado_id' => TipoEstadoRecibo::ENVIADO]);

                    continue;
                }

                if ($recibo->estado_id === TipoEstadoRecibo::COBRADO) {
                    $cobrosSueltos = $recibo->cobros()->whereNull('linea_remesa_id')->get();

                    if ($cobrosSueltos->count() === 1) {
                        $cobrosSueltos->first()->update(['linea_remesa_id' => $linea->id]);
                        $enlazados++;
                    } else {
                        $sinEnlazar++;
                    }
                }
            }

            return ['remesa' => $remesa, 'enlazados' => $enlazados, 'sinEnlazar' => $sinEnlazar];
        });
    }

    /** Mismo formato que GeneradorRemesa::referencia(), con prefijo distinto para que se note que es importada. */
    private function referencia(Comunidad $comunidad, string $fechaCargo): string
    {
        $base = 'IMP'.$comunidad->id.'-'.str_replace('-', '', $fechaCargo);

        $existentes = Remesa::where('comunidad_id', $comunidad->id)
            ->where('referencia', 'like', $base.'%')
            ->count();

        return $base.'-'.($existentes + 1);
    }

    private function error(string $mensaje): array
    {
        return [
            'error'              => $mensaje,
            'referenciaOriginal' => null,
            'fechaCargo'         => null,
            'candidatas'         => [],
            'sinCasar'           => [],
            'yaImportadas'       => [],
        ];
    }
}
