<?php
namespace App\Livewire\AdministracionSistema\Usuarios;

use App\Mail\ConfirmacionCorreoUsuario;
use App\Models\User;
use App\Models\Persona;
use App\Models\EstadoUsuario;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;

class Lista extends ListaComponent
{
    use ConHistorialEstadoModal;
    use ConFiltroEstado;
    use ConBajaPorEstado;

    public bool $abrirResetPassword = false;

    public ?int $resetUsuarioId = null;

    public string $resetUsuarioNombre = '';

    public string $nuevaPassword = '';

    public string $nuevaPassword_confirmation = '';

    protected function modeloHistorial(): string
    {
        return User::class;
    }

    protected function modeloEstado(): string
    {
        return EstadoUsuario::class;
    }

    protected function modeloBaja(): string
    {
        return User::class;
    }

    // Baja y reactivación las hace ConBajaPorEstado, que es genérico y compartido; aquí
    // solo se antepone el permiso. Se reimplementan (no se llama al trait) porque un
    // método de trait tapado por el de la clase no es un parent al que delegar.
    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        abort_unless(auth()->user()->can('user-delete'), 403);

        $usuario = User::find($id);
        if ($usuario) {
            $usuario->estado_id = User::ESTADO_BAJA;
            $usuario->save();
            $this->dispatch('toast-success', ['title' => __('Usuario dado de baja')]);
        }
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        abort_unless(auth()->user()->can('user-delete'), 403);

