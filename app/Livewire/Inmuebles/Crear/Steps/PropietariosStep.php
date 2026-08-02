<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\Titularidad;
use Illuminate\Support\Facades\DB;
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
 * en terminar().
 */
class PropietariosStep extends CrearInmuebleStep
{

    public ?int $inmuebleId = null;
    public ?int $comunidad_id = null;

    /** [['ref', 'titularidad_id', 'persona_comunidad_id', 'persona_nueva', 'nombre', 'cuota_percent', 'causa', 'fecha_inicio'], …] */
    public array $propietarios = [];

    /** Titularidades reales quitadas en esta sesión, con la fecha_fin elegida: [['titularidad_id', 'fecha_fin'], …] */
    public array $propietariosQuitados = [];
    public bool $cargado = false;

    // Quitar una titularidad ya real: en vez de borrarla, pide desde cuándo deja de
    // estarlo (nunca hay opción de borrar la titularidad de verdad, ver terminar()).
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
        $this->modalPropietarioContador++;
        $this->modalPropietarioAbierto = true;
    }

    #[On('cerrar-modal-propietario')]
    public function cerrarModalPropietario(): void
    {
        $this->modalPropietarioAbierto = false;
    }

    /** El wizard embebido terminó: selecciona solo al propietario recién creado. */
    #[On('propietario-creado')]
    public function propietarioCreado($id, $nombre = null): void
    {
        $this->modalPropietarioAbierto = false;

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
            'titularidad_id'       => null,
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

    /** Cierra la titularidad real con la fecha_fin elegida (nunca la borra: ver terminar()). */
    public function guardarQuitarPropietario()
    {
        $this->validate([
            'quitar_fecha_fin' => ['required', 'date'],
        ], [], ['quitar_fecha_fin' => __('fecha de fin')]);

        $linea = collect($this->propietarios)->firstWhere('ref', $this->quitandoId);
        if ($linea) {
            $this->propietariosQuitados[] = [
                'titularidad_id' => $linea['titularidad_id'],
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

    /** "Terminar": no deja salir si las cuotas no suman 100%. Graba todo de golpe. */
    public function terminar()
    {
        $suma = collect($this->propietarios)->sum('cuota_percent');

        if (abs((float) $suma - 100) > 0.01) {
            $this->addError('cuota_percent', __('Los propietarios deben sumar el 100% de la propiedad (ahora mismo: :suma%).', ['suma' => $suma]));

            return;
        }

        $borrador = $this->borradorActual();
        if (! $borrador || empty($borrador->payload['datos'])) {
            $this->addError('cuota_percent', __('Faltan los datos del inmueble. Vuelve al paso anterior.'));

            return;
        }

        DB::transaction(function () use ($borrador) {
            if ($this->inmuebleId) {
                $inmueble = Inmueble::findOrFail($this->inmuebleId);
                $inmueble->update($borrador->payload['datos']);
            } else {
                $inmueble = Inmueble::create($borrador->payload['datos']);
                $this->inmuebleId = $inmueble->id;
            }

            $titularidadIdsVigentes = [];

            foreach ($this->propietarios as $linea) {
                $personaId = $linea['persona_comunidad_id']
                    ?? PersonaComunidad::create($linea['persona_nueva'])->id;

                $propietario = Propietario::firstOrCreate(['persona_comunidad_id' => $personaId]);

                if ($linea['titularidad_id']) {
                    Titularidad::whereKey($linea['titularidad_id'])->update([
                        'cuota_percent' => $linea['cuota_percent'],
                        'causa'         => $linea['causa'],
                        'fecha_inicio'  => $linea['fecha_inicio'] ?? now()->toDateString(),
                    ]);
                    $titularidadIdsVigentes[] = $linea['titularidad_id'];
                } else {
                    $titularidad = Titularidad::create([
                        'inmueble_id'    => $inmueble->id,
                        'propietario_id' => $propietario->id,
                        'cuota_percent'  => $linea['cuota_percent'],
                        'causa'          => $linea['causa'],
                        'fecha_inicio'   => $linea['fecha_inicio'] ?? now()->toDateString(),
                        'fecha_fin'      => null,
                    ]);
                    $titularidadIdsVigentes[] = $titularidad->id;
                }
            }

            // Las que se quitaron explícitamente (ver guardarQuitarPropietario) se cierran
            // con la fecha que se eligió al quitarlas, nunca se borran.
            foreach ($this->propietariosQuitados as $quitada) {
                Titularidad::whereKey($quitada['titularidad_id'])->update(['fecha_fin' => $quitada['fecha_fin']]);
            }

            // Red de seguridad: cualquier otra titularidad vigente que ya no esté en la
            // lista final (por una vía distinta a "quitar") se cierra hoy.
            Titularidad::vigente()
                ->where('inmueble_id', $inmueble->id)
                ->whereNotIn('id', $titularidadIdsVigentes)
                ->update(['fecha_fin' => now()->toDateString()]);
        });

        $borrador->delete();
        session()->forget('inmueble_borrador_id');

        $this->salir();
    }

    public function render()
    {
        return view('livewire.inmuebles.crear.steps.propietarios-step', [
            'causas' => $this->causas(),
        ]);
    }
}
