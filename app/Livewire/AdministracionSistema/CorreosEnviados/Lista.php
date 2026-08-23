<?php

namespace App\Livewire\AdministracionSistema\CorreosEnviados;

use App\Livewire\ListaComponent;
use App\Models\CorreoEnviado;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'enviado_at';
        $this->direction = 'desc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['enviado_at', 'tipo', 'destinatario', 'asunto'];
    }

    #[On('correos-enviados-renderizado')]
    public function render()
    {
        $correos = CorreoEnviado::with('user')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('destinatario', 'like', '%'.$this->search.'%')
                        ->orWhere('asunto', 'like', '%'.$this->search.'%')
                        ->orWhere('tipo', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.administracion-sistema.correos-enviados.lista', compact('correos'));
    }
}
