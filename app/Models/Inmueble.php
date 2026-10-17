<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inmueble extends Model
{
    protected $fillable = [
        'comunidad_id',
        'ocupacion_id',
        'tipo_inmueble_id',
        'planta',
        'puerta',
        'nomenclatura',
        'coeficiente',
        'referencia_catastral',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:3',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function ocupacion()
    {
        return $this->belongsTo(TipoOcupacion::class, 'ocupacion_id');
    }

    public function tipoInmueble()
    {
        return $this->belongsTo(TipoInmueble::class);
    }

    /** Propietarios VIGENTES (titularidad sin fecha_fin). Para el histórico, ver titularidades(). */
    public function propietarios()
    {
        return $this->belongsToMany(Propietario::class, 'titularidades')
            ->withPivot(['cuota_percent', 'causa', 'fecha_inicio', 'fecha_fin'])
            ->wherePivotNull('fecha_fin');
    }

    public function titularidades()
    {
        return $this->hasMany(Titularidad::class);
    }

    public function formasPago()
    {
        return $this->hasMany(FormaPagoInmueble::class);
    }

    /** Forma de pago (y cuenta bancaria, si es recibo bancario) VIGENTE. Para el histórico, ver formasPago(). */
    public function formaPagoVigente()
    {
        return $this->hasOne(FormaPagoInmueble::class)->whereNull('fecha_fin');
    }

    public function gruposDeReparto()
    {
        return $this->belongsToMany(GrupoDeReparto::class, 'inmueble_grupo_de_reparto', 'inmueble_id', 'grupo_de_reparto_id')
            ->withPivot('coeficiente')
            ->withTimestamps();
    }

    /**
     * Nomenclatura que propone la ficha a partir de tipo, planta y puerta: "1ºA", "4º IZQDA",
     * "Bajo", y sin puerta, con el tipo delante ("Piso 3º"), que el número solo queda raro.
     * La planta 0 es el "Bajo"; una que no es número ("ÁTICO") se deja tal cual.
     */
    public static function sugerirNomenclatura(?string $tipo, $planta, $puerta): string
    {
        $planta = trim((string) $planta);
        $puerta = trim((string) $puerta);

        if ($planta === '') {
            return $puerta;
        }

        if (is_numeric($planta)) {
            $planta = (int) $planta === 0 ? 'Bajo' : ((int) $planta).'º';
        }

        if ($puerta !== '') {
            // Pegada solo la letra suelta tras el ordinal ("1ºA"); "4º IZQDA", "Bajo A".
            return $planta.(str_ends_with($planta, 'º') && mb_strlen($puerta) === 1 ? '' : ' ').$puerta;
        }

        return $planta === 'Bajo' ? $planta : trim(trim((string) $tipo).' '.$planta);
    }

    /** La de la ficha; si no tiene (alta por otra vía que no es la ficha), la propuesta. */
    public function nomenclaturaOSugerida(): string
    {
        return trim((string) $this->nomenclatura)
            ?: self::sugerirNomenclatura($this->tipoInmueble?->descripcion, $this->planta, $this->puerta);
    }

    /** Orden natural de los inmuebles de un edificio: por planta (numérica primero) y puerta. */
    public static function ordenarPorPlanta($inmuebles)
    {
        return $inmuebles->sortBy([
            fn ($a, $b) => [! is_numeric($a->planta), (int) $a->planta, (string) $a->planta] <=> [! is_numeric($b->planta), (int) $b->planta, (string) $b->planta],
            fn ($a, $b) => strnatcasecmp((string) $a->puerta, (string) $b->puerta),
        ])->values();
    }
}
