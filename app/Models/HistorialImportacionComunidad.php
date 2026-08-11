<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rastro de cada importación de comunidad desde ZIP: qué CIF traía, en qué comunidad
 * acabó, si se quedó enlazada a contabilidad y qué avisos de saneo saltaron. El CIF y el
 * nombre se copian tal cual en el momento de importar, no por join: si la comunidad se
 * borra después, la fila tiene que seguir contando lo que pasó.
 */
class HistorialImportacionComunidad extends Model
{
    protected $table = 'historial_importaciones_comunidades';

    protected $fillable = [
        'comunidad_id',
        'cif',
        'nombre_comunidad',
        'nombre_fichero',
        'enlazado_contabilidad',
        'avisos',
        'user_id',
    ];

    protected $casts = [
        'enlazado_contabilidad' => 'boolean',
        'avisos' => 'array',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
