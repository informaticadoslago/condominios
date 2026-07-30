<?php
namespace App\Livewire\AdministracionSistema\Usuarios;

use App\Models\Pais;
use App\Models\User;
use App\Models\Persona;
use App\Rules\IsNieRule;
use App\Rules\IsNifRule;
use App\Models\EstadoUsuario;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Livewire\CrudComponent;
use App\Livewire\Forms\PersonaForm;
use App\Livewire\Traits\WithGenero;
use Spatie\Permission\Models\Role;
use App\Models\TipoDocumentoIdentificativo;

class Crear extends CrudComponent
{
    use WithGenero;

    public PersonaForm $formulario;
    public $paises;

    /** El documento ya se ha comprobado contra la BD. */
    public bool $comprobado = false;
    /** La persona ya existe: sus datos se muestran bloqueados. */
    public bool $personaExiste = false;
    /** La persona ya tiene un usuario: ofrecemos editarlo en vez de crearlo. */
    public bool $usuarioYaExiste = false;
    public ?int $usuarioExistenteId = null;

    // Credenciales de acceso
    public string $login = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array $roles = [];

    private bool $inicializado = false;

    public function mount()
    {
        $this->inicializa();
    }

    public function inicializa()
    {
        if ($this->inicializado) {
            return;
        }
        $this->formulario->persona = new Persona();
        // Los usuarios sólo pueden ser personas físicas: excluimos la jurídica (CIF).
        $this->formulario->soloDocumentosFisica = true;
        // Un usuario debe ser mayor de edad.
        $this->formulario->exigeMayorEdad = true;
        $this->formulario->refrescarTiposDocumento();
        $this->formulario->tipo_documento_id              = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->paises                                     = Pais::activo()->ordenGrupo()->get();
        if ($this->paises->isNotEmpty()) {
            $this->formulario->documento_pais_id = Pais::porDefecto();
        }
        $this->setGeneros();
        $this->inicializado = true;
    }

    #[On('abrir-crear')]
    public function abrirModal()
    {
        abort_unless(auth()->user()->can('user-create'), 403);

        $this->inicializa();
        $this->reset(['login', 'email', 'password', 'password_confirmation', 'roles', 'comprobado', 'personaExiste', 'usuarioYaExiste', 'usuarioExistenteId']);
        // Un usuario nuevo nace con el rol básico marcado.
        $this->roles = ['user'];
        $this->formulario->resetForm();
        $this->formulario->persona           = new Persona();
        $this->formulario->tipo_documento_id = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
        $this->formulario->documento_pais_id = Pais::porDefecto();
        $this->abrirCrear                    = true;
    }

    // Cualquier cambio en el documento obliga a volver a comprobar.
    public function updatedFormularioDocumentoIdentificativo()
    {
        $this->resetComprobacion();
    }

    public function updatedFormularioTipoDocumentoId()
    {
        $this->resetComprobacion();
    }

    public function updatedFormularioDocumentoPaisId()
    {
        $this->resetComprobacion();
    }

    private function resetComprobacion()
    {
        $this->comprobado          = false;
        $this->personaExiste       = false;
        $this->usuarioYaExiste     = false;
        $this->usuarioExistenteId  = null;
        $this->formulario->persona = new Persona();
        $this->formulario->persona_id       = 0;
        $this->formulario->nombre           = '';
        $this->formulario->apellido1        = '';
        $this->formulario->apellido2        = '';
        $this->formulario->fecha_nacimiento = null;
        $this->formulario->genero_id        = null;
        $this->formulario->resetValidation();
    }

