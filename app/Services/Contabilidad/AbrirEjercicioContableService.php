<?php

namespace App\Services\Contabilidad;

use App\Exceptions\EjercicioContableInvalidoException;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use Illuminate\Database\QueryException;

/**
 * Abre un ejercicio en una empresa contable.
 *
 * Es el segundo paso, separado a propósito del alta de la empresa: dar de alta la
 * empresa no abre ningún ejercicio. Quien la crea decide después qué ejercicios quiere y
 * con qué fechas — un año natural, un ejercicio partido, o varios de golpe al migrar
 * datos antiguos.
 *
 * El nombre es con lo que se pedirá luego cada asiento: RegistrarAsientoService busca el
 * ejercicio por nombre, no por id.
 *
 * Es idempotente: si esa empresa ya tiene un ejercicio con ese nombre se devuelve el que
 * hay y no se toca, esté abierto o cerrado. Repetir la llamada no reabre un ejercicio
 * cerrado ni le mueve las fechas.
 */
final class AbrirEjercicioContableService
{
    public function ejecutar(int $empresaContableId, string $nombre, string $fechaInicio, string $fechaFin): EjercicioContable
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new EjercicioContableInvalidoException('El ejercicio necesita un nombre.');
        }

        if ($fechaFin < $fechaInicio) {
            throw new EjercicioContableInvalidoException('La fecha de fin del ejercicio es anterior a la de inicio.');
        }

        if (! EmpresaContable::whereKey($empresaContableId)->exists()) {
            throw new EjercicioContableInvalidoException('No existe esa empresa contable.');
        }

        if ($existente = $this->ejercicio($empresaContableId, $nombre)) {
            return $existente;
        }

        try {
            return EjercicioContable::create([
                'empresa_contable_id' => $empresaContableId,
                'nombre'              => $nombre,
                'fecha_inicio'        => $fechaInicio,
                'fecha_fin'           => $fechaFin,
                'cerrado'             => false,
            ]);
        } catch (QueryException $e) {
            // Dos altas a la vez: la que pierde choca contra el único
            // (empresa_contable_id, nombre). El ejercicio existe, que es lo que se quería.
            if ($this->esClaveDuplicada($e) && $existente = $this->ejercicio($empresaContableId, $nombre)) {
                return $existente;
            }

            throw $e;
        }
    }

    private function ejercicio(int $empresaContableId, string $nombre): ?EjercicioContable
    {
        return EjercicioContable::where('empresa_contable_id', $empresaContableId)
            ->where('nombre', $nombre)
            ->first();
    }

    private function esClaveDuplicada(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
