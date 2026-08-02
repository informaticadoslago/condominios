<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaFactura extends Model
{
    protected $table = 'plantillas_facturas';

    protected $fillable = [
        'cif',
        'razon_social',
    ];

    public function campos()
    {
        return $this->hasMany(CampoPlantillaFactura::class);
    }

    /**
     * Guarda (o actualiza) la plantilla de este CIF junto con sus campos anclados.
     * $campos: [tipoCampoId => ['ancla' => string, 'valor' => string]] — solo los
     * campos anclables (CIF, FECHA, NUMERO_FACTURA, IMPORTE); la razón social no
     * lleva ancla, se guarda tal cual (null = no tocar la que ya hubiera).
     */
    public static function guardarDesdeCampos(string $cif, ?string $razonSocial, array $campos): self
    {
        $plantilla = static::firstOrNew(['cif' => $cif]);
        if ($razonSocial !== null) {
            $plantilla->razon_social = $razonSocial;
        }
        $plantilla->save();

        foreach ($campos as $tipoCampoId => $datos) {
            if (empty($datos['ancla'])) {
                continue;
            }

            CampoPlantillaFactura::updateOrCreate(
                ['plantilla_factura_id' => $plantilla->id, 'tipo_campo_plantilla_factura_id' => $tipoCampoId],
                [
                    'texto_ancla'    => $datos['ancla'],
                    'valor_ejemplo'  => $datos['valor'],
                    'delta_columna'  => $datos['delta_columna'] ?? null,
                    'delta_lineas'   => $datos['delta_lineas'] ?? null,
                    'longitud_valor' => $datos['longitud_valor'] ?? null,
                ]
            );
        }

        return $plantilla;
    }
}
