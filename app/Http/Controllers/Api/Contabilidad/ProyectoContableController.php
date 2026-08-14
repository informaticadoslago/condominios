<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\AltaProyectoContableRequest;
use App\Services\Contabilidad\ResolverProyectoContableService;
use Illuminate\Http\JsonResponse;

/**
 * Capa fina sobre ResolverProyectoContableService: traduce JSON a la entrada del servicio
 * y el resultado a un código de estado. Aquí no hay ninguna regla contable.
 */
class ProyectoContableController extends Controller
{
    public function store(AltaProyectoContableRequest $request, ResolverProyectoContableService $servicio): JsonResponse
    {
        $datos = $request->validated();

        $proyecto = $servicio->ejecutar(
            empresaContableId: $datos['empresa_contable_id'],
            nombre: $datos['nombre'],
            sujetoTipo: $datos['sujeto']['tipo'],
            sujetoId: $datos['sujeto']['id'],
        );

        // 201 si se ha creado ahora, 200 si ese sujeto ya tenía proyecto: repetir la
        // llamada no crea uno segundo, devuelve el que ya existía.
        return response()->json([
            'id'     => $proyecto->id,
            'nombre' => $proyecto->nombre,
        ], $proyecto->wasRecentlyCreated ? 201 : 200);
    }
}
