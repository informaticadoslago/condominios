<?php

namespace App\Http\Controllers;

use App\Exceptions\MandatoSepaFaltanteException;
use App\Models\Remesa;
use App\Services\Recibos\FicheroRemesaSepa;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga el pain.008 de una remesa.
 *
 * El XML no se guarda en disco: se vuelve a componer con los datos de la remesa cada vez
 * que se pide. Sus líneas ya llevan congelado el IBAN con el que se presentó cada
 * adeudo, así que el fichero sale idéntico aunque el propietario haya cambiado de cuenta
 * después.
 */
class RemesaFicheroController extends Controller
{
    public function __invoke(Remesa $remesa, FicheroRemesaSepa $fichero): StreamedResponse
    {
        abort_unless($remesa->comunidad_id == session('comunidad_actual_id'), 404);

        try {
            $xml = $fichero->generar($remesa);
        } catch (MandatoSepaFaltanteException $e) {
            abort(422, $e->getMessage());
        }

        $nombre = $remesa->referencia.'.xml';

        return response()->streamDownload(
            fn () => print($xml),
            $nombre,
            ['Content-Type' => 'application/xml'],
        );
    }
}
