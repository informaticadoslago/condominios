<?php

/**
 * Documentos adjuntos (tabla 'documentos', polimórfica).
 *
 * El disco 'documentos' (config/filesystems.php) cuelga de storage/, en la carpeta
 * que diga DOCUMENTOS_ROOT (por defecto 'app/documentos', igual que en L9). Ahí hay
 * que dejar los ficheros que L9 ya tenía: se localizan solo por 'nombrefichero'.
 */
return [
    'disco' => env('DOCUMENTOS_DISCO', 'documentos'),

    // Tamaño máximo por fichero (KB) y extensiones admitidas.
    'max_kb'      => env('DOCUMENTOS_MAX_KB', 12288),
    'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'odt', 'doc', 'docx', 'xls', 'xlsx'],

    // Subcarpeta (dentro del disco) donde esperan los ficheros de un alta a medias,
    // hasta que se graba y se mueven a la raíz (junto a los de L9).
    'carpeta_borradores' => 'borradores',
];
