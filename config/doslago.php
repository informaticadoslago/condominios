<?php

return [

    'superadmin' => [
        'nombre_rol' => 'super-admin',
        'email' => env('SUPERADMIN_EMAIL', 'admin@ejemplo.com'),
        'login' => env('SUPERADMIN_LOGIN', 'superadmin'),
    ],
    'pais' => [
        'españa' => 67,
        'inicial' => 67,   // España
    ],

    // Si es true, en los formularios Enter y las flechas izda/dcha navegan
    // entre campos (como Tab) en vez de comportarse de forma nativa.
    'tab_style' => (bool) env('RLAU_TAB_STYLE', false),

    // Versión del código desplegado, para «Acerca de…»: las escribe doslago:version al
    // desplegar. Etiqueta vacía = el último commit no tiene etiqueta y vale la fecha;
    // las dos vacías = versión desconocida.
    'version' => [
        'etiqueta' => env('LAST_GIT_TAG'),
        'fecha'    => env('LAST_GIT_DATE'),
    ],
];
