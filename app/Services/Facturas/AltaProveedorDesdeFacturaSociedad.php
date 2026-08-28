<?php

namespace App\Services\Facturas;

use App\Exceptions\DocumentoInvalidoException;
use App\Exceptions\FacturaDuplicadaException;
use App\Models\CuotaIvaFacturaProveedorSociedad;
use App\Models\FacturaProveedorSociedad;
use App\Models\Pais;
use App\Models\PersonaSociedad;
use App\Models\Proveedor;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoProveedorSociedad;
use App\Rules\Includes\ValidadorDocumentoId;

/**
 * Da de alta un proveedor de sociedad a partir de los datos ya resueltos de una factura
 * (o reutiliza el que ya exista con ese CIF/NIF) y le adjunta el PDF como documento.
 * Mismo criterio que AltaProveedorDesdeFactura (comunidad), pero guardando base/total y
 * las cuotas de IVA en vez de un único importe.
 */
class AltaProveedorDesdeFacturaSociedad
{
    /**
     * @param  array  $metadatosFichero  vacío para una factura sin soporte.
     * @param  array<int, array{tipo_iva: float, importe: float}>  $cuotasIva
     *
     * @return array{proveedor: Proveedor, creado: bool}
     */
    public function ejecutar(
        int $sociedadId,
        string $documento,
        ?string $razonSocial,
        array $metadatosFichero,
        ?string $numeroFactura,
        ?string $fecha,
        ?string $importeBase = null,
        ?string $importeTotal = null,
        array $cuotasIva = [],
        bool $sobrescribir = false,
        ?int $documentoPaisId = null,
        ?int $tipoDocumentoId = null,
        ?int $tipoProveedorId = null,
        ?string $nombreComercial = null,
        ?string $nombre = null,
        ?string $apellido1 = null,
        ?string $apellido2 = null,
        ?int $generoId = null,
        ?string $fechaNacimiento = null,
    ): array {
        $documento = $this->normalizarDocumento($documento);
        $documentoPaisId ??= Pais::ESPAÑA;
        $tipoDocumentoId ??= $this->detectarTipoDocumento($documento);
        $fecha = (new AltaProveedorDesdeFactura())->normalizarFecha($fecha);

        $persona = PersonaSociedad::where('sociedad_id', $sociedadId)
            ->where('documento_identificativo', $documento)
            ->first();

        $creado = false;

        if ($persona) {
            $proveedor = Proveedor::where('persona_type', PersonaSociedad::class)->where('persona_id', $persona->id)->first()
                ?? Proveedor::create([
                    'persona_type' => PersonaSociedad::class,
                    'persona_id'   => $persona->id,
                    ...$this->datosTipo($tipoProveedorId),
                ]);
        } else {
            $persona = PersonaSociedad::create([
                'sociedad_id'              => $sociedadId,
                'nombre'                   => $nombre ?? '', // personas_sociedad.nombre es NOT NULL
                'apellido1'                => $apellido1,
                'apellido2'                => $apellido2,
                'razon_social'             => $razonSocial,
                'nombre_comercial'         => $nombreComercial,
                'documento_pais_id'        => $documentoPaisId,
                'tipo_documento_id'        => $tipoDocumentoId,
                'documento_identificativo' => $documento,
                'genero_id'                => $generoId,
                'fecha_nacimiento'         => $fechaNacimiento,
            ]);

            $proveedor = Proveedor::create([
                'persona_type' => PersonaSociedad::class,
                'persona_id'   => $persona->id,
                ...$this->datosTipo($tipoProveedorId),
            ]);
            $creado = true;
        }

        $proveedor->setRelation('persona', $persona);

        $normalizador = new AltaProveedorDesdeFactura();

        // CIF, fecha y número son obligatorios para dar de alta (ver ResultadoFactura::darDeAlta,
        // que ya lo exige antes de llegar aquí); con número siempre presente, el duplicado se
        // detecta solo por él, sin falta de respaldos.
        $duplicada = FacturaProveedorSociedad::where('proveedor_id', $proveedor->id)
            ->where('numero_factura', $numeroFactura)
            ->first();

        if ($duplicada) {
            if (! $sobrescribir) {
                throw new FacturaDuplicadaException(
                    __('Ya existe una factura nº :numero de este proveedor (de fecha :fecha).', [
                        'numero' => $numeroFactura,
                        'fecha'  => $duplicada->fecha_factura,
                    ])
                );
            }

            $duplicada->documento ? $duplicada->documento->delete() : $duplicada->delete();
        }

        $documentoCreado = $metadatosFichero
            ? (new AdjuntarSoporteFactura())->ejecutar($proveedor, $numeroFactura, $metadatosFichero)
            : null;

        $factura = FacturaProveedorSociedad::create([
            'documento_id'   => $documentoCreado?->id,
            'proveedor_id'   => $proveedor->id,
            'numero_factura' => $numeroFactura,
            'fecha_factura'  => $fecha,
            'importe_base'   => $normalizador->normalizarImporte($importeBase),
            'importe_total'  => $normalizador->normalizarImporte($importeTotal),
        ]);

        foreach ($cuotasIva as $cuota) {
            $importeCuota = $normalizador->normalizarImporte($cuota['importe'] ?? null);
            if ($importeCuota === null || ! isset($cuota['tipo_iva'])) {
                continue;
            }

            CuotaIvaFacturaProveedorSociedad::create([
                'factura_proveedor_sociedad_id' => $factura->id,
                'tipo_iva'                      => $cuota['tipo_iva'],
                'importe'                       => $importeCuota,
            ]);
        }

        return ['proveedor' => $proveedor, 'creado' => $creado];
    }

