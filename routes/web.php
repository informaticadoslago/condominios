<?php

use App\Http\Controllers\BackupDescargaController;
use App\Http\Controllers\ComunidadContextoController;
use App\Http\Controllers\ConfirmarCorreoUsuarioController;
use App\Http\Controllers\DocumentoDescargaController;
use App\Livewire\AdministracionSistema\Backups\Lista as BackupsLista;
use App\Livewire\AdministracionSistema\Empresa\Editar as EmpresaEditar;
use App\Livewire\AdministracionSistema\Permisos\Lista as PermisosLista;
use App\Livewire\AdministracionSistema\Personas\Editar as PersonasEditar;
use App\Livewire\AdministracionSistema\Personas\Lista as PersonasLista;
use App\Livewire\AdministracionSistema\Roles\Lista as RolesLista;
use App\Livewire\AdministracionSistema\Usuarios\Lista as UsuariosLista;
use App\Livewire\AsientosContables\Formulario as AsientosContablesFormulario;
use App\Livewire\AsientosContables\Lista as AsientosContablesLista;
use App\Livewire\Catalogos\Lista as CatalogosLista;
use App\Livewire\Comunidades\Lista as ComunidadesLista;
use App\Livewire\GruposDeReparto\Lista as GruposDeRepartoLista;
use App\Livewire\CuentasContables\Lista as CuentasContablesLista;
use App\Livewire\EjerciciosContables\Lista as EjerciciosContablesLista;
use App\Livewire\Inmuebles\Formulario as InmueblesFormulario;
use App\Livewire\Inmuebles\Lista as InmueblesLista;
use App\Livewire\Maestros\EntidadesBancarias\Lista as EntidadesBancariasLista;
use App\Livewire\Propietarios\Formulario as PropietariosFormulario;
use App\Livewire\Propietarios\Lista as PropietariosLista;
use App\Livewire\Proveedores\Lista as ProveedoresLista;
use App\Livewire\Maestros\FormasDePago\Lista as FormasDePagoLista;
use App\Livewire\Maestros\Paises\Lista as PaisesLista;
use App\Livewire\Maestros\Periodicidades\Lista as PeriodicidadesLista;
use App\Livewire\Presupuestos\Conceptos as PresupuestosConceptos;
use App\Livewire\Presupuestos\Lista as PresupuestosLista;
use App\Livewire\Presupuestos\Reparto as PresupuestosReparto;
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

    // Cambio de contexto: entrar/salir de una comunidad
    Route::get('/comunidad/{comunidad}/entrar', [ComunidadContextoController::class, 'entrar'])->name('comunidad.entrar');
    Route::get('/comunidad/salir', [ComunidadContextoController::class, 'salir'])->name('comunidad.salir');

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

    Route::get('/comunidades', ComunidadesLista::class)->name('comunidades.index');

    // Rutas de una comunidad: exigen comunidad activa en sesión y acceso a ella.
    Route::middleware('comunidad.activa')->group(function () {
        Route::get('/propietarios', PropietariosLista::class)->name('propietarios.index');
        Route::get('/propietarios/nuevo', PropietariosFormulario::class)->name('propietarios.crear');
        Route::get('/propietarios/{propietario}/editar', PropietariosFormulario::class)->name('propietarios.editar');
        Route::get('/proveedores', ProveedoresLista::class)->name('proveedores.index');
        Route::get('/documentos/{documento}/descargar', DocumentoDescargaController::class)->name('documentos.download');
        Route::get('/inmuebles', InmueblesLista::class)->name('inmuebles.index');
        Route::get('/inmuebles/nuevo', InmueblesFormulario::class)->name('inmuebles.crear');
        Route::get('/inmuebles/{inmueble}/editar', InmueblesFormulario::class)->name('inmuebles.editar');
        Route::get('/grupos-de-reparto', GruposDeRepartoLista::class)->name('grupos-de-reparto.index');
        Route::get('/presupuestos', PresupuestosLista::class)->name('presupuestos.index');
        Route::get('/presupuestos/{presupuesto}/conceptos', PresupuestosConceptos::class)->name('presupuestos.conceptos');
        Route::get('/presupuestos/{presupuesto}/reparto', PresupuestosReparto::class)->name('presupuestos.reparto');
        Route::get('/ejercicios-contables', EjerciciosContablesLista::class)->name('ejercicios-contables.index');
        Route::get('/asientos-contables', AsientosContablesLista::class)->name('asientos-contables.index');
        Route::get('/asientos-contables/{ejercicioContable}/nuevo', AsientosContablesFormulario::class)->name('asientos-contables.crear');
    });

    // Gestión contable (global)
    Route::get('/cuentas-contables', CuentasContablesLista::class)->name('cuentas-contables.index');

    // Maestros
    Route::get('/entidades-bancarias', EntidadesBancariasLista::class)->name('entidades-bancarias.index');
    Route::get('/formas-de-pago', FormasDePagoLista::class)->name('formas-de-pago.index');
    Route::get('/paises', PaisesLista::class)->name('paises.index');
    Route::get('/periodicidades', PeriodicidadesLista::class)->name('periodicidades.index');

    // Catálogos simples (mismo par Lista/Formulario, ver config/catalogos.php)
    Route::get('/catalogos/tipo-ocupaciones', CatalogosLista::class)->defaults('clave', 'tipo-ocupaciones')->name('catalogos.tipo-ocupaciones');
    Route::get('/catalogos/tipo-inmuebles', CatalogosLista::class)->defaults('clave', 'tipo-inmuebles')->name('catalogos.tipo-inmuebles');
    Route::get('/catalogos/tipo-cuenta-contables', CatalogosLista::class)->defaults('clave', 'tipo-cuenta-contables')->name('catalogos.tipo-cuenta-contables');
    Route::get('/catalogos/tipo-estado-presupuestos', CatalogosLista::class)->defaults('clave', 'tipo-estado-presupuestos')->name('catalogos.tipo-estado-presupuestos');
    Route::get('/catalogos/estados', CatalogosLista::class)->defaults('clave', 'estados')->name('catalogos.estados');

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
