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
     * campos anclables (CIF, FECHA, NUMERO_FACTURA, IMPORTE); la razón social no
     * lleva ancla, se guarda tal cual (null = no tocar la que ya hubiera). Un campo
     * puede venir anclado por texto ('ancla') o, si no había ninguna etiqueta cerca
     * del valor, por posición en la página ('pagina'/'pos_x'/'pos_y'/'pos_ancho') —
     * ver ExtractorPorCoordenadas. $hashImagen (null = no tocar el que ya hubiera):
     * huella de la imagen de cabecera, para reconocer facturas nuevas de este proveedor
     * cuando su CIF no aparece en el texto (ver LectorPdf::extraerImagenPrincipal).
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
            if (empty($datos['ancla']) && ($datos['pos_x'] ?? null) === null) {
                continue;
            }

            CampoPlantillaFactura::updateOrCreate(
                ['plantilla_factura_id' => $plantilla->id, 'tipo_campo_plantilla_factura_id' => $tipoCampoId],
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

        return $plantilla;
    }
}
