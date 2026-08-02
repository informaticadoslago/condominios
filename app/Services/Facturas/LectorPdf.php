<?php

namespace App\Services\Facturas;

use App\Services\Facturas\Lectores\PdfParserLector;
use App\Services\Facturas\Lectores\PdfplumberLector;
use App\Services\Facturas\Lectores\PdftotextLector;

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
}
