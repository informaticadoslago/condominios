<?php

namespace App\Mail;

use App\Models\ConvocatoriaJunta;
use App\Models\Documento;
use App\Models\Inmueble;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Manda a un propietario la convocatoria de junta de uno de sus inmuebles: el asunto y el
 * texto que se escribieron en el modal de envío (o los de fábrica, ver asuntoPorDefecto y
 * textoPorDefecto), con el saludo a su nombre y los datos de la junta y de su inmueble.
 * Adjuntos, los que se dejaron en el modal: la carta de ese inmueble en PDF (la misma que
 * enseña el ojo) y los documentos de la convocatoria.
 *
 * El PDF se hace al enviarse (en la cola), no al encolar: así no viaja por la tabla jobs.
 * Los documentos, igual: viajan sus ids y el fichero se lee del disco al enviarse. Uno que
 * ya no esté (borrado entre medias) se salta: mejor el correo sin él que no mandar nada.
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
        /** @var array<int, int> */
        protected array $documentoIds = [],
        protected bool $conCarta = true,
        protected ?string $asunto = null,
        protected ?string $texto = null,
    ) {
        $this->onQueue('EnviarCorreo');
        $this->locale($idioma ?? config('app.locale'));
    }

    public static function asuntoPorDefecto(ConvocatoriaJunta $convocatoria): string
    {
        return __('Convocatoria de Junta General :tipo — :comunidad', [
            'tipo'      => $convocatoria->tipoJunta?->descripcion,
            'comunidad' => $convocatoria->comunidad?->nombre,
        ]);
    }

    /** Lo que sale de partida en el Texto del modal. El saludo no: va en cada correo con su nombre. */
    public static function textoPorDefecto(ConvocatoriaJunta $convocatoria): string
    {
        return __('Te enviamos la convocatoria de la Junta General :tipo de :comunidad. En la carta adjunta están el lugar de la reunión, el orden del día y la autorización para delegar el voto si no puedes asistir.', [
            'tipo'      => $convocatoria->tipoJunta?->descripcion,
            'comunidad' => $convocatoria->comunidad?->nombre,
        ]);
    }

    public function asunto(): string
    {
        return filled($this->asunto) ? $this->asunto : self::asuntoPorDefecto($this->convocatoria);
    }

    public function build()
    {
        $comunidad  = $this->convocatoria->comunidad;
        $documentos = $this->documentos();

        $this->subject($this->asunto())
            ->view('emails.carta-convocatoria', [
                'nombre'         => $this->nombre,
                'comunidad'      => $comunidad?->nombre,
                'fecha'          => $this->convocatoria->fecha->locale('es')->translatedFormat('j \d\e F \d\e Y'),
                'horaPrimera'    => substr($this->convocatoria->hora_primera_convocatoria, 0, 5),
                'horaSegunda'    => substr($this->convocatoria->hora_segunda_convocatoria, 0, 5),
                'inmueble'       => $this->inmueble->nomenclaturaOSugerida(),
                'correoContacto' => $comunidad?->correo_contacto,
                'texto'          => filled($this->texto) ? $this->texto : self::textoPorDefecto($this->convocatoria),
            ]);

        if ($this->conCarta) {
            $this->attachData(
                app(GenerarCartaConvocatoria::class)->pdfInmueble($this->convocatoria, $this->inmueble),
                $this->nombreAdjunto(),
                ['mime' => 'application/pdf'],
            );
        }

        foreach ($documentos as $documento) {
            $this->attachFromStorageDisk(config('documentos.disco'), $documento->ruta, $documento->nombre_mostrado);
        }

        return $this;
    }

    /** Nombres de todo lo que va adjunto, la carta la primera: lo que queda en correos_enviados. */
    public function nombresAdjuntos(): array
    {
        return [
            ...($this->conCarta ? [$this->nombreAdjunto()] : []),
            ...$this->documentos()->map->nombre_mostrado,
        ];
    }

    /** Los documentos marcados que siguen siendo de la convocatoria y tienen su fichero. */
    private function documentos(): Collection
    {
        return $this->convocatoria->documentos()
            ->whereIn('id', $this->documentoIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (Documento $documento) => $documento->existeFichero())
            ->values();
    }

    /** "convocatoria-2026-10-28-1oa.pdf": la fecha de la junta y el inmueble. */
    public function nombreAdjunto(): string
    {
        return 'convocatoria-'.$this->convocatoria->fecha->format('Y-m-d').'-'.Str::slug($this->inmueble->nomenclaturaOSugerida()).'.pdf';
    }
}
