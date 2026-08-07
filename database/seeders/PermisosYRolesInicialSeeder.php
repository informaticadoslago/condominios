<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosYRolesInicialSeeder extends Seeder
{

    private function crearpermisos($permisos): void
    {
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso,
                'guard_name'                      => 'web']);
        }
    }

    private function crearRol($rol): void
    {
        Role::firstOrCreate(['name' => $rol]);
    }

    private function asignarPermisos($rol, $permisos, $sync = false)
    {
        $this->crearpermisos($permisos);
        $this->crearRol($rol);
        $role = Role::where('name', $rol)->first();
        if ($role) {
            if ($sync) {
                $role->syncPermissions($permisos);
            } else {
                $role->givePermissionTo($permisos);
            }
        }
        return $role;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Permission::query()->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos_administrativo = [
            'download-create',
            'download-delete',
            'download-edit',
            'download-list',
            'download-show',
            'inicio-cumpleaños-show',
            'inicio-favoritos-show',
            'inicio-situacion-show',
            'menu-informes',
            'usuario-perfil',
        ];

         $this->asignarPermisos('administrativo', $permisos_administrativo, true);
        // $this->crearpermisos($permisos_administrativo);

        // $roladministrativo = Role::where('name', 'administrativo')->first();
        // if ($roladministrativo) {
        //     $roladministrativo->syncPermissions($permisos_administrativo);
        // }

        $permisos = [
            'configuracion-delete',
            'configuracion-edit',
            'configuracion-list',
            // Pantalla de tokens de API: caducidad por defecto y revocar los de cualquiera.
            'configuracion-token',
            'global-configuracion',
            'log-viewer-admin',
            'operador-list',
            'permiso-create',
            'permiso-delete',
            'permiso-edit',
            'permiso-list',
            'persona-cambiarde',
            'persona-create',
            'persona-delete',
            'persona-edit',
            'persona-list',
            'personal-data-list',
            'personas-anonimizar',
            'role-create',
            'role-delete',
            'role-edit',
            'role-list',
            'user-2fa',
            'user-create',
            'user-delete',
            'user-edit',
            'user-email-verify',
            'user-list',
            'user-password-reset',
            'user-sendwelcomeemails',
        ];

        $this->crearpermisos($permisos);

        // admin lleva TODO: lo de administrativo más lo suyo propio.
        $permisos = array_merge($permisos_administrativo, $permisos);

        $roladministrador = Role::where('name', 'admin')->first();
        if ($roladministrador) {
            $roladministrador->syncPermissions($permisos);
        }

        $permisos_noadmin = [
            'anonimiza-list',
            'api-access',
            'backup-module',
            'backup-delete',
            'backup-download',
            'backup-list',
            'menu-administracion-sistema',
            'menu-maestros',
            'puede-impersonate',
        ];

        $this->crearpermisos($permisos_noadmin);

        // Rol puerta de entrada: quien lo tenga accede a TODAS las comunidades, sin
        // permisos propios (esos los dan los demás roles). No se poda como los permisos.
        $this->crearRol('global');

        $permisos_usuario = [
            'usuario-perfil',
        ];
        $this->asignarPermisos('user', $permisos_usuario, true);

        // El seeder es la única fuente de verdad: lo que no esté en estas listas
        // se borra de la tabla (y con él sus asignaciones a roles y usuarios).
        $this->podarPermisos(array_merge(
            $permisos,
            $permisos_noadmin,
            $permisos_usuario,
        ));
    }

    private function podarPermisos(array $permisos): void
    {
        Permission::whereNotIn('name', array_unique($permisos))->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
