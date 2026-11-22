<?php
namespace App\Livewire;

use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal «Acerca de…»: nombre de la app y versión. La versión la deja en el .env el
 * comando doslago:version al desplegar; aquí solo se lee.
 */
class AcercaDe extends Component
{
    public bool $abrirAcercaDe = false;

    #[On('abrir-acerca-de')]
    public function abrir()
    {
        $this->abrirAcercaDe = true;
    }

    public function close()
    {
        $this->abrirAcercaDe = false;
    }

    /**
     * «Versión v1.8.1 del 17/11/2026», «Versión v1.8.1» (sin fecha), «Versión del 17/11/2026»
     * (sin etiqueta) o «Versión desconocida».
     */
    protected function version(): string
    {
        $etiqueta = config('doslago.version.etiqueta');
        $fecha    = $this->fecha();

        if (filled($etiqueta)) {
            return $fecha !== null
                ? __('Versión :version del :fecha', ['version' => $etiqueta, 'fecha' => $fecha])
                : __('Versión :version', ['version' => $etiqueta]);
        }

        return $fecha !== null ? __('Versión del :fecha', ['fecha' => $fecha]) : __('Versión desconocida');
    }

    /** La fecha de la versión en dd/mm/aaaa, o null si no hay o está mal escrita. */
    protected function fecha(): ?string
    {
        $fecha = config('doslago.version.fecha');

        if (blank($fecha)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.acerca-de', ['version' => $this->version()]);
    }
}
