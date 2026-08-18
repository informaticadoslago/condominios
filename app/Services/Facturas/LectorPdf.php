<?php

namespace App\Services\Facturas;

use App\Services\Facturas\Lectores\PdfParserLector;
use App\Services\Facturas\Lectores\PdfplumberLector;
use App\Services\Facturas\Lectores\PdftotextLector;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** Convierte un PDF a texto plano. El motor (pdftotext, pdfparser o pdfplumber) lo decide config('facturas.lector_pdf'). */
class LectorPdf
{
    public static function aTexto(string $rutaAbsoluta): string
    {
        $lector = match (config('facturas.lector_pdf')) {
            'pdfparser'  => new PdfParserLector(),
            'pdfplumber' => new PdfplumberLector(),
            default      => new PdftotextLector(),
        };

        return self::quitarSangriaInicial($lector->aTexto($rutaAbsoluta));
    }

    /**
     * Lee el PDF con las coordenadas reales de cada línea de texto en la página (pdftotext
     * -bbox-layout), para anclar por posición cuando no hay ninguna etiqueta de texto cerca
     * del valor (ver ExtractorPorCoordenadas). Siempre usa pdftotext: es el único de los tres
     * motores que da coordenadas, y ya es una dependencia del sistema.
     *
     * @return array<int, array{texto: string, pagina: int, x: float, y: float, ancho: float}>
     */
    public static function aBloquesConPosicion(string $rutaAbsoluta): array
    {
        $process = new Process(['pdftotext', '-bbox-layout', $rutaAbsoluta, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return self::parsearBbox($process->getOutput());
    }

    protected static function parsearBbox(string $xml): array
    {
        // El xmlns por defecto del XHTML de pdftotext estorba a las propiedades mágicas de
        // SimpleXML (todo quedaría bajo un namespace); no hace falta para nada aquí.
        $xml = str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $xml);

        $doc     = new \SimpleXMLElement($xml);
        $bloques = [];
        $pagina  = 0;

        foreach ($doc->body->doc->page as $page) {
            $pagina++;

            foreach ($page->xpath('.//line') as $linea) {
                $palabras = [];
                foreach ($linea->word as $palabra) {
                    $palabras[] = trim((string) $palabra);
                }

                $texto = trim(implode(' ', $palabras));
                if ($texto === '') {
                    continue;
                }

                $bloques[] = [
                    'texto'  => $texto,
                    'pagina' => $pagina,
                    'x'      => (float) $linea['xMin'],
                    'y'      => (float) $linea['yMin'],
                    'ancho'  => (float) $linea['xMax'] - (float) $linea['xMin'],
                ];
            }
        }

        return $bloques;
    }

    /**
     * Quita los espacios en blanco al principio de cada línea (una especie de LTRIM por
     * línea): el margen izquierdo variable que deja pdftotext -layout según dónde empieza
     * el texto en la página añade ruido a las comparaciones de columna por distancia de
     * caracteres que hace ExtractorPosicional, sin aportar nada útil para marcar/anclar
     * campos.
     */
    protected static function quitarSangriaInicial(string $texto): string
    {
        return implode("\n", array_map('ltrim', explode("\n", $texto)));
    }

    /**
     * Extrae la imagen embebida más grande de la página 1 (pdfimages, poppler-utils). Algunas
     * plantillas preimpresas llevan la razón social, el CIF y hasta las cajas/tabla del
     * formulario "quemadas" en una única imagen de fondo, sin ningún rastro en el texto: esto
     * permite mostrársela al usuario para que la lea a ojo y la escriba a mano (ver
     * MarcarPlantillaFactura), ya que no hay forma de localizarla por texto ni por posición.
     */
    public static function extraerImagenPrincipal(string $rutaAbsoluta): ?string
    {
        $directorioTemporal = sys_get_temp_dir() . '/' . uniqid('pdfimg_', true);
        mkdir($directorioTemporal);

        try {
            $process = new Process(['pdfimages', '-f', '1', '-l', '1', '-png', $rutaAbsoluta, $directorioTemporal . '/img']);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $ficheros = glob($directorioTemporal . '/img*.png');
            if (empty($ficheros)) {
                return null;
            }

            usort($ficheros, fn ($a, $b) => filesize($b) <=> filesize($a));

            return file_get_contents($ficheros[0]);
        } finally {
            array_map('unlink', glob($directorioTemporal . '/*') ?: []);
            @rmdir($directorioTemporal);
        }
    }
}
