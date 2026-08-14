<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UpdateSuperUserSeeder extends Seeder
{
    /**
     * Resetea la contraseña de todos los usuarios con rol super-admin a la
     * marcada en config('defines.superadmin.password') (SUPERADMIN_PASSWORD en .env).
     *
     * @return void
     */
    public function run()
    {
        $usuarios = User::role(config('defines.superadmin.nombre_rol'))->get();

        if ($usuarios->isEmpty()) {
            $this->command->warn('No existe ningún usuario con rol super-admin. Este seeder no crea usuarios.');

            return;
        }

        foreach ($usuarios as $usuario) {
            $usuario->update(['password' => config('defines.superadmin.password')]);
            $this->command->info("Contraseña reseteada para {$usuario->login}.");
        }
    }
}
