<?php

namespace App\Livewire\ComisionesBancarias;

use App\Models\Comunidad;
use App\Models\TipoComisionBancaria;
use App\Services\ComisionesBancarias\ImportarComisionesBancariasCsv;
use App\Services\ComisionesBancarias\RegistrarComisionBancariaService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importa comisiones bancarias desde el extracto CSV del banco: se analiza el fichero,
 * se enseña qué se va a dar de alta (con lo ya importado y lo descartado aparte, para
 * que quede claro por qué no está), y solo al confirmar se escribe algo.
 */
class ImportarCsv extends Component
{
    use WithFileUploads;

    public bool $abrir = false;
    public $fichero = null;
    public bool $analizado = false;
    public ?string $error = null;

    public ?int $cuentaBancariaId = null;

    /** [['fecha','referencia','codigo','concepto','lineas' => [...]], ...] */
    public array $candidatas = [];
    public array $yaProcesadas = [];
    public array $descartadas = [];

    /** Índices de $candidatas marcados para importar. */
    public array $seleccionadas = [];

    protected function rules()
    {
        return ['fichero' => ['required', 'file', 'mimes:csv,txt', 'max:5120']];
    }

    #[On('abrir-importar-csv')]
    public function mostrar()
    {
        $this->reset(['fichero', 'analizado', 'error', 'cuentaBancariaId', 'candidatas', 'yaProcesadas', 'descartadas', 'seleccionadas']);
        $this->resetErrorBag();
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
        $this->seleccionadas    = array_map('strval', array_keys($this->candidatas));
        $this->analizado        = true;
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

        foreach ($this->seleccionadas as $indice) {
            $candidata = $this->candidatas[$indice] ?? null;
            $tipo      = $tipos[$candidata['codigo'] ?? ''] ?? null;

            if (! $candidata || ! $tipo) {
                continue;
            }

            $servicio->registrar(
                cuentaBancariaId: $this->cuentaBancariaId,
                tipoComisionBancariaId: $tipo->id,
                remesaId: null,
                fecha: $candidata['fecha'],
                concepto: $candidata['concepto'],
                referencia: $candidata['referencia'],
                lineas: $candidata['lineas'],
            );

            $importadas++;
        }

        $this->dispatch($importadas > 0 ? 'toast-success' : 'toast-error', [
            'title' => $importadas > 0
                ? __(':count comisiones importadas', ['count' => $importadas])
                : __('No se ha importado nada: no había ninguna seleccionada'),
        ]);

        $this->cerrar();
        $this->dispatch('comision-bancaria-importada');
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.comisiones-bancarias.importar-csv');
    }
}
