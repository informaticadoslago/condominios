<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Proveedor;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoDescargaController extends Controller
{
    public function __invoke(Documento $documento): StreamedResponse
    {
        $esDelProveedorDeLaComunidadActual = $documento->documentable instanceof Proveedor
            && $documento->documentable->persona->comunidad_id == session('comunidad_actual_id');

        abort_unless($esDelProveedorDeLaComunidadActual, 404);
        abort_unless($documento->existeFichero(), 404);

        return Documento::disco()->download($documento->ruta, $documento->nombre_mostrado);
    }
}
