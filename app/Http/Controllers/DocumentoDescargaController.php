<?php

namespace App\Http\Controllers;

use App\Models\ConvocatoriaJunta;
use App\Models\Documento;
use App\Models\Proveedor;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoDescargaController extends Controller
{
    public function __invoke(Documento $documento): StreamedResponse
    {
        $documentable = $documento->documentable;
        $esDeLaComunidadActual = match (true) {
            $documentable instanceof Proveedor         => $documentable->persona->comunidad_id == session('comunidad_actual_id'),
            $documentable instanceof ConvocatoriaJunta => $documentable->comunidad_id == session('comunidad_actual_id'),
            default                                    => false,
        };

        abort_unless($esDeLaComunidadActual, 404);
        abort_unless($documento->existeFichero(), 404);

        return Documento::disco()->download($documento->ruta, $documento->nombre_mostrado);
    }
}
