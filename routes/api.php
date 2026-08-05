<?php

use App\Http\Controllers\Api\Contabilidad\AsientoController;
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
});
