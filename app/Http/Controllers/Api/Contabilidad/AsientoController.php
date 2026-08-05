<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Exceptions\SubcuentasAgotadasException;
use App\Exceptions\TerceroContableDesconocidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\RegistrarAsientoRequest;
use App\Models\AsientoContable;
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Capa fina sobre RegistrarAsientoService: traduce JSON a la entrada del servicio y el
 * resultado a un código de estado. Aquí no hay ninguna regla contable.
 */
class AsientoController extends Controller
{
    public function store(RegistrarAsientoRequest $request, RegistrarAsientoService $servicio): JsonResponse
    {
        try {
            $asiento = $servicio->ejecutar(DatosAsiento::desdeArray($request->validated()));
        } catch (EjercicioCerradoException|SubcuentasAgotadasException $e) {
            return $this->fallo($e, 409);
        } catch (AsientoInvalidoException|CuentaContableDesconocidaException
            |EjercicioContableDesconocidoException|TerceroContableDesconocidoException $e) {
            return $this->fallo($e, 422);
        }

        // 201 si se ha creado ahora, 200 si la referencia ya estaba registrada: reenviar
        // el mismo evento no duplica el asiento, devuelve el que ya existía.
        return response()->json($this->comoArray($asiento), $asiento->wasRecentlyCreated ? 201 : 200);
    }

    private function fallo(RuntimeException $e, int $estado): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], $estado);
    }

    private function comoArray(AsientoContable $asiento): array
    {
        $asiento->load(['apuntesContables.cuentaContable', 'ejercicioContable']);

        return [
            'id'         => $asiento->id,
            'numero'     => $asiento->numero,
            'ejercicio'  => $asiento->ejercicioContable?->nombre,
            'fecha'      => $asiento->fecha->toDateString(),
            'diario'     => $asiento->diario,
            'concepto'   => $asiento->concepto,
            'referencia' => $asiento->referencia_tipo === null ? null : [
                'tipo'   => $asiento->referencia_tipo,
                'id'     => $asiento->referencia_id,
                'evento' => $asiento->evento,
            ],
            'lineas' => $asiento->apuntesContables->map(fn ($apunte): array => [
                'cuenta'   => $apunte->cuentaContable?->codigo,
                'debe'     => $apunte->debe,
                'haber'    => $apunte->haber,
                'concepto' => $apunte->concepto,
            ])->all(),
        ];
    }
}
