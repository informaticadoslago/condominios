<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Proveedor;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoVistaController extends Controller
{
    public function __invoke(Documento $documento): StreamedResponse
    {
        $esDelProveedorActivo = $documento->documentable instanceof Proveedor
            && $documento->documentable->perteneceAlContextoActivo();

        abort_unless($esDelProveedorActivo, 404);
        abort_unless($documento->existeFichero(), 404);

        return Documento::disco()->response($documento->ruta, $documento->nombre_mostrado);
    }
}
