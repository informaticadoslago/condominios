<?php

namespace App\Livewire\ComisionesBancarias;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\Comunidad;
use App\Models\Remesa;
use App\Models\TipoComisionBancaria;
use App\Services\ComisionesBancarias\ImportarComisionesBancariasCsv;
use App\Services\ComisionesBancarias\RegistrarComisionBancariaService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importa comisiones bancarias desde el extracto del banco (CSV o Q43/Norma 43, el
 * formato se reconoce solo): se analiza el fichero, se enseña qué se va a dar de alta
 * (con lo ya importado y lo descartado aparte, para que quede claro por qué no está), y
 * solo al confirmar se escribe algo.
 */
class ImportarCsv extends Component
{
    use WithFileUploads;

    public bool $abrir = false;
    public $fichero = null;
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

    protected function rules()
    {
        // extensions y no mimes: Q43 no tiene un tipo MIME que Symfony reconozca, así
        // que mimes lo rechazaría siempre pasara lo que pasara; extensions mira el
        // nombre de fichero tal cual lo mandó el navegador.
        return ['fichero' => ['required', 'file', 'extensions:csv,txt,q43', 'max:5120']];
    }

    #[On('abrir-importar-csv')]
    public function mostrar($remesaId = null)
    {
        $this->reset(['fichero', 'analizado', 'error', 'cuentaBancariaId', 'candidatas', 'yaProcesadas', 'descartadas', 'seleccionadas']);
        $this->resetErrorBag();
        $this->remesaId = $remesaId ? (int) $remesaId : null;
        $this->abrir = true;
    }

    public function procesar(ImportarComisionesBancariasCsv $servicio)
    {
        $this->validate();

        $resultado = $servicio->analizar(file_get_contents($this->fichero->getRealPath()));

        if ($resultado['error']) {
            $this->error = $resultado['error'];

            return;
        }

        $this->error            = null;
        $this->cuentaBancariaId = $resultado['cuentaBancaria']->id;
        $this->candidatas       = $resultado['candidatas'];
        $this->yaProcesadas     = $resultado['yaProcesadas'];
        $this->descartadas      = $resultado['descartadas'];

        // Las de fuera del ejercicio en curso no se premarcan: si en su día no se
        // importó el fichero, lo normal es que ya se metieran a mano entonces. Si se
        // abrió desde una remesa, el fichero puede traer también la liquidación normal
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
