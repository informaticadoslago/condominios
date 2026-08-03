<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Estado;
use App\Models\EntidadBancaria;
use App\Models\Pais;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoContacto;
use App\Models\TipoDireccion;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\TipoGenero;
use App\Rules\IsIBANRule;
use App\Rules\IsMayorEdad;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class CuentaBancariaStep extends CrearPropietarioStep
{
    #[Locked]
    public ?int $propietarioId = null;

    public ?string $iban = null;
    public ?int $entidad_bancaria_id = null;
    public ?string $entidad_bancaria_texto = '';
    public ?string $alias = null;

    public bool $cargado = false;

    /** Resultados del buscador de entidad bancaria (ver x-dosl.input-autocomplete). */
    public array $resultadosEntidadesBancarias = [];

    // El titular de la cuenta tiene que ser mayor de edad (los menores no tienen
    // firma): si el propietario lo es, se calcula aquí y no hace falta pedir nada
    // más — el titular real será él mismo. Si es menor, hay que elegir o dar de
    // alta a otra persona adulta (ver reglasTitular()/terminar()).
    public bool $propietarioEsMenor = false;

    public ?int $persona_comunidad_id_titular = null;
    public string $titularNombreMostrado = '';
    public string $titularBusqueda = '';
    public array $titularResultados = [];

    // Alta inline de un titular sustituto que todavía no está en el sistema.
    public bool $titularNuevo = false;
    public ?int $titular_documento_pais_id = null;
    public ?int $titular_tipo_documento_id = null;
    public ?string $titular_documento_identificativo = null;
    public ?string $titular_nombre = null;
    public ?string $titular_apellido1 = null;
    public ?string $titular_apellido2 = null;
    public ?string $titular_fecha_nacimiento = null;
    public ?int $titular_genero_id = null;

    public function stepInfo(): array
    {
        return ['label' => __('Cuenta bancaria')];
    }

    public function mount()
    {
        if ($this->cargado) {
            return;
        }
        $this->cargado = true;

        $datos = $this->borradorActual()?->payload['datos'] ?? [];
        $esJuridica = $datos['es_tipo_documento_cif'] ?? false;
        $fechaNacimiento = $datos['datosPersona']['fecha_nacimiento'] ?? null;

        $this->propietarioEsMenor = ! $esJuridica && ! (new IsMayorEdad())->passes('fecha_nacimiento', $fechaNacimiento);

        if ($this->propietarioId && ! $this->iban) {
            $cuenta = Propietario::find($this->propietarioId)?->cuentasBancarias->first();

            if ($cuenta) {
                $this->iban                = $cuenta->iban;
                $this->entidad_bancaria_id = $cuenta->entidad_bancaria_id;
                $this->alias               = $cuenta->alias;
                $this->entidad_bancaria_texto = $cuenta->entidadBancaria
                    ? $cuenta->entidadBancaria->codigo.' - '.$cuenta->entidadBancaria->descripcion
                    : '';

                if ($this->propietarioEsMenor && $cuenta->persona_comunidad_id
                        && $cuenta->persona_comunidad_id != ($datos['persona_comunidad_id'] ?? null)) {
                    $titular = $cuenta->personaComunidad;
                    $this->persona_comunidad_id_titular = $cuenta->persona_comunidad_id;
                    $this->titularNombreMostrado = $titular
                        ? ($titular->documento_identificativo ?? '').' — '.$titular->nombreCompleto
                        : '';
                }
            }
        }
    }

    /** Buscador del autocompletado de entidad bancaria: por código o nombre. */
    public function buscarEntidadesBancarias(string $q, int $limit = 8): void
    {
        $q = trim($q);

        $this->resultadosEntidadesBancarias = $q === '' ? [] : EntidadBancaria::activo()
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => ['valor' => $e->id, 'etiqueta' => "{$e->codigo} - {$e->descripcion}"])
            ->all();
    }

    // --- Buscador de titular sustituto (solo cuando el propietario es menor) ---
    public function updatedTitularBusqueda()
    {
        $busqueda = trim($this->titularBusqueda);
        if (mb_strlen($busqueda) < 2) {
            $this->titularResultados = [];

            return;
        }

        $comunidadId = $this->borradorActual()?->payload['datos']['comunidad_id'] ?? session('comunidad_actual_id');

        $this->titularResultados = PersonaComunidad::where('comunidad_id', $comunidadId)
            ->mayorDeEdad()
            ->where(fn ($q) => $q->buscarNombreCompleto($busqueda)->orWhere('documento_identificativo', 'like', "%{$busqueda}%"))
            ->limit(8)->get()
            ->map(fn ($p) => ['id' => $p->id, 'texto' => ($p->documento_identificativo ?? '').' — '.$p->nombreCompleto])
            ->all();
    }

    public function seleccionarTitular($id)
    {
        $persona = PersonaComunidad::find($id);
        if (! $persona) {
            return;
        }

        $this->persona_comunidad_id_titular = $persona->id;
        $this->titularNombreMostrado = ($persona->documento_identificativo ?? '').' — '.$persona->nombreCompleto;
        $this->titularBusqueda   = '';
        $this->titularResultados = [];
        $this->titularNuevo      = false;
    }

    public function quitarTitularSeleccionado()
    {
        $this->persona_comunidad_id_titular = null;
        $this->titularNombreMostrado = '';
        $this->titularBusqueda   = '';
        $this->titularResultados = [];
    }

    public function nuevoTitular()
    {
        $this->quitarTitularSeleccionado();

        $this->titularNuevo = true;
        $this->titular_documento_pais_id = Pais::porDefecto();
        $this->titular_tipo_documento_id = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->titular_documento_identificativo = null;
        $this->titular_nombre     = null;
        $this->titular_apellido1  = null;
        $this->titular_apellido2  = null;
        $this->titular_fecha_nacimiento = null;
        $this->titular_genero_id  = null;

        $this->resetErrorBag();
    }

    public function cancelarNuevoTitular()
    {
        $this->titularNuevo = false;
        $this->resetErrorBag();
    }

    protected function rules()
    {
        $rules = [
            'iban'                 => ['nullable', 'string', new IsIBANRule()],
            'entidad_bancaria_id'  => ['nullable', 'exists:entidades_bancarias,id', 'required_with:iban'],
            'alias'                => ['nullable', 'string', 'max:50'],
        ];

        if ($this->propietarioEsMenor && ! empty($this->iban)) {
            if ($this->titularNuevo) {
                $rules['titular_documento_pais_id']        = ['required', 'exists:paises,id'];
                $rules['titular_tipo_documento_id']        = ['required', 'exists:tipo_documento_identificativos,id'];
                $rules['titular_documento_identificativo'] = ['required', 'string', 'max:40'];
                $rules['titular_nombre']                   = ['required', 'string', 'max:100'];
                $rules['titular_apellido1']                = ['required', 'string', 'max:100'];
                $rules['titular_apellido2']                = ['nullable', 'string', 'max:100'];
                $rules['titular_fecha_nacimiento']         = ['required', 'date', 'before_or_equal:today', new IsMayorEdad()];
                $rules['titular_genero_id']                = ['nullable', 'exists:tipo_generos,id'];

                if ($this->titular_documento_pais_id == Pais::ESPAÑA) {
                    if ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                        $rules['titular_documento_identificativo'][] = new IsNifRule();
                    } elseif ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                        $rules['titular_documento_identificativo'][] = new IsNieRule();
                    }
                }

                $comunidadId = $this->borradorActual()?->payload['datos']['comunidad_id'] ?? session('comunidad_actual_id');
                $rules['titular_documento_identificativo'][] = Rule::unique('personas_comunidad', 'documento_identificativo')
                    ->where(fn ($q) => $q->where('comunidad_id', $comunidadId));
            } else {
                $rules['persona_comunidad_id_titular'] = ['required', 'exists:personas_comunidad,id'];
            }
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        return [
            'iban'                => __('IBAN'),
            'entidad_bancaria_id' => __('entidad bancaria'),
            'persona_comunidad_id_titular'      => __('titular de la cuenta'),
            'titular_documento_identificativo'  => __('documento del titular'),
            'titular_nombre'                    => __('nombre del titular'),
            'titular_apellido1'                 => __('apellido 1 del titular'),
            'titular_fecha_nacimiento'          => __('fecha de nacimiento del titular'),
        ];
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session($this->claveBorrador());

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;
    }

    /** "Terminar": graba persona, dirección, contactos y cuenta bancaria de golpe. */
    public function terminar()
    {
        $cuenta = $this->validate();

        $borrador = $this->borradorActual();
        if (! $borrador || empty($borrador->payload['datos'])) {
            $this->addError('iban', __('Faltan los datos fiscales. Vuelve al primer paso.'));

            return;
        }

        $datos      = $borrador->payload['datos'];
        $direccion  = $borrador->payload['direccion'] ?? [];
        $contactos  = $borrador->payload['contactos'] ?? [];

        DB::transaction(function () use ($datos, $direccion, $contactos, $cuenta) {
            if ($this->propietarioId) {
                $propietario = Propietario::findOrFail($this->propietarioId);
                $persona     = $propietario->persona;
                $persona->update($datos['datosPersona']);
            } elseif (! empty($datos['personaExistente']) && ! empty($datos['persona_comunidad_id'])) {
                $persona     = PersonaComunidad::findOrFail($datos['persona_comunidad_id']);
                $propietario = Propietario::firstOrCreate(['persona_comunidad_id' => $persona->id]);
            } else {
                $persona     = PersonaComunidad::create($datos['datosPersona']);
                $propietario = Propietario::firstOrCreate(['persona_comunidad_id' => $persona->id]);
            }

            if (array_filter($direccion)) {
                $persona->direcciones()->updateOrCreate(
                    ['tipo_direccion_id' => TipoDireccion::idDomicilio()],
                    $direccion + ['pais_id' => Pais::ESPAÑA, 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($contactos['telefono'])) {
                $persona->contactos()->updateOrCreate(
                    ['tipo_contacto_id' => TipoContacto::MOVIL],
                    ['descripcion' => __('Teléfono'), 'valor' => $contactos['telefono'], 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($contactos['email'])) {
                $persona->contactos()->updateOrCreate(
                    ['tipo_contacto_id' => TipoContacto::EMAIL],
                    ['descripcion' => __('Email'), 'valor' => $contactos['email'], 'estado_id' => Estado::ESTADO_ACTIVO],
                );
            }

            if (! empty($cuenta['iban'])) {
                // Titular real de la cuenta: el propio propietario, salvo que sea menor
                // de edad — entonces tiene que ser otra persona adulta (ver mount()).
                $personaComunidadIdTitular = $persona->id;

                if ($this->propietarioEsMenor) {
                    $personaComunidadIdTitular = $this->titularNuevo
                        ? PersonaComunidad::create([
                            'comunidad_id'             => $persona->comunidad_id,
                            'nombre'                   => $this->titular_nombre,
                            'apellido1'                => $this->titular_apellido1,
                            'apellido2'                => $this->titular_apellido2,
                            'documento_pais_id'        => $this->titular_documento_pais_id,
                            'tipo_documento_id'        => $this->titular_tipo_documento_id,
                            'documento_identificativo' => $this->titular_documento_identificativo,
                            'fecha_nacimiento'         => $this->titular_fecha_nacimiento,
                            'genero_id'                => $this->titular_genero_id ?: TipoGenero::GENERO_OTRO,
                        ])->id
                        : $this->persona_comunidad_id_titular;
                }

                $datosCuenta = [
                    'iban'                 => $cuenta['iban'],
                    'entidad_bancaria_id'  => $cuenta['entidad_bancaria_id'],
                    'alias'                => $cuenta['alias'],
                    'persona_comunidad_id' => $personaComunidadIdTitular,
                ];

                $cuentaExistente = $propietario->cuentasBancarias->first();

                if ($cuentaExistente) {
                    $cuentaExistente->update($datosCuenta);
                } else {
                    $propietario->cuentasBancarias()->create($datosCuenta);
                }
            }

            $this->propietarioId = $propietario->id;
        });

        $borrador->delete();
        session()->forget($this->claveBorrador());

        if ($this->embebido) {
            $propietario = Propietario::with('persona')->find($this->propietarioId);
            $this->dispatch(
                'propietario-creado',
                id: $propietario->id,
                nombre: ($propietario->persona->documento_identificativo ?? '').' — '.$propietario->persona->nombreCompleto,
            );
        }

        $this->salir();
    }

    public function render()
    {
        return view('livewire.propietarios.crear.steps.cuenta-bancaria-step', [
            'paises'                => Pais::activo()->ordenGrupo()->get(),
            'tiposDocumentoTitular' => TipoDocumentoIdentificativo::persona_fisica()->get(),
            'generos'               => TipoGenero::orderBy('nombre')->get(),
        ]);
    }
}
