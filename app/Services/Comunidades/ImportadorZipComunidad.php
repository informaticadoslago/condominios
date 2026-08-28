<?php

namespace App\Services\Comunidades;

use App\Models\Comunidad;
use App\Models\Documento;
use App\Models\EmpresaContable;
use App\Models\HistorialEstado;
use App\Models\HistorialImportacionComunidad;
use App\Models\Persona;
use App\Models\Recibo;
use App\Models\TipoEstadoRecibo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use ZipArchive;

class ImportadorZipComunidad
{
    /**
     * Desempaqueta el ZIP y solo mira lo justo para poder decidir si la importación es
     * viable: el CIF de la comunidad y si venía enlazada a una contabilidad. No toca la
     * base de datos.
     *
     * @return array{cif: string, enlazadaContabilidad: bool}
     */
    public function analizarComunidad(string $rutaTemporal): array
    {
        $disco = Storage::disk('local');

        if (! $disco->exists($rutaTemporal)) {
            throw new RuntimeException("No existe el ZIP temporal '{$rutaTemporal}'.");
        }

        $zip = new ZipArchive();
        if ($zip->open($disco->path($rutaTemporal)) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP de comunidad.');
        }

        try {
            $datos = $zip->getFromName('datos.xml');
            if ($datos === false) {
                throw new RuntimeException('El ZIP no contiene datos.xml.');
            }

            $xml = simplexml_load_string($datos);
            if ($xml === false) {
                throw new RuntimeException('datos.xml no se pudo leer.');
            }

            $cif = trim((string) ($xml->personas->fila[0]->documento_identificativo ?? ''));
            if ($cif === '') {
                throw new RuntimeException('El ZIP no trae el CIF de la comunidad.');
            }

            $empresaContableId = trim((string) ($xml->comunidad->fila[0]->empresa_contable_id ?? ''));

            return [
                'cif' => $cif,
                'enlazadaContabilidad' => $empresaContableId !== '',
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array{cif: string, enlazadaContabilidad: bool}  $analisis
     */
    private function validarImportacionPosible(array $analisis): void
    {
        if (Persona::where('documento_identificativo', $analisis['cif'])->exists()) {
            throw new RuntimeException('Ese CIF ya pertenece a otra persona registrada en el sistema.');
        }

        if (! $analisis['enlazadaContabilidad']) {
            return;
        }

        $cifNormalizado = $this->normalizarCif($analisis['cif']);
        $existeEmpresa = EmpresaContable::query()
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(cif, ' ', ''), '-', ''), '.', '')) = ?", [$cifNormalizado])
            ->exists();

        if ($existeEmpresa) {
            throw new RuntimeException('La comunidad estaba enlazada a contabilidad y ya existe una empresa contable con ese CIF. No se puede importar.');
        }
    }

