<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Exceptions\EmpresaContableInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\ResolverEmpresaContableRequest;
use App\Services\Contabilidad\ResolverEmpresaContableService;
use Illuminate\Http\JsonResponse;

/**
 * Capa fina sobre ResolverEmpresaContableService: traduce JSON a la entrada del servicio
 * y el resultado a un código de estado. Aquí no hay ninguna regla.
 */
class EmpresaContableController extends Controller
{
    public function store(ResolverEmpresaContableRequest $request, ResolverEmpresaContableService $servicio): JsonResponse
    {
        $datos = $request->validated();

        try {
            $empresa = $servicio->ejecutar($datos['cif'], $datos['razon_social']);
        } catch (EmpresaContableInvalidaException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // 201 si se ha creado ahora, 200 si ese CIF ya tenía empresa: repetir la llamada
        // no crea una segunda, devuelve la que ya existía.
        return response()->json([
            'id'           => $empresa->id,
            'cif'          => $empresa->cif,
            'razon_social' => $empresa->razon_social,
        ], $empresa->wasRecentlyCreated ? 201 : 200);
    }
}
