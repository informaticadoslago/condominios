<?php

namespace App\Services\Facturas;

use App\Exceptions\DocumentoInvalidoException;
use App\Models\Documento;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\Includes\ValidadorDocumentoId;

/**
 * Da de alta un proveedor a partir de los datos ya resueltos de una factura (o
 * reutiliza el que ya exista con ese CIF/NIF) y le adjunta el PDF como documento.
 */
class AltaProveedorDesdeFactura
{
    /**
     * @return array{proveedor: Proveedor, creado: bool}
     */
    public function ejecutar(
        int $comunidadId,
        string $documento,
        ?string $razonSocial,
        array $metadatosFichero,
        ?string $numeroFactura,
        ?string $fecha
    ): array {
        $documento       = $this->normalizarDocumento($documento);
        $tipoDocumentoId = $this->detectarTipoDocumento($documento);

        $persona = PersonaComunidad::where('comunidad_id', $comunidadId)
            ->where('documento_identificativo', $documento)
            ->first();

        $creado = false;

        if ($persona) {
            $proveedor = Proveedor::where('persona_comunidad_id', $persona->id)->first()
                ?? Proveedor::create(['persona_comunidad_id' => $persona->id]);
        } else {
            $persona = PersonaComunidad::create([
                'comunidad_id'             => $comunidadId,
                'nombre'                   => '', // personas_comunidad.nombre es NOT NULL
                'razon_social'             => $razonSocial,
                'documento_pais_id'        => Pais::ESPAÑA,
                'tipo_documento_id'        => $tipoDocumentoId,
                'documento_identificativo' => $documento,
                'fecha_nacimiento'         => '1801-01-01', // marcador técnico, sin significado
                'genero_id'                => TipoGenero::GENERO_OTRO,
            ]);

            $proveedor = Proveedor::create(['persona_comunidad_id' => $persona->id]);
            $creado    = true;
        }

        $proveedor->setRelation('persona', $persona);

        // documentos.descripcion es varchar(30): no cabe "Factura + nº + fecha", solo el nº.
        $descripcion = mb_substr(trim($numeroFactura ? "Factura {$numeroFactura}" : 'Factura'), 0, 30);

        $proveedor->documentos()->create(
            Documento::consolidarFichero($metadatosFichero) + [
                'tipo_documento_id' => TipoDocumento::FACTURA,
                'descripcion'       => $descripcion,
            ]
        );

        return ['proveedor' => $proveedor, 'creado' => $creado];
    }

    protected function normalizarDocumento(string $documento): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $documento));
    }

    protected function detectarTipoDocumento(string $documento): int
    {
        $validador = new ValidadorDocumentoId();

        return match (true) {
            $validador->isValidCIF($documento) => TipoDocumentoIdentificativo::DOCUMENTO_CIF,
            $validador->isValidNIF($documento) => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
            $validador->isValidNIE($documento) => TipoDocumentoIdentificativo::DOCUMENTO_NIE,
            default => throw new DocumentoInvalidoException(
                __('«:documento» no es un CIF, NIF ni NIE válido.', ['documento' => $documento])
            ),
        };
    }
}
