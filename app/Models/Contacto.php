<?php

namespace App\Models;

use App\Models\Traits\ConCopiaAlBorrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Contacto polimórfico (tabla 'contactos'). Un contacto (teléfono, email…)
 * pertenece a cualquier contactable (Persona, etc.). Evolución del 'contactos'
 * de L9, ya polimórfico + estado_id en L12.
 */
class Contacto extends Model
{
    use ConCopiaAlBorrar;

    protected $table = 'contactos';

    protected $fillable = [
        'tipo_contacto_id',
        'descripcion',
        'valor',
        'verified_at',
        'estado_id',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /** ¿El contacto está validado? */
    public function estaValidado(): bool
    {
        return ! is_null($this->verified_at);
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipo()
    {
        return $this->belongsTo(TipoContacto::class, 'tipo_contacto_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', Estado::ESTADO_ACTIVO);
    }
}
