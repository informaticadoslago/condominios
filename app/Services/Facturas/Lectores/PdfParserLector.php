<?php

namespace App\Services\Facturas\Lectores;

use Smalot\PdfParser\Parser;

/** Convierte un PDF a texto con smalot/pdfparser, librería PHP pura (sin binarios del sistema). */
class PdfParserLector implements LectorPdfContrato
{
    public function aTexto(string $rutaAbsoluta): string
    {
        return (new Parser())->parseFile($rutaAbsoluta)->getText();
    }
}
