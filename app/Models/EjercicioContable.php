<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjercicioContable extends Model
{
    protected $table = 'ejercicio_contables';

    protected $fillable = [
        'empresa_contable_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'cerrado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'cerrado' => 'boolean',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function asientosContables()
    {
        return $this->hasMany(AsientoContable::class);
    }

    /**
     * Nombre del ejercicio al que pertenece una fecha, que es lo que pide un asiento.
     *
     * Se busca por la fecha, no por el año: quien lleva los libros decide cómo se llama y
     * cuándo empieza. Si no hay ninguno que la contenga se devuelve el año, para que sea
     * la contabilidad quien dé el error con su propio mensaje.
     */
    public static function nombrePara(int $empresaContableId, string $fecha): string
    {
        $ejercicio = static::where('empresa_contable_id', $empresaContableId)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha)
            ->first();

        return $ejercicio?->nombre ?? substr($fecha, 0, 4);
    }
}
