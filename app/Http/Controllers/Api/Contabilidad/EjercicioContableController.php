<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Exceptions\EjercicioContableInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\AbrirEjercicioContableRequest;
use App\Services\Contabilidad\AbrirEjercicioContableService;
use Illuminate\Http\JsonResponse;

/**
 * Capa fina sobre AbrirEjercicioContableService: traduce JSON a la entrada del servicio y
 * el resultado a un código de estado. Aquí no hay ninguna regla.
 */
class EjercicioContableController extends Controller
{
    public function store(AbrirEjercicioContableRequest $request, AbrirEjercicioContableService $servicio): JsonResponse
    {
        $datos = $request->validated();

        try {
            $ejercicio = $servicio->ejecutar(
                (int) $datos['empresa_contable_id'],
                $datos['nombre'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
            );
        } catch (EjercicioContableInvalidoException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // 201 si se ha abierto ahora, 200 si esa empresa ya tenía un ejercicio con ese
        // nombre: repetir la llamada no crea un segundo, devuelve el que ya existía.
        return response()->json([
            'id'                  => $ejercicio->id,
            'empresa_contable_id' => $ejercicio->empresa_contable_id,
            'nombre'              => $ejercicio->nombre,
            'fecha_inicio'        => $ejercicio->fecha_inicio->toDateString(),
            'fecha_fin'           => $ejercicio->fecha_fin->toDateString(),
            'cerrado'             => $ejercicio->cerrado,
        ], $ejercicio->wasRecentlyCreated ? 201 : 200);
    }
}