    public function importar(string $rutaTemporal, ?string $nombreFichero = null): void
    {
        $disco = Storage::disk('local');

        if (! $disco->exists($rutaTemporal)) {
            throw new RuntimeException("No existe el ZIP temporal '{$rutaTemporal}'.");
        }

        try {
            $analisis = $this->analizarComunidad($rutaTemporal);
            $this->validarImportacionPosible($analisis);

            $rutaZip = $disco->path($rutaTemporal);
            $carpeta = 'importaciones-comunidades/'.Str::random(20);
            $disco->makeDirectory($carpeta);

            try {
                $zip = new ZipArchive();
                if ($zip->open($rutaZip) !== true) {
                    throw new RuntimeException('No se pudo abrir el ZIP de comunidad.');
                }

                $zip->extractTo($disco->path($carpeta));
                $zip->close();

                $datosPath = $disco->path($carpeta.'/datos.xml');
                $ficherosPath = $disco->path($carpeta.'/ficheros.json');

                if (! file_exists($datosPath)) {
                    throw new RuntimeException('El ZIP no contiene datos.xml.');
                }

                $xml = simplexml_load_file($datosPath);
                if ($xml === false) {
                    throw new RuntimeException('datos.xml no se pudo leer.');
                }

                $ficheros = file_exists($ficherosPath)
                    ? json_decode((string) file_get_contents($ficherosPath), true, 512, JSON_THROW_ON_ERROR)
                    : [];

                $avisosContables = [];
                $comunidadIdImportada = null;
                $enlazadoContabilidad = false;

                DB::transaction(function () use ($xml, $ficheros, &$avisosContables, &$comunidadIdImportada, &$enlazadoContabilidad) {
                    $driver = DB::connection()->getDriverName();
                    $comunidadIds = [];
                    $mapaIds = [];
                    $mantenerReferenciasContables = true;

                    if ($driver === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    }

                    try {
                        $bloques = [];
                        foreach ($xml->children() as $tabla => $filas) {
                            $tablaDestino = $this->tablaDestino((string) $tabla);
                            $bloques[$tablaDestino] = $filas;
                        }

                        foreach ($this->ordenarTablasImportacion($bloques) as $tablaDestino) {
                            $filas = $bloques[$tablaDestino];

                            foreach ($filas->fila as $fila) {
                                $datosOriginales = $this->filaAtributos($fila);

                                if ($tablaDestino === 'comunidades') {
                                    $resolucion = $this->resolverEnlaceContableComunidad($datosOriginales);
                                    $datosOriginales['empresa_contable_id'] = $resolucion['empresa_contable_id'];
                                    $mantenerReferenciasContables = $resolucion['mantener_referencias'];

                                    if ($resolucion['aviso'] !== null) {
                                        $avisosContables[] = $resolucion['aviso'];
                                    }
                                }

                                $datos = $this->remapearFila($tablaDestino, $datosOriginales, $mapaIds, $mantenerReferenciasContables);

                                $idOriginal = isset($datosOriginales['id']) ? (int) $datosOriginales['id'] : null;
                                $idRemapeable = $idOriginal !== null && $this->tablaConIdAutoIncrement($tablaDestino);

                                if ($idRemapeable) {
                                    unset($datos['id']);
                                    $idNuevo = (int) DB::table($tablaDestino)->insertGetId($datos);
                                    $mapaIds[$tablaDestino][$idOriginal] = $idNuevo;
                                } else {
                                    DB::table($tablaDestino)->insert($datos);
                                }

                                if ($tablaDestino === 'comunidades') {
                                    $comunidadIds[] = $idRemapeable
                                        ? $mapaIds['comunidades'][$idOriginal]
                                        : (int) ($datos['id'] ?? 0);
                                }
                            }
                        }

                        if (! $mantenerReferenciasContables) {
                            $this->normalizarEstadosRecibosSinContabilidad($mapaIds['recibos'] ?? []);
                        }

                        $this->reponerDocumentos($ficheros, $mapaIds['documentos'] ?? []);
                        $this->asegurarRolesComunidad($comunidadIds);

                        $comunidadIdImportada = $comunidadIds[0] ?? null;
                        $enlazadoContabilidad = $mantenerReferenciasContables;
                    } finally {
                        if ($driver === 'mysql') {
                            DB::statement('SET FOREIGN_KEY_CHECKS=1');
                        }
                    }
                });

                $this->registrarHistorial($analisis, $comunidadIdImportada, $nombreFichero, $enlazadoContabilidad, $avisosContables);

                foreach ($avisosContables as $aviso) {
                    Log::warning('Importación de comunidad: resolución de enlace contable', $aviso);
                }
            } finally {
                $disco->deleteDirectory($carpeta);
            }
        } finally {
            // Sin colas ni reintentos: se acabó el intento, se acabó el ZIP temporal.
            $disco->delete($rutaTemporal);
        }
    }

