<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\Titularidad;
use Livewire\Attributes\On;

/**
 * Paso: propietarios del inmueble.
 *
 * Nada de esto es real hasta "Terminar": las líneas que se van añadiendo (o
 * quitando, o editando) se acumulan en el borrador (payload JSON), tanto en
 * alta nueva como editando un inmueble ya existente (cuyo estado se copió al
 * borrador al entrar, ver Inmuebles\Formulario). Por eso "quitar"/"editar" aquí
 * son reversibles sin más: solo tocan el array en memoria + el borrador, no
 * ninguna Titularidad real. La reconciliación con lo que hay en BD (crear lo
 * nuevo, actualizar lo que cambió, cerrar lo que ya no está) pasa una única vez,
 * en el último paso, ver DatosFinancierosStep::terminar().
 */
class PropietariosStep extends CrearInmuebleStep
{

    public ?int $inmuebleId = null;
    public ?int $comunidad_id = null;

    /** [['ref', 'titularidad_id', 'persona_comunidad_id', 'persona_nueva', 'nombre', 'cuota_percent', 'causa', 'fecha_inicio'], …] */
    public array $propietarios = [];

    /**
     * Titularidades reales quitadas en esta sesión, con la fecha_fin elegida:
     * [['titularidad_id', 'nombre', 'cuota_percent', 'causa', 'fecha_inicio', 'fecha_fin'], …].
     * Lleva los mismos datos que tenía la línea en $propietarios (no solo el id) para
     * poder pintarla como histórico en pantalla sin esperar a "Terminar" ni consultar
     * la BD — todavía no es real ahí, solo vive en el borrador.
     */
    public array $propietariosQuitados = [];
    public bool $cargado = false;

    // Quitar una titularidad ya real: en vez de borrarla, pide desde cuándo deja de
    // estarlo (nunca hay opción de borrar la titularidad de verdad, ver DatosFinancierosStep::terminar()).
    public $quitandoId = null;
    public ?string $quitar_fecha_fin = null;

    // Persona a añadir: existente (buscador).
    public ?int $persona_id      = null;
    public string $personaNombre = '';
    public string $personaBusqueda  = '';
    public array $personaResultados = [];

    // Cuota, causa y fecha de inicio de la línea que se va a abrir. La fecha por
    // defecto es hoy, pero editable: hace falta poder poner una fecha pasada al
    // migrar un histórico o al dar de alta un propietario que ya lo era desde antes.
    public $cuota_percent = null;
    public string $causa  = Titularidad::CAUSA_COMPRAVENTA;
    public ?string $fecha_inicio = null;

    // Alta de persona nueva: ya no es un formulario aparte aquí — abre el wizard
    // completo de Propietario (datos+dirección+contactos+cuenta) embebido en un
    // modal (ver la vista). Al terminar dispara 'propietario-creado' y se
    // selecciona sola (ver propietarioCreado()). El contador cambia la wire:key del
    // componente embebido cada vez que se abre, para que arranque limpio.
    public bool $modalPropietarioAbierto = false;
    public int $modalPropietarioContador = 0;

    // Editar un propietario ya añadido a la lista (datos, cuenta bancaria…), con el
    // mismo modal: propietarioIdParaModal fija el modo edición del wizard embebido, y
    // editandoRefModal recuerda qué línea de la lista hay que refrescar al terminar.
    public ?int $propietarioIdParaModal = null;
    public $editandoRefModal = null;

    // Edición de una línea ya añadida (siempre en memoria/borrador).
    public $editandoId = null;
    public $edit_cuota_percent    = null;
    public string $edit_causa     = Titularidad::CAUSA_COMPRAVENTA;
    public ?string $edit_fecha_inicio = null;

    public function stepInfo(): array
    {
        return ['label' => __('Propietarios')];
    }

