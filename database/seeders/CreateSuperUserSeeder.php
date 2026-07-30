<?php
namespace Database\Seeders;

use App\Models\EstadoUsuario;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\TipoDocumentoIdentificativo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class CreateSuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        Schema::disableForeignKeyConstraints();
        User::truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info('Creando  rol SuperAdmin con TODOS los permisos.');
        $hoy = \Carbon\Carbon::now();
        $rol = Role::firstOrCreate(['name' => config('doslago.superadmin.nombre_rol')]);

        $this->command->info('Creando  uno.');

        $persona = Persona::firstOrCreate(['documento_identificativo' => '36000000D'],
            [
                'tipo_documento_id' => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
                'nombre'            => 'Administrador',
                'apellido1'         => 'Uno',
                'genero_id'         => 3,
                'fecha_nacimiento'  => $hoy,
                'nif_pais_id'       => config('doslago.pais.inicial', Pais::ESPAÑA),
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
        $user->syncRoles([config('doslago.superadmin.nombre_rol')]);

        $this->command->info('Creando  Admin con TODOS los permisos.');

        $superadmin_rol_name = config('doslago.superadmin.nombre_rol', 'super-admin');
        $hoy                 = \Carbon\Carbon::now();
        // $rol = Role::firstOrCreate(['name' => 'super-admin']);

        $rol = Role::firstOrCreate(['name' => $superadmin_rol_name]);

        $this->command->info('Creando  Admin doslago.');
        $persona = Persona::firstOrCreate(['documento_identificativo' => '36000023D'], [
            'tipo_documento_id' => TipoDocumentoIdentificativo::DOCUMENTO_NIF,
            'nombre'            => 'Administrador1',
            'apellido1'         => 'doslago',
            'genero_id'         => 3,
            'fecha_nacimiento'  => $hoy,
            'nif_pais_id'       => config('doslago.pais.inicial', Pais::ESPAÑA)]);

        $this->command->info('Creando  usuario superalagoro');
        $user = $persona->usuario()->updateOrCreate(['login' => 'superalagoro'], [
            'email'             => 'admin@doslago.com',
            'password'          => 'Aa123456', //Hash::make('Aa123456'),
            'email_verified_at' => $hoy,
            'estado_id'         => EstadoUsuario::USUARIO_ACTIVO,
            'estado'         => EstadoUsuario::USUARIO_ACTIVO,
        ]);

        $user->assignRole($superadmin_rol_name);

        $this->command->info('Autorizado Admin con TODOS los permisos.');
        $this->command->info($user->password . '<--->' . Hash::check('Aa123456', $user->password));

    }
}
