<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Borrador;
use App\Models\CuentaBancaria;
use App\Models\FormaDePago;
use App\Models\FormaPagoInmueble;
use App\Exceptions\MandatoSepaInvalidoException;
use App\Models\Inmueble;
use App\Models\MandatoSepa;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\TipoDocumento;
use App\Models\Titularidad;
use App\Services\Recibos\RegistrarMandatoSepa;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

/**
 * Último paso: forma de pago del inmueble y, si es recibo bancario, el propietario
 * (uno de los titulares elegidos en PropietariosStep) y una de sus cuentas
 * bancarias. "Terminar" graba de golpe TODO lo acumulado en el borrador: inmueble,
 * titularidades (ver PropietariosStep) y esta forma de pago — nada es real hasta
 * aquí.
 */
class DatosFinancierosStep extends CrearInmuebleStep
{
    use WithFileUploads;

    public ?int $inmuebleId = null;

    public $forma_de_pago_id = null;

    /** Desde cuándo rige esta forma de pago: hecho del mundo real, se escribe a mano igual que Titularidad::fecha_inicio. */
    public $forma_pago_fecha_inicio = null;

    /** persona_comunidad_id del titular elegido como responsable del recibo (solo si RECIBO_BANCARIO). */
    public $persona_comunidad_id_pago = null;
    public $cuenta_bancaria_id = null;

    // Mandato SEPA de la cuenta elegida. Los dos se escriben a mano: el número lo
    // decide quien numera los mandatos (P19 + NIF del titular + contador) y la fecha es
    // la del papel firmado. O se rellenan los dos o ninguno.
    public $mandato_referencia = null;
    public $mandato_fecha_firma = null;

    /** El PDF firmado, si ya se tiene. Se cuelga del mandato al terminar. */
    public $mandato_documento = null;

    public bool $modalPlantillaMandatoAbierta = false;

    /** Editando en línea la referencia/fecha del mandato ya registrado de esta cuenta. */
    public bool $editandoMandato = false;

    public bool $cargado = false;

    // Si el titular elegido no tiene ninguna cuenta bancaria, se puede editar (o dar de
    // alta, si todavía no es un Propietario real) sin salir de este wizard: abre el
    // wizard completo de Propietario embebido en un modal, igual que PropietariosStep.
    public bool $modalPropietarioAbierto = false;
    public int $modalPropietarioContador = 0;
    public ?int $propietarioIdParaModal = null;

    public function stepInfo(): array
    {
        return ['label' => __('Datos financieros')];
    }

    public function mount()
    {
        if ($this->cargado) {
            return;
        }
        $this->cargado = true;

        $financiero = $this->borradorActual()?->payload['financiero'] ?? null;

        if ($financiero) {
            $this->forma_de_pago_id          = $financiero['forma_de_pago_id'] ?? null;
            $this->persona_comunidad_id_pago = $financiero['persona_comunidad_id_pago'] ?? null;
            $this->cuenta_bancaria_id        = $financiero['cuenta_bancaria_id'] ?? null;
            $this->forma_pago_fecha_inicio   = $financiero['forma_pago_fecha_inicio']
                ?? $this->fechaInicioVigente()?->toDateString()
                ?? now()->toDateString();
        } else {
            $this->forma_pago_fecha_inicio = $this->fechaInicioVigente()?->toDateString() ?? now()->toDateString();
        }
    }

    /**
     * Titulares elegibles: los que hay ahora mismo en el borrador (ver PropietariosStep),
     * no los reales en BD. De mayor a menor cuota, que es el orden en que se busca al
     * responsable del pago.
     */
    private function titulares(): array
    {
        $propietarios = $this->borradorActual()?->payload['propietarios'] ?? [];

        return collect($propietarios)
            ->sortByDesc(fn ($p) => (float) ($p['cuota_percent'] ?? 0))
            ->unique('persona_comunidad_id')
            ->map(fn ($p) => ['persona_comunidad_id' => $p['persona_comunidad_id'], 'nombre' => $p['nombre']])
            ->values()
            ->all();
    }

