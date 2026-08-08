<?php
namespace App\Livewire\AdministracionSistema\Usuarios;

use App\Models\User;
use App\Rules\IsMayorEdad;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Livewire\Traits\WithGenero;

class Editar extends Component
{
    use WithGenero;

    public bool $abrirEditar = false;
    public bool $activoGuardar = true;

    public ?User $usuario = null;

    // Datos de la persona (se editan y se guardan)
    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public ?int $genero_id = null;
    public ?string $fecha_nacimiento = null;

    // Datos de acceso
    public string $login = '';
    public string $email = '';
    public array $roles = [];

    public function rules()
    {
        return [
            'nombre'           => ['required', 'string', 'max:255'],
            'apellido1'        => ['required', 'string', 'max:255'],
            'apellido2'        => ['nullable', 'string', 'max:255'],
            'genero_id'        => ['nullable', 'integer', 'exists:tipo_generos,id'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today', new IsMayorEdad()],
            'login'            => ['required', 'string', 'max:255', Rule::unique('users', 'login')->ignore($this->usuario->id)],
            'email'            => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuario->id)],
            'roles'            => ['array'],
            // El rol superadmin no se asigna desde aquí: tendrá su propio formulario.
            'roles.*'          => ['string', 'exists:roles,name', 'not_in:'.config('doslago.superadmin.nombre_rol')],
        ];
    }

    public function messages()
    {
        return [
            'login.unique'    => __('Ya existe un usuario con ese login.'),
            'email.unique'    => __('Ya existe un usuario con ese email.'),
            'before_or_equal' => __('La fecha no puede ser futura.'),
        ];
    }

    #[On('usuarioeditar')]
    public function editar($usuario_id)
    {
        abort_unless(auth()->user()->can('user-edit'), 403);

        $usuario = User::with(['persona', 'roles', 'estado'])->find($usuario_id);
        if (! $usuario) {
            session()->flash('error', __('El usuario no existe o ha sido eliminado.'));
            return;
        }

        $this->usuario          = $usuario;
        $this->nombre           = $usuario->persona?->nombre ?? '';
        $this->apellido1        = $usuario->persona?->apellido1 ?? '';
        $this->apellido2        = $usuario->persona?->apellido2 ?? '';
        $this->genero_id        = $usuario->persona?->genero_id;
        $this->fecha_nacimiento = $usuario->persona?->fecha_nacimiento
            ? \Carbon\Carbon::parse($usuario->persona->fecha_nacimiento)->format('Y-m-d')
            : null;
        $this->login            = $usuario->login;
        $this->email            = $usuario->email;
        $this->roles            = $usuario->getRoleNames()
            ->reject(fn ($rol) => $rol === config('doslago.superadmin.nombre_rol'))
            ->values()->all();
        $this->abrirEditar      = true;

        // El foco arranca en login: los datos de persona quedan visibles pero
        // lo habitual es venir a tocar las credenciales de acceso.
        $this->dispatch('foco-campo', id: 'input-editar-login');
    }

    public function render()
    {
        $this->setGeneros();
        // El rol superadmin no se ofrece aquí: tendrá su propio formulario.
        $rolesDisponibles = Role::where('name', '<>', config('doslago.superadmin.nombre_rol'))
            ->orderBy('name')->get();

        return view('livewire.administracion-sistema.usuarios.editar', compact('rolesDisponibles'));
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('user-edit'), 403);

        $validated = $this->validate();

        $persona                   = $this->usuario->persona;
        $persona->nombre           = $validated['nombre'];
        $persona->apellido1        = $validated['apellido1'];
        $persona->apellido2        = $validated['apellido2'];
        $persona->genero_id        = $validated['genero_id'];
        $persona->fecha_nacimiento = $validated['fecha_nacimiento'];
        $persona->save();

        $usuario        = $this->usuario;
        $usuario->login = $validated['login'];
        $usuario->email = $validated['email'];
        $usuario->save();

        // El rol superadmin no pasa por este formulario: si el usuario ya lo
        // tenía, se conserva al margen de lo marcado en los checkboxes.
        $roles = $this->usuario->hasRole(config('doslago.superadmin.nombre_rol'))
            ? [...$this->roles, config('doslago.superadmin.nombre_rol')]
            : $this->roles;
        $usuario->syncRoles($roles);

        $this->dispatch('toast-success', [
            'title' => __('El usuario ha sido modificado'),
        ]);

        $this->dispatch('usuarios-renderizado');
        $this->close();
    }

    public function close()
    {
        $this->reset();
        $this->abrirEditar = false;
    }
}
