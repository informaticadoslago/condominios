<?php

use App\Http\Controllers\BackupDescargaController;
use App\Http\Controllers\ConfirmarCorreoUsuarioController;
use App\Livewire\AdministracionSistema\Backups\Lista as BackupsLista;
use App\Livewire\AdministracionSistema\Empresa\Editar as EmpresaEditar;
use App\Livewire\AdministracionSistema\Permisos\Lista as PermisosLista;
use App\Livewire\AdministracionSistema\Personas\Editar as PersonasEditar;
use App\Livewire\AdministracionSistema\Personas\Lista as PersonasLista;
use App\Livewire\AdministracionSistema\Roles\Lista as RolesLista;
use App\Livewire\AdministracionSistema\Usuarios\Lista as UsuariosLista;
use App\Livewire\Maestros\EntidadesBancarias\Lista as EntidadesBancariasLista;
use App\Livewire\Maestros\FormasDePago\Lista as FormasDePagoLista;
use App\Livewire\Maestros\Paises\Lista as PaisesLista;
use Illuminate\Support\Facades\Route;
use Lab404\Impersonate\Controllers\ImpersonateController;

Route::get('/phpinfo', function () {
    abort_unless(config('app.debug'), 403);
    phpinfo();
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/confirmar-correo/{usuario}', ConfirmarCorreoUsuarioController::class)
    ->middleware('signed')
    ->name('usuarios.confirmar-correo');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Rutas de Administración del Sistema
    Route::prefix('administracion-sistema')->name('sysadmin.')->group(function () {
        Route::get('/personas', PersonasLista::class)
            ->name('personas.index'); // ->can('sistema-personas-listar')
        // Route::get('/personas/{persona}/modificar', PersonasEditar::class)
        //     ->name('personas.edit'); // ->can('sistema-personas-editar')

        // Añade aquí las demás rutas cuando las tengas:
        Route::get('/empresa', EmpresaEditar::class)->name('empresa.edit');
        Route::get('/usuarios', UsuariosLista::class)
            ->can('user-list')
            ->name('usuarios.index');
        Route::get('/roles', RolesLista::class)
            ->can('role-list')
            ->name('roles.index');
        Route::get('/permisos', PermisosLista::class)
            ->can('permiso-list')
            ->name('permisos.index');
        Route::get('/backups', BackupsLista::class)->name('backups.index');
        Route::get('/backups/{fichero}/descargar', BackupDescargaController::class)
            ->where('fichero', '.*')
            ->name('backups.download');
    });

    // Maestros de la música
    Route::get('/entidades-bancarias', EntidadesBancariasLista::class)->name('entidades-bancarias.index');
    Route::get('/formas-de-pago', FormasDePagoLista::class)->name('formas-de-pago.index');
    Route::get('/paises', PaisesLista::class)->name('paises.index');

});

// Impersonación. Fuera del grupo anterior a propósito: con `verified` puesto, suplantar
// a un usuario sin email verificado te dejaría atrapado sin poder ni salir.
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    // La contraseña se exige solo al entrar. Al salir no: quien está en sesión es el
    // suplantado, que no conoce la contraseña de quien lo suplantó.
    Route::get('/impersonate/take/{id}/{guardName?}', [ImpersonateController::class, 'take'])
        ->middleware('password.confirm')
        ->name('impersonate');
    Route::get('/impersonate/leave', [ImpersonateController::class, 'leave'])
        ->name('impersonate.leave');
});
