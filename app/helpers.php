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

if (! function_exists('comunidad_actual')) {
    /**
     * La comunidad en la que se está trabajando, leída UNA vez por petición.
     *
     * Existe para que preguntar por ella salga gratis: la respuesta se memoiza, así que
     * da igual que la pregunten una pantalla, un menú y tres filas de una tabla.
     */
    function comunidad_actual(): ?App\Models\Comunidad
    {
        return once(fn () => App\Models\Comunidad::find(session('comunidad_actual_id')));
    }
}

if (! function_exists('contabilidad_activa')) {
    /**
     * ¿La comunidad activa lleva contabilidad? Es lo que decide si se enseñan las
     * acciones que hablan con ella; las de dentro (EnlaceContableComunidad) no
     * preguntan: si no está enlazada, no hacen nada.
     */
    function contabilidad_activa(): bool
    {
        return comunidad_actual()?->empresa_contable_id !== null;
    }
}
