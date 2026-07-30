<?php
namespace App\Livewire;

use App\Models\EstadoUsuario;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Impersonar extends Component
{
    public bool $abrirImpersonar = false;
    public ?int $usuarioId       = null;

    #[On('abrir-impersonar')]
    public function abrir()
    {
        abort_unless(auth()->user()->canImpersonate(), 403);

        $this->reset(['usuarioId']);
        $this->abrirImpersonar = true;
    }

    public function close()
    {
        $this->reset(['usuarioId']);
        $this->abrirImpersonar = false;
    }

    public function impersonar()
    {
        $this->validate(
            ['usuarioId' => ['required', 'integer', 'exists:users,id']],
            [],
            ['usuarioId' => __('usuario')]
        );

        $usuario = User::findOrFail($this->usuarioId);

        // El controlador del paquete lo vuelve a comprobar y responde 403; aquí cortamos
        // antes para no llevar a una pantalla de error a quien no puede pasar.
        abort_unless(auth()->user()->canImpersonate() && $usuario->canBeImpersonated(), 403);

        // La ruta exige `password.confirm`, pero da por buena una confirmación de hasta
        // `auth.password_timeout` (3h). Olvidándola aquí, la contraseña se pide siempre.
        session()->forget('auth.password_confirmed_at');

        return redirect()->route('impersonate', $usuario->id);
    }

    public function render()
    {
        // Mismo criterio que User::canBeImpersonated(), para no ofrecer lo que la ruta
        // rechazaría con un 403.
        $usuarios = User::query()
            ->join('personas', 'users.persona_id', '=', 'personas.id')
            ->select('users.*')
            ->with('persona')
            ->where('users.id', '!=', auth()->id())
            ->where('users.estado_id', EstadoUsuario::USUARIO_ACTIVO)
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', config('doslago.superadmin.nombre_rol'));
            })
            ->orderBy('personas.apellido1')
            ->orderBy('personas.apellido2')
            ->orderBy('personas.nombre')
            ->get();

        return view('livewire.impersonar', compact('usuarios'));
    }
}
