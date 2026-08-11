<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\CuentaBancaria;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class CuentaBancariaStep extends CrearPropietarioStep
{
    #[Locked]
    public ?int $propietarioId = null;

    /** La fila en línea: lo que se está tecleando para añadir una cuenta nueva. */
    public array $nueva = [
        'iban'                   => null,
        'entidad_bancaria_id'    => null,
        'entidad_bancaria_texto' => '',
        'alias'                  => null,
    ];

    /**
     * Cuentas ya validadas pero todavía no guardadas: solo se usa mientras el
     * propietario no existe todavía en BD (alta nueva). Se persisten todas de golpe
     * en terminar(). Si el propietario ya existe, agregarCuenta() las crea al
     * momento y esto se queda vacío.
     */
    public array $cuentasPendientes = [];

    public bool $cargado = false;

    /** Resultados del buscador de entidad bancaria (ver x-dosl.input-autocomplete). */
    public array $resultadosEntidadesBancarias = [];

    // El titular de la cuenta tiene que ser mayor de edad (los menores no tienen
    // firma): si el propietario lo es, se calcula aquí y no hace falta pedir nada
    // más — el titular real será él mismo. Si es menor, hay que elegir o dar de
    // alta a otra persona adulta (ver reglasTitular()/resolverTitularReal()). Es
    // el mismo titular sustituto para todas las cuentas que se añadan en esta
    // pantalla, no uno distinto por cuenta.
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

        $this->titularResultados = PersonaComunidad::where('comunidad_id', $this->comunidadIdActual())
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

    /** Vacía: nada tecleado en ningún campo. No es un error, es que no se quería añadir nada. */
    private function esFilaVacia(array $fila): bool
    {
        return empty($fila['iban']) && empty($fila['entidad_bancaria_id']) && empty($fila['alias']);
    }

    private function reglasTitular(): array
    {
        if ($this->titularNuevo) {
            $reglas = [
                'titular_documento_pais_id'        => ['required', 'exists:paises,id'],
                'titular_tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
                'titular_documento_identificativo' => ['required', 'string', 'max:40'],
                'titular_nombre'                   => ['required', 'string', 'max:100'],
                'titular_apellido1'                => ['required', 'string', 'max:100'],
                'titular_apellido2'                => ['nullable', 'string', 'max:100'],
                'titular_fecha_nacimiento'         => ['required', 'date', 'before_or_equal:today', new IsMayorEdad()],
                'titular_genero_id'                => ['nullable', 'exists:tipo_generos,id'],
            ];

            if ($this->titular_documento_pais_id == Pais::ESPAÑA) {
                if ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                    $reglas['titular_documento_identificativo'][] = new IsNifRule();
                } elseif ($this->titular_tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                    $reglas['titular_documento_identificativo'][] = new IsNieRule();
                }
            }

            $reglas['titular_documento_identificativo'][] = Rule::unique('personas_comunidad', 'documento_identificativo')
                ->where(fn ($q) => $q->where('comunidad_id', $this->comunidadIdActual()));

            return $reglas;
        }

        return [
            'persona_comunidad_id_titular' => ['required', 'exists:personas_comunidad,id'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nueva.iban'                        => __('IBAN'),
            'nueva.entidad_bancaria_id'          => __('entidad bancaria'),
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

    private function comunidadIdActual()
    {
        return $this->borradorActual()?->payload['datos']['comunidad_id'] ?? session('comunidad_actual_id');
    }

    /**
     * Titular real que firma la cuenta: el propio propietario, salvo que sea menor
     * de edad — entonces tiene que ser otra persona adulta, elegida o dada de alta
     * en el bloque de titular sustituto. Si hay que crearla, se crea una sola vez:
     * las siguientes cuentas que se añadan en esta misma sesión la reutilizan.
     */
    private function resolverTitularReal(PersonaComunidad $persona): int
    {
        if (! $this->propietarioEsMenor) {
            return $persona->id;
        }

        if ($this->titularNuevo) {
            $titular = PersonaComunidad::create([
                'comunidad_id'             => $persona->comunidad_id,
                'nombre'                   => $this->titular_nombre,
                'apellido1'                => $this->titular_apellido1,
                'apellido2'                => $this->titular_apellido2,
                'documento_pais_id'        => $this->titular_documento_pais_id,
                'tipo_documento_id'        => $this->titular_tipo_documento_id,
                'documento_identificativo' => $this->titular_documento_identificativo,
                'fecha_nacimiento'         => $this->titular_fecha_nacimiento,
                'genero_id'                => $this->titular_genero_id ?: TipoGenero::GENERO_OTRO,
            ]);

            $this->titularNuevo = false;
            $this->persona_comunidad_id_titular = $titular->id;
            $this->titularNombreMostrado = ($titular->documento_identificativo ?? '').' — '.$titular->nombreCompleto;

            return $titular->id;
        }

        return $this->persona_comunidad_id_titular;
    }

    private function resetearFilaNueva(): void
    {
        $this->nueva = [
            'iban'                   => null,
            'entidad_bancaria_id'    => null,
            'entidad_bancaria_texto' => '',
            'alias'                  => null,
        ];
    }

    /**
     * "+": valida la fila y la añade. Si el propietario ya existe se crea al
     * momento (como cancelar); si todavía no existe (alta en curso) se apila en
     * cuentasPendientes y se persiste junto con todo lo demás en terminar().
     */
    public function agregarCuenta()
    {
        if ($this->esFilaVacia($this->nueva)) {
            return;
        }

        $reglas = [
            'nueva.iban'                => ['required', 'string', new IsIBANRule()],
            'nueva.entidad_bancaria_id' => ['required', 'exists:entidades_bancarias,id'],
            'nueva.alias'               => ['nullable', 'string', 'max:50'],
        ];

        if ($this->propietarioEsMenor) {
            $reglas = array_merge($reglas, $this->reglasTitular());
        }

        $datos = $this->validate($reglas, [], $this->validationAttributes());

        if ($this->propietarioId) {
            $propietario = Propietario::with('persona')->findOrFail($this->propietarioId);

            $propietario->cuentasBancarias()->create([
                'iban'                 => $datos['nueva']['iban'],
                'entidad_bancaria_id'  => $datos['nueva']['entidad_bancaria_id'],
                'alias'                => $datos['nueva']['alias'],
                'persona_comunidad_id' => $this->resolverTitularReal($propietario->persona),
                'estado_id'            => CuentaBancaria::ESTADO_ACTIVA,
            ]);

            $this->dispatch('toast-success', ['title' => __('Cuenta bancaria añadida')]);
        } else {
            $this->cuentasPendientes[] = [
                '_key'                   => Str::random(10),
                'iban'                   => $datos['nueva']['iban'],
                'entidad_bancaria_id'    => $datos['nueva']['entidad_bancaria_id'],
                'entidad_bancaria_texto' => $this->nueva['entidad_bancaria_texto'],
                'alias'                  => $datos['nueva']['alias'],
            ];
        }

        $this->resetearFilaNueva();
    }

    /** Quita una cuenta todavía no guardada (solo tiene sentido antes de Terminar). */
    public function quitarCuentaPendiente(string $key): void
    {
        $this->cuentasPendientes = array_values(array_filter(
            $this->cuentasPendientes,
            fn ($c) => $c['_key'] !== $key,
        ));
    }

    public function confirmarCancelarCuenta($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Cancelar esta cuenta?'),
            'text'               => __('Dejará de poder usarse. No se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, cancelar'),
            'cancelButtonText'   => __('Volver'),
            'confirmCallback'    => 'ejecutarCancelarCuenta',
            'cancelCallback'     => 'cancelacionCuentaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarCancelarCuenta')]
    public function ejecutarCancelarCuenta($id)
    {
        $cuenta = CuentaBancaria::with('titular.persona')->find($id);

        // El id viene del navegador: se comprueba que la cuenta es de un
        // propietario de esta comunidad antes de tocar nada.
        $esDeLaComunidadActual = $cuenta
            && $cuenta->titular_type === Propietario::class
            && $cuenta->titular?->persona?->comunidad_id == session('comunidad_actual_id');

        if (! $esDeLaComunidadActual) {
            return;
        }

        if ($cuenta->enUso()) {
            $this->dispatch('toast-error', [
                'title' => __('No se puede cancelar: sigue siendo la forma de pago vigente de algún inmueble.'),
            ]);

            return;
        }

        $cuenta->update(['estado_id' => CuentaBancaria::ESTADO_CANCELADA]);
        $this->dispatch('toast-success', ['title' => __('Cuenta bancaria cancelada')]);
    }

    #[On('cancelacionCuentaCancelada')]
    public function cancelacionCuentaCancelada($id = null)
    {
        // el usuario canceló el diálogo de confirmación; no hacemos nada
    }

    /** "Terminar": graba persona, dirección, contactos y cuentas bancarias pendientes de golpe. */
    public function terminar()
    {
        $borrador = $this->borradorActual();
        if (! $borrador || empty($borrador->payload['datos'])) {
            $this->addError('nueva.iban', __('Faltan los datos fiscales. Vuelve al primer paso.'));

            return;
        }

        $datos      = $borrador->payload['datos'];
        $direccion  = $borrador->payload['direccion'] ?? [];
        $contactos  = $borrador->payload['contactos'] ?? [];

        DB::transaction(function () use ($datos, $direccion, $contactos) {
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

            // Si el propietario ya existía al entrar en este paso, las cuentas se
            // fueron creando al momento con agregarCuenta() y esto está vacío.
            if ($this->cuentasPendientes) {
                $personaComunidadIdTitular = null;

                foreach ($this->cuentasPendientes as $pendiente) {
                    $personaComunidadIdTitular ??= $this->resolverTitularReal($persona);

                    $propietario->cuentasBancarias()->create([
                        'iban'                 => $pendiente['iban'],
                        'entidad_bancaria_id'  => $pendiente['entidad_bancaria_id'],
                        'alias'                => $pendiente['alias'],
                        'persona_comunidad_id' => $personaComunidadIdTitular,
                        'estado_id'            => CuentaBancaria::ESTADO_ACTIVA,
                    ]);
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
            'cuentasExistentes'     => $this->propietarioId
                ? Propietario::find($this->propietarioId)->cuentasBancarias()->with('entidadBancaria')->orderBy('id')->get()
                : collect(),
        ]);
    }
}