        $usuario = User::find($id);
        if ($usuario) {
            $usuario->estado_id = User::ESTADO_ACTIVO;
            $usuario->save();
            $this->dispatch('toast-success', ['title' => __('Usuario reactivado')]);
        }
    }

    /**
     * Tapa confirmarReactivar() del trait: si el correo no está verificado, antes de
     * reactivar hay que decidir qué hacer con eso (no se puede reactivar a Activo sin
     * más, a ciegas de si el correo es de verdad suyo).
     */
    public function confirmarReactivar($id)
    {
        $usuario = User::find($id);
        if (! $usuario) {
            return;
        }

        if ($usuario->email_verified_at) {
            $this->dispatch('swalConfirm', [
                'title'              => __('¿Reactivar?'),
                'text'               => __('Se marcará como activo.'),
                'icon'               => 'question',
                'showCancelButton'   => true,
                'confirmButtonColor' => '#3085d6',
                'cancelButtonColor'  => '#f1c40f',
                'confirmButtonText'  => __('Sí, reactivar'),
                'cancelButtonText'   => __('Cancelar'),
                'confirmCallback'    => 'ejecutarReactivar',
                'cancelCallback'     => 'bajaCancelada',
                'id'                 => $id,
            ]);

            return;
        }

        $this->dispatch('swalConfirmDeny', [
            'title'              => __('Este usuario no tiene el correo verificado'),
            'text'               => __('¿Lo activas igualmente (queda marcado como verificado hoy) o lo dejas en Inicial?'),
            'icon'               => 'warning',
            'showDenyButton'     => true,
            'showCancelButton'   => true,
            'confirmButtonColor' => '#16a34a',
            'denyButtonColor'    => '#f59e0b',
            'cancelButtonColor'  => '#6b7280',
            'confirmButtonText'  => __('Activar'),
            'denyButtonText'     => __('Dejar en Inicial'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarReactivarSinVerificar',
            'denyCallback'       => 'ejecutarDejarInicial',
            'cancelCallback'     => 'confirmCancelado',
            'id'                 => $id,
        ]);
    }

    /** Activar sin verificar previamente: se marca verificado hoy (inmediato). */
    #[On('ejecutarReactivarSinVerificar')]
    public function ejecutarReactivarSinVerificar($id): void
    {
        abort_unless(auth()->user()->can('user-delete'), 403);

        $usuario = User::find($id);
        if (! $usuario) {
            return;
        }

        $usuario->email_verified_at = now();
        $usuario->estado_id = User::ESTADO_ACTIVO;
        $usuario->save();

        $this->dispatch('toast-success', ['title' => __('Usuario reactivado y marcado como verificado')]);
    }

    /** Dejarlo en Inicial en vez de activarlo: inmediato. */
    #[On('ejecutarDejarInicial')]
    public function ejecutarDejarInicial($id): void
    {
        abort_unless(auth()->user()->can('user-delete'), 403);

        $usuario = User::find($id);
        if (! $usuario) {
            return;
        }

        $usuario->estado_id = EstadoUsuario::USUARIO_INICIAL;
        $usuario->save();

        $this->dispatch('toast-success', ['title' => __('Usuario dejado en Inicial')]);
    }

    public function confirmarActivar(int $id): void
    {
        $this->dispatch('swalConfirmDeny', [
            'title'              => __('¿Cómo se activa este usuario?'),
            'text'               => __('Puedes pedirle que confirme su correo, o activarlo ya y darlo por verificado.'),
            'icon'               => 'warning',
            'showDenyButton'     => true,
            'showCancelButton'   => true,
            'confirmButtonColor' => '#16a34a',
            'denyButtonColor'    => '#f59e0b',
            'cancelButtonColor'  => '#6b7280',
            'confirmButtonText'  => __('Enviar correo de verificación'),
            'denyButtonText'     => __('Activar sin correo. Fecha de verificación hoy'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarActivar',
            'denyCallback'       => 'ejecutarActivarSinCorreo',
            'cancelCallback'     => 'confirmCancelado',
            'id'                 => $id,
        ]);
    }

    /** El usuario pasa a Activo y se le envía el correo de confirmación (encolado). */
    #[On('ejecutarActivar')]
    public function ejecutarActivar(int $id): void
    {
        abort_unless(auth()->user()->can('user-sendwelcomeemails'), 403);

        $usuario = User::find($id);
        if (! $usuario || $usuario->estado_id != EstadoUsuario::USUARIO_INICIAL) {
            return;
        }

        $usuario->estado_id = EstadoUsuario::USUARIO_ACTIVO;
        $usuario->save();
        Mail::to($usuario->email)->queue(new ConfirmacionCorreoUsuario($usuario));

        $this->dispatch('toast-success', ['title' => __('Usuario activado; correo enviado')]);
    }

    /** Activar sin mandar correo: se marca verificado hoy, sin pasar por el enlace. */
    #[On('ejecutarActivarSinCorreo')]
    public function ejecutarActivarSinCorreo(int $id): void
    {
        abort_unless(auth()->user()->can('user-sendwelcomeemails'), 403);

        $usuario = User::find($id);
        if (! $usuario || $usuario->estado_id != EstadoUsuario::USUARIO_INICIAL) {
            return;
        }

        $usuario->estado_id = EstadoUsuario::USUARIO_ACTIVO;
        $usuario->email_verified_at = now();
        $usuario->save();

        $this->dispatch('toast-success', ['title' => __('Usuario activado y marcado como verificado')]);
    }

    /** Reenvía el correo de bienvenida a un usuario ya activo (para probarlo o reenviarlo). */
    public function reenviarBienvenida(int $id): void
    {
        abort_unless(auth()->user()->can('user-sendwelcomeemails'), 403);

        $usuario = User::find($id);
        if (! $usuario) {
            return;
        }

        Mail::to($usuario->email)->queue(new ConfirmacionCorreoUsuario($usuario));

        $this->dispatch('toast-success', ['title' => __('Correo de bienvenida reenviado')]);
    }

    public function abrirModalResetPassword(int $id): void
    {
        abort_unless(auth()->user()->can('user-password-reset'), 403);

        $usuario = User::find($id);
        if (! $usuario) {
            return;
        }

        $this->resetUsuarioId = $usuario->id;
        $this->resetUsuarioNombre = $usuario->nombreCompleto;
        $this->nuevaPassword = '';
        $this->nuevaPassword_confirmation = '';
        $this->resetValidation();
        $this->abrirResetPassword = true;
    }

    public function generarPasswordAdmin(): void
    {
        $password = Str::password(12);
        $this->nuevaPassword = $password;
        $this->nuevaPassword_confirmation = $password;
    }

    public function guardarResetPassword(): void
    {
        abort_unless(auth()->user()->can('user-password-reset'), 403);

        $validado = $this->validate([
            'nuevaPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['nuevaPassword' => __('contraseña')]);

        $usuario = User::find($this->resetUsuarioId);
        if (! $usuario) {
            return;
        }

        $usuario->password = $validado['nuevaPassword']; // cast 'hashed'
        $usuario->save();

        $this->dispatch('toast-success', ['title' => __('Contraseña cambiada')]);
        $this->cerrarResetPassword();
    }

    public function cerrarResetPassword(): void
    {
        $this->reset(['abrirResetPassword', 'resetUsuarioId', 'resetUsuarioNombre', 'nuevaPassword', 'nuevaPassword_confirmation']);
    }

    /** El usuario canceló el diálogo de confirmación: no se hace nada. */
    #[On('confirmCancelado')]
    public function confirmCancelado($id = null): void
    {
        //
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado('users.estado_id'),
        ];
    }

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    // El único orden que ofrece el blade. Protege contra preferencias guardadas de cuando
    // la columna se llamaba 'nombre_completo' (era un alias SQL real; hoy es un accessor).
    protected function columnasOrdenables(): ?array
    {
        return ['nombre'];
    }

    // public function refreshList()
    // {
    //     //
    // }

    #[On('usuarios-renderizado')]
    public function render()
    {
        $search   = trim($this->search); // lo que viene del input

        $usuarios = $this->aplicarFiltros(
            User::with(['persona', 'roles', 'estado'])->join('personas', 'users.persona_id', '=', 'personas.id')
                ->select('users.*')
                ->withCount('historialEstados')
        )
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', function ($q2) use ($search) {
                    $q2->buscarNombreCompleto($search);
                });
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.administracion-sistema.usuarios.lista', compact('usuarios'));
    }
}
