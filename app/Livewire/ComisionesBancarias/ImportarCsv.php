<?php

namespace App\Livewire\ComisionesBancarias;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\Remesa;
use App\Models\TipoComisionBancaria;
use App\Services\ComisionesBancarias\ClasificarComisionesDesdeMovimientos;
use App\Services\ComisionesBancarias\RegistrarComisionBancariaService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Clasifica los movimientos que ya están en movimientos_bancarios (ver
 * MovimientosBancarios\ImportarCsv, que es quien de verdad lee el extracto del banco)
 * en comisiones: se elige la cuenta, se enseña qué se va a dar de alta (con lo ya
 * importado y lo descartado aparte, para que quede claro por qué no está), y solo al
 * confirmar se escribe algo.
 */
class ImportarCsv extends Component
{
    public bool $abrir = false;
    public bool $analizado = false;
    public ?string $error = null;

    public ?int $cuentaBancariaId = null;

    /** Si se abre desde una remesa (comisión de devolución), sus comisiones quedan
     *  asociadas a ella; si se abre de forma general, va sin remesa. */
    public ?int $remesaId = null;

    /** [['fecha','referencia','codigo','concepto','lineas' => [...]], ...] */
    public array $candidatas = [];
    public array $yaProcesadas = [];
    public array $descartadas = [];

    /** Índices de $candidatas marcados para importar. */
    public array $seleccionadas = [];

    #[On('abrir-importar-csv')]
    public function mostrar($remesaId = null)
    {
        $this->reset(['analizado', 'error', 'candidatas', 'yaProcesadas', 'descartadas', 'seleccionadas']);
        $this->resetErrorBag();
        $this->remesaId = $remesaId ? (int) $remesaId : null;

        $remesa = $this->remesaId ? Remesa::find($this->remesaId) : null;
        $this->cuentaBancariaId = $remesa?->cuenta_bancaria_id
            ?? $this->cuentasBancariasComunidad()->first()?->id;

        $this->abrir = true;
    }

    /** Cuentas bancarias de la comunidad activa, para el selector. */
    public function cuentasBancariasComunidad()
    {
        return CuentaBancaria::where('titular_type', Comunidad::class)
            ->where('titular_id', session('comunidad_actual_id'))
            ->orderBy('alias')
            ->get();
    }

    /** Una descartada que en realidad sí es una comisión: se marca a mano desde aquí. */
    public function convertirDescartada(int $movimientoId): void
    {
        $this->dispatch('abrir-convertir-en-comision', movimientoId: $movimientoId);
    }

    public function procesar(ClasificarComisionesDesdeMovimientos $servicio)
    {
        if (! $this->cuentaBancariaId) {
            $this->error = __('Esta comunidad no tiene ninguna cuenta bancaria dada de alta.');

            return;
        }

        $resultado = $servicio->analizar($this->cuentaBancariaId);

        if ($resultado['error']) {
            $this->error = $resultado['error'];

            return;
        }

        $this->error         = null;
        $this->candidatas     = $resultado['candidatas'];
        $this->yaProcesadas   = $resultado['yaProcesadas'];
        $this->descartadas    = $resultado['descartadas'];

        // Las de fuera del ejercicio en curso no se premarcan: si en su día no se
        // importó el fichero, lo normal es que ya se metieran a mano entonces. Si se
        // abrió desde una remesa, el extracto puede traer también la liquidación normal
        // u otras comisiones sueltas: aquí solo interesa la de devolución, así que las
        // demás se enseñan pero sin marcar, para no importarlas de rebote.
        $this->seleccionadas = array_map('strval', array_keys(array_filter(
            $this->candidatas,
            fn ($c) => ! $c['fuera_ejercicio']
                && (! $this->remesaId || $c['codigo'] === TipoComisionBancaria::DEVOLUCION),
        )));

        $this->analizado = true;
    }

    public function importar(RegistrarComisionBancariaService $servicio)
    {
        $empresaId = Comunidad::find(session('comunidad_actual_id'))?->empresa_contable_id;

        if (! $empresaId) {
            $this->dispatch('toast-error', ['title' => __('Esta comunidad no está enlazada con ninguna empresa contable.')]);

            return;
        }

        $tipos = TipoComisionBancaria::where('empresa_contable_id', $empresaId)->get()->keyBy('codigo');

        $importadas = 0;
        $sinContabilizar = 0;

        foreach ($this->seleccionadas as $indice) {
            $candidata = $this->candidatas[$indice] ?? null;
            $tipo      = $tipos[$candidata['codigo'] ?? ''] ?? null;

            if (! $candidata || ! $tipo) {
                continue;
            }

            try {
                $servicio->registrar(
                    cuentaBancariaId: $this->cuentaBancariaId,
                    tipoComisionBancariaId: $tipo->id,
                    remesaId: $this->remesaId,
                    fecha: $candidata['fecha'],
                    concepto: $candidata['concepto'],
                    referencia: $candidata['referencia'],
                    lineas: $candidata['lineas'],
                );
            } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException) {
                // La comisión queda registrada (registrar() ya la creó antes de intentar
                // el asiento); lo que falta es contabilizarla, luego con "Contabilizar"
                // en la lista una vez exista el ejercicio o la cuenta que falte.
                $sinContabilizar++;
            }

            $importadas++;
        }

        $this->dispatch($importadas > 0 ? 'toast-success' : 'toast-error', [
            'title' => match (true) {
                $importadas === 0 => __('No se ha importado nada: no había ninguna seleccionada'),
                $sinContabilizar > 0 => __(':total importadas, :sin sin contabilizar (ejercicio o cuenta pendiente)', ['total' => $importadas, 'sin' => $sinContabilizar]),
                default => __(':count comisiones importadas', ['count' => $importadas]),
            },
        ]);

        $this->cerrar();
        $this->dispatch('comision-bancaria-importada');
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    #[Computed]
    public function remesa(): ?Remesa
    {
        return $this->remesaId ? Remesa::find($this->remesaId) : null;
    }

    public function render()
    {
        return view('livewire.comisiones-bancarias.importar-csv');
    }
}
