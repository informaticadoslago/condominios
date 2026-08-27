<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SociedadDireccion extends Model
{
    use ConHistorialEstado;

    protected $table = 'sociedad_direcciones';

    protected $fillable = [
        'sociedad_id',
        'direccion1',
        'numero',
        'piso',
        'puerta',
        'codigo_postal',
        'provincia_id',
        'municipio_id',
        'estado_id',
        'es_centro_trabajo',
    ];

    protected $casts = [
        'es_centro_trabajo' => 'boolean',
    ];

    public function sociedad()
    {
        return $this->belongsTo(Sociedad::class);
    }

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoDireccionSociedad::class, 'estado_id');
    }

    public function scopeCentrosDeTrabajo($query)
    {
        return $query->where('es_centro_trabajo', true);
    }

    /**
     * Convierte esta dirección en el domicilio social de su sociedad: la que lo
     * era antes pasa a Sede (ConHistorialEstado deja rastro de las dos
     * transiciones). Solo puede haber una activa por sociedad; lo garantiza este
     * método, no una constraint de BD.
     */
    public function marcarComoDomicilioSocial(): void
    {
        DB::transaction(function () {
            $this->sociedad->direcciones()
                ->where('estado_id', EstadoDireccionSociedad::DOMICILIO_SOCIAL)
                ->where('id', '!=', $this->id)
                ->each(fn (self $anterior) => $anterior->update(['estado_id' => EstadoDireccionSociedad::SEDE]));

            $this->update(['estado_id' => EstadoDireccionSociedad::DOMICILIO_SOCIAL]);
        });
    }
}
