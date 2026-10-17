<?php

namespace App\Mail;

use App\Models\ConvocatoriaJunta;
use App\Models\Inmueble;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Manda a un propietario la convocatoria de junta de uno de sus inmuebles, con la carta de
 * ese inmueble en PDF adjunta (la misma que enseña el ojo del modal de envío).
 *
 * El PDF se hace al enviarse (en la cola), no al encolar: así no viaja por la tabla jobs.
 */
class CartaConvocatoria extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Protected, no public: ver AvisoRemesa (una pública pisaría los datos de la vista). */
    public function __construct(
        protected ConvocatoriaJunta $convocatoria,
        protected Inmueble $inmueble,
        protected ?string $nombre = null,
        ?string $idioma = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public function asunto(): string
    {
        return __('Convocatoria de Junta General :tipo — :comunidad', [
            'tipo'      => $this->convocatoria->tipoJunta?->descripcion,
            'comunidad' => $this->convocatoria->comunidad?->nombre,
        ]);
    }

    public function build()
    {
        $comunidad = $this->convocatoria->comunidad;

        return $this->subject($this->asunto())
            ->view('emails.carta-convocatoria', [
                'nombre'         => $this->nombre,
                'comunidad'      => $comunidad?->nombre,
                'tipo'           => $this->convocatoria->tipoJunta?->descripcion,
                'fecha'          => $this->convocatoria->fecha->locale('es')->translatedFormat('j \d\e F \d\e Y'),
                'horaPrimera'    => substr($this->convocatoria->hora_primera_convocatoria, 0, 5),
                'horaSegunda'    => substr($this->convocatoria->hora_segunda_convocatoria, 0, 5),
                'inmueble'       => $this->inmueble->nomenclaturaOSugerida(),
                'correoContacto' => $comunidad?->correo_contacto,
            ])
            ->attachData(
                app(GenerarCartaConvocatoria::class)->pdfInmueble($this->convocatoria, $this->inmueble),
                $this->nombreAdjunto(),
                ['mime' => 'application/pdf'],
            );
    }

    /** "convocatoria-2026-10-28-1oa.pdf": la fecha de la junta y el inmueble. */
    public function nombreAdjunto(): string
    {
        return 'convocatoria-'.$this->convocatoria->fecha->format('Y-m-d').'-'.Str::slug($this->inmueble->nomenclaturaOSugerida()).'.pdf';
    }
}
