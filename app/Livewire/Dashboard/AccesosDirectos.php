<?php
namespace App\Livewire\Dashboard;

use App\Models\AccesoDirecto;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AccesosDirectos extends Component
{
    public bool $ordenando = false;

    #[Computed]
    public function accesos()
    {
        return AccesoDirecto::where('user_id', auth()->id())
            ->deMenu()
            ->orderBy('orden')->orderBy('id')->get();
    }

    /** Entradas a una comunidad o a una empresa contable: van arriba y con su color. */
    #[Computed]
    public function fichas()
    {
        return AccesoDirecto::where('user_id', auth()->id())
            ->fichas()
            ->orderBy('orden')->orderBy('id')->get();
    }

    public function toggleOrdenar()
    {
        $this->ordenando = ! $this->ordenando;
    }

    public function moverAntesDe($origenId, $destinoId)
    {
        if ($origenId == $destinoId) {
            return;
        }

        $userId = auth()->id();
        $ids    = AccesoDirecto::where('user_id', $userId)
            ->orderBy('orden')->orderBy('id')->pluck('id')->all();

        $ids = array_values(array_filter($ids, fn ($id) => $id != $origenId));
        $pos = array_search((int) $destinoId, $ids);
        if ($pos === false) {
            $pos = count($ids);
        }
        array_splice($ids, $pos, 0, [(int) $origenId]);

        foreach ($ids as $i => $id) {
            AccesoDirecto::where('id', $id)->where('user_id', $userId)->update(['orden' => $i + 1]);
        }

        unset($this->accesos, $this->fichas);
    }

    public function eliminar($id)
    {
        AccesoDirecto::where('id', $id)->where('user_id', auth()->id())->delete();
        unset($this->accesos, $this->fichas);
    }

    public function render()
    {
        return view('livewire.dashboard.accesos-directos');
    }
}
