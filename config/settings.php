<?php

/*
 * Programado por Alberto Lago Rodríguez, ALAGORO 2019
 * Código libre, PERO NO GRATUÍTO.
 */

return [

//    'nombre_rol_superadmin'=>'super-admin',

    'file_path' => env('APP_FILE_PATH', 'demo'),

    'quienessomos' => env('APP_QUIENESSOMOS', 'Alagoro Software'),
    'cliente' => env('APP_CLIENTE', 'Alagoro Software'),

    'log_level' => env('APP_LOG_LEVEL', 'error'),

    'servers' => [
        'xestion' => env('SERVER_XESTION'),
    ],
    'trackLogin'    => env('TRACK_LOGIN', false),
    'trackNewUserRegistration'  => env('TRACK_NEW_USER_REGISTRATION', false),
    'prefix_carpeta_textos' => env('APP_FILE_PATH', 'demo2lago'),

    'storage_settings_path' => 'settings/',
    'storage_settings_file' => 'settings.json',

    'storage_templates_path' => 'templates/',
    'storage_images_path' => 'images/',
    'storage_documentos_path' => 'documentos/',

    'storage_imagenes_usuarios_path' => 'imagenes/usuarios/',
    'storage_documentos_admitidos' => 'jpeg,jpg,doc,pdf,docx,zip,epub',

    'settingsDir' => 'settings/',
    'settingsFile' => 'settings.json',
    'admitidos' => 'jpeg,jpg,png,svg',
    'logoFile' => '/imagenes/logo.jpg',
    'logoFile_Heading' => '/imagenes/logo.png',
    'logoFile360' => '/imagenes/logo360.svg',
    'provinciaDefault' => env('DEFAULT.PROVINCIA', 37),
    'municipioDefault' => env('DEFAULT_MUNICIPIO', 5327),
    'nombrecompleto' => [
        'apellidosnombre' => env('LIST_NOMBRECOMPLETO', 1), //{1=Nombre+apellidos, 2=apellidos,Nombre}'
    ],
    'list_nombre_completo' => env('LIST_NOMBRECOMPLETO', 1),
];
