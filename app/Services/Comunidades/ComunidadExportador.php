<?php

namespace App\Services\Comunidades;

use App\Models\AvisoRecibo;
use App\Models\Cobro;
use App\Models\ComunidadDirectivo;
use App\Models\ConceptoPresupuesto;
use App\Models\Contacto;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\Direccion;
use App\Models\Documento;
use App\Models\FacturaProveedor;
use App\Models\FormaPagoInmueble;
use App\Models\GrupoDeReparto;
use App\Models\HistorialEstado;
use App\Models\Inmueble;
use App\Models\LineaRemesa;
use App\Models\MandatoSepa;
use App\Models\Presupuesto;
use App\Models\PersonaComunidad;
use App\Models\Propietario;
use App\Models\Proveedor;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\Titularidad;
use App\Services\Exportacion\ExportadorZip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exporta una comunidad completa (todas sus filas de BD + los documentos/facturas
 * adjuntos) a un único .zip en el disco 'coms'. Pensado para poder llevarse una
 * comunidad a otro sistema o guardarla fuera de la BD: los datos van en un XML (una
 * fila = un elemento, con sus columnas crudas), los binarios en un JSON aparte (base64,
 * referenciados por el id de 'documentos'), y un indice.md explica cómo encajan las dos
 * cosas para quien reciba el .zip.
 */
class ComunidadExportador extends ExportadorZip
{
    /** Devuelve el nombre del .zip generado dentro del disco 'coms'. */
    public function exportar(Comunidad $comunidad): string
    {
        $comunidad->loadMissing('persona');
        $datos = $this->recopilar($comunidad);

        return $this->empaquetar($this->nombreZip($comunidad), [
            'datos.xml'     => $this->generarXml('comunidad_export', [
                'version'      => '1',
                'comunidad_id' => (string) $comunidad->id,
                'exportado_en' => now()->toIso8601String(),
            ], $datos),
            'ficheros.json' => $this->generarFicherosJson($datos['documentos']),
            'indice.md'     => $this->generarIndice($comunidad, $datos),
        ]);
    }

    private function nombreZip(Comunidad $comunidad): string
    {
        $slug = Str::slug($comunidad->persona->razon_social ?? $comunidad->persona->nombre_comercial ?? 'comunidad');

        return "comunidad-{$comunidad->id}-{$slug}-" . now()->format('Ymd_His') . '.zip';
    }