    public function mount()
    {
        if (! $this->comunidad_id) {
            $this->comunidad_id = $this->inmuebleId
                ? Inmueble::find($this->inmuebleId)?->comunidad_id
                : session('comunidad_actual_id');
        }

        if (! $this->cargado) {
            $this->cargarPropietarios();
        }

        $this->fecha_inicio ??= now()->toDateString();
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session('inmueble_borrador_id');

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId) : null;
    }

    /** Admiten coma o punto como separador decimal: se normalizan a punto antes de validar. */
    public function updatedCuotaPercent($value)
    {
        $this->cuota_percent = str_replace(',', '.', $value);
    }

    public function updatedEditCuotaPercent($value)
    {
        $this->edit_cuota_percent = str_replace(',', '.', $value);
    }

    public function causas(): array
    {
        return [
            Titularidad::CAUSA_COMPRAVENTA => __('Compraventa'),
            Titularidad::CAUSA_HERENCIA    => __('Herencia'),
            Titularidad::CAUSA_DONACION    => __('Donación'),
            Titularidad::CAUSA_DIVORCIO    => __('Divorcio/separación'),
        ];
    }

    private function cargarPropietarios(): void
    {
        $payload = $this->borradorActual()?->payload ?? [];
        $this->propietarios = $payload['propietarios'] ?? [];
        $this->propietariosQuitados = $payload['propietarios_quitados'] ?? [];
        $this->cargado = true;
    }

    // --- Buscador de persona existente ---
    public function updatedPersonaBusqueda()
    {
        $busqueda = trim($this->personaBusqueda);
        if (mb_strlen($busqueda) < 2) {
            $this->personaResultados = [];

            return;
        }
        $this->personaResultados = PersonaComunidad::where('comunidad_id', $this->comunidad_id)
            ->where(function ($query) use ($busqueda) {
                $query->buscarNombreCompleto($busqueda)->orWhere('documento_identificativo', 'like', "%{$busqueda}%");
            })
            // Un propietario dado de baja no se puede volver a elegir aquí (hay que
            // reactivarlo desde Propietarios primero).
            ->whereDoesntHave('propietario', fn ($q) => $q->where('estado_id', Propietario::ESTADO_BAJA))
            ->limit(8)->get()
            ->map(fn ($persona) => ['id' => $persona->id, 'texto' => ($persona->documento_identificativo ?? '').' — '.$persona->nombreCompleto])->all();
    }

    public function seleccionarPersona($id)
    {
        $persona = PersonaComunidad::find($id);
        if (! $persona) {
            return;
        }
        $this->persona_id        = $persona->id;
        $this->personaNombre     = ($persona->documento_identificativo ?? '').' — '.$persona->nombreCompleto;
        $this->personaBusqueda   = '';
        $this->personaResultados = [];
    }

    public function quitarSeleccion()
    {
        $this->persona_id        = null;
        $this->personaNombre     = '';
        $this->personaBusqueda   = '';
        $this->personaResultados = [];
    }

    // --- Alta de persona nueva: abre el wizard completo de Propietario en un modal ---
    public function abrirModalPropietario(): void
    {
        $this->quitarSeleccion();
        $this->propietarioIdParaModal = null;
        $this->editandoRefModal = null;
        $this->modalPropietarioContador++;
        $this->modalPropietarioAbierto = true;
    }

    /**
     * Editar un propietario ya en la lista (lápiz junto a su nombre): mismo modal,
     * pero en modo edición — abre su wizard ya con sus datos, hasta la cuenta
     * bancaria. Al terminar, solo se refresca el nombre mostrado (ver propietarioCreado()).
     */
    public function editarPropietarioModal($ref)
    {
        $linea = collect($this->propietarios)->firstWhere('ref', $ref);
        if (! $linea || empty($linea['persona_comunidad_id'])) {
            return;
        }

        $this->propietarioIdParaModal = Propietario::where('persona_comunidad_id', $linea['persona_comunidad_id'])->value('id');
        $this->editandoRefModal = $ref;

        // El wizard embebido reutiliza un borrador de sesión si lo hay (para poder
        // retomarlo): si el que queda ahí es de OTRO propietario (p.ej. un alta nueva
        // a medias que se abandonó), no vale para esta edición.
        $borradorId = session('propietario_borrador_id_modal');
        if ($borradorId) {
            $borrador = Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId);
            if (! $borrador || ($borrador->payload['propietario_id'] ?? null) != $this->propietarioIdParaModal) {
                session()->forget('propietario_borrador_id_modal');
            }
        }

        $this->modalPropietarioContador++;
        $this->modalPropietarioAbierto = true;
    }

    #[On('cerrar-modal-propietario')]
    public function cerrarModalPropietario(): void
    {
        $this->modalPropietarioAbierto = false;
        $this->editandoRefModal = null;
    }

    /**
     * El wizard embebido terminó. Si era una edición (ver editarPropietarioModal()), solo
     * refresca el nombre mostrado de esa línea; si era un alta nueva, selecciona al
     * propietario recién creado para añadirlo (comportamiento de siempre).
     */
    #[On('propietario-creado')]
    public function propietarioCreado($id, $nombre = null): void
    {
        $this->modalPropietarioAbierto = false;

        if ($this->editandoRefModal !== null) {
            $this->propietarios = collect($this->propietarios)->map(function ($p) use ($nombre) {
                if ($p['ref'] === $this->editandoRefModal) {
                    $p['nombre'] = $nombre;
                }

                return $p;
            })->all();
            $this->guardarBorradorPropietarios();
            $this->editandoRefModal = null;

            return;
        }

        $propietario = Propietario::find($id);
        if ($propietario) {
            $this->seleccionarPersona($propietario->persona_comunidad_id);
        }
    }

    // --- Añadir a la lista: acumula en el borrador, nada real todavía ---
    public function agregarPropietario()
    {
        if (! $this->persona_id) {
            $this->addError('persona_id', __('Selecciona o da de alta a la persona del propietario.'));

            return;
        }

        $this->validate([
            'cuota_percent' => ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'causa'         => ['required', 'in:'.implode(',', array_keys($this->causas()))],
            'fecha_inicio'  => ['required', 'date'],
        ], [], ['cuota_percent' => __('cuota de propiedad'), 'fecha_inicio' => __('fecha de inicio')]);

        if (collect($this->propietarios)->contains('persona_comunidad_id', $this->persona_id)) {
            $this->addError('persona_id', __('Ese propietario ya está en este inmueble.'));

            return;
        }

        $linea = [
            'titularidad_id'       => $this->titularidadRealDe($this->persona_id),
            'persona_comunidad_id' => $this->persona_id,
            'persona_nueva'        => null,
            'nombre'               => $this->personaNombre,
        ];

        $linea['cuota_percent'] = (float) $this->cuota_percent;
        $linea['causa']         = $this->causa;
        $linea['fecha_inicio']  = $this->fecha_inicio;
        $linea['ref']           = count($this->propietarios) ? max(array_column($this->propietarios, 'ref')) + 1 : 0;

        $this->propietarios[] = $linea;
        $this->guardarBorradorPropietarios();
        $this->resetFormularioPropietario();
    }

    /**
     * Si esta persona ya era titular real de este inmueble, se reengancha a SU
     * titularidad real en vez de crear una duplicada al añadirla por el buscador —
     * nunca debe haber dos titularidades vigentes del mismo propietario en el mismo
     * inmueble. Cubre sobre todo el caso de quitarla y volver a añadirla sin haber
     * terminado el wizard todavía (se deshace el "quitar").
     */
    private function titularidadRealDe(int $personaComunidadId): ?int
    {
        if (! $this->inmuebleId) {
            return null;
        }

        $quitadaIndex = collect($this->propietariosQuitados)->search(
            fn ($q) => Titularidad::find($q['titularidad_id'])?->propietario?->persona_comunidad_id == $personaComunidadId
        );

        if ($quitadaIndex !== false) {
            $titularidadId = $this->propietariosQuitados[$quitadaIndex]['titularidad_id'];
            unset($this->propietariosQuitados[$quitadaIndex]);
            $this->propietariosQuitados = array_values($this->propietariosQuitados);

            return $titularidadId;
        }

        return Titularidad::vigente()
            ->where('inmueble_id', $this->inmuebleId)
            ->whereHas('propietario', fn ($q) => $q->where('persona_comunidad_id', $personaComunidadId))
            ->value('id');
    }

    private function guardarBorradorPropietarios(): void
    {
        $borrador = $this->borradorActual();
        if (! $borrador) {
            return;
        }
        $payload = $borrador->payload ?? [];
        $payload['propietarios'] = $this->propietarios;
        $payload['propietarios_quitados'] = $this->propietariosQuitados;
        $borrador->update(['payload' => $payload]);
    }

    private function resetFormularioPropietario(): void
    {
        $this->quitarSeleccion();
        $this->cuota_percent = null;
        $this->causa         = Titularidad::CAUSA_COMPRAVENTA;
        $this->fecha_inicio  = now()->toDateString();
        $this->resetErrorBag();
    }

    /**
     * Quitar: si la línea nunca ha sido una titularidad real (persona añadida en esta
     * misma sesión, todavía sin guardar), se quita sin más. Si SÍ lo es, no se borra
     * nunca de verdad — se pide desde cuándo deja de estarlo (ver guardarQuitarPropietario).
     */
    public function quitarPropietario($ref)
    {
        $linea = collect($this->propietarios)->firstWhere('ref', $ref);
        if (! $linea) {
            return;
        }

        if (! $linea['titularidad_id']) {
            $this->propietarios = array_values(array_filter($this->propietarios, fn ($p) => $p['ref'] !== $ref));
            $this->guardarBorradorPropietarios();

            return;
        }

        $this->quitandoId = $ref;
        $this->quitar_fecha_fin = now()->toDateString();
    }

    public function cancelarQuitar()
    {
        $this->quitandoId = null;
        $this->quitar_fecha_fin = null;
        $this->resetErrorBag();
    }

    /** Cierra la titularidad real con la fecha_fin elegida (nunca la borra: ver DatosFinancierosStep::terminar()). */
    public function guardarQuitarPropietario()
    {
        $this->validate([
            'quitar_fecha_fin' => ['required', 'date'],
        ], [], ['quitar_fecha_fin' => __('fecha de fin')]);

        $linea = collect($this->propietarios)->firstWhere('ref', $this->quitandoId);
        if ($linea) {
            $this->propietariosQuitados[] = [
                'titularidad_id' => $linea['titularidad_id'],
                'nombre'         => $linea['nombre'],
                'cuota_percent'  => $linea['cuota_percent'],
                'causa'          => $linea['causa'],
                'fecha_inicio'   => $linea['fecha_inicio'],
                'fecha_fin'      => $this->quitar_fecha_fin,
            ];
        }

        $this->propietarios = array_values(array_filter($this->propietarios, fn ($p) => $p['ref'] !== $this->quitandoId));
        $this->guardarBorradorPropietarios();

        $this->quitandoId = null;
        $this->quitar_fecha_fin = null;
    }

    // --- Editar: cambia cuota/causa de una línea ya añadida ---
    public function activarEdicion($ref)
    {
        $linea = collect($this->propietarios)->firstWhere('ref', $ref);
        if (! $linea) {
            return;
        }
        $this->editandoId         = $ref;
        $this->edit_cuota_percent = $linea['cuota_percent'];
        $this->edit_causa         = $linea['causa'];
        $this->edit_fecha_inicio  = $linea['fecha_inicio'] ?? now()->toDateString();
    }

    public function cancelarEdicion()
    {
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    public function guardarEdicion()
    {
        $this->validate([
            'edit_cuota_percent' => ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'edit_causa'         => ['required', 'in:'.implode(',', array_keys($this->causas()))],
            'edit_fecha_inicio'  => ['required', 'date'],
        ], [], ['edit_cuota_percent' => __('cuota de propiedad'), 'edit_fecha_inicio' => __('fecha de inicio')]);

        foreach ($this->propietarios as &$linea) {
            if ($linea['ref'] === $this->editandoId) {
                $linea['cuota_percent'] = (float) $this->edit_cuota_percent;
                $linea['causa']         = $this->edit_causa;
                $linea['fecha_inicio']  = $this->edit_fecha_inicio;
            }
        }
        unset($linea);

        $this->guardarBorradorPropietarios();
        $this->editandoId = null;
    }

    /**
     * "Siguiente": no deja avanzar si las cuotas no suman 100%. Nada se graba de
     * verdad aquí — la persistencia real (inmueble + titularidades + forma de pago)
     * pasa de golpe en el último paso, ver DatosFinancierosStep::terminar().
     */
    public function submit()
    {
        $suma = collect($this->propietarios)->sum('cuota_percent');

        if (abs((float) $suma - 100) > 0.01) {
            $this->addError('cuota_percent', __('Los propietarios deben sumar el 100% de la propiedad (ahora mismo: :suma%).', ['suma' => $suma]));

            return;
        }

        if (! $this->borradorActual() || empty($this->borradorActual()->payload['datos'])) {
            $this->addError('cuota_percent', __('Faltan los datos del inmueble. Vuelve al paso anterior.'));

            return;
        }

        $this->nextStep();
    }

    /**
     * Titularidades ya cerradas de este inmueble (fecha_fin puesta): se enseñan como
     * histórico, solo lectura. No viven en $propietarios ni en el borrador — si
     * estuvieran ahí, el bucle de guardado de DatosFinancierosStep::terminar() las
     * tocaría como si siguieran vigentes.
     */
    private function propietariosHistoricos(): array
    {
        if (! $this->inmuebleId) {
            return [];
        }

        return Titularidad::where('inmueble_id', $this->inmuebleId)
            ->whereNotNull('fecha_fin')
            ->with('propietario.persona')
            ->orderByDesc('fecha_fin')
            ->get()
            ->map(fn (Titularidad $t) => [
                'nombre'        => ($t->propietario->persona->documento_identificativo ?? '').' — '.$t->propietario->persona->nombreCompleto,
                'cuota_percent' => (float) $t->cuota_percent,
                'causa'         => $t->causa,
                // Sin formatear: hace falta en Y-m-d para poder ordenar por fecha junto
                // con los quitados en esta sesión (ver render()). Se formatea en el blade.
                'fecha_inicio'  => $t->fecha_inicio?->toDateString(),
                'fecha_fin'     => $t->fecha_fin?->toDateString(),
            ])
            ->all();
    }

    public function render()
    {
        // Los quitados en esta sesión (todavía no reales en BD) y los ya cerrados de
        // antes se enseñan juntos, de la fecha más antigua a la más moderna.
        $propietariosBorrados = collect($this->propietariosQuitados)
            ->concat($this->propietariosHistoricos())
            ->sortBy('fecha_fin')
            ->values()
            ->all();

        return view('livewire.inmuebles.crear.steps.propietarios-step', [
            'causas' => $this->causas(),
            'propietariosBorrados' => $propietariosBorrados,
        ]);
    }
}
