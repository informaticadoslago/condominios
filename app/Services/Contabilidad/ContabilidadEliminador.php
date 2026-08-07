<?php

namespace App\Services\Contabilidad;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\TerceroContable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * El equivalente contable de ComunidadEliminador: borra una empresa contable y todos sus
 * libros (cuentas, terceros, ejercicios, asientos y apuntes). Es un borrado real, no hay
 * soft deletes en el proyecto.
 *
 * Ninguna FK del árbol tiene ON DELETE CASCADE (todas son RESTRICT), así que el orden lo
 * marca este servicio: de las hojas hacia la empresa. El único punto delicado son las
 * cuentas, que se apuntan a sí mismas con `cuenta_padre_id`: se borran por niveles, de
 * las hojas hacia la raíz.
 *
 * Como el módulo contable no conoce a la gestión, aquí no se toca nada de comunidades:
 * si alguna estaba enlazada a esta empresa, quien llame se ocupa de su
 * `empresa_contable_id` (lo hace el comando condominios:contabilidad-borrar).
 */
class ContabilidadEliminador
{
    public function eliminar(EmpresaContable $empresaContable): void
    {
        DB::transaction(function () use ($empresaContable) {
            $asientoIds = AsientoContable::where('empresa_contable_id', $empresaContable->id)->pluck('id');

            // 1-2. Apuntes y asientos.
            ApunteContable::whereIn('asiento_contable_id', $asientoIds)->delete();
            AsientoContable::where('empresa_contable_id', $empresaContable->id)->delete();

            // 3. Terceros (cada uno apunta a su cuenta, así que van antes que las cuentas).
            TerceroContable::where('empresa_contable_id', $empresaContable->id)->delete();

            // 4. Cuentas, de hojas a raíces.
            $this->borrarCuentas($empresaContable);

            // 5. Ejercicios.
            EjercicioContable::where('empresa_contable_id', $empresaContable->id)->delete();

            // 6. Rol de acceso a la empresa (model_has_roles cae solo, tiene ON DELETE
            // CASCADE hacia roles) y tokens de API que solo valían para ella.
            $this->borrarTokens($empresaContable);
            Role::where('name', $empresaContable->nombreRol())->delete();

            // 7. La empresa en sí.
            $empresaContable->delete();
        });
    }

    /**
     * `cuenta_padre_id` es RESTRICT contra la propia tabla, así que no vale un DELETE de
     * golpe: se borran por niveles, empezando por las que no son madre de ninguna otra,
     * hasta que no queda ninguna.
     */
    private function borrarCuentas(EmpresaContable $empresaContable): void
    {
        $cuentas = CuentaContable::where('empresa_contable_id', $empresaContable->id);

        while (($quedan = (clone $cuentas)->count()) > 0) {
            $borradas = CuentaContable::where('empresa_contable_id', $empresaContable->id)
                ->whereNotIn('id', function ($q) use ($empresaContable) {
                    $q->select('cuenta_padre_id')->from('cuenta_contables')
                        ->where('empresa_contable_id', $empresaContable->id)
                        ->whereNotNull('cuenta_padre_id');
                })
                ->delete();

            // Si en una vuelta no cae ninguna, hay un ciclo de cuenta_padre_id (no debería
            // pasar nunca): mejor reventar que quedarse dando vueltas.
            if ($borradas === 0) {
                throw new RuntimeException(
                    "Quedan {$quedan} cuentas de la empresa contable #{$empresaContable->id} que no se pueden borrar: "
                    . 'hay un ciclo en cuenta_padre_id.'
                );
            }
        }
    }

    /**
     * Los tokens de API llevan escrita dentro la empresa para la que valen
     * ('empresa-contable:{id}'). Si la empresa se va, esos tokens no abren nada: se
     * revocan aquí en vez de dejarlos vivos apuntando a un id que ya no existe.
     */
    private function borrarTokens(EmpresaContable $empresaContable): void
    {
        $habilidad = $empresaContable->habilidadToken();

        PersonalAccessToken::where('abilities', 'like', '%' . $habilidad . '%')
            ->get()
            ->filter(fn (PersonalAccessToken $token) => in_array($habilidad, $token->abilities ?? [], true))
            ->each(fn (PersonalAccessToken $token) => $token->delete());
    }
}
