<?php

namespace App\Services\Recibos;

use App\Exceptions\MandatoSepaFaltanteException;
use App\Models\MandatoSepa;
use App\Models\Remesa;
use Digitick\Sepa\GroupHeader;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;

/**
 * XML de adeudos directos (pain.008) de una remesa, con digitick/sepa-xml.
 *
 * Todos los adeudos van como RCUR: decisión tomada, ver MandatoSepa::secuencia().
 *
 * Los importes se pasan en CÉNTIMOS enteros, que es lo que espera el paquete: los
 * recibos guardan euros con dos decimales, así que se convierten aquí y una sola vez.
 */
final class FicheroRemesaSepa
{
    public function generar(Remesa $remesa): string
    {
        $remesa->load([
            'comunidad',
            'cuentaBancaria.entidadBancaria',
            'lineas.recibo.inmueble',
            'lineas.recibo.cuentaBancaria.entidadBancaria',
            'lineas.recibo.propietario.persona',
        ]);

        $comunidad = $remesa->comunidad;
        $acreedor  = mb_substr((string) $comunidad->nombre, 0, 70);

        // Sin esto el banco rechaza el fichero («7001: La identificación del
        // presentador»): InitgPty necesita su <Id>, no solo el nombre. El presentador
        // es la propia comunidad, así que se reutiliza su identificador de acreedor.
        $cabecera = new GroupHeader($remesa->referencia, $acreedor);
        $cabecera->setInitiatingPartyId($this->limpiar($comunidad->identificador_acreedor_sepa));

        $fichero = TransferFileFacadeFactory::createDirectDebitWithGroupHeader($cabecera, 'pain.008.001.02');

        $pago = [
            'id'                  => $remesa->referencia,
            'creditorName'        => $acreedor,
            'creditorAccountIBAN' => $this->limpiar($remesa->cuentaBancaria->iban),
            'seqType'             => PaymentInformation::S_RECURRING,
            'creditorId'          => $this->limpiar($comunidad->identificador_acreedor_sepa),
            'localInstrumentCode' => 'CORE',
            'dueDate'             => $remesa->fecha_cargo->format('Y-m-d'),
        ];

        if ($bic = $remesa->cuentaBancaria->entidadBancaria?->bic) {
            $pago['creditorAgentBIC'] = $bic;
        }

        $fichero->addPaymentInfo($remesa->referencia, $pago);

        $mandatos = $this->mandatosPorCuenta($remesa);

        $this->comprobarMandatos($remesa, $mandatos);

        foreach ($remesa->lineas as $linea) {
            $recibo  = $linea->recibo;
            $cuenta  = $recibo->cuentaBancaria;
            $mandato = $mandatos[$recibo->cuenta_bancaria_id];

            $adeudo = [
                'amount'                => (int) round((float) $linea->importe * 100),
                'debtorIban'            => $this->limpiar($linea->iban),
                'debtorName'            => mb_substr((string) $cuenta->titularReal()?->nombreCompleto, 0, 70),
                'debtorMandate'         => $mandato->referencia,
                'debtorMandateSignDate' => $mandato->fecha_firma->format('Y-m-d'),
                'remittanceInformation' => $this->concepto($recibo),
                // La línea de remesa, que es única por intento de cobro: si el recibo se
                // devuelve y se vuelve a presentar, el segundo adeudo lleva otro.
                'endToEndId'            => $remesa->referencia.'-'.$linea->id,
            ];

            if ($bic = $cuenta->entidadBancaria?->bic) {
                $adeudo['debtorBic'] = $bic;
            }

            $fichero->addTransfer($remesa->referencia, $adeudo);
        }

        return $fichero->asXML();
    }

    /** @return array<int, MandatoSepa> */
    private function mandatosPorCuenta(Remesa $remesa): array
    {
        // Solo el ACTIVO: uno cancelado no puede acabar en el fichero que se manda al
        // banco, aunque la cuenta llegara a tener más de uno con el tiempo.
        return MandatoSepa::where('comunidad_id', $remesa->comunidad_id)
            ->where('estado_id', MandatoSepa::ESTADO_ACTIVO)
            ->whereIn('cuenta_bancaria_id', $remesa->lineas->pluck('recibo.cuenta_bancaria_id')->unique())
            ->get()
            ->keyBy('cuenta_bancaria_id')
            ->all();
    }

    /**
     * Antes de montar el XML: si algún recibo de la remesa se quedó sin mandato ACTIVO
     * (se permite domiciliar sin él, a la espera del papel firmado, o el mandato se
     * canceló después de generar el recibo), se avisa con nombres en vez de romper con
     * un "Undefined array key" al buscarlo en el bucle principal.
     *
     * @param array<int, MandatoSepa> $mandatos
     */
    private function comprobarMandatos(Remesa $remesa, array $mandatos): void
    {
        $faltan = $remesa->lineas
            ->reject(fn ($linea) => isset($mandatos[$linea->recibo->cuenta_bancaria_id]))
            ->map(function ($linea) {
                $recibo   = $linea->recibo;
                $inmueble = trim(($recibo->inmueble?->planta ?? '').' '.($recibo->inmueble?->puerta ?? ''));

                return trim($inmueble.' ('.$recibo->propietario?->persona?->nombreCompleto.')');
            })
            ->unique()
            ->values();

        if ($faltan->isNotEmpty()) {
            throw new MandatoSepaFaltanteException(
                __('No se puede generar el fichero: falta el mandato SEPA de :inmuebles.', [
                    'inmuebles' => $faltan->implode(', '),
                ])
            );
        }
    }

    /** Lo que verá el propietario en su extracto. */
    private function concepto($recibo): string
    {
        $inmueble = trim(($recibo->inmueble?->planta ?? '').' '.($recibo->inmueble?->puerta ?? ''));

        return mb_substr(trim(__('Recibo').' '.$recibo->numero_pago.' '.$inmueble), 0, 140);
    }

    private function limpiar(?string $valor): string
    {
        return strtoupper(str_replace(' ', '', (string) $valor));
    }
}
