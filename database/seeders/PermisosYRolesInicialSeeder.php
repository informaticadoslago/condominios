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
        // El seeder es la única fuente de verdad: se borra todo (permisos, roles y sus
        // asignaciones a usuarios) y se reconstruye desde cero. El super-admin no lo
        // gestiona este seeder, así que se salva de la quema.
        Permission::query()->delete();
        Role::where('name', '<>', config('doslago.superadmin.nombre_rol'))->delete();
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
        ];
        $this->asignarPermisos('administrativo', $permisos_administrativo, true);

        $permisos_administrativo_comunidad = [
            'comunidad-create',
            'comunidad-delete',
            'comunidad-edit',
            'comunidad-list',
            'sociedad-create',
            'sociedad-delete',
            'sociedad-edit',
            'sociedad-list',
            // Entradas del menú principal: gestión de comunidades y gestión contable.
            'gestion-comunidad',
        ];

         $this->asignarPermisos('administrativo_comunidad', $permisos_administrativo_comunidad, true);

        $permisos_administrativo_contabilidad = [
            'asiento-contable-list',
            'cuenta-contable-create',
            'cuenta-contable-delete',
            'cuenta-contable-edit',
            'cuenta-contable-list',
            'empresa-contable-create',
            'empresa-contable-delete',
            'empresa-contable-edit',
            'empresa-contable-list',
            // Entradas del menú principal: gestión de comunidades y gestión contable.
            'gestion-contable',
        ];
         $this->asignarPermisos('administrativo_contabilidad', $permisos_administrativo_contabilidad, true);

        $permisos_admin_contabilidad = [
            'asiento-contable-delete',
            'asiento-contable-edit',
        ];
         $this->asignarPermisos('admin_contabilidad', $permisos_admin_contabilidad, true);

         $permisos = [
            'configuracion-delete',
            'configuracion-edit',
            'configuracion-list',
            // Pantalla de tokens de API: caducidad por defecto y revocar los de cualquiera.
            'configuracion-token',
            'correo-enviado-list',
            'correo-prueba-enviar',            
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

        // admin lleva TODO: lo de administrativo más lo suyo propio.
        $this->asignarPermisos('admin', $permisos, true);

        $permisos_noadmin = [
            'anonimiza-list',
            'api-access',
            'backup-module',
            'backup-delete',
            'backup-download',
            'backup-list',
            'global-configuracion',
            'menu-administracion-sistema',
            'menu-maestros',
            'puede-impersonate',
        ];

        $this->crearpermisos($permisos_noadmin);

        // Rol puerta de entrada: quien lo tenga accede a TODAS las comunidades, sin
        // permisos propios (esos los dan los demás roles).
        $this->crearRol('global');

        // Igual que 'global', pero solo para sociedades: son ámbitos separados, no un
        // superconjunto de 'global'.
        $this->crearRol('global-sociedad');

        $permisos_usuario = [
            'usuario-perfil',
        ];
        $this->asignarPermisos('user', $permisos_usuario, true);
    }
}