    /**
     * Recopila, en memoria, todas las filas de BD que cuelgan de la comunidad (directa
     * o indirectamente). El orden de las claves es el mismo en que se reconstruirían
     * en un sistema nuevo (de la raíz hacia las hojas).
     *
     * @return array<string, Collection>
     */
    private function recopilar(Comunidad $comunidad): array
    {
        $inmuebles   = $comunidad->inmuebles()->get();
        $inmuebleIds = $inmuebles->pluck('id');
        $gruposDeReparto = $comunidad->gruposDeReparto()->get();

        $personasComunidad   = PersonaComunidad::where('comunidad_id', $comunidad->id)->get();
        $personaComunidadIds = $personasComunidad->pluck('id');

        $propietarios   = Propietario::whereIn('persona_comunidad_id', $personaComunidadIds)->get();
        $propietarioIds = $propietarios->pluck('id');

        $proveedores   = Proveedor::whereIn('persona_comunidad_id', $personaComunidadIds)->get();
        $proveedorIds  = $proveedores->pluck('id');

        $presupuestos   = Presupuesto::where('comunidad_id', $comunidad->id)->get();
        $presupuestoIds = $presupuestos->pluck('id');

        $mandatos    = MandatoSepa::where('comunidad_id', $comunidad->id)->get();
        $mandatoIds  = $mandatos->pluck('id');

        // Los recibos cuelgan del presupuesto que los generó; de ahí para abajo van las
        // remesas en que se cobraron, sus cobros y los avisos que se enviaron.
        $recibos   = Recibo::whereIn('presupuesto_id', $presupuestoIds)->get();
        $reciboIds = $recibos->pluck('id');

        $remesas   = Remesa::where('comunidad_id', $comunidad->id)->get();
        $remesaIds = $remesas->pluck('id');

        $lineasRemesas = LineaRemesa::whereIn('remesa_id', $remesaIds)->get();

        $documentos = Documento::where(function ($q) use ($proveedorIds, $mandatoIds) {
            $q->where(fn ($q2) => $q2->where('documentable_type', Proveedor::class)->whereIn('documentable_id', $proveedorIds))
                ->orWhere(fn ($q2) => $q2->where('documentable_type', MandatoSepa::class)->whereIn('documentable_id', $mandatoIds));
        })->get();

        return [
            'comunidad'                => collect([$comunidad]),
            'personas'                 => collect([$comunidad->persona])->filter(),
            'personas_comunidad'       => $personasComunidad,
            'direcciones'              => Direccion::where('direccionable_type', PersonaComunidad::class)
                ->whereIn('direccionable_id', $personaComunidadIds)->get(),
            'contactos'                => Contacto::where('contactable_type', PersonaComunidad::class)
                ->whereIn('contactable_id', $personaComunidadIds)->get(),
            'comunidad_directivos'     => ComunidadDirectivo::where('comunidad_id', $comunidad->id)->get(),
            'propietarios'             => $propietarios,
            'proveedores'              => $proveedores,
            'cuentas_bancarias'        => CuentaBancaria::where(function ($q) use ($comunidad, $propietarioIds, $proveedorIds, $personaComunidadIds) {
                $q->where(fn ($q2) => $q2->where('titular_type', Comunidad::class)->where('titular_id', $comunidad->id))
                    ->orWhere(fn ($q2) => $q2->where('titular_type', Propietario::class)->whereIn('titular_id', $propietarioIds))
                    ->orWhere(fn ($q2) => $q2->where('titular_type', Proveedor::class)->whereIn('titular_id', $proveedorIds))
                    ->orWhereIn('persona_comunidad_id', $personaComunidadIds);
            })->get(),
            'mandatos_sepa'            => $mandatos,
            'inmuebles'                => $inmuebles,
            'titularidades'            => Titularidad::whereIn('inmueble_id', $inmuebleIds)->get(),
            'formas_pago_inmuebles'    => FormaPagoInmueble::whereIn('inmueble_id', $inmuebleIds)->get(),
            'grupos_de_reparto'        => $gruposDeReparto,
            'inmueble_grupo_de_reparto' => collect(DB::table('inmueble_grupo_de_reparto')->whereIn('inmueble_id', $inmuebleIds)->get()),
            'presupuestos'             => $presupuestos,
            'conceptos_presupuestos'   => ConceptoPresupuesto::whereIn('presupuesto_id', $presupuestoIds)->get(),
            'recibos'                  => $recibos,
            'remesas'                  => $remesas,
            'lineas_remesas'           => $lineasRemesas,
            'cobros'                   => Cobro::whereIn('recibo_id', $reciboIds)->get(),
            'avisos_recibos'           => AvisoRecibo::whereIn('recibo_id', $reciboIds)->get(),
            'documentos'               => $documentos,
            'facturas_proveedores'     => FacturaProveedor::whereIn('proveedor_id', $proveedorIds)->get(),
            'historial_estados'        => HistorialEstado::where(function ($q) use ($presupuestoIds, $propietarioIds, $proveedorIds) {
                $q->where(fn ($q2) => $q2->where('estadoable_type', Presupuesto::class)->whereIn('estadoable_id', $presupuestoIds))
                    ->orWhere(fn ($q2) => $q2->where('estadoable_type', Propietario::class)->whereIn('estadoable_id', $propietarioIds))
                    ->orWhere(fn ($q2) => $q2->where('estadoable_type', Proveedor::class)->whereIn('estadoable_id', $proveedorIds));
            })->get(),
        ];
    }

