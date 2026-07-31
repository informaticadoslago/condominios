<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Livewire\Traits\WithGenero;
use App\Models\Inmueble;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\Titularidad;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use Livewire\Attributes\On;

/**
 * Paso: propietarios del inmueble. El inmueble ya existe (se creó en el paso
 * anterior), así que cada alta se persiste al momento como una Titularidad
 * (cuota %, fecha_inicio, causa). La historia es inmutable: "quitar" (🗑️) NUNCA
 * borra una titularidad, le pone fecha_fin (hoy). "Editar" (✏️) solo corrige un
 * error de captura en una fila (no representa un cambio real de propiedad: eso
 * es cerrar + añadir una nueva).
 */
class PropietariosStep extends CrearInmuebleStep
{
    use WithGenero;

    public ?int $inmuebleId = null;

    /** Lista en memoria (reflejo de lo ya persistido): [['titularidad_id','nombre','cuota_percent','causa'], …] */
    public array $propietarios = [];
    public bool $cargado = false;

    // Persona a añadir: existente (buscador).
    public ?int $persona_id      = null;
    public string $personaNombre = '';
    public string $personaBusqueda  = '';
    public array $personaResultados = [];

    // Cuota y causa de la titularidad que se va a abrir.
    public $cuota_percent = null;
    public string $causa  = Titularidad::CAUSA_COMPRAVENTA;

    // Alta inline de persona nueva (si no existe).
    public bool $personaNueva                     = false;
    public bool $personaComprobada                = false;
    public ?int $prop_documento_pais_id           = null;
    public ?int $prop_tipo_documento_id           = null;
    public ?string $prop_documento_identificativo = null;
    public ?string $prop_nombre                   = null;
    public ?string $prop_apellido1                = null;
    public ?string $prop_apellido2                = null;
    public ?string $prop_fecha_nacimiento         = null;
    public ?int $prop_genero_id                   = null;

    // Edición de una titularidad ya existente (solo para corregir errores).
    public ?int $editandoId       = null;
    public $edit_cuota_percent    = null;
    public string $edit_causa     = Titularidad::CAUSA_COMPRAVENTA;
    public ?string $edit_fecha_inicio = null;

    public function stepInfo(): array
    {
        return ['label' => __('Propietarios')];
    }

    public function mount()
    {
        $this->setGeneros();
        if (! $this->cargado) {
            $this->cargarPropietarios();
        }
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
        if (! $this->inmuebleId) {
            $this->cargado = true;

            return;
        }

        $this->propietarios = Titularidad::vigente()
            ->where('inmueble_id', $this->inmuebleId)
            ->with('propietario.persona')
            ->whereHas('propietario.persona', fn ($p) => $p->visible())
            ->get()
            ->map(fn (Titularidad $t) => [
                'titularidad_id' => $t->id,
                'nombre'         => ($t->propietario->persona->documento_identificativo ?? '').' — '.$t->propietario->persona->nombreCompleto,
                'cuota_percent'  => $t->cuota_percent,
                'causa'          => $this->causas()[$t->causa] ?? $t->causa,
            ])->values()->all();

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
        $this->personaResultados = Persona::visible()->where(function ($query) use ($busqueda) {
            $query->buscarNombreCompleto($busqueda)->orWhere('documento_identificativo', 'like', "%{$busqueda}%");
        })->limit(8)->get()
            ->map(fn ($persona) => ['id' => $persona->id, 'texto' => ($persona->documento_identificativo ?? '').' — '.$persona->nombreCompleto])->all();
    }

    public function seleccionarPersona($id)
    {
        $persona = Persona::find($id);
        if (! $persona) {
            return;
        }
        $this->persona_id        = $persona->id;
        $this->personaNombre     = ($persona->documento_identificativo ?? '').' — '.$persona->nombreCompleto;
        $this->personaBusqueda   = '';
        $this->personaResultados = [];
        $this->personaNueva      = false;
    }

    public function quitarSeleccion()
    {
        $this->persona_id        = null;
        $this->personaNombre     = '';
        $this->personaBusqueda   = '';
        $this->personaResultados = [];
    }

