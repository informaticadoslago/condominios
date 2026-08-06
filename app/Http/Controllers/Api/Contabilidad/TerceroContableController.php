<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\SubcuentasAgotadasException;
use App\Exceptions\TerceroContableDesconocidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\AltaTerceroRequest;
use App\Models\TerceroContable;
use App\Services\Contabilidad\DatosTercero;
use App\Services\Contabilidad\ResolvedorCuentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Capa fina sobre ResolvedorCuentasService: traduce JSON a la entrada del servicio y el
 * resultado a un código de estado. Aquí no hay ninguna regla contable.
 */
class TerceroContableController extends Controller
{
    public function store(AltaTerceroRequest $request, ResolvedorCuentasService $servicio): JsonResponse
    {
        $datos = $request->validated();

        $yaExistia = TerceroContable::where('empresa_contable_id', $datos['empresa_contable_id'])
            ->where('sujeto_tipo', $datos['sujeto']['tipo'])
            ->where('sujeto_id', $datos['sujeto']['id'])
            ->exists();

        try {
            // En transacción porque el servicio bloquea la cuenta de grupo para repartir
            // el correlativo, y ese bloqueo solo vale mientras dure.
            $cuenta = DB::transaction(fn () => $servicio->resolver(
                $datos['empresa_contable_id'],
                new DatosTercero(
                    tipo: $datos['sujeto']['tipo'],
                    id: $datos['sujeto']['id'],
                    clase: $datos['clase'],
                    nif: $datos['nif'],
                    razonSocial: $datos['razon_social'],
                ),
                puedeCrear: true,
            ));
        } catch (SubcuentasAgotadasException $e) {
            return $this->fallo($e, 409);
        } catch (TerceroContableDesconocidoException|CuentaContableDesconocidaException $e) {
            return $this->fallo($e, 422);
        }

        // 201 si se ha dado de alta ahora, 200 si ese sujeto ya tenía cuenta: repetir la
        // llamada no crea una segunda, devuelve la que ya existía.
        return response()->json([
            'cuenta' => $cuenta->codigo,
            'nombre' => $cuenta->nombre,
        ], $yaExistia ? 200 : 201);
    }

    private function fallo(RuntimeException $e, int $estado): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], $estado);
    }
}
