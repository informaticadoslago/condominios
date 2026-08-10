<?php

namespace App\Livewire\AdministracionSistema\Comunidades;

use App\Jobs\ImportarComunidadZipJob;
use App\Services\Comunidades\ImportadorZipComunidad;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Importar extends Component
{
    use WithFileUploads;

    public bool $abrir = false;

    public $zip = null;

    protected function rules(): array
    {
        return [
            'zip' => 'required',
        ];
    }

    public function mount(): void
    {
        $this->abrir = true;
    }

    public function importar(): void
    {
        $this->validate();

        if (! $this->zip) {
            return;
        }

        $nombreOriginal = strtolower((string) $this->zip->getClientOriginalName());
        if (! str_ends_with($nombreOriginal, '.zip')) {
            $this->addError('zip', __('El fichero debe ser un .zip'));
            $this->dispatch('toast-error', ['title' => __('El fichero debe ser un .zip')]);

            return;
        }

        try {
            $ruta = $this->zip->store('importaciones-comunidades', 'local');
        } catch (\Throwable $e) {
            // El temporal de Livewire pudo caducar o perderse antes de pulsar Importar.
            $this->addError('zip', __('No se pudo leer el fichero subido. Vuelve a seleccionarlo.'));
            $this->dispatch('toast-error', ['title' => __('No se pudo leer el fichero subido. Vuelve a seleccionarlo.')]);
            $this->reset('zip');

            return;
        }

        try {
            app(ImportadorZipComunidad::class)->validarCifDisponible($ruta);
        } catch (\RuntimeException $e) {
            Storage::disk('local')->delete($ruta);
            $this->addError('zip', $e->getMessage());
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        ImportarComunidadZipJob::dispatch($ruta);

        $this->dispatch('toast-success', ['title' => __('Importación de comunidad en curso')]);
        $this->reset('zip');
        $this->abrir = false;
    }

    public function cerrar(): void
    {
        $this->abrir = false;
        $this->reset('zip');
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.administracion-sistema.comunidades.importar');
    }
}