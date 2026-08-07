<?php

namespace App\Services\Recibos;

use App\Models\LineaRemesa;
use Illuminate\Support\Collection;

/**
 * Lee el fichero que manda el banco con los adeudos que ha devuelto (pain.002) y dice a
 * qué líneas de qué remesa corresponden.
 *
 * No toca nada: solo lee y empareja, para que la pantalla pueda enseñar la tanda antes de
 * aplicarla. Marcar las devoluciones es cosa de RegistrarDevolucion.
 *
 * El emparejamiento es exacto porque el identificador vuelve tal y como se mandó: en el
 * adeudo escribimos `{referencia de la remesa}-{id de línea}` (ver FicheroRemesaSepa), y
 * el banco lo devuelve en `OrgnlEndToEndId`. Ni el importe ni el IBAN valdrían: dos
 * inmuebles del mismo propietario comparten mandato y pueden coincidir en importe.
 */
final class LeerDevolucionesSepa
{
    /**
     * Los motivos que manda el banco vienen en código ISO. Se traducen los que salen de
     * verdad en adeudos de comunidad; de los demás se enseña el código tal cual, que
     * siempre es mejor que nada.
     */
    private const MOTIVOS = [
        'AC01' => 'Número de cuenta incorrecto',
        'AC04' => 'Cuenta cancelada',
        'AC06' => 'Cuenta bloqueada',
        'AG01' => 'Adeudo no permitido en la cuenta',
        'AG02' => 'Operación incorrecta',
        'AM04' => 'Sin fondos',
        'AM05' => 'Adeudo duplicado',
        'BE05' => 'Acreedor no reconocido',
        'MD01' => 'Sin mandato válido',
        'MD02' => 'Faltan datos del mandato',
        'MD06' => 'Devuelto a petición del deudor',
        'MD07' => 'Deudor fallecido',
        'MS02' => 'Rechazado por el deudor',
        'MS03' => 'Sin motivo especificado',
        'RC01' => 'BIC incorrecto',
        'RR01' => 'Faltan datos de la cuenta del deudor',
        'RR04' => 'Motivo normativo',
        'SL01' => 'Rechazado por un servicio del banco',
        'TM01' => 'Fuera de plazo',
    ];

    /**
     * Devuelve una fila por devolución del fichero, emparejada cuando se ha podido:
     *
     *   ['linea_remesa_id' => 12, 'referencia' => 'REM-3-1', 'motivo' => 'AM04',
     *    'importe' => 389.28, 'deudor' => 'JOSE LUIS…', 'linea' => LineaRemesa|null]
     *
     * Las que no casan vienen con `linea` a null para que la pantalla pueda enseñarlas y
     * decir por qué se quedan fuera, en vez de perderlas en silencio.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leer(string $contenido, ?int $remesaId = null): Collection
    {
        $xml = @simplexml_load_string($contenido);

        if ($xml === false) {
            return collect();
        }

        // El pain.002 va con espacio de nombres; se quita para poder recorrerlo por
        // nombre a secas, que es lo que hace legible el recorrido.
        $xml = @simplexml_load_string(preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $contenido));

        if ($xml === false) {
            return collect();
        }

        $devoluciones = collect();

        foreach ($xml->xpath('//TxInfAndSts') ?: [] as $tx) {
            // Solo lo rechazado de verdad. Un fichero puede traer también aceptados.
            if ((string) $tx->TxSts !== 'RJCT') {
                continue;
            }

            $referencia = trim((string) $tx->OrgnlEndToEndId);
            $codigo     = (string) ($tx->StsRsnInf->Rsn->Cd ?? '');

            $devoluciones->push([
                'referencia'      => $referencia,
                'linea_remesa_id' => $this->idDeLinea($referencia),
                'motivo'          => trim($codigo.' '.(self::MOTIVOS[$codigo] ?? '')),
                'importe'         => (float) ($tx->OrgnlTxRef->Amt->InstdAmt ?? 0),
                'deudor'          => (string) ($tx->OrgnlTxRef->Dbtr->Nm ?? ''),
            ]);
        }

        return $this->emparejar($devoluciones, $remesaId);
    }

    /**
     * El id de línea es lo que va detrás del último guion. La referencia de la remesa
     * puede llevar guiones, el id no.
     *
     * Sin guion no hay id: null. Un fichero de otro programa trae identificadores con
     * otro formato, y quedarse con el último trozo a la brava haría casar una devolución
     * con una línea que no es suya.
     */
    private function idDeLinea(string $referencia): ?int
    {
        $posicion = strrpos($referencia, '-');

        if ($posicion === false) {
            return null;
        }

        $trozo = trim(substr($referencia, $posicion + 1));

        return ctype_digit($trozo) && (int) $trozo > 0 ? (int) $trozo : null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $devoluciones
     * @return Collection<int, array<string, mixed>>
     */
    private function emparejar(Collection $devoluciones, ?int $remesaId): Collection
    {
        $lineas = LineaRemesa::with('recibo.inmueble', 'recibo.propietario.persona')
            ->whereIn('id', $devoluciones->pluck('linea_remesa_id')->filter()->all())
            // Un fichero de otra remesa no puede marcar devoluciones en esta.
            ->when($remesaId, fn ($q) => $q->where('remesa_id', $remesaId))
            ->get()
            ->keyBy('id');

        return $devoluciones->map(fn (array $devolucion) => $devolucion + [
            'linea' => $lineas->get($devolucion['linea_remesa_id']),
        ]);
    }
}