    /** Formas de pago anteriores del inmueble (las ya cerradas), de más reciente a más antigua. */
    private function historicoFormasPago()
    {
        if (! $this->inmuebleId) {
            return collect();
        }

        return FormaPagoInmueble::whereNotNull('fecha_fin')
            ->where('inmueble_id', $this->inmuebleId)
            ->with('formaDePago')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /** Desde cuándo rige la forma de pago vigente en BD (no la que se esté editando sin guardar todavía). */
    private function fechaInicioVigente(): ?\Illuminate\Support\Carbon
    {
        if (! $this->inmuebleId) {
            return null;
        }

        return FormaPagoInmueble::vigente()
            ->where('inmueble_id', $this->inmuebleId)
            ->first()?->fecha_inicio;
    }

    /** Cuentas bancarias del titular elegido (puede no tener ninguna si es un propietario recién creado). */
    private function cuentasDelTitular(): array
    {
        if (! $this->persona_comunidad_id_pago) {
            return [];
        }

        $propietario = Propietario::where('persona_comunidad_id', $this->persona_comunidad_id_pago)->first();

        if (! $propietario) {
            return [];
        }

        return $propietario->cuentasBancarias->map(function (CuentaBancaria $c) use ($propietario) {
            $texto = trim(($c->alias ? $c->alias.' — ' : '').$c->iban);

            // El titular real puede no ser el propio propietario (p.ej. propietario
            // menor de edad: ver Propietarios\Crear\Steps\CuentaBancariaStep).
            if ($c->persona_comunidad_id && $c->persona_comunidad_id != $propietario->persona_comunidad_id) {
                $texto .= ' — '.__('titular').': '.($c->personaComunidad?->nombreCompleto ?? '');
            }

            return ['id' => $c->id, 'texto' => $texto];
        })->all();
    }

    public function updatedFormaDePagoId(): void
    {
        // El propietario responsable se pide siempre, también sin domiciliación: es a
        // quien se le emite el recibo y a quien se le manda el aviso de pago. Solo la
        // cuenta deja de tener sentido al dejar de ser recibo bancario.
        if ((int) $this->forma_de_pago_id !== FormaDePago::RECIBO_BANCARIO) {
            $this->cuenta_bancaria_id = null;
        }
    }

    public function updatedPersonaComunidadIdPago(): void
    {
        $this->cuenta_bancaria_id = null;
        $this->olvidarMandato();
    }

    /** Cada cuenta tiene su propio mandato: al cambiar de cuenta, lo tecleado ya no vale. */
    public function updatedCuentaBancariaId(): void
    {
        $this->olvidarMandato();
    }

    private function olvidarMandato(): void
    {
        $this->mandato_referencia  = null;
        $this->mandato_fecha_firma = null;
        $this->mandato_documento   = null;
    }

    /** El mandato ya registrado de la cuenta elegida, si lo hay: entonces no se pide otro. */
    private function mandatoVigente(): ?MandatoSepa
    {
        if (! $this->cuenta_bancaria_id || ! $this->comunidadId()) {
            return null;
        }

        return app(RegistrarMandatoSepa::class)
            ->mandatoDeLaCuenta((int) $this->comunidadId(), (int) $this->cuenta_bancaria_id);
    }

    public function abrirPlantillaMandato(): void
    {
        $this->modalPlantillaMandatoAbierta = true;
    }

    public function editarMandato(): void
    {
        $vigente = $this->mandatoVigente();
        if (! $vigente) {
            return;
        }

        $this->mandato_referencia  = $vigente->referencia;
        $this->mandato_fecha_firma = $vigente->fecha_firma?->format('Y-m-d');
        $this->editandoMandato = true;
        $this->resetErrorBag(['mandato_referencia', 'mandato_fecha_firma']);
    }

    public function cancelarEdicionMandato(): void
    {
        $this->editandoMandato = false;
        $this->olvidarMandato();
        $this->resetErrorBag(['mandato_referencia', 'mandato_fecha_firma']);
    }

    public function guardarEdicionMandato(RegistrarMandatoSepa $registrar): void
    {
        $vigente = $this->mandatoVigente();
        if (! $vigente) {
            $this->editandoMandato = false;

            return;
        }

        $datos = $this->validate([
            'mandato_referencia'  => ['required', 'string', 'max:35'],
            'mandato_fecha_firma' => ['required', 'date', 'before_or_equal:today'],
        ], [], $this->validationAttributes());

        try {
            $registrar->corregir($vigente, $datos['mandato_referencia'], $datos['mandato_fecha_firma']);
        } catch (MandatoSepaInvalidoException $e) {
            $this->addError('mandato_referencia', $e->getMessage());

            return;
        }

        $this->editandoMandato = false;
        $this->olvidarMandato();
        $this->dispatch('toast-success', ['title' => __('Mandato corregido')]);
    }

    public function confirmarCancelarMandato($id): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Cancelar este mandato?'),
            'text'               => __('Se podrá registrar uno nuevo para esta cuenta. No se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, cancelar'),
            'cancelButtonText'   => __('Volver'),
            'confirmCallback'    => 'ejecutarCancelarMandato',
            'cancelCallback'     => 'cancelacionMandatoCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarCancelarMandato')]
    public function ejecutarCancelarMandato($id, RegistrarMandatoSepa $registrar): void
    {
        $mandato = MandatoSepa::where('id', $id)
            ->where('comunidad_id', $this->comunidadId())
            ->where('cuenta_bancaria_id', $this->cuenta_bancaria_id)
            ->first();

        if (! $mandato) {
            return;
        }

        $registrar->cancelar($mandato);
        $this->dispatch('toast-success', ['title' => __('Mandato cancelado')]);
    }

    #[On('cancelacionMandatoCancelada')]
    public function cancelacionMandatoCancelada($id = null): void
    {
        // el usuario canceló el diálogo de confirmación; no hacemos nada
    }

    public function cerrarPlantillaMandato(): void
    {
        $this->modalPlantillaMandatoAbierta = false;
    }

    /**
     * La plantilla se saca a nombre del propietario responsable del pago. Sin el piso:
     * el mandato es del titular y su cuenta, y con un piso impreso no valdría para los
     * demás inmuebles de ese mismo titular.
     */
    private function urlPlantillaMandato(): ?string
    {
        if (! $this->persona_comunidad_id_pago) {
            return null;
        }

        return route('mandatos-sepa.plantilla', ['personaComunidad' => $this->persona_comunidad_id_pago]);
    }

    /** Comunidad del inmueble en curso, para el wizard de Propietario embebido en el modal. */
    private function comunidadId(): ?int
    {
        return $this->borradorActual()?->payload['datos']['comunidad_id'] ?? session('comunidad_actual_id');
    }

    /** Abre el wizard de Propietario embebido: edición si ya existe como Propietario real, alta si todavía no. */
    public function abrirModalPropietario(): void
    {
        if (! $this->persona_comunidad_id_pago) {
            return;
        }

        $this->propietarioIdParaModal = Propietario::where('persona_comunidad_id', $this->persona_comunidad_id_pago)->value('id');

        // El wizard embebido reutiliza un borrador de sesión si lo hay (para poder
        // retomarlo): si el que queda ahí es de OTRO propietario (p.ej. un alta nueva
        // a medias que se abandonó desde PropietariosStep), no vale para este modal.
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
    }

    /** El wizard embebido terminó (alta o edición): se cierra y la lista de cuentas se recalcula sola al renderizar. */
    #[On('propietario-creado')]
    public function propietarioActualizado(): void
    {
        $this->modalPropietarioAbierto = false;
    }

    protected function rules()
    {
        $esReciboBancario = (int) $this->forma_de_pago_id === FormaDePago::RECIBO_BANCARIO;

        // Si la cuenta ya tiene un mandato ACTIVO no hace falta pedirlo otra vez (se
        // enseña el que hay); si no lo tiene, es obligatorio: sin mandato no se puede
        // remesar (ver FicheroRemesaSepa::comprobarMandatos()).
        $mandatoObligatorio = $esReciboBancario && ! $this->mandatoVigente();

        return [
            'forma_de_pago_id'          => ['required', 'exists:formas_de_pago,id'],
            // Con recibo bancario la fecha la da el mandato (nueva firma o la ya
            // registrada): aquí no se pide, se duplica en terminar().
            'forma_pago_fecha_inicio'   => [$esReciboBancario ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'persona_comunidad_id_pago' => ['required', 'exists:personas_comunidad,id'],
            'cuenta_bancaria_id'        => [$esReciboBancario ? 'required' : 'nullable', 'exists:cuentas_bancarias,id'],
            'mandato_referencia'        => [$mandatoObligatorio ? 'required' : 'nullable', 'string', 'max:35'],
            'mandato_fecha_firma'       => [$mandatoObligatorio ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'mandato_documento'         => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'forma_de_pago_id'          => __('forma de pago'),
            'forma_pago_fecha_inicio'   => __('fecha de inicio'),
            'persona_comunidad_id_pago' => __('propietario'),
            'cuenta_bancaria_id'        => __('cuenta bancaria'),
            'mandato_referencia'        => __('número de mandato'),
            'mandato_fecha_firma'       => __('fecha de firma del mandato'),
            'mandato_documento'         => __('documento del mandato'),
        ];
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session('inmueble_borrador_id');

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId) : null;
    }

    /** "Terminar": graba de golpe inmueble + titularidades (ver PropietariosStep) + forma de pago. */
    public function terminar()
    {
        $datos = $this->validate();

        $borrador = $this->borradorActual();
        if (! $borrador || empty($borrador->payload['datos'])) {
            $this->addError('forma_de_pago_id', __('Faltan los datos del inmueble. Vuelve al primer paso.'));

            return;
        }

        $esReciboBancario = (int) $datos['forma_de_pago_id'] === FormaDePago::RECIBO_BANCARIO;
        $propietarioPago  = Propietario::where('persona_comunidad_id', $datos['persona_comunidad_id_pago'])->first();

        // La cuenta elegida tiene que ser realmente de ese propietario: el
        // desplegable ya la restringe, pero esto es lo que de verdad lo garantiza.
        if ($esReciboBancario) {
            $cuentaValida = $propietarioPago && CuentaBancaria::where('id', $datos['cuenta_bancaria_id'])
                ->where('titular_type', Propietario::class)
                ->where('titular_id', $propietarioPago->id)
                ->exists();

            if (! $cuentaValida) {
                $this->addError('cuenta_bancaria_id', __('La cuenta bancaria elegida no pertenece a ese propietario.'));

                return;
            }
        }

        $payload = $borrador->payload;
        $payload['financiero'] = $datos;
        $borrador->update(['payload' => $payload]);

        $propietarios         = $payload['propietarios'] ?? [];
        $propietariosQuitados = $payload['propietarios_quitados'] ?? [];
        $cuentaBancariaId     = $esReciboBancario ? $datos['cuenta_bancaria_id'] : null;

        try {
            DB::transaction(function () use ($borrador, $propietarios, $propietariosQuitados, $datos, $cuentaBancariaId, $esReciboBancario) {
            if ($this->inmuebleId) {
                $inmueble = Inmueble::findOrFail($this->inmuebleId);
                $inmueble->update($borrador->payload['datos']);
            } else {
                $inmueble = Inmueble::create($borrador->payload['datos']);
                $this->inmuebleId = $inmueble->id;
            }

            $titularidadIdsVigentes = [];

            foreach ($propietarios as $linea) {
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

            // Las que se quitaron explícitamente (ver PropietariosStep::guardarQuitarPropietario)
            // se cierran con la fecha que se eligió al quitarlas, nunca se borran.
            foreach ($propietariosQuitados as $quitada) {
                Titularidad::whereKey($quitada['titularidad_id'])->update(['fecha_fin' => $quitada['fecha_fin']]);
            }

            // Red de seguridad: cualquier otra titularidad vigente que ya no esté en la
            // lista final (por una vía distinta a "quitar") se cierra hoy.
            Titularidad::vigente()
                ->where('inmueble_id', $inmueble->id)
                ->whereNotIn('id', $titularidadIdsVigentes)
                ->update(['fecha_fin' => now()->toDateString()]);

            // El responsable del pago se resuelve aquí, no antes de la transacción: si
            // es un propietario recién dado de alta en este mismo wizard, no existe
            // hasta que lo crea el bucle de arriba.
            $propietarioPagoId = Propietario::where('persona_comunidad_id', $datos['persona_comunidad_id_pago'])->value('id');

            // Forma de pago: igual que la titularidad, nunca se modifica la fila
            // vigente — se cierra y se abre otra, salvo que no haya cambiado nada.
            $vigente = FormaPagoInmueble::vigente()->where('inmueble_id', $inmueble->id)->first();

            $sinCambios = $vigente
                && $vigente->forma_de_pago_id == $datos['forma_de_pago_id']
                && $vigente->cuenta_bancaria_id == $cuentaBancariaId
                && $vigente->propietario_id == $propietarioPagoId;

            if (! $sinCambios) {
                // Con recibo bancario no se pide fecha aparte: es la de la firma del
                // mandato (la recién tecleada, o la del ya registrado en esa cuenta).
                $fechaInicioForma = $esReciboBancario
                    ? ($datos['mandato_fecha_firma'] ?: $this->mandatoVigente()?->fecha_firma?->toDateString())
                    : $datos['forma_pago_fecha_inicio'];

                if ($vigente) {
                    $vigente->update(['fecha_fin' => $fechaInicioForma]);
                }

                FormaPagoInmueble::create([
                    'inmueble_id'        => $inmueble->id,
                    'propietario_id'     => $propietarioPagoId,
                    'forma_de_pago_id'   => $datos['forma_de_pago_id'],
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'fecha_inicio'       => $fechaInicioForma,
                    'fecha_fin'          => null,
                ]);
            }

            // Mandato SEPA de la cuenta, si se ha registrado el papel firmado. Cuelga de
            // la cuenta y de la comunidad, no del inmueble: sirve para todos los que
            // paguen con ella.
            if ($cuentaBancariaId && filled($datos['mandato_referencia'])) {
                $mandato = app(RegistrarMandatoSepa::class)->ejecutar(
                    (int) $inmueble->comunidad_id,
                    CuentaBancaria::findOrFail($cuentaBancariaId),
                    $datos['mandato_referencia'],
                    $datos['mandato_fecha_firma'],
                );

                if ($this->mandato_documento) {
                    $mandato->adjuntarDocumento(
                        $this->mandato_documento,
                        TipoDocumento::MANDATO_SEPA,
                        __('Mandato firmado'),
                    );
                }
            }
            });
        } catch (MandatoSepaInvalidoException $e) {
            // El número tecleado no cuadra con la cuenta. No se graba nada: la
            // transacción entera se ha deshecho.
            $this->addError('mandato_referencia', $e->getMessage());

            return;
        }

        $borrador->delete();
        session()->forget('inmueble_borrador_id');

        $this->salir();
    }

    public function render()
    {
        return view('livewire.inmuebles.crear.steps.datos-financieros-step', [
            'formasDePago'         => FormaDePago::activo()->orderBy('descripcion')->get(),
            'titulares'            => $this->titulares(),
            'cuentas'              => $this->cuentasDelTitular(),
            'esReciboBancario'     => (int) $this->forma_de_pago_id === FormaDePago::RECIBO_BANCARIO,
            'comunidadIdParaModal' => $this->comunidadId(),
            // Si la cuenta ya tiene mandato no se pide otro: se enseña el que hay.
            'mandatoVigente'       => $this->mandatoVigente(),
            'urlPlantillaMandato'  => $this->urlPlantillaMandato(),
            'historicoFormasPago'  => $this->historicoFormasPago(),
        ]);
    }
}
