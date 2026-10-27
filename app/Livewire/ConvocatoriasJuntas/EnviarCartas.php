<?php

namespace App\Livewire\ConvocatoriasJuntas;

use App\Mail\CartaConvocatoria;
use App\Models\ConvocatoriaJunta;
use App\Models\Documento;
use App\Models\Inmueble;
use App\Services\ConvocatoriasJuntas\EnviarCartasConvocatoria;
use App\Services\ConvocatoriasJuntas\GenerarCartaConvocatoria;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de envío por correo de la convocatoria. Para: una línea por inmueble, con sus
 * propietarios y el sobre verde (correo validado) o rojo (sin validar), casilla para
 * dejar alguno fuera y "+" para CC/CCO, como el aviso de cargo de las remesas. Debajo, el
 * asunto y el texto del correo (de partida, los de fábrica) y los adjuntos: la carta de
 * cada inmueble y los documentos de la convocatoria, cada uno con su casilla (todos marcados
 * de partida). Asunto, texto y adjuntos son los mismos para todos los correos.
 */
class EnviarCartas extends Component
{
    public bool $abrir = false;

    public ?int $convocatoriaId = null;

    /** Ver EnviarCartasConvocatoria::lineas(). */
    public array $lineas = [];

    /** inmueble_id marcados (como texto: así los devuelven las casillas). */
    public array $seleccion = [];

    /** inmueble_id con los campos de CC/CCO abiertos ("+" de la línea). */
    public array $conCopia = [];

    /** CC y CCO de cada línea, por inmueble_id. */
    public array $cc = [];
    public array $cco = [];

    public string $asunto = '';
    public string $texto = '';

    /** Si va adjunta la carta (la de su inmueble, en cada correo). */
    public bool $conCarta = true;

    /** Documentos de la convocatoria: ['id' => int, 'nombre' => string, 'tamano' => string]. */
    public array $documentos = [];

    /** id de los documentos marcados para adjuntar (como texto, como $seleccion). */
    public array $documentosMarcados = [];

    #[On('abrir-envio-convocatoria-junta')]
    public function abrir($id, EnviarCartasConvocatoria $servicio, GenerarCartaConvocatoria $generar): void
    {
        $convocatoria = $this->convocatoria($id);

        if (! $convocatoria) {
            return;
        }

        // Si aún no tiene carta, se genera ya: así la del correo y la impresa llevan la
        // misma fecha (la del día en que se generó).
        if (! $convocatoria->tieneCarta()) {
            $generar->ejecutar($convocatoria);
            $this->dispatch('convocatoria-junta-guardada');
        }

        $lineas = $servicio->lineas($convocatoria);

        $this->convocatoriaId = $convocatoria->id;
        $this->lineas         = $lineas->all();
        // Sin validar no empieza marcado: no está confirmado que esa dirección sea suya.
        $this->seleccion      = $lineas->where('validado', true)
            ->pluck('inmueble_id')->map(fn ($id) => (string) $id)->all();
        $this->conCopia       = [];
        $this->cc             = [];
        $this->cco            = [];
        $this->asunto         = CartaConvocatoria::asuntoPorDefecto($convocatoria);
        $this->texto          = CartaConvocatoria::textoPorDefecto($convocatoria);
        $this->conCarta       = true;
        $this->documentos     = $convocatoria->documentos()->orderBy('id')->get()
            ->map(fn (Documento $documento) => [
                'id'     => $documento->id,
                'nombre' => $documento->nombre_mostrado,
                'tamano' => $documento->tamano,
            ])->all();
        $this->documentosMarcados = array_map(fn ($documento) => (string) $documento['id'], $this->documentos);
        $this->resetValidation();
        $this->abrir          = true;
    }

    /** Casilla de cabecera: marca o desmarca todas las que tienen a quién escribir. */
    public function toggleTodos(): void
    {
        $conCorreo = collect($this->lineas)->where('con_correo', true)->pluck('inmueble_id')->map(fn ($id) => (string) $id);

        $this->seleccion = count($this->seleccion) === $conCorreo->count() ? [] : $conCorreo->all();
    }

    /** Abre o cierra los campos de CC/CCO de una línea. */
    public function toggleConCopia(int $inmuebleId): void
    {
        if (in_array($inmuebleId, $this->conCopia, true)) {
            $this->conCopia = array_values(array_diff($this->conCopia, [$inmuebleId]));
        } else {
            $this->conCopia[] = $inmuebleId;
        }
    }

    public function enviar(EnviarCartasConvocatoria $servicio): void
    {
        $convocatoria = $this->convocatoria($this->convocatoriaId);

        if (! $convocatoria) {
            return;
        }

        if ($this->seleccion === []) {
            $this->dispatch('toast-error', ['title' => __('No queda ningún inmueble marcado')]);

            return;
        }

        $this->validate([
            // 150: lo que cabe en correos_enviados.asunto.
            'asunto' => ['required', 'string', 'max:150'],
            'texto'  => ['required', 'string', 'max:5000'],
            'cc.*'   => ['nullable', 'email'],
            'cco.*'  => ['nullable', 'email'],
        ], [], [
            'asunto' => __('Asunto'),
            'texto'  => __('Texto'),
            'cc.*'   => __('CC'),
            'cco.*'  => __('CCO'),
        ]);

        $inmuebles = Inmueble::where('comunidad_id', $convocatoria->comunidad_id)
            ->whereIn('id', array_map('intval', $this->seleccion))
            ->get();

        // Cruzados con los de la convocatoria: un id ajeno colado en la casilla no se adjunta.
        $documentoIds = $convocatoria->documentos()
            ->whereIn('id', array_map('intval', $this->documentosMarcados))
            ->pluck('id')->all();

        $enviados = $inmuebles->sum(fn (Inmueble $inmueble) => $servicio->enviarInmueble(
            $convocatoria,
            $inmueble,
            cc: $this->cc[$inmueble->id] ?? null,
            cco: $this->cco[$inmueble->id] ?? null,
            documentoIds: $documentoIds,
            conCarta: $this->conCarta,
            asunto: trim($this->asunto),
            texto: trim($this->texto),
        ));

        $this->cerrar();

        $this->dispatch($enviados > 0 ? 'toast-success' : 'toast-error', [
            'title' => $enviados > 0
                ? __('Convocatoria enviada: :n correos', ['n' => $enviados])
                : __('No se ha enviado nada: ningún propietario marcado tiene correo'),
        ]);
    }

    public function cerrar(): void
    {
        $this->abrir = false;
        $this->reset(['convocatoriaId', 'lineas', 'seleccion', 'conCopia', 'cc', 'cco', 'asunto', 'texto', 'conCarta', 'documentos', 'documentosMarcados']);
    }

    /** Solo de la comunidad activa: mismo scope que la lista. */
    private function convocatoria($id): ?ConvocatoriaJunta
    {
        return $id ? ConvocatoriaJunta::where('comunidad_id', session('comunidad_actual_id'))->find($id) : null;
    }

    public function render()
    {
        return view('livewire.convocatorias-juntas.enviar-cartas');
    }
}
