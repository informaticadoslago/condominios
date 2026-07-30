<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UpdateSuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $hoy = \Carbon\Carbon::now();
        $rol = Role::firstOrCreate(['name' => config('defines.superadmin.nombre_rol')]);

        $persona = Persona::firstOrCreate(['nombre' => 'Administrador1', 'apellido1' => 'administrador1','nif'=>'36000000D']);
        $user = $persona->usuario()->updateOrCreate(['email' => config('defines.superadmin.email')],['login' => config('defines.superadmin.login'),
         'password' => 'Aa123456','email_verified_at'=>$hoy]);
        $user->syncRoles([config('defines.superadmin.nombre_rol')]);

        $persona = Persona::firstOrCreate(['nombre' => 'Administrador', 'apellido1' => 'administrador','nif'=>'36000023D']);
        $user = $persona->usuario()->updateOrCreate(['login' => 'superadmin'], ['email' => 'superadmin@doslago.com', 'password' => 'superadmin','email_verified_at'=>$hoy]);
        $user->syncRoles([config('defines.superadmin.nombre_rol')]);

        $this->command->info('Autorizado Admin con TODOS los permisos.');

    }
}
