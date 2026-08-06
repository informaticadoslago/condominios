<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Constancia de un aviso mandado por un recibo: cuándo, a qué dirección, por qué motivo
 * y quién lo lanzó (nulo si fue un proceso automático).
 *
 * No guarda el cuerpo del correo. Sirve para responder «¿se le avisó, y cuándo?» cuando
 * alguien dice que no se enteró, no para reproducir lo que se le mandó.
 */
class AvisoRecibo extends Model
{
    protected $table = 'avisos_recibos';

    protected $fillable = [
        'recibo_id',
        'motivo',
        'destinatario',
        'enviado_at',
        'user_id',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
    ];

    public function recibo()
    {
        return $this->belongsTo(Recibo::class);
    }

    /** Quién lo mandó; nulo si lo mandó un proceso, no una persona. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function esAutomatico(): bool
    {
        return $this->user_id === null;
    }
}
