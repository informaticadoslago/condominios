<?php

/**
 * Análisis de facturas de proveedores (PDF -> texto -> datos).
 */
return [
    // Motor para convertir el PDF a texto:
    //  'pdftotext' -> binario del sistema (poppler-utils), mejor calidad con facturas en
    //                 columnas/tablas, pero requiere que esté instalado en el servidor.
    //  'pdfparser' -> librería PHP pura (smalot/pdfparser), sin dependencias del sistema.
    'lector_pdf' => env('FACTURAS_LECTOR_PDF', 'pdftotext'),
];
