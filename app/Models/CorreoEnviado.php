<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Constancia de un correo enviado (o encolado): cuándo, a qué dirección, con qué asunto,
 * de qué tipo (la clase Mailable usada) y quién lo lanzó (nulo si fue un proceso
 * automático). `recibo_id` solo se rellena en los correos ligados a un recibo (aviso de
 * remesa, transferencia o devolución); el resto lo dejan a null.
 *
 * No guarda el cuerpo del correo. Sirve para responder «¿se le avisó, y cuándo?», no
 * para reproducir lo que se le mandó.
 */
class CorreoEnviado extends Model
{
    protected $table = 'correos_enviados';

    protected $fillable = [
        'tipo',
        'recibo_id',
        'asunto',
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