    public function comprobar()
    {
        // El documento debe ser válido para el tipo elegido antes de buscarlo:
        // "123456" no es un NIF, así que no es que "no exista", es que no puede existir.
        $documentoRules = ['required', 'string', 'max:40'];
        if ($this->formulario->documento_pais_id == Pais::ESPAÑA) {
            if ($this->formulario->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIF) {
                $documentoRules[] = new IsNifRule();
            } elseif ($this->formulario->tipo_documento_id == TipoDocumentoIdentificativo::DOCUMENTO_NIE) {
                $documentoRules[] = new IsNieRule();
            }
        }

        $this->validate([
            'formulario.documento_pais_id'        => ['required', 'exists:paises,id'],
            'formulario.tipo_documento_id'        => ['required', 'exists:tipo_documento_identificativos,id'],
            'formulario.documento_identificativo' => $documentoRules,
        ]);

        $persona = Persona::where('documento_identificativo', $this->formulario->documento_identificativo)->first();

        if ($persona) {
            if (TipoDocumentoIdentificativo::isTipoDocumento($persona->tipo_documento_id, TipoDocumentoIdentificativo::TIPO_JURIDICA)) {
                $this->dispatch('toast-error', [
                    'title' => __('Los usuarios no pueden ser personas jurídicas.'),
                ]);
                return;
            }
            if ($persona->usuario()->exists()) {
                // Ya tiene usuario: no se crea, se ofrece editarlo.
                $this->usuarioYaExiste    = true;
                $this->usuarioExistenteId = $persona->usuario()->value('id');
                return;
            }
            // Persona existente: volcamos sus datos y quedarán bloqueados.
            $this->formulario->persona = $persona;
            $this->formulario->setPersona();
            $this->personaExiste = true;
        } else {
            // Persona nueva: se pedirán el resto de datos.
            $this->personaExiste = false;
        }

        $this->comprobado = true;

        // Si ya existe (datos bloqueados) el foco salta a login; si es nueva, a nombre.
        $this->dispatch('foco-campo', id: $this->personaExiste ? 'input-crear-login' : 'input-crear-nombre');
    }

    /** Confirmación afirmativa: cerramos crear y abrimos el usuario existente en editar. */
    public function editarUsuarioExistente()
    {
        if (! $this->usuarioExistenteId) {
            return;
        }
        $id = $this->usuarioExistenteId;
        $this->close();
        $this->dispatch('usuarioeditar', usuario_id: $id);
    }

    /** Confirmación negativa: volvemos al mini-diálogo de introducir el documento. */
    public function noEditarUsuarioExistente()
    {
        $this->resetComprobacion();
        $this->formulario->documento_identificativo = '';
        $this->dispatch('foco-campo', id: 'input-crear-documento-identificativo');
    }

    public function generarPassword(): void
    {
        $password = Str::password(12);
        $this->password = $password;
        $this->password_confirmation = $password;
    }

    public function guardar()
    {
        abort_unless(auth()->user()->can('user-create'), 403);

        if (! $this->comprobado) {
            $this->comprobar();
            if (! $this->comprobado) {
                return;
            }
        }

        // Credenciales de acceso (siempre)
        $this->validate($this->credencialesRules(), [], $this->credencialesAtributos());

        if ($this->personaExiste) {
            $persona = $this->formulario->persona;
        } else {
            $validated = $this->formulario->validate();
            $persona   = $this->formulario->store($validated);
        }

        $usuario             = new User();
        $usuario->persona_id = $persona->id;
        $usuario->login      = $this->login;
        $usuario->email      = $this->email;
        $usuario->password   = $this->password; // cast 'hashed'
        $usuario->estado_id  = EstadoUsuario::USUARIO_INICIAL;
        // T-L9-L12: fijamos también la columna vieja `estado` para que nazca
        // sincronizada con `estado_id` (el trigger solo sincroniza si una es NULL).
        $usuario->estado     = EstadoUsuario::USUARIO_INICIAL;
        $usuario->save();

        if ($usuario->exists) {
            $usuario->syncRoles($this->roles);
            $this->dispatch('toast-success', [
                'title' => __('El usuario ha sido creado'),
            ]);
        } else {
            $this->dispatch('toast-error', [
                'title' => __('Error: el usuario NO ha sido creado'),
            ]);
        }

        $this->dispatch('usuarios-renderizado');
        $this->close();
    }

    private function credencialesRules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:255', 'unique:users,login'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles'    => ['array'],
            // El rol superadmin no se asigna desde aquí: tendrá su propio formulario.
            'roles.*'  => ['string', 'exists:roles,name', 'not_in:'.config('doslago.superadmin.nombre_rol')],
        ];
    }

    private function credencialesAtributos(): array
    {
        return [
            'login'    => __('login'),
            'email'    => __('email'),
            'password' => __('contraseña'),
        ];
    }

    public function render()
    {
        // El rol superadmin no se ofrece aquí: tendrá su propio formulario.
        $rolesDisponibles = Role::where('name', '<>', config('doslago.superadmin.nombre_rol'))
            ->orderBy('name')->get();

        return view('livewire.administracion-sistema.usuarios.crear', compact('rolesDisponibles'));
    }

    public function close()
    {
        $this->reset(['login', 'email', 'password', 'password_confirmation', 'roles', 'comprobado', 'personaExiste']);
        $this->formulario->resetForm();
        $this->abrirCrear = false;
    }
}
