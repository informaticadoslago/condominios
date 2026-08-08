<?php

namespace App\Services\Facturas;

use App\Models\Documento;
use App\Models\Proveedor;
use App\Models\TipoDocumento;

/**
 * Guarda el papel de una factura como documento del proveedor.
 *
 * Lo usan el alta (que crea el documento antes que la propia factura, porque necesita su
 * id) y la lista, cuando el PDF de una factura «sin soporte» aparece más tarde. Los dos
 * tienen que dejar el documento igual: mismo tipo, misma descripción y el fichero sacado
 * de la carpeta de borradores.
 */
class AdjuntarSoporteFactura
{
    public function ejecutar(Proveedor $proveedor, ?string $numeroFactura, array $metadatosFichero): Documento
    {
        // documentos.descripcion es varchar(30): no cabe "Factura + nº + fecha", solo el nº.
        $descripcion = mb_substr(trim($numeroFactura ? "Factura {$numeroFactura}" : 'Factura'), 0, 30);

        return $proveedor->documentos()->create(
            Documento::consolidarFichero($metadatosFichero) + [
                'tipo_documento_id' => TipoDocumento::FACTURA,
                'descripcion'       => $descripcion,
            ]
        );
    }
}
