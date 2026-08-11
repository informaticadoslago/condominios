<?php

namespace App\Livewire\AdministracionSistema\Comunidades;

use App\Services\Comunidades\ImportadorZipComunidad;
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

    public function importar()
    {
        $this->validate();

        if (! $this->zip) {
            return;
        }

        $nombreOriginal = (string) $this->zip->getClientOriginalName();
        if (! str_ends_with(strtolower($nombreOriginal), '.zip')) {
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
            app(ImportadorZipComunidad::class)->importar($ruta, $nombreOriginal);
        } catch (\RuntimeException $e) {
            $this->addError('zip', $e->getMessage());
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', ['title' => __('Comunidad importada correctamente')]);

        return redirect()->route('comunidades.index');
    }

    public function cerrar()
    {
        return redirect()->route('comunidades.index');
    }

    public function render()
    {
        return view('livewire.administracion-sistema.comunidades.importar');
    }
}