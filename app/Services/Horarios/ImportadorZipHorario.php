<?php

namespace App\Services\Horarios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use ZipArchive;

/**
 * El equivalente, para Horarios, de ImportadorZipComunidad: reconstruye en la base de
 * datos lo que exportó HorarioExportador. Mucho más simple que aquel porque Horario no
 * tiene documentos adjuntos ni enlaces con otros módulos: solo hay que remapear
 * horario_id / asignatura_id al insertar, para no chocar con ids que ya existan.
 */
class ImportadorZipHorario
{
    /** De la raíz hacia las hojas: cada tabla puede depender de las anteriores. */
    private const ORDEN_TABLAS = ['horarios', 'asignaturas', 'jornadas', 'horario_sesiones'];

    public function importar(string $rutaTemporal, ?string $nombreFichero = null): void
    {
        $disco = Storage::disk('local');

        if (! $disco->exists($rutaTemporal)) {
            throw new RuntimeException("No existe el ZIP temporal '{$rutaTemporal}'.");
        }

        try {
            $carpeta = 'importaciones-horarios/'.Str::random(20);
            $disco->makeDirectory($carpeta);

            try {
                $zip = new ZipArchive();
                if ($zip->open($disco->path($rutaTemporal)) !== true) {
                    throw new RuntimeException('No se pudo abrir el ZIP de horario.');
                }

                $zip->extractTo($disco->path($carpeta));
                $zip->close();

                $datosPath = $disco->path($carpeta.'/datos.xml');

                if (! file_exists($datosPath)) {
                    throw new RuntimeException('El ZIP no contiene datos.xml.');
                }

                $xml = simplexml_load_file($datosPath);
                if ($xml === false) {
                    throw new RuntimeException('datos.xml no se pudo leer.');
                }

                if (! isset($xml->horarios->fila[0])) {
                    throw new RuntimeException('El ZIP no trae ningún horario.');
                }

                DB::transaction(function () use ($xml) {
                    $driver = DB::connection()->getDriverName();
                    $mapaIds = [];

                    if ($driver === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    }

                    try {
                        $bloques = [];
                        foreach ($xml->children() as $tabla => $filas) {
                            $bloques[(string) $tabla] = $filas;
                        }

                        $horarioId = null;

                        foreach (self::ORDEN_TABLAS as $tabla) {
                            if (! isset($bloques[$tabla])) {
                                continue;
                            }

                            foreach ($bloques[$tabla]->fila as $fila) {
                                $datosOriginales = $this->filaAtributos($fila);
                                $datos = $this->remapearFila($tabla, $datosOriginales, $mapaIds);

                                $idOriginal = isset($datosOriginales['id']) ? (int) $datosOriginales['id'] : null;
                                unset($datos['id']);

                                $idNuevo = (int) DB::table($tabla)->insertGetId($datos);

                                if ($idOriginal !== null) {
                                    $mapaIds[$tabla][$idOriginal] = $idNuevo;
                                }

                                if ($tabla === 'horarios') {
                                    $horarioId = $idNuevo;
                                }
                            }
                        }

                        $this->asegurarRolHorario($horarioId);
                    } finally {
                        if ($driver === 'mysql') {
                            DB::statement('SET FOREIGN_KEY_CHECKS=1');
                        }
                    }
                });
            } finally {
                $disco->deleteDirectory($carpeta);
            }
        } finally {
            // Sin colas ni reintentos: se acabó el intento, se acabó el ZIP temporal.
            $disco->delete($rutaTemporal);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<int, int>>  $mapaIds
     * @return array<string, mixed>
     */
    private function remapearFila(string $tabla, array $datos, array $mapaIds): array
    {
        switch ($tabla) {
            case 'asignaturas':
            case 'jornadas':
                $this->remapearColumna($datos, 'horario_id', 'horarios', $mapaIds);
                break;

            case 'horario_sesiones':
                $this->remapearColumna($datos, 'horario_id', 'horarios', $mapaIds);
                $this->remapearColumna($datos, 'asignatura_id', 'asignaturas', $mapaIds);
                break;
        }

        return $datos;
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

    private function filaAtributos(\SimpleXMLElement $fila): array
    {
        $datos = [];

        foreach ($fila->children() as $columna => $valor) {
            $nulo = (string) ($valor['nulo'] ?? '') === 'true';
            $datos[$columna] = $nulo ? null : (string) $valor;
        }

        return $datos;
    }

    private function asegurarRolHorario(?int $horarioId): void
    {
        if (! $horarioId) {
            return;
        }

        Role::firstOrCreate([
            'name' => 'horario-'.$horarioId,
            'guard_name' => 'web',
        ]);
    }
}
