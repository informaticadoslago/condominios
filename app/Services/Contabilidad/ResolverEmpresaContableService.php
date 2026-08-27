<?php

namespace App\Services\Contabilidad;

use App\Exceptions\EmpresaContableInvalidaException;
use App\Models\CuentaContable;
use App\Models\EmpresaContable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Da el id de la empresa contable de un CIF, creándola si todavía no existe.
 *
 * El CIF es el eje de la contabilidad, así que es la clave: dos llamadas con el mismo
 * CIF devuelven siempre la misma empresa, no una segunda. La razón social solo se usa
 * al crearla; si la empresa ya existe no se toca, porque quien lleva sus libros es quien
 * decide cómo se llama, no quien la busca.
 *
 * Igual que el registro de asientos, esto es la frontera del módulo: aquí no entra
 * ningún modelo de fuera, y da lo mismo que llame la gestión de comunidades o un sistema
 * ajeno por HTTP.
 */
final class ResolverEmpresaContableService
{
    /**
     * $plantilla: qué plantilla añadir encima de la común al copiar el plan de cuentas
     * (ver CuentaContable::copiarPlanGlobalA), solo si esta llamada CREA la empresa -si ya
     * existe, su plan no se toca-. null = solo la común, es un value object (string), no
     * un modelo de gestión: no rompe la frontera del módulo.
     */
    public function ejecutar(string $cif, string $razonSocial, ?string $plantilla = null): EmpresaContable
    {
        $cif         = $this->normalizar($cif);
        $razonSocial = trim($razonSocial);

        if ($cif === '') {
            throw new EmpresaContableInvalidaException('Hace falta el CIF para enlazar con la contabilidad.');
        }

        if ($razonSocial === '') {
            throw new EmpresaContableInvalidaException('Hace falta el nombre para dar de alta la empresa contable.');
        }

        if ($existente = EmpresaContable::where('cif', $cif)->first()) {
            return $existente;
        }

        try {
            // En transacción porque al crearse arrastra el plan de cuentas y su rol de
            // acceso: o está todo o no está nada.
            return DB::transaction(function () use ($cif, $razonSocial, $plantilla): EmpresaContable {
                $empresa = EmpresaContable::create([
                    'cif'          => $cif,
                    'razon_social' => $razonSocial,
                ]);

                CuentaContable::copiarPlanGlobalA($empresa, $plantilla);

                return $empresa;
            });
        } catch (QueryException $e) {
            // Dos peticiones a la vez con el mismo CIF: la que pierde choca contra el
            // único. La empresa existe, que es lo que quería quien llamó.
            if ($this->esClaveDuplicada($e) && $existente = EmpresaContable::where('cif', $cif)->first()) {
                return $existente;
            }

            throw $e;
        }
    }

    /** El mismo CIF escrito con espacios o en minúsculas es el mismo CIF. */
    private function normalizar(string $cif): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($cif)) ?? '');
    }

    private function esClaveDuplicada(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
