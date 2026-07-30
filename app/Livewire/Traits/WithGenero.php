<?php
namespace App\Livewire\Traits;

use App\Models\TipoGenero;

trait WithGenero
{

    public $generos;

    protected function setGeneros()
    {
        $this->generos = TipoGenero::query()->orderBy('nombre', 'ASC')->get();
    }

}
