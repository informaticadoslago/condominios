<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\SubcuentasAgotadasException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\AltaCuentaIngresoRequest;
use App\Services\Contabilidad\ResolverCuentaIngresoService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Capa fina sobre ResolverCuentaIngresoService: traduce JSON a la entrada del servicio y
 * el resultado a un código de estado. Aquí no hay ninguna regla contable.
 */
class CuentaIngresoController extends Controller
{
    public function store(AltaCuentaIngresoRequest $request, ResolverCuentaIngresoService $servicio): JsonResponse
    {
        $datos = $request->validated();

        try {
            $cuenta = $servicio->ejecutar(
                empresaContableId: $datos['empresa_contable_id'],
                clase: $datos['clase'],
                nombre: $datos['nombre'],
                sujetoTipo: $datos['sujeto']['tipo'],
                sujetoId: $datos['sujeto']['id'],
            );
        } catch (SubcuentasAgotadasException $e) {
            return $this->fallo($e, 409);
        } catch (CuentaContableDesconocidaException $e) {
            return $this->fallo($e, 422);
        }

        // 201 si se ha creado ahora, 200 si ese presupuesto ya tenía cuenta: repetir la
        // llamada no crea una segunda, devuelve la que ya existía.
        return response()->json([
            'cuenta' => $cuenta->codigo,
            'nombre' => $cuenta->nombre,
        ], $cuenta->wasRecentlyCreated ? 201 : 200);
    }

    private function fallo(RuntimeException $e, int $estado): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], $estado);
    }
}