    /** Contenido de cada documento adjunto, en base64, indexado por su id de la tabla 'documentos'. */
    private function generarFicherosJson(Collection $documentos): string
    {
        $disco = Documento::disco();

        $ficheros = $documentos->map(function (Documento $documento) use ($disco) {
            if (! $disco->exists($documento->ruta)) {
                return null;
            }

            return [
                'documento_id'     => $documento->id,
                'nombre_original'  => $documento->nombrelocal,
                'extension'        => $documento->extension,
                'contenido_base64' => base64_encode($disco->get($documento->ruta)),
            ];
        })->filter()->values();

        return json_encode($ficheros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, Collection> $datos */
    private function generarIndice(Comunidad $comunidad, array $datos): string
    {
        $nombreComunidad = $comunidad->persona->razon_social ?? $comunidad->persona->nombre_comercial ?? "comunidad #{$comunidad->id}";

        $documentosConFichero = $datos['documentos']->filter(fn (Documento $d) => Documento::disco()->exists($d->ruta));
        $documentosSinFichero = $datos['documentos']->count() - $documentosConFichero->count();

        $lineasTablas = collect($datos)
            ->map(fn (Collection $filas, string $tabla) => "- **{$tabla}**: {$filas->count()} fila(s)")
            ->implode("\n");

        $avisoFaltantes = $documentosSinFichero > 0
            ? "\n> ⚠️ {$documentosSinFichero} documento(s) de la tabla `documentos` no tenían el fichero físico en disco en el momento de exportar: sus filas están en `datos.xml`, pero no hay contenido para ellos en `ficheros.json`.\n"
            : '';

        return <<<MD
        # Exportación de comunidad: {$nombreComunidad} (id {$comunidad->id})

        Generado el {$comunidad->freshTimestamp()->toDateTimeString()} por `condominios:comunidad-exportar`.

        ## Contenido del .zip

        - `datos.xml`: todas las filas de base de datos que cuelgan de esta comunidad, una tabla por
          elemento raíz y una fila por `<fila>`, con sus columnas tal cual están en la base de datos
          (sin campos calculados). Los valores nulos se representan como `<columna nulo="true"/>`.
        - `ficheros.json`: el contenido binario (en base64) de los documentos adjuntos a los
          proveedores de la comunidad y a sus mandatos SEPA. Es un array de objetos `{documento_id,
          nombre_original, extension, contenido_base64}`; `documento_id` enlaza con el `id` de la fila
          correspondiente dentro de `<documentos>` en `datos.xml` (que ya trae el resto de metadatos:
          tipo, tamaño, fecha de alta...).
        - `indice.md`: este fichero.

        ## Tablas incluidas en datos.xml

        {$lineasTablas}
        {$avisoFaltantes}
        ## Orden recomendado para reconstruir en otro sistema

        Al insertar en un sistema nuevo, respeta este orden (cada tabla puede depender de las
        anteriores):

        1. `personas` (la persona/CIF de la propia comunidad)
        2. `comunidad` (la fila de `comunidades`)
        3. `personas_comunidad`, `direcciones`, `contactos`
        4. `comunidad_directivos`, `propietarios`, `proveedores`
        5. `cuentas_bancarias`, `mandatos_sepa`
        6. `inmuebles`, `titularidades`, `formas_pago_inmuebles`
        7. `grupos_de_reparto`, `inmueble_grupo_de_reparto`
        8. `presupuestos`, `conceptos_presupuestos`
        9. `recibos`, `remesas`, `lineas_remesas`, `cobros`, `avisos_recibos`
        10. `documentos` (creando primero el fichero físico con el contenido de `ficheros.json`,
            luego la fila), `facturas_proveedores`
        11. `historial_estados`

        La contabilidad (`empresas_contables`, `cuenta_contables`, `tercero_contables`,
        `ejercicio_contables`, `asiento_contables`, `apunte_contables`) es un módulo independiente
        sin FK a comunidades, así que no cuelga de esta comunidad y no se incluye aquí: se exporta
        por separado con `condominios:contabilidad-exportar`. Las columnas de esta exportación que
        apuntan a ella (`comunidades.empresa_contable_id`, `presupuestos.cuenta_contable_id`,
        `propietarios.cuenta_contable_id`, `cuentas_bancarias.cuenta_contable_id`,
        `recibos.asiento_contable_id`, `cobros.asiento_contable_id`) llevan ids de esa otra
        exportación y solo valen si se reconstruyen las dos a la vez.

        ## Catálogos NO incluidos (deben existir ya en el sistema destino)

        Los ids de estas tablas se referencian por id en `datos.xml` pero no viajan en la
        exportación, porque son catálogos globales compartidos por todas las comunidades:
        `estados`, `paises`, `provincias`, `municipios`, `poblaciones`, `vias`,
        `tipo_documento_identificativos`, `tipo_generos`, `tipo_direcciones`, `tipo_contactos`,
        `tipo_ocupaciones`, `tipo_inmuebles`, `formas_de_pago`, `entidades_bancarias`,
        `tipo_estado_presupuestos`, `tipo_presupuestos`, `tipo_periodicidad_pagos`,
        `tipo_estado_recibos`, `tipo_documentos`.

        Si el sistema destino no usa los mismos ids para estos catálogos, hay que traducirlos a
        mano al reconstruir (`estado_id`, `tipo_documento_id`, `forma_de_pago_id`,
        `entidad_bancaria_id`, `documento_pais_id`, `nacionalidad_id`,
        `genero_id`, `ocupacion_id`, `tipo_inmueble_id`, `periodicidad_id`, etc.).
        MD;
    }
}
