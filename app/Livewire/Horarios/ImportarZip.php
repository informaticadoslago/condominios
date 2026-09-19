<?php

namespace App\Livewire\Horarios;

use App\Services\Horarios\ImportadorZipHorario;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Importa el .zip generado por `condominios:horario-exportar`: no crea tablas nuevas,
 * inserta filas en las existentes con ids nuevos (ver ImportadorZipHorario).
 */
class ImportarZip extends Component
{
    use WithFileUploads;

    public bool $abrir = false;

    public $zip = null;

    protected function rules(): array
    {
        return ['zip' => ['required', 'file', 'extensions:zip', 'max:10240']];
    }

    #[On('abrir-importar-horario')]
    public function mostrar(): void
    {
        $this->reset('zip');
        $this->resetErrorBag();
        $this->abrir = true;
    }

    public function importar(ImportadorZipHorario $importador): void
    {
        $this->validate();

        $nombreOriginal = (string) $this->zip->getClientOriginalName();
        $ruta = $this->zip->store('importaciones-horarios', 'local');

        try {
            $importador->importar($ruta, $nombreOriginal);
        } catch (RuntimeException $e) {
            $this->addError('zip', $e->getMessage());
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->abrir = false;
        $this->dispatch('toast-success', ['title' => __('Horario importado correctamente')]);
        $this->dispatch('horario-importado');
    }

    public function cerrar(): void
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.importar-zip');
    }
}
