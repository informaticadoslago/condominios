<?php
namespace Database\Seeders;

use App\Models\EstadoUsuario;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateSuperUserSeeder extends Seeder
{
    /**
     * Crea o sobrescribe (por login/documento) los dos usuarios superadmin,
     * sin tocar el resto de usuarios existentes.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando  rol SuperAdmin con TODOS los permisos.');
        $hoy = \Carbon\Carbon::now();
        $rol = Role::firstOrCreate(['name' => config('doslago.superadmin.nombre_rol')]);

        $this->command->info('Creando  uno.');

        $persona = Persona::updateOrCreate(['documento_identificativo' => '36000000D'],
            [
                'tipo_documento_id' => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
                'nombre'            => 'Administrador',
                'apellido1'         => 'Uno',
                'genero_id'         => 3,
                'fecha_nacimiento'  => $hoy,
                'nif_pais_id'       => config('doslago.pais.inicial', Pais::ESPAÑA),
                'documento_pais_id' => config('doslago.pais.inicial', Pais::ESPAÑA),
                // El superadmin no puede salir como propietario/proveedor/etc: invisible.
                'invisible'         => true,
            ]);
        $this->command->info('Creando  usuario superadmin.');
        $user = $persona->usuario()->updateOrCreate(['login' => config('doslago.superadmin.login')],
            [
                'email'             => config('doslago.superadmin.email'),
                'password'          => 'Aa123456', //Hash::make('Aa123456'),
                'email_verified_at' => $hoy,
                'estado_id'         => EstadoUsuario::USUARIO_INACTIVO,
                'estado'         => EstadoUsuario::USUARIO_INACTIVO,
            ]);
        $user->syncRoles([config('doslago.superadmin.nombre_rol'), 'user', 'global']);

        $this->command->info('Creando  Admin con TODOS los permisos.');

        $superadmin_rol_name = config('doslago.superadmin.nombre_rol', 'super-admin');
        $hoy                 = \Carbon\Carbon::now();
        // $rol = Role::firstOrCreate(['name' => 'super-admin']);

        $rol = Role::firstOrCreate(['name' => $superadmin_rol_name]);

        $this->command->info('Creando  Admin doslago.');
        $persona = Persona::updateOrCreate(['documento_identificativo' => '36000023D'], [
            'tipo_documento_id' => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
            'nombre'            => 'Administrador1',
            'apellido1'         => 'doslago',
            'genero_id'         => 3,
            'fecha_nacimiento'  => $hoy,
            'nif_pais_id'       => config('doslago.pais.inicial', Pais::ESPAÑA),
            'documento_pais_id' => config('doslago.pais.inicial', Pais::ESPAÑA),
            // El superadmin no puede salir como propietario/proveedor/etc: invisible.
            'invisible'         => true,
        ]);

        $this->command->info('Creando  usuario superalagoro');
        $user = $persona->usuario()->updateOrCreate(['login' => 'superalagoro'], [
            'email'             => 'admin@doslago.com',
            'password'          => 'Aa123456', //Hash::make('Aa123456'),
            'email_verified_at' => $hoy,
            'estado_id'         => EstadoUsuario::USUARIO_ACTIVO,
            'estado'         => EstadoUsuario::USUARIO_ACTIVO,
        ]);

        $user->syncRoles([$superadmin_rol_name, 'user', 'global']);

        $this->command->info('Autorizado Admin con TODOS los permisos.');
        $this->command->info($user->password . '<--->' . Hash::check('Aa123456', $user->password));

    }
}
