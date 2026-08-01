<?php

namespace App\Services\Facturas;

use App\Services\Facturas\Lectores\PdfParserLector;
use App\Services\Facturas\Lectores\PdftotextLector;

/** Convierte un PDF a texto plano. El motor (pdftotext o pdfparser) lo decide config('facturas.lector_pdf'). */
class LectorPdf
{
    public static function aTexto(string $rutaAbsoluta): string
    {
        $lector = match (config('facturas.lector_pdf')) {
            'pdfparser' => new PdfParserLector(),
            default     => new PdftotextLector(),
        };

        return $lector->aTexto($rutaAbsoluta);
    }
}
