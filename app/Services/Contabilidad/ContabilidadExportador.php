<?php

namespace App\Services\Contabilidad;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use App\Models\EmpresaContable;
use App\Models\TerceroContable;
use App\Services\Exportacion\ExportadorZip;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El equivalente contable de ComunidadExportador: se lleva una empresa contable entera
 * (plan de cuentas, terceros, ejercicios, asientos y apuntes) a un .zip en el disco
 * 'coms'.
 *
 * No hay ficheros.json porque la contabilidad no guarda adjuntos: los justificantes
 * viven en el módulo administrativo, que es un cliente suyo, y viajan en la exportación
 * de la comunidad. Aquí solo hay datos.xml e indice.md.
 *
 * Igual que el módulo, esta exportación no sabe nada de comunidades: `sujeto_tipo` /
 * `sujeto_id` y `referencia_tipo` / `referencia_id` salen tal cual, como el texto opaco
 * que son para la contabilidad.
 */
class ContabilidadExportador extends ExportadorZip
{
    /** Devuelve el nombre del .zip generado dentro del disco 'coms'. */
    public function exportar(EmpresaContable $empresaContable): string
    {
        $datos = $this->recopilar($empresaContable);

        return $this->empaquetar($this->nombreZip($empresaContable), [
            'datos.xml' => $this->generarXml('contabilidad_export', [
                'version'             => '1',
                'empresa_contable_id' => (string) $empresaContable->id,
                'cif'                 => (string) $empresaContable->cif,
                'exportado_en'        => now()->toIso8601String(),
            ], $datos),
            'indice.md' => $this->generarIndice($empresaContable, $datos),
        ]);
    }

    private function nombreZip(EmpresaContable $empresaContable): string
    {
        $slug = Str::slug($empresaContable->razon_social ?: 'empresa');

        return "contabilidad-{$empresaContable->id}-{$slug}-" . now()->format('Ymd_His') . '.zip';
    }

    /**
     * Todas las filas de la empresa, de la raíz hacia las hojas. Las cuentas van
     * ordenadas por código para que una cuenta padre aparezca siempre antes que sus
     * hijas (el código de la hija empieza por el del padre), que es lo que hace falta
     * para poder insertarlas de arriba abajo respetando `cuenta_padre_id`.
     *
     * @return array<string, Collection>
     */
    private function recopilar(EmpresaContable $empresaContable): array
    {
        $cuentas = CuentaContable::where('empresa_contable_id', $empresaContable->id)
            ->orderBy('codigo')
            ->get();

        $ejercicios   = EjercicioContable::where('empresa_contable_id', $empresaContable->id)
            ->orderBy('fecha_inicio')
            ->get();

        $asientos   = AsientoContable::where('empresa_contable_id', $empresaContable->id)
            ->orderBy('ejercicio_contable_id')
            ->orderBy('numero')
            ->get();
        $asientoIds = $asientos->pluck('id');

        return [
            'empresa_contable'    => collect([$empresaContable]),
            'cuenta_contables'    => $cuentas,
            'tercero_contables'   => TerceroContable::where('empresa_contable_id', $empresaContable->id)
                ->orderBy('id')->get(),
            'ejercicio_contables' => $ejercicios,
            'asiento_contables'   => $asientos,
            'apunte_contables'    => ApunteContable::whereIn('asiento_contable_id', $asientoIds)
                ->orderBy('asiento_contable_id')->orderBy('id')->get(),
        ];
    }