    // --- Alta inline de persona nueva ---
    public function nuevaPersona()
    {
        $this->quitarSeleccion();
        $this->personaNueva                  = true;
        $this->personaComprobada             = false;
        $this->prop_documento_pais_id        = Pais::porDefecto();
        $this->prop_tipo_documento_id        = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->prop_documento_identificativo = null;
        $this->prop_nombre                   = null;
        $this->prop_apellido1                = null;
        $this->prop_apellido2                = null;
        $this->prop_fecha_nacimiento         = null;
        $this->prop_genero_id                = null;
        $this->resetErrorBag();
    }

    public function cancelarNuevaPersona()
    {
        $this->personaNueva      = false;
        $this->personaComprobada = false;
        $this->resetErrorBag();
    }

    /** Comprueba el documento: si la persona ya existe, la selecciona; si no, deja crearla. */
    public function comprobarPersona()
    {
        $rules = [
            'prop_documento_pais_id'        => ['required', 'exists:paises,id'],
            'prop_tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
            'prop_documento_identificativo' => ['required', 'string', 'max:40'],
        ];

        if ($this->prop_documento_pais_id == Pais::ESPAÑA) {
            if ($this->prop_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                $rules['prop_documento_identificativo'][] = new IsNifRule();
            } elseif ($this->prop_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                $rules['prop_documento_identificativo'][] = new IsNieRule();
            } elseif ($this->prop_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_CIF) {
                $rules['prop_documento_identificativo'][] = new IsCifRule();
            }
        }

        $this->validate($rules);

        $persona = Persona::visible()->where('documento_identificativo', $this->prop_documento_identificativo)->first();

        if ($persona) {
            $this->seleccionarPersona($persona->id);

            return;
        }

        $this->personaComprobada = true;
    }

    // --- Añadir a la lista: abre una titularidad nueva (persiste al momento) ---
    public function agregarPropietario()
    {
        $personaId = $this->resolverPersona();
        if (! $personaId) {
            $this->addError('persona_id', __('Selecciona o da de alta a la persona del propietario.'));

            return;
        }

        $this->validate([
            'cuota_percent' => ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'causa'         => ['required', 'in:'.implode(',', array_keys($this->causas()))],
        ], [], ['cuota_percent' => __('cuota de propiedad')]);

        $propietario = Propietario::firstOrCreate(['persona_id' => $personaId]);

        $inmueble = Inmueble::findOrFail($this->inmuebleId);
        if ($inmueble->propietarios()->where('propietarios.id', $propietario->id)->exists()) {
            $this->addError('persona_id', __('Ese propietario ya está en este inmueble.'));

            return;
        }

        Titularidad::create([
            'inmueble_id'    => $this->inmuebleId,
            'propietario_id' => $propietario->id,
            'cuota_percent'  => $this->cuota_percent,
            'causa'          => $this->causa,
            'fecha_inicio'   => now()->toDateString(),
            'fecha_fin'      => null,
        ]);

        $this->cargado = false;
        $this->cargarPropietarios();
        $this->resetFormularioPropietario();
    }

    private function resetFormularioPropietario(): void
    {
        $this->quitarSeleccion();
        $this->personaNueva      = false;
        $this->personaComprobada = false;
        $this->cuota_percent     = null;
        $this->causa             = Titularidad::CAUSA_COMPRAVENTA;
        $this->resetErrorBag();
    }

