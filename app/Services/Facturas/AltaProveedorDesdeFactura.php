<?php

namespace App\Services\Facturas;

use App\Exceptions\DocumentoInvalidoException;
use App\Exceptions\FacturaDuplicadaException;
use App\Models\Documento;
use App\Models\FacturaProveedor;
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
     * @param  array  $metadatosFichero  vacío para una factura sin soporte: la tecleada a
     *                                   mano o leída del QR, que no trae PDF que adjuntar.
     *
     * @return array{proveedor: Proveedor, creado: bool}
     */
    public function ejecutar(
        int $comunidadId,
        string $documento,
        ?string $razonSocial,
        array $metadatosFichero,
        ?string $numeroFactura,
        ?string $fecha,
        ?string $importe = null,
        bool $sobrescribir = false,
        ?int $documentoPaisId = null,
        ?int $tipoDocumentoId = null,
        ?int $tipoProveedorId = null
    ): array {
        $documento = $this->normalizarDocumento($documento);
        // De un PDF no se sabe el país ni el tipo, se deducen de la letra (y solo puede
        // ser español). Cuando se teclean a mano, mandan los elegidos.
        $documentoPaisId ??= Pais::ESPAÑA;
        $tipoDocumentoId ??= $this->detectarTipoDocumento($documento);
        $fecha = $this->normalizarFecha($fecha);

        $persona = PersonaComunidad::where('comunidad_id', $comunidadId)
            ->where('documento_identificativo', $documento)
            ->first();

        $creado = false;

        if ($persona) {
            // El tipo solo se pone al crearlo: al que ya estaba no se le toca.
            $proveedor = Proveedor::where('persona_comunidad_id', $persona->id)->first()
                ?? Proveedor::create([
                    'persona_comunidad_id' => $persona->id,
                    'tipo_proveedor_id'    => $tipoProveedorId,
                ]);
        } else {
            $persona = PersonaComunidad::create([
                'comunidad_id'             => $comunidadId,
                'nombre'                   => '', // personas_comunidad.nombre es NOT NULL
                'razon_social'             => $razonSocial,
                'documento_pais_id'        => $documentoPaisId,
                'tipo_documento_id'        => $tipoDocumentoId,
                'documento_identificativo' => $documento,
                'fecha_nacimiento'         => '1801-01-01', // marcador técnico, sin significado
                'genero_id'                => TipoGenero::GENERO_OTRO,
            ]);

            $proveedor = Proveedor::create([
                'persona_comunidad_id' => $persona->id,
                'tipo_proveedor_id'    => $tipoProveedorId,
            ]);
            $creado = true;
        }

        $proveedor->setRelation('persona', $persona);

        // Un proveedor no numera dos facturas igual: el número manda por sí solo, sin
        // mirar la fecha (si repite número con otra fecha, una de las dos está mal).
        $duplicada = $numeroFactura
            ? FacturaProveedor::where('proveedor_id', $proveedor->id)
                ->where('numero_factura', $numeroFactura)
                ->first()
            : null;

        if ($duplicada) {
            if (! $sobrescribir) {
                throw new FacturaDuplicadaException(
                    __('Ya existe una factura nº :numero de este proveedor (de fecha :fecha).', [
                        'numero' => $numeroFactura,
                        'fecha'  => $duplicada->fecha_factura,
                    ])
                );
            }

            // Con papel, borrar el documento se lleva por delante la fila en cascada; sin
            // papel no hay documento del que tirar, así que se borra la factura.
            $duplicada->documento ? $duplicada->documento->delete() : $duplicada->delete();
        }

        // documentos.descripcion es varchar(30): no cabe "Factura + nº + fecha", solo el nº.
        $descripcion = mb_substr(trim($numeroFactura ? "Factura {$numeroFactura}" : 'Factura'), 0, 30);

        // Sin fichero, la factura queda sin soporte: no se inventa un documento vacío.
        $documentoCreado = $metadatosFichero
            ? $proveedor->documentos()->create(
                Documento::consolidarFichero($metadatosFichero) + [
                    'tipo_documento_id' => TipoDocumento::FACTURA,
                    'descripcion'       => $descripcion,
                ]
            )
            : null;

        FacturaProveedor::create([
            'documento_id'   => $documentoCreado?->id,
            'proveedor_id'   => $proveedor->id,
            'numero_factura' => $numeroFactura,
            'fecha_factura'  => $fecha,
            'importe'        => $this->normalizarImporte($importe),
        ]);

        return ['proveedor' => $proveedor, 'creado' => $creado];
    }

    /**
     * "119,03 €" / "1.234,56" -> 1234.56; pero también "20.00 €" (formato con punto
     * decimal, sin coma) -> 20.00, tal cual puede venir de facturas que no usan la
     * coma española (ej. Piensa Solutions/Tesys).
     */
    public function normalizarImporte(?string $importe): ?float
    {
        if (! $importe) {
            return null;
        }

        $limpio = trim(preg_replace('/[^\d,.]/', '', $importe));
        if ($limpio === '') {
            return null;
        }

        if (str_contains($limpio, ',')) {
            // Formato español: el punto (si lo hay) es separador de miles, la coma es decimal.
            $limpio = str_replace('.', '', $limpio);
            $limpio = str_replace(',', '.', $limpio);
        }
        // Si no hay coma, el punto (si lo hay) ya es el separador decimal tal cual.

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    /**
     * La fecha no siempre llega en dd/mm/aaaa: según el proveedor (o incluso entre facturas del
     * mismo proveedor) puede venir con guiones, o incluso escrita en letra ("31 de octubre de
     * 2025"). Se normaliza siempre a dd/mm/aaaa, el formato que ya usan la vista y la
     * comprobación de duplicados — y de paso evita reventar la columna (varchar 20) con un
     * texto largo sin reconocer.
     */
    public function normalizarFecha(?string $fecha): ?string
    {
        if (! $fecha) {
            return null;
        }

        $fecha = trim($fecha);

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $fecha, $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $fecha, $m)) {
            [, $dia, $mes, $anio] = $m;
            if (strlen($anio) === 2) {
                $anio = '20' . $anio;
            }

            return sprintf('%02d/%02d/%04d', (int) $dia, (int) $mes, (int) $anio);
        }

        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
        ];

        if (preg_match('/(\d{1,2})\s+de\s+(\p{L}+)\s+de\s+(\d{4})/ui', $fecha, $m)) {
            $mes = $meses[mb_strtolower($m[2])] ?? null;
            if ($mes) {
                return sprintf('%02d/%02d/%04d', (int) $m[1], $mes, (int) $m[3]);
            }
        }

        // Formato no reconocido: al menos que quepa en la columna, para no reventar el insert.
        return mb_substr($fecha, 0, 20);
    }

    /**
     * Mismo criterio de duplicado que ejecutar() (proveedor + nº de factura), pero sin
     * intentar el alta: para poder avisar en un listado antes de que el usuario pulse
     * "Importar", en vez de descubrirlo al vuelo con la excepción.
     */
    /** Si el CIF/NIF ya es proveedor de esta comunidad no hay que preguntar su tipo. */
    public function proveedorExiste(int $comunidadId, ?string $documento): bool
    {
        if (! $documento) {
            return false;
        }

        $persona = PersonaComunidad::where('comunidad_id', $comunidadId)
            ->where('documento_identificativo', $this->normalizarDocumento($documento))
            ->first();

        return $persona && Proveedor::where('persona_comunidad_id', $persona->id)->exists();
    }

    public function existeDuplicada(int $comunidadId, string $documento, ?string $numeroFactura, ?string $fecha = null): bool
    {
        if (! $numeroFactura) {
            return false;
        }

        $documento = $this->normalizarDocumento($documento);

        $persona = PersonaComunidad::where('comunidad_id', $comunidadId)
            ->where('documento_identificativo', $documento)
            ->first();

        $proveedor = $persona ? Proveedor::where('persona_comunidad_id', $persona->id)->first() : null;

        if (! $proveedor) {
            return false;
        }

        return FacturaProveedor::where('proveedor_id', $proveedor->id)
            ->where('numero_factura', $numeroFactura)
            ->exists();
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