    /**
     * @param  array{cif: string, enlazadaContabilidad: bool}  $analisis
     * @param  array<int, array<string, mixed>>  $avisosContables
     */
    private function registrarHistorial(
        array $analisis,
        ?int $comunidadId,
        ?string $nombreFichero,
        bool $enlazadoContabilidad,
        array $avisosContables,
    ): void {
        $comunidad = $comunidadId ? Comunidad::with('persona')->find($comunidadId) : null;

        HistorialImportacionComunidad::create([
            'comunidad_id' => $comunidadId,
            'cif' => $analisis['cif'],
            'nombre_comunidad' => $comunidad?->nombre,
            'nombre_fichero' => $nombreFichero,
            'enlazado_contabilidad' => $enlazadoContabilidad,
            'avisos' => $avisosContables === [] ? null : $avisosContables,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * El ZIP de comunidad usa <comunidad> en singular, pero la tabla real es
     * comunidades. El resto de bloques ya usan el nombre de tabla.
     */
    private function tablaDestino(string $tablaXml): string
    {
        return $tablaXml === 'comunidad' ? 'comunidades' : $tablaXml;
    }

    /**
     * @param  array<string, \SimpleXMLElement>  $bloques
     * @return array<int, string>
     */
    private function ordenarTablasImportacion(array $bloques): array
    {
        $orden = [
            'personas',
            'comunidades',
            'personas_comunidad',
            'direcciones',
            'contactos',
            'comunidad_directivos',
            'propietarios',
            'proveedores',
            'cuentas_bancarias',
            'mandatos_sepa',
            'inmuebles',
            'titularidades',
            'formas_pago_inmuebles',
            'grupos_de_reparto',
            'inmueble_grupo_de_reparto',
            'presupuestos',
            'conceptos_presupuestos',
            'recibos',
            'remesas',
            'lineas_remesas',
            'cobros',
            'correos_enviados',
            'documentos',
            'facturas_proveedores',
            'pagos_facturas',
            'historial_estados',
        ];

        $presentes = array_keys($bloques);
        $ordenadas = array_values(array_intersect($orden, $presentes));

        foreach ($presentes as $tabla) {
            if (! in_array($tabla, $ordenadas, true)) {
                $ordenadas[] = $tabla;
            }
        }

        return $ordenadas;
    }

    private function tablaConIdAutoIncrement(string $tabla): bool
    {
        return in_array($tabla, [
            'personas',
            'comunidades',
            'personas_comunidad',
            'direcciones',
            'contactos',
            'comunidad_directivos',
            'propietarios',
            'proveedores',
            'cuentas_bancarias',
            'mandatos_sepa',
            'inmuebles',
            'titularidades',
            'formas_pago_inmuebles',
            'grupos_de_reparto',
            'presupuestos',
            'conceptos_presupuestos',
            'recibos',
            'remesas',
            'lineas_remesas',
            'cobros',
            'correos_enviados',
            'documentos',
            'facturas_proveedores',
            'pagos_facturas',
            'historial_estados',
        ], true);
    }

    /**
     * Traduce IDs de la exportación a IDs nuevos en destino para mantener
     * integridad referencial cuando la BD ya tiene datos.
     *
     * @param  array<string, array<int, int>>  $mapaIds
     * @return array<string, mixed>
     */
    private function remapearFila(string $tabla, array $datos, array $mapaIds, bool $mantenerReferenciasContables): array
    {
        if (! $mantenerReferenciasContables) {
            $this->limpiarReferenciasContablesDeFila($tabla, $datos);
        }

        switch ($tabla) {
            case 'comunidades':
                $this->remapearColumna($datos, 'persona_id', 'personas', $mapaIds);
                break;

            case 'personas_comunidad':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                break;

            case 'direcciones':
                if (($datos['direccionable_type'] ?? null) === Persona::class) {
                    $this->remapearColumna($datos, 'direccionable_id', 'personas', $mapaIds);
                } elseif (($datos['direccionable_type'] ?? null) === 'App\\Models\\PersonaComunidad') {
                    $this->remapearColumna($datos, 'direccionable_id', 'personas_comunidad', $mapaIds);
                }
                break;

            case 'contactos':
                if (($datos['contactable_type'] ?? null) === Persona::class) {
                    $this->remapearColumna($datos, 'contactable_id', 'personas', $mapaIds);
                } elseif (($datos['contactable_type'] ?? null) === 'App\\Models\\PersonaComunidad') {
                    $this->remapearColumna($datos, 'contactable_id', 'personas_comunidad', $mapaIds);
                }
                break;

            case 'comunidad_directivos':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                $this->remapearColumna($datos, 'persona_comunidad_id', 'personas_comunidad', $mapaIds);
                break;

            case 'propietarios':
                $this->remapearColumna($datos, 'persona_comunidad_id', 'personas_comunidad', $mapaIds);
                break;

            case 'proveedores':
                $this->remapearColumna($datos, 'persona_id', 'personas_comunidad', $mapaIds);
                break;

            case 'cuentas_bancarias':
                $this->remapearColumna($datos, 'persona_comunidad_id', 'personas_comunidad', $mapaIds);
                $this->remapearTitularCuentaBancaria($datos, $mapaIds);
                break;

            case 'mandatos_sepa':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                $this->remapearColumna($datos, 'cuenta_bancaria_id', 'cuentas_bancarias', $mapaIds);
                break;

            case 'inmuebles':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                break;

            case 'titularidades':
                $this->remapearColumna($datos, 'inmueble_id', 'inmuebles', $mapaIds);
                $this->remapearColumna($datos, 'propietario_id', 'propietarios', $mapaIds);
                break;

            case 'formas_pago_inmuebles':
                $this->remapearColumna($datos, 'inmueble_id', 'inmuebles', $mapaIds);
                $this->remapearColumna($datos, 'propietario_id', 'propietarios', $mapaIds);
                $this->remapearColumna($datos, 'cuenta_bancaria_id', 'cuentas_bancarias', $mapaIds);
                break;

            case 'grupos_de_reparto':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                break;

            case 'inmueble_grupo_de_reparto':
                $this->remapearColumna($datos, 'inmueble_id', 'inmuebles', $mapaIds);
                $this->remapearColumna($datos, 'grupo_de_reparto_id', 'grupos_de_reparto', $mapaIds);
                break;

            case 'presupuestos':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                break;

            case 'conceptos_presupuestos':
                $this->remapearColumna($datos, 'presupuesto_id', 'presupuestos', $mapaIds);
                $this->remapearColumna($datos, 'grupo_de_reparto_id', 'grupos_de_reparto', $mapaIds);
                break;

            case 'recibos':
                $this->remapearColumna($datos, 'presupuesto_id', 'presupuestos', $mapaIds);
                $this->remapearColumna($datos, 'inmueble_id', 'inmuebles', $mapaIds);
                $this->remapearColumna($datos, 'propietario_id', 'propietarios', $mapaIds);
                $this->remapearColumna($datos, 'cuenta_bancaria_id', 'cuentas_bancarias', $mapaIds);
                break;

            case 'remesas':
                $this->remapearColumna($datos, 'comunidad_id', 'comunidades', $mapaIds);
                $this->remapearColumna($datos, 'cuenta_bancaria_id', 'cuentas_bancarias', $mapaIds);
                break;

            case 'lineas_remesas':
                $this->remapearColumna($datos, 'remesa_id', 'remesas', $mapaIds);
                $this->remapearColumna($datos, 'recibo_id', 'recibos', $mapaIds);
                break;

            case 'cobros':
                $this->remapearColumna($datos, 'recibo_id', 'recibos', $mapaIds);
                $this->remapearColumna($datos, 'linea_remesa_id', 'lineas_remesas', $mapaIds);
                break;

            case 'correos_enviados':
                $this->remapearColumna($datos, 'recibo_id', 'recibos', $mapaIds);
                break;

            case 'documentos':
                $this->remapearDocumentable($datos, $mapaIds);
                break;

            case 'facturas_proveedores':
                $this->remapearColumna($datos, 'documento_id', 'documentos', $mapaIds);
                $this->remapearColumna($datos, 'proveedor_id', 'proveedores', $mapaIds);
                break;

            case 'pagos_facturas':
                $this->remapearColumna($datos, 'factura_proveedor_id', 'facturas_proveedores', $mapaIds);
                $this->remapearColumna($datos, 'cuenta_bancaria_id', 'cuentas_bancarias', $mapaIds);
                break;

            case 'historial_estados':
                $this->remapearEstadoable($datos, $mapaIds);
                break;
        }

        return $datos;
    }

    /**
     * La comunidad importada nunca se enlaza sola con una contabilidad ajena: si venía
     * enlazada en origen, aquí siempre se desvincula. Que ya exista una empresa contable
     * con ese CIF en destino se comprueba antes de empezar (validarImportacionPosible) y
     * hace fallar toda la importación, así que si se llega hasta aquí es seguro
     * desvincular sin más.
     *
     * @return array{empresa_contable_id:int|null,mantener_referencias:bool,aviso:array<string,mixed>|null}
     */
    private function resolverEnlaceContableComunidad(array $datosComunidad): array
    {
        $empresaIdZip = isset($datosComunidad['empresa_contable_id']) && $datosComunidad['empresa_contable_id'] !== ''
            ? (int) $datosComunidad['empresa_contable_id']
            : null;

        return [
            'empresa_contable_id' => null,
            'mantener_referencias' => false,
            'aviso' => $empresaIdZip === null ? null : [
                'motivo' => 'comunidad_enlazada_desvinculada_en_importacion',
                'empresa_zip_id' => $empresaIdZip,
            ],
        ];
    }

    private function normalizarCif(string $valor): string
    {
        $limpio = strtoupper(trim($valor));

        return str_replace([' ', '-', '.'], '', $limpio);
    }

    /**
     * Si la comunidad no puede enlazar contabilidad en destino, se eliminan todas las
     * referencias importadas al módulo contable para dejar un estado coherente.
     */
    private function limpiarReferenciasContablesDeFila(string $tabla, array &$datos): void
    {
        switch ($tabla) {
            case 'comunidades':
                $datos['empresa_contable_id'] = null;
                break;

            case 'presupuestos':
            case 'propietarios':
                if (array_key_exists('cuenta_contable', $datos)) {
                    $datos['cuenta_contable'] = null;
                }
                break;

            case 'cuentas_bancarias':
                if (array_key_exists('cuenta_contable', $datos)) {
                    $datos['cuenta_contable'] = null;
                }
                if (array_key_exists('nombre_contable', $datos)) {
                    $datos['nombre_contable'] = null;
                }
                break;

            case 'recibos':
            case 'cobros':
            case 'facturas_proveedores':
            case 'pagos_facturas':
                if (array_key_exists('asiento_contable', $datos)) {
                    $datos['asiento_contable'] = null;
                }
                if ($tabla === 'facturas_proveedores' && array_key_exists('cuenta_gasto', $datos)) {
                    $datos['cuenta_gasto'] = null;
                }
                break;
        }
    }

    /**
     * Sin la contabilidad que enlazaba, un recibo que solo estaba COBRADO porque el
     * enlace le seguía el rastro a un pago que aquí no existe vuelve a su estado
     * anterior, y la línea de historial que registró ese "cobrado" se borra: ya no
     * cuenta nada cierto en este sistema.
     *
     * @param  array<int, int>  $mapaRecibos
     */
    private function normalizarEstadosRecibosSinContabilidad(array $mapaRecibos): void
    {
        $reciboIds = array_values($mapaRecibos);
        if ($reciboIds === []) {
            return;
        }

        foreach (Recibo::query()->whereIn('id', $reciboIds)->get(['id', 'estado_id', 'saldo']) as $recibo) {
            if ((float) $recibo->saldo <= 0) {
                if ((int) $recibo->estado_id !== TipoEstadoRecibo::COBRADO) {
                    DB::table('recibos')->where('id', $recibo->id)->update(['estado_id' => TipoEstadoRecibo::COBRADO]);
                }

                continue;
            }

            if ((int) $recibo->estado_id !== TipoEstadoRecibo::COBRADO) {
                continue;
            }

            $historial = HistorialEstado::query()
                ->where('estadoable_type', Recibo::class)
                ->where('estadoable_id', $recibo->id)
                ->where('estado_nuevo', TipoEstadoRecibo::COBRADO)
                ->orderByDesc('id')
                ->first();

            $estadoAnterior = $historial?->estado_anterior ? (int) $historial->estado_anterior : TipoEstadoRecibo::GENERADO;

            DB::table('recibos')->where('id', $recibo->id)->update(['estado_id' => $estadoAnterior]);

            $historial?->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<int, int>>  $mapaIds
     */
    private function remapearColumna(array &$datos, string $columna, string $tablaDestino, array $mapaIds): void
    {
        if (! array_key_exists($columna, $datos) || $datos[$columna] === null || $datos[$columna] === '') {
            return;
        }

        $idOriginal = (int) $datos[$columna];
        $idNuevo = $mapaIds[$tablaDestino][$idOriginal] ?? null;

        if ($idNuevo === null) {
            throw new RuntimeException("No se pudo remapear {$tablaDestino}.id={$idOriginal} (columna {$columna}).");
        }

        $datos[$columna] = $idNuevo;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<int, int>>  $mapaIds
     */
    private function remapearTitularCuentaBancaria(array &$datos, array $mapaIds): void
    {
        $tipo = (string) ($datos['titular_type'] ?? '');

        if ($tipo === 'App\\Models\\Comunidad') {
            $this->remapearColumna($datos, 'titular_id', 'comunidades', $mapaIds);
            return;
        }

        if ($tipo === 'App\\Models\\Propietario') {
            $this->remapearColumna($datos, 'titular_id', 'propietarios', $mapaIds);
            return;
        }

        if ($tipo === 'App\\Models\\Proveedor') {
            $this->remapearColumna($datos, 'titular_id', 'proveedores', $mapaIds);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<int, int>>  $mapaIds
     */
    private function remapearDocumentable(array &$datos, array $mapaIds): void
    {
        $tipo = (string) ($datos['documentable_type'] ?? '');

        if ($tipo === 'App\\Models\\Proveedor') {
            $this->remapearColumna($datos, 'documentable_id', 'proveedores', $mapaIds);
            return;
        }

        if ($tipo === 'App\\Models\\MandatoSepa') {
            $this->remapearColumna($datos, 'documentable_id', 'mandatos_sepa', $mapaIds);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<int, int>>  $mapaIds
     */
    private function remapearEstadoable(array &$datos, array $mapaIds): void
    {
        $tipo = (string) ($datos['estadoable_type'] ?? '');

        if ($tipo === 'App\\Models\\Presupuesto') {
            $this->remapearColumna($datos, 'estadoable_id', 'presupuestos', $mapaIds);
            return;
        }

        if ($tipo === 'App\\Models\\Propietario') {
            $this->remapearColumna($datos, 'estadoable_id', 'propietarios', $mapaIds);
            return;
        }

        if ($tipo === 'App\\Models\\Proveedor') {
            $this->remapearColumna($datos, 'estadoable_id', 'proveedores', $mapaIds);
        }
    }

    private function obtenerCif(string $rutaTemporal): string
    {
        $disco = Storage::disk('local');

        if (! $disco->exists($rutaTemporal)) {
            throw new RuntimeException("No existe el ZIP temporal '{$rutaTemporal}'.");
        }

        $zip = new ZipArchive();
        if ($zip->open($disco->path($rutaTemporal)) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP de comunidad.');
        }

        try {
            $datos = $zip->getFromName('datos.xml');
            if ($datos === false) {
                throw new RuntimeException('El ZIP no contiene datos.xml.');
            }

            $xml = simplexml_load_string($datos);
            if ($xml === false) {
                throw new RuntimeException('datos.xml no se pudo leer.');
            }

            $cif = trim((string) ($xml->personas->fila[0]->documento_identificativo ?? ''));
            if ($cif === '') {
                throw new RuntimeException('El ZIP no trae el CIF de la comunidad.');
            }

            return $cif;
        } finally {
            $zip->close();
        }
    }

    private function filaAtributos(\SimpleXMLElement $fila): array
    {
        $datos = [];

        foreach ($fila->children() as $columna => $valor) {
            $nulo = (string) ($valor['nulo'] ?? '') === 'true';
            $datos[$columna] = $nulo ? null : (string) $valor;
        }

        return $datos;
    }

    /**
     * @param  array<int, int>  $mapaDocumentos
     */
    private function reponerDocumentos(array $ficheros, array $mapaDocumentos): void
    {
        if ($ficheros === []) {
            return;
        }

        $disco = Documento::disco();

        foreach ($ficheros as $item) {
            $documentoIdOriginal = $item['documento_id'] ?? null;
            $contenido = $item['contenido_base64'] ?? null;

            if (! $documentoIdOriginal || ! $contenido) {
                continue;
            }

            $documentoId = $mapaDocumentos[(int) $documentoIdOriginal] ?? null;
            if (! $documentoId) {
                throw new RuntimeException("No se pudo remapear el documento {$documentoIdOriginal} al reponer ficheros.");
            }

            $documento = Documento::find($documentoId);
            if (! $documento) {
                continue;
            }

            $binario = base64_decode($contenido, true);
            if ($binario === false) {
                throw new RuntimeException("El fichero base64 del documento {$documentoId} no se pudo decodificar.");
            }

            $ruta = ltrim(trim((string) $documento->camino, '/') . '/' . $documento->nombrefichero, '/');
            $disco->put($ruta, $binario);
        }
    }

    /**
     * La inserción cruda no dispara el booted() de Comunidad, así que el rol de acceso
     * hay que recrearlo manualmente para cada comunidad importada.
     *
     * @param  array<int>  $comunidadIds
     */
    private function asegurarRolesComunidad(array $comunidadIds): void
    {
        foreach (array_unique(array_filter($comunidadIds)) as $comunidadId) {
            Role::firstOrCreate([
                'name' => 'comunidad-'.$comunidadId,
                'guard_name' => 'web',
            ]);
        }
    }
}