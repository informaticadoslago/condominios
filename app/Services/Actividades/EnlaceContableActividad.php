<?php

namespace App\Services\Actividades;

use App\Models\Actividad;
use App\Services\Contabilidad\ResolverProyectoContableService;
use Illuminate\Support\Facades\DB;

/**
 * Lo que la gestión le pide a la contabilidad cuando la comunidad de una actividad está
 * enlazada con una empresa contable: su proyecto. Si la comunidad no está enlazada no se
 * hace nada, que es el caso de las comunidades que solo se administran o que todavía no
 * han enlazado la contabilidad.
 *
 * Mismo patrón que EnlaceContableComunidad: llamada directa al servicio, no por HTTP, para
 * poder compartir la transacción con lo que haya originado la petición. El proyecto se
 * guarda como id, no como texto: no es una subcuenta, es una etiqueta que la gestión
 * necesita poder mandar de vuelta en cada apunte (ver [[project-proyecto-contable]]).
 */
final class EnlaceContableActividad
{
    public function __construct(private readonly ResolverProyectoContableService $proyectos)
    {
    }

    /**
     * Da de alta la actividad como proyecto contable y le guarda el id. Devuelve null si
     * la comunidad no lleva contabilidad. Volver a llamar no crea otro: la contabilidad
     * reconoce al mismo sujeto y devuelve el que ya tenía.
     */
    public function asignarProyecto(Actividad $actividad): ?int
    {
        if ($actividad->proyecto_contable_id) {
            return $actividad->proyecto_contable_id;
        }

        $empresaId = $actividad->comunidad?->empresa_contable_id;

        if (! $empresaId) {
            return null;
        }

        $proyecto = DB::transaction(fn () => $this->proyectos->ejecutar(
            empresaContableId: $empresaId,
            nombre: $actividad->nombre,
            sujetoTipo: 'actividad',
            sujetoId: (string) $actividad->id,
        ));

        $actividad->update(['proyecto_contable_id' => $proyecto->id]);

        return $proyecto->id;
    }

    /**
     * Da de alta de golpe las actividades que se le digan y devuelve cuántas han quedado
     * con proyecto. Las que ya lo tenían no cuentan: no se les toca nada.
     *
     * @param  array<int|string>  $actividadIds
     * @return array{enlazadas: int, omitidas: int}
     */
    public function asignarProyectos(array $actividadIds): array
    {
        $actividades = Actividad::with('comunidad')
            ->whereIn('id', $actividadIds)
            ->whereNull('proyecto_contable_id')
            ->get();

        $enlazadas = 0;

        foreach ($actividades as $actividad) {
            if ($this->asignarProyecto($actividad)) {
                $enlazadas++;
            }
        }

        return [
            'enlazadas' => $enlazadas,
            'omitidas'  => $actividades->count() - $enlazadas,
        ];
    }
}