    /** Si el CIF/NIF ya es proveedor de esta sociedad no hay que preguntar su tipo. */
    public function proveedorExiste(int $sociedadId, ?string $documento): bool
    {
        if (! $documento) {
            return false;
        }

        $persona = PersonaSociedad::where('sociedad_id', $sociedadId)
            ->where('documento_identificativo', $this->normalizarDocumento($documento))
            ->first();

        return $persona && Proveedor::where('persona_type', PersonaSociedad::class)->where('persona_id', $persona->id)->exists();
    }

    public function existeDuplicada(int $sociedadId, string $documento, ?string $numeroFactura, ?string $fecha = null, ?string $importeTotal = null): bool
    {
        $documento = $this->normalizarDocumento($documento);

        $persona = PersonaSociedad::where('sociedad_id', $sociedadId)
            ->where('documento_identificativo', $documento)
            ->first();

        $proveedor = $persona ? Proveedor::where('persona_type', PersonaSociedad::class)->where('persona_id', $persona->id)->first() : null;

        if (! $proveedor) {
            return false;
        }

        $importeTotalNormalizado = (new AltaProveedorDesdeFactura())->normalizarImporte($importeTotal);

        return $this->buscarDuplicada($proveedor->id, $numeroFactura, $fecha, $importeTotalNormalizado) !== null;
    }

    /**
     * Sin número de factura no hay nada fiable que identifique la factura por sí sola: sin
     * este respaldo, un PDF cuyo número no se reconoce se podría importar sin límite (visto
     * en pruebas: el mismo PDF entraba una y otra vez). Con la misma fecha y el mismo
     * importe total ya hay bastante para tratarlo como el mismo papel — solo se compara
     * contra facturas que TAMPOCO tienen número, para no bloquear una numerada de verdad
     * que coincida en fecha por casualidad.
     */
    protected function buscarDuplicada(int $proveedorId, ?string $numeroFactura, ?string $fecha, ?float $importeTotalNormalizado): ?FacturaProveedorSociedad
    {
        if ($numeroFactura) {
            return FacturaProveedorSociedad::where('proveedor_id', $proveedorId)
                ->where('numero_factura', $numeroFactura)
                ->first();
        }

        if (! $fecha) {
            return null;
        }

        return FacturaProveedorSociedad::where('proveedor_id', $proveedorId)
            ->whereNull('numero_factura')
            ->where('fecha_factura', $fecha)
            ->when($importeTotalNormalizado !== null, fn ($q) => $q->where('importe_total', $importeTotalNormalizado))
            ->when($importeTotalNormalizado === null, fn ($q) => $q->whereNull('importe_total'))
            ->first();
    }

    /** @return array{tipo_type?: string, tipo_id?: int} */
    private function datosTipo(?int $tipoProveedorId): array
    {
        return $tipoProveedorId !== null
            ? ['tipo_type' => TipoProveedorSociedad::class, 'tipo_id' => $tipoProveedorId]
            : [];
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
