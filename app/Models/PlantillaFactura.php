<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaFactura extends Model
{
    protected $table = 'plantillas_facturas';

    protected $fillable = [
        'cif',
        'razon_social',
        'hash_imagen',
    ];

    public function campos()
    {
        return $this->hasMany(CampoPlantillaFactura::class);
    }

    /**
     * Guarda (o actualiza) la plantilla de este CIF junto con sus campos anclados.
     * $campos: [tipoCampoId => ['ancla' => string, 'valor' => string]] — solo los
     * campos anclables (CIF, FECHA, NUMERO_FACTURA, IMPORTE, IMPORTE_BASE, IMPORTE_TOTAL);
     * la razón social no lleva ancla, se guarda tal cual (null = no tocar la que ya
     * hubiera). Un campo puede venir anclado por texto ('ancla') o, si no había ninguna
     * etiqueta cerca del valor, por posición en la página ('pagina'/'pos_x'/'pos_y'/
     * 'pos_ancho') — ver ExtractorPorCoordenadas. CUOTA_IVA es distinto: una plantilla
     * puede tener varias (una por cada % de IVA del proveedor), así que su entrada es una
     * lista de esos mismos datos más 'tipo_iva' en vez de un único array. $hashImagen
     * (null = no tocar el que ya hubiera): huella de la imagen de cabecera, para reconocer
     * facturas nuevas de este proveedor cuando su CIF no aparece en el texto (ver
     * LectorPdf::extraerImagenPrincipal).
     */
    public static function guardarDesdeCampos(string $cif, ?string $razonSocial, array $campos, ?string $hashImagen = null): self
    {
        $plantilla = static::firstOrNew(['cif' => $cif]);
        if ($razonSocial !== null) {
            $plantilla->razon_social = $razonSocial;
        }
        if ($hashImagen !== null) {
            $plantilla->hash_imagen = $hashImagen;
        }
        $plantilla->save();

        foreach ($campos as $tipoCampoId => $datos) {
            if ($tipoCampoId === TipoCampoPlantillaFactura::CUOTA_IVA) {
                foreach ($datos as $cuota) {
                    static::guardarCampo($plantilla, $tipoCampoId, $cuota, $cuota['tipo_iva']);
                }

                continue;
            }

            static::guardarCampo($plantilla, $tipoCampoId, $datos);
        }

        return $plantilla;
    }

    protected static function guardarCampo(self $plantilla, int $tipoCampoId, array $datos, ?float $tipoIva = null): void
    {
        if (empty($datos['ancla']) && ($datos['pos_x'] ?? null) === null) {
            return;
        }

        CampoPlantillaFactura::updateOrCreate(
            ['plantilla_factura_id' => $plantilla->id, 'tipo_campo_plantilla_factura_id' => $tipoCampoId, 'tipo_iva' => $tipoIva],
            [
                'texto_ancla'    => $datos['ancla'] ?? null,
                'valor_ejemplo'  => $datos['valor'],
                'delta_columna'  => $datos['delta_columna'] ?? null,
                'delta_lineas'   => $datos['delta_lineas'] ?? null,
                'longitud_valor' => $datos['longitud_valor'] ?? null,
                'pagina'         => $datos['pagina'] ?? null,
                'pos_x'          => $datos['pos_x'] ?? null,
                'pos_y'          => $datos['pos_y'] ?? null,
                'pos_ancho'      => $datos['pos_ancho'] ?? null,
            ]
        );
    }
}
