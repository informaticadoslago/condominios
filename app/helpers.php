<?php

// Helpers sueltos de app/Helpers/ que no están en el autoload de composer.
require_once __DIR__ . '/Helpers/formatIbanSegments.php';

if (! function_exists('trans_key')) {
    /**
     * Marca una cadena como CLAVE de traducción para el extractor
     * (informaticadoslago:extract) sin traducirla: devuelve la clave tal cual.
     *
     * La traducción real la hace __() en la vista, en el momento del render y
     * con el locale de la petición. Por eso es seguro usarlo en ficheros de
     * config (incluido config:cache): lo que se guarda es la clave, no el texto.
     *
     *   trans_key('menu.Alumnos')  →  'menu.Alumnos'
     */
    function trans_key(string $key): string
    {
        return $key;
    }
}
