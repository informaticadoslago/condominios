<?php
namespace App\Livewire;

use Livewire\Component;

class CrudComponent extends Component
{
    public bool $abrirCrear = false;

    public function close()
    {
        $this->reset();
        $this->abrirCrear = false;
    }

}
