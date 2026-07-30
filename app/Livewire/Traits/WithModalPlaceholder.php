<?php
namespace App\Livewire\Traits;

trait WithModalPlaceholder
{
    public function placeholder()
    {
        return <<<'HTML'
    <div class="flex justify-center items-center">
        <i class="fa fa-spinner fa-spin"></i> Cargando...
    </div>
    HTML;
    }
}
