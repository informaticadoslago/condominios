<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Livewire\Traits\WithGenero;
use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\Titularidad;
use App\Rules\IsCifRule;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use Illuminate\Support\Facades\DB;

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
    use WithGenero;

    public ?int $inmuebleId = null;
    public ?int $comunidad_id = null;

    /** [['ref', 'titularidad_id', 'persona_comunidad_id', 'persona_nueva', 'nombre', 'cuota_percent', 'causa'], …] */
    public array $propietarios = [];
    public bool $cargado = false;

    // Persona a añadir: existente (buscador).
    public ?int $persona_id      = null;
    public string $personaNombre = '';
    public string $personaBusqueda  = '';
    public array $personaResultados = [];

    // Cuota y causa de la línea que se va a abrir.
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

    // Edición de una línea ya añadida (siempre en memoria/borrador).
    public $editandoId = null;
    public $edit_cuota_percent    = null;
    public string $edit_causa     = Titularidad::CAUSA_COMPRAVENTA;

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

        $this->setGeneros();
        if (! $this->cargado) {
            $this->cargarPropietarios();
        }
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
        $this->propietarios = $this->borradorActual()?->payload['propietarios'] ?? [];
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
            })->limit(8)->get()
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

        $persona = PersonaComunidad::where('comunidad_id', $this->comunidad_id)
            ->where('documento_identificativo', $this->prop_documento_identificativo)
            ->first();

        if ($persona) {
            $this->seleccionarPersona($persona->id);

            return;
        }

        $this->personaComprobada = true;
    }

    // --- Añadir a la lista: acumula en el borrador, nada real todavía ---
    public function agregarPropietario()
    {
        if (! $this->persona_id && ! ($this->personaNueva && $this->personaComprobada)) {
            $this->addError('persona_id', __('Selecciona o da de alta a la persona del propietario.'));

            return;
        }

        $this->validate([
            'cuota_percent' => ['required', 'numeric', 'min:0.01', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'causa'         => ['required', 'in:'.implode(',', array_keys($this->causas()))],
        ], [], ['cuota_percent' => __('cuota de propiedad')]);

        if ($this->persona_id) {
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
        } else {
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

            $linea = [
                'titularidad_id'       => null,
                'persona_comunidad_id' => null,
                'persona_nueva'        => [
                    'comunidad_id'             => $this->comunidad_id,
                    'documento_pais_id'        => $this->prop_documento_pais_id,
                    'tipo_documento_id'        => $this->prop_tipo_documento_id,
                    'documento_identificativo' => $this->prop_documento_identificativo,
                    'nombre'                   => $this->prop_nombre,
                    'apellido1'                => $this->prop_apellido1,
                    'apellido2'                => $this->prop_apellido2,
                    'fecha_nacimiento'         => $this->prop_fecha_nacimiento,
                    'genero_id'                => $this->prop_genero_id,
                ],
                'nombre' => ($this->prop_documento_identificativo ?? '').' — '.trim($this->prop_nombre.' '.$this->prop_apellido1.' '.$this->prop_apellido2),
            ];
        }

        $linea['cuota_percent'] = (float) $this->cuota_percent;
        $linea['causa']         = $this->causa;
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
        $borrador->update(['payload' => $payload]);
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

    // --- Quitar: solo saca la línea del borrador, no toca nada real ---
    public function quitarPropietario($ref)
    {
        $this->propietarios = array_values(array_filter(
            $this->propietarios,
            fn ($p) => $p['ref'] !== $ref
        ));
        $this->guardarBorradorPropietarios();
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
        ], [], ['edit_cuota_percent' => __('cuota de propiedad')]);

        foreach ($this->propietarios as &$linea) {
            if ($linea['ref'] === $this->editandoId) {
                $linea['cuota_percent'] = (float) $this->edit_cuota_percent;
                $linea['causa']         = $this->edit_causa;
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
                    ]);
                    $titularidadIdsVigentes[] = $linea['titularidad_id'];
                } else {
                    $titularidad = Titularidad::create([
                        'inmueble_id'    => $inmueble->id,
                        'propietario_id' => $propietario->id,
                        'cuota_percent'  => $linea['cuota_percent'],
                        'causa'          => $linea['causa'],
                        'fecha_inicio'   => now()->toDateString(),
                        'fecha_fin'      => null,
                    ]);
                    $titularidadIdsVigentes[] = $titularidad->id;
                }
            }

            // Las titularidades vigentes que ya no están en la lista final se cierran
            // (nunca se borran: queda como historial de que dejó de ser propietario).
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
            'paisesPropietario' => Pais::activo()->ordenGrupo()->get(),
            'tiposPropietario'  => TipoDocumentoIdentificativo::persona_fisica()->get(),
            'causas'            => $this->causas(),
        ]);
    }
}
