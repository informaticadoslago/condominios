<?php

/**
 * Análisis de facturas de proveedores (PDF -> texto -> datos).
 */
return [
    // Motor para convertir el PDF a texto:
    //  'pdftotext'  -> binario del sistema (poppler-utils), mejor calidad con facturas en
    //                  columnas/tablas, pero requiere que esté instalado en el servidor.
    //  'pdfparser'  -> librería PHP pura (smalot/pdfparser), sin dependencias del sistema.
    //  'pdfplumber' -> Python, vía el venv de storage/app/pyenv; usa coordenadas reales de
    //                  palabra/línea en vez de aproximar columnas por espacios en blanco.
    'lector_pdf' => env('FACTURAS_LECTOR_PDF', 'pdftotext'),

    // Modelo usado para "Generar plantilla con IA": solo necesita localizar texto
    // literal en un documento corto, así que un modelo barato/rápido es de sobra.
    'ia_modelo' => env('FACTURAS_IA_MODELO', 'claude-haiku-4-5-20251001'),
];