    /** Persona (id) a añadir: existente seleccionada o creación de la nueva. */
    private function resolverPersona(): ?int
    {
        if ($this->persona_id) {
            return $this->persona_id;
        }

        if ($this->personaNueva && $this->personaComprobada) {
            $this->validate([
                'prop_nombre'           => ['required', 'string', 'max:100'],
                'prop_apellido1'        => ['required', 'string', 'max:100'],
                'prop_apellido2'        => ['nullable', 'string', 'max:100'],
                'prop_fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
                'prop_genero_id'        => ['required', 'exists:tipo_generos,id'],
            ], [], [
                'prop_nombre'           => __('nombre del propietario'),
                'prop_apellido1'        => __('primer apellido del propietario'),
                'prop_fecha_nacimiento' => __('fecha de nacimiento del propietario'),
                'prop_genero_id'        => __('género del propietario'),
            ]);

            $persona = Persona::create([
                'documento_pais_id'        => $this->prop_documento_pais_id,
                'tipo_documento_id'        => $this->prop_tipo_documento_id,
                'documento_identificativo' => $this->prop_documento_identificativo,
                'nombre'                   => $this->prop_nombre,
                'apellido1'                => $this->prop_apellido1,
                'apellido2'                => $this->prop_apellido2,
                'fecha_nacimiento'         => $this->prop_fecha_nacimiento,
                'genero_id'                => $this->prop_genero_id,
            ]);

            return $persona->id;
        }

        return null;
    }

    // --- Quitar (🗑️): cierra la titularidad, nunca la borra ---
    public function confirmarCerrar($titularidadId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja a este propietario?'),
            'text'               => __('Se pondrá la fecha de hoy como fin de su titularidad. No se borra: queda como historial.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, dar de baja'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarCerrarTitularidad',
            'cancelCallback'     => 'cerrarTitularidadCancelado',
            'id'                 => $titularidadId,
        ]);
    }

    #[On('ejecutarCerrarTitularidad')]
    public function cerrarTitularidad($id)
    {
        Titularidad::whereKey($id)->whereNull('fecha_fin')->update(['fecha_fin' => now()->toDateString()]);

        $this->cargado = false;
        $this->cargarPropietarios();
    }

    #[On('cerrarTitularidadCancelado')]
    public function cerrarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    // --- Editar (✏️): SOLO para corregir un error de captura, nunca un cambio real ---
    public function confirmarEditar($titularidadId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Editar esta titularidad?'),
            'text'               => __('Solo debe modificarse si hubo un error al darla de alta. Un cambio real de propiedad (venta, herencia, divorcio…) se hace dando de baja esta fila y añadiendo una nueva, no editando.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#3085d6',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, es un error, corregir'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEditarTitularidad',
            'cancelCallback'     => 'editarTitularidadCancelado',
            'id'                 => $titularidadId,
        ]);
    }

    #[On('ejecutarEditarTitularidad')]
    public function activarEdicion($id)
    {
        $titularidad = Titularidad::find($id);
        if (! $titularidad) {
            return;
        }

        $this->editandoId        = $titularidad->id;
        $this->edit_cuota_percent = $titularidad->cuota_percent;
        $this->edit_causa         = $titularidad->causa;
        $this->edit_fecha_inicio  = $titularidad->fecha_inicio?->format('Y-m-d');
    }

    #[On('editarTitularidadCancelado')]
    public function editarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
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
        ], [], ['edit_cuota_percent' => __('cuota de propiedad')]);

        Titularidad::whereKey($this->editandoId)->update([
            'cuota_percent' => $this->edit_cuota_percent,
            'causa'         => $this->edit_causa,
            'fecha_inicio'  => $this->edit_fecha_inicio,
        ]);

        $this->editandoId = null;
        $this->cargado     = false;
        $this->cargarPropietarios();
    }

    /** "Terminar": no deja salir si las cuotas vigentes no suman 100%. */
    public function terminar()
    {
        $suma = Titularidad::vigente()->where('inmueble_id', $this->inmuebleId)->sum('cuota_percent');

        if (abs((float) $suma - 100) > 0.01) {
            $this->addError('cuota_percent', __('Los propietarios deben sumar el 100% de la propiedad (ahora mismo: :suma%).', ['suma' => $suma]));

            return;
        }

        $this->salir();
    }

    public function render()
    {
        return view('livewire.inmuebles.crear.steps.propietarios-step', [
            'paisesPropietario' => Pais::activo()->ordenGrupo()->get(),
            'tiposPropietario'  => TipoDocumentoIdentificativo::persona_fisica()->get(),
            'causas'            => $this->causas(),
        ]);
    }
}
