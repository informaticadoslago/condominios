<?php

namespace App\Livewire\MovimientosBancarios;

use App\Services\MovimientosBancarios\ImportarMovimientosBancariosCsv;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importa el extracto del banco (CSV o Q43/Norma 43, se reconoce solo) tal cual: no
 * hay paso de revisión, el propio hash de cada fila evita duplicar lo que ya estaba.
 */
class ImportarCsv extends Component
{
    use WithFileUploads;

    public bool $abrir = false;
    public $fichero = null;
    public ?string $error = null;

    protected function rules()
    {
        // extensiones y no mimes: Q43 no tiene un tipo MIME que Symfony reconozca, así
        // que mimes lo rechazaría siempre pasara lo que pasara; extensions mira el
        // nombre de fichero tal cual lo mandó el navegador.
        return ['fichero' => ['required', 'file', 'extensions:csv,txt,q43', 'max:5120']];
    }

    #[On('abrir-importar-movimientos-bancarios')]
    public function mostrar(): void
    {
        $this->reset(['fichero', 'error']);
        $this->resetErrorBag();
        $this->abrir = true;
    }

    public function procesar(ImportarMovimientosBancariosCsv $servicio): void
    {
        $this->validate();

        $resultado = $servicio->importar(file_get_contents($this->fichero->getRealPath()));

        if ($resultado['error']) {
            $this->error = $resultado['error'];

            return;
        }

        $this->dispatch('toast-success', [
            'title' => $resultado['importados'] > 0
                ? __(':importados movimientos importados, :saltados ya existían', ['importados' => $resultado['importados'], 'saltados' => $resultado['saltados']])
                : __('No había ningún movimiento nuevo que importar'),
        ]);

        $this->cerrar();
        $this->dispatch('movimiento-bancario-importado');
    }

    public function cerrar(): void
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.movimientos-bancarios.importar-csv');
    }
}
