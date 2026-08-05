<?php

namespace App\Livewire\Inmuebles\Crear\Steps;

use App\Livewire\Inmuebles\Crear\CrearInmuebleStep;
use App\Models\Borrador;
use App\Models\CuentaBancaria;
use App\Models\FormaDePago;
use App\Models\FormaPagoInmueble;
use App\Models\Inmueble;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\Titularidad;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Último paso: forma de pago del inmueble y, si es recibo bancario, el propietario
 * (uno de los titulares elegidos en PropietariosStep) y una de sus cuentas
 * bancarias. "Terminar" graba de golpe TODO lo acumulado en el borrador: inmueble,
 * titularidades (ver PropietariosStep) y esta forma de pago — nada es real hasta
 * aquí.
 */
class DatosFinancierosStep extends CrearInmuebleStep
{
    public ?int $inmuebleId = null;

    public $forma_de_pago_id = null;

    /** persona_comunidad_id del titular elegido como responsable del recibo (solo si RECIBO_BANCARIO). */
    public $persona_comunidad_id_pago = null;
    public $cuenta_bancaria_id = null;

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

        return [
            'forma_de_pago_id'          => ['required', 'exists:formas_de_pago,id'],
            'persona_comunidad_id_pago' => ['required', 'exists:personas_comunidad,id'],
            'cuenta_bancaria_id'        => [$esReciboBancario ? 'required' : 'nullable', 'exists:cuentas_bancarias,id'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'forma_de_pago_id'          => __('forma de pago'),
            'persona_comunidad_id_pago' => __('propietario'),
            'cuenta_bancaria_id'        => __('cuenta bancaria'),
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

        DB::transaction(function () use ($borrador, $propietarios, $propietariosQuitados, $datos, $cuentaBancariaId) {
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
                if ($vigente) {
                    $vigente->update(['fecha_fin' => now()->toDateString()]);
                }

                FormaPagoInmueble::create([
                    'inmueble_id'        => $inmueble->id,
                    'propietario_id'     => $propietarioPagoId,
                    'forma_de_pago_id'   => $datos['forma_de_pago_id'],
                    'cuenta_bancaria_id' => $cuentaBancariaId,
                    'fecha_inicio'       => now()->toDateString(),
                    'fecha_fin'          => null,
                ]);
            }
        });

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
        ]);
    }
}
