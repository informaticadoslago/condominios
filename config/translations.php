<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor de traducción
    |--------------------------------------------------------------------------
    | Valores admitidos: dummy | google | openai
    */
    'provider' => env('TRANSLATIONS_PROVIDER', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    | Se usa la misma variable para el proveedor activo:
    |   - google → clave de Google Cloud Translation API
    |   - openai → clave de la API de OpenAI (sk-...)
    */
    'api_key' => env('TRANSLATIONS_API_KEY'),

    // Modelo a usar cuando el proveedor es OpenAI.
    'openai_model' => env('TRANSLATIONS_OPENAI_MODEL', 'gpt-4o-mini'),

    // Timeout (segundos) de las peticiones HTTP al proveedor.
    'timeout' => (int) env('TRANSLATIONS_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Caché en base de datos
    |--------------------------------------------------------------------------
    | Usa la tabla translations_caches para no volver a traducir textos ya
    | conocidos. Puedes desactivarla puntualmente con --nodatabase.
    */
    'database' => (bool) env('TRANSLATIONS_DATABASE', true),

    /*
    |--------------------------------------------------------------------------
    | Tamaño de lote
    |--------------------------------------------------------------------------
    | Nº de textos únicos por petición al proveedor.
    */
    'batch_size' => (int) env('TRANSLATIONS_BATCH_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Extractor (comando informaticadoslago:extract)
    |--------------------------------------------------------------------------
    | Se añade "config" para que se escaneen las claves del menú definidas en
    | config/sidebar.php mediante el marcador trans_key().
    */
    'extract' => [
        'paths'      => ['app', 'resources/views', 'config'],
        'include_js' => false,
    ],

    // Llamadas PHP que marcan traducción (heurística: grupo.item → PHP; texto
    // suelto → JSON).
    'php_functions' => [
        '__',
        'trans',
        'trans_choice',
        'Lang::get',
        '\\Illuminate\\Support\\Facades\\Lang::get',
    ],

    /*
    | Funciones-clave: su literal es SIEMPRE un short-key namespaced y va al
    | fichero PHP correspondiente, con espacios o sin ellos. trans_key() es un
    | marcador no-op (devuelve la clave sin traducir) usado en config/sidebar.php
    | para que el extractor descubra las claves del menú; la traducción real la
    | hace __() en la vista.
    */
    'key_functions' => [
        'trans_key',
    ],

];
