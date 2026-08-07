<?php

namespace App\Services\Exportacion;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Lo que comparten las exportaciones que se llevan un bloque entero de datos a un .zip
 * (una comunidad, una empresa contable): volcar filas de BD a un XML plano y empaquetar
 * ese XML junto a los ficheros que lo acompañan en el disco 'coms'.
 *
 * Cada exportador concreto pone lo suyo: qué filas recopila, cómo se llama el .zip y qué
 * cuenta su indice.md. Aquí solo está la mecánica del formato.
 */
abstract class ExportadorZip
{
    /**
     * Vuelca las filas a un XML: un elemento por tabla, un <fila> por fila y un elemento
     * por columna con el valor crudo de la base de datos (sin campos calculados). Los
     * nulos se marcan con <columna nulo="true"/> para distinguirlos de la cadena vacía.
     *
     * @param  array<string, string>      $atributosRaiz
     * @param  array<string, Collection>  $datos
     */
    protected function generarXml(string $elementoRaiz, array $atributosRaiz, array $datos): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $raiz = $dom->createElement($elementoRaiz);
        foreach ($atributosRaiz as $nombre => $valor) {
            $raiz->setAttribute($nombre, $valor);
        }
        $dom->appendChild($raiz);

        foreach ($datos as $tabla => $filas) {
            $this->anadirTabla($dom, $raiz, $tabla, $filas);
        }

        return $dom->saveXML();
    }

    private function anadirTabla(DOMDocument $dom, DOMElement $raiz, string $tabla, Collection $filas): void
    {
        $contenedor = $dom->createElement($tabla);

        foreach ($filas as $fila) {
            $atributos = $fila instanceof \Illuminate\Database\Eloquent\Model
                ? $fila->getAttributes()
                : (array) $fila;

            $elemento = $dom->createElement('fila');

            foreach ($atributos as $columna => $valor) {
                $campo = $dom->createElement($this->nombreValido($columna));

                if ($valor === null) {
                    $campo->setAttribute('nulo', 'true');
                } else {
                    $campo->appendChild($dom->createTextNode((string) $valor));
                }

                $elemento->appendChild($campo);
            }

            $contenedor->appendChild($elemento);
        }

        $raiz->appendChild($contenedor);
    }

    /** Un nombre de columna es casi siempre un nombre de elemento XML válido, pero por si acaso. */
    private function nombreValido(string $columna): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $columna) ? $columna : 'col_' . preg_replace('/[^A-Za-z0-9_]/', '_', $columna);
    }

    /**
     * Escribe los ficheros en una carpeta temporal del disco 'coms', los mete en el .zip
     * y borra la carpeta: al final ahí solo debe quedar el .zip.
     *
     * @param  array<string, string>  $ficheros  nombre dentro del zip => contenido
     */
    protected function empaquetar(string $nombreZip, array $ficheros): string
    {
        $carpetaTemporal = 'tmp/' . Str::random(20);
        Storage::disk('coms')->makeDirectory($carpetaTemporal);

        try {
            foreach ($ficheros as $nombre => $contenido) {
                Storage::disk('coms')->put("{$carpetaTemporal}/{$nombre}", $contenido);
            }

            $zip = new ZipArchive();
            if ($zip->open(Storage::disk('coms')->path($nombreZip), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("No se pudo crear el fichero zip '{$nombreZip}'.");
            }

            foreach (array_keys($ficheros) as $nombre) {
                $zip->addFile(Storage::disk('coms')->path("{$carpetaTemporal}/{$nombre}"), $nombre);
            }

            $zip->close();

            return $nombreZip;
        } finally {
            Storage::disk('coms')->deleteDirectory($carpetaTemporal);
        }
    }
}
