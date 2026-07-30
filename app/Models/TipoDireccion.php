<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDireccion extends Model
{
    public $timestamps = false;
    protected $table = 'tipo_direcciones';

    // T-L9-L12: los ids del catálogo vienen del cajón legacy tipos_de_tipos y cambian de una
    // instalación a otra (en Bembrive no son los mismos), así que el tipo se resuelve por nombre.
    const
    NOMBRE_DOMICILIO = 'Domicilio',
    NOMBRE_FACTURACION = 'Facturación',
    NOMBRE_DELEGACION = 'Delegación';

    protected static array $idsPorNombre = [];

    public static function idPorNombre(string $nombre): ?int
    {
        return static::$idsPorNombre[$nombre] ??= static::where('nombre', $nombre)->value('id');
    }

    public static function idDomicilio(): ?int
    {
        return static::idPorNombre(self::NOMBRE_DOMICILIO);
    }

    public function isDomicilio(): bool
    {
        return $this->attributes['nombre'] == self::NOMBRE_DOMICILIO;
    }
}
