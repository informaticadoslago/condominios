<?php

namespace App\Services\Horarios;

use App\Models\Asignatura;
use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\Jornada;
use App\Services\Exportacion\ExportadorZip;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El equivalente, para Horarios, de ContabilidadExportador: se lleva un horario entero
 * (jornadas, asignaturas y las sesiones que las asignan a cada día/hueco) a un .zip en
 * el disco 'coms'. Horario no tiene FK a Comunidad ni a la contabilidad, así que no hay
 * ningún enlace con otro módulo que resolver.
 *
 * No hay ficheros.json: este módulo no guarda adjuntos.
 */
class HorarioExportador extends ExportadorZip
{
    /** Devuelve el nombre del .zip generado dentro del disco 'coms'. */
    public function exportar(Horario $horario): string
    {
        $datos = $this->recopilar($horario);

        return $this->empaquetar($this->nombreZip($horario), [
            'datos.xml' => $this->generarXml('horario_export', [
                'version'      => '1',
                'horario_id'   => (string) $horario->id,
                'exportado_en' => now()->toIso8601String(),
            ], $datos),
            'indice.md' => $this->generarIndice($horario, $datos),
        ]);
    }

    private function nombreZip(Horario $horario): string
    {
        $slug = Str::slug($horario->nombre ?: 'horario');

        return "horario-{$horario->id}-{$slug}-" . now()->format('Ymd_His') . '.zip';
    }

    /** @return array<string, Collection> */
    private function recopilar(Horario $horario): array
    {
        return [
            'horarios'         => collect([$horario]),
            'asignaturas'      => Asignatura::where('horario_id', $horario->id)->orderBy('id')->get(),
            'jornadas'         => Jornada::where('horario_id', $horario->id)->orderBy('id')->get(),
            'horario_sesiones' => HorarioSesion::where('horario_id', $horario->id)->orderBy('id')->get(),
        ];
    }

    /** @param array<string, Collection> $datos */
    private function generarIndice(Horario $horario, array $datos): string
    {
        $lineasTablas = collect($datos)
            ->map(fn (Collection $filas, string $tabla) => "- **{$tabla}**: {$filas->count()} fila(s)")
            ->implode("\n");

        return <<<MD
        # Exportación de horario: {$horario->nombre} (id {$horario->id})

        Generado el {$horario->freshTimestamp()->toDateTimeString()} por `condominios:horario-exportar`.

        ## Contenido del .zip

        - `datos.xml`: todas las filas de base de datos de este horario, una tabla por elemento
          raíz y una fila por `<fila>`, con sus columnas tal cual están en la base de datos (sin
          campos calculados). Los valores nulos se representan como `<columna nulo="true"/>`.
        - `indice.md`: este fichero.

        ## Tablas incluidas en datos.xml

        {$lineasTablas}

        ## Orden recomendado para reconstruir en otro sistema

        1. `horarios` (la fila de `horarios`)
        2. `asignaturas`
        3. `jornadas`
        4. `horario_sesiones` (referencia `asignaturas`; a `jornadas` solo indirectamente, por
           día de la semana y número de sesión, no por id)

        ## NO incluido en la exportación

        - **El rol de acceso** `{$horario->nombreRol()}`: es una credencial, no un dato del
          horario. En el destino se crea de nuevo (lo hace `Horario::booted()` al dar de alta la
          fila desde la aplicación, o el propio importador si se inserta en crudo).
        MD;
    }
}
