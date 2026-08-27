<?php

use App\Http\Controllers\Api\Contabilidad\AsientoController;
use App\Http\Controllers\Api\Contabilidad\CuentaIngresoController;
use App\Http\Controllers\Api\Contabilidad\EjercicioContableController;
use App\Http\Controllers\Api\Contabilidad\EmpresaContableController;
use App\Http\Controllers\Api\Contabilidad\ProyectoContableController;
use App\Http\Controllers\Api\Contabilidad\TerceroContableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 * API de contabilidad. Es la puerta de los sistemas que no viven dentro de esta
 * aplicación; desde dentro se llama directamente a RegistrarAsientoService, que permite
 * compartir transacción con lo que haya originado el asiento.
 */
Route::middleware('auth:sanctum')->prefix('contabilidad')->name('api.contabilidad.')->group(function () {
    Route::post('asientos', [AsientoController::class, 'store'])->name('asientos.store');
    // Nombre + CIF y devuelve el id de la empresa contable: la crea si ese CIF todavía
    // no tiene una, y si ya la tiene devuelve la misma.
    Route::post('empresas', [EmpresaContableController::class, 'store'])->name('empresas.store');
    // Segundo paso, aparte del alta de la empresa: dar de alta la empresa no abre
    // ningún ejercicio, las fechas las decide quien la crea.
    Route::post('ejercicios', [EjercicioContableController::class, 'store'])->name('ejercicios.store');
    // Alta de quien paga (un propietario se da de alta como cliente) y devuelve su
    // subcuenta, 43000001. Repetir la llamada con el mismo sujeto devuelve la misma.
    Route::post('terceros', [TerceroContableController::class, 'store'])->name('terceros.store');
    // Alta del concepto por el que se cobra —el presupuesto del año, cada derrama— y
    // devuelve su subcuenta de ingresos, 75000001 o 75010001.
    Route::post('cuentas-ingreso', [CuentaIngresoController::class, 'store'])->name('cuentas-ingreso.store');
    // Alta de la dimensión analítica de una actividad (una torre, un negocio) y
    // devuelve su id, el que se manda luego en `proyecto` en cada línea de asiento.
    Route::post('proyectos', [ProyectoContableController::class, 'store'])->name('proyectos.store');
});