    /** @param array<string, Collection> $datos */
    private function generarIndice(EmpresaContable $empresaContable, array $datos): string
    {
        $lineasTablas = collect($datos)
            ->map(fn (Collection $filas, string $tabla) => "- **{$tabla}**: {$filas->count()} fila(s)")
            ->implode("\n");

        $ejercicios = $datos['ejercicio_contables']
            ->map(fn (EjercicioContable $e) => "- **{$e->nombre}**: del {$e->fecha_inicio->format('d/m/Y')} al {$e->fecha_fin->format('d/m/Y')}"
                . ($e->cerrado ? ' (cerrado)' : ''))
            ->implode("\n") ?: '- (ninguno)';

        $descuadres = $this->descuadres($datos['asiento_contables'], $datos['apunte_contables']);

        $avisoDescuadres = $descuadres === []
            ? "Todos los asientos exportados cuadran (suma del debe = suma del haber)."
            : "> ⚠️ Asientos que NO cuadran en el momento de exportar (id: debe / haber):\n>\n> " . implode("\n> ", $descuadres);

        return <<<MD
        # Exportación de contabilidad: {$empresaContable->razon_social} ({$empresaContable->cif})

        Empresa contable id {$empresaContable->id}. Generado el {$empresaContable->freshTimestamp()->toDateTimeString()}
        por `condominios:contabilidad-exportar`.

        ## Contenido del .zip

        - `datos.xml`: todas las filas de base de datos de esta empresa contable, una tabla por
          elemento raíz y una fila por `<fila>`, con sus columnas tal cual están en la base de datos
          (los importes en céntimos, como se guardan). Los valores nulos se representan como
          `<columna nulo="true"/>`.
        - `indice.md`: este fichero.

        No hay `ficheros.json`: la contabilidad no guarda adjuntos. Los justificantes (facturas,
        documentos) viven en el módulo administrativo y viajan en su propia exportación
        (`condominios:comunidad-exportar`).

        ## Tablas incluidas en datos.xml

        {$lineasTablas}

        ## Ejercicios

        {$ejercicios}

        {$avisoDescuadres}

        ## Orden recomendado para reconstruir en otro sistema

        1. `empresa_contable` (la fila de `empresas_contables`)
        2. `cuenta_contables`, **en el orden en que vienen** (por código): así una cuenta padre
           siempre se inserta antes que sus hijas y `cuenta_padre_id` se puede resolver sobre la
           marcha
        3. `tercero_contables` (apuntan a su cuenta de `cuenta_contables`)
        4. `ejercicio_contables`
        5. `asiento_contables`
        6. `apunte_contables`

        ## Referencias opacas (no se traducen)

        La contabilidad es un módulo independiente: no tiene ninguna clave ajena hacia la gestión de
        comunidades y no sabe qué hay al otro lado. Estas columnas guardan de dónde vino cada cosa,
        en el sistema que alimentó los libros, y viajan tal cual:

        - `cuenta_contables.sujeto_tipo` / `sujeto_id` y `tercero_contables.sujeto_tipo` / `sujeto_id`:
          a quién representa la cuenta o el tercero.
        - `asiento_contables.referencia_tipo` / `referencia_id` y `evento`: qué hecho generó el asiento.

        Si el sistema destino no es el mismo, estos valores no apuntarán a nada; los libros siguen
        siendo correctos, solo se pierde el enlace de vuelta.

        ## NO incluido en la exportación

        - **Catálogos globales**, compartidos por todas las empresas contables y que deben existir ya
          en el destino: `tipo_cuenta_contables`, `tipo_tercero_contables`, `tipo_ingreso_contables`,
          `estados`. Se referencian por id desde `tipo_cuenta_contable_id`,
          `tipo_tercero_contable_id` y `estado_id`.
        - **El plan de cuentas maestro** (las filas de `cuenta_contables` con `empresa_contable_id`
          nulo), que es la plantilla desde la que se copia el plan de cada empresa nueva. Aquí solo
          van las cuentas propias de esta empresa.
        - **El rol de acceso** `{$empresaContable->nombreRol()}` y los **tokens de API** de la
          empresa: son credenciales, no datos contables. En el destino se crean de nuevo.
        MD;
    }

    /**
     * Asientos cuya suma de debe no coincide con la de haber, para avisar en el índice de que
     * lo exportado no cuadra (la exportación no arregla nada: copia lo que hay).
     *
     * @return array<int, string>
     */
    private function descuadres(Collection $asientos, Collection $apuntes): array
    {
        $porAsiento = $apuntes->groupBy('asiento_contable_id');

        return $asientos->map(function (AsientoContable $asiento) use ($porAsiento) {
            $suyos = $porAsiento->get($asiento->id, collect());
            $debe  = $suyos->sum('debe');
            $haber = $suyos->sum('haber');

            return $debe === $haber ? null : "#{$asiento->id} (asiento {$asiento->numero}): {$debe} / {$haber} céntimos";
        })->filter()->values()->all();
    }
}
