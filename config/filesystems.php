<?php

/*
 * Permisos de los discos con datos de personas (boletines, documentos): son de la
 * aplicación y de nadie más. Sin esto, cada fichero sale con lo que le dé el umask del
 * proceso que lo cree —php-fpm crea a 0700 y la CLI a 0775—, así que una misma carpeta
 * tenía trozos que ni el usuario de desarrollo podía leer ni borrar.
 *
 * 0770/0660: el usuario del servidor web y su grupo, y nadie más. Ni 'otros' (son PDFs de
 * menores, y el resto de usuarios de la máquina no pintan nada ahí) ni depender del umask.
 */
$permisosPrivados = [
    'file' => ['public' => 0660, 'private' => 0660],
    'dir'  => ['public' => 0770, 'private' => 0770],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Documentos adjuntos (tabla 'documentos'). DOCUMENTOS_ROOT es relativa a
        // storage/ (como en L9, que guardaba en app/documentos): los ficheros se
        // localizan solo por 'nombrefichero' dentro de esa carpeta.
        'documentos' => [
            'driver' => 'local',
            'root' => storage_path(env('DOCUMENTOS_ROOT', 'app/documentos')),
            'visibility' => 'private',
            'permissions' => $permisosPrivados,
            'throw' => false,
            'report' => false,
        ],

        // Boletines en PDF. Cuelgan de aquí por id de curso/evaluación/matrícula, así que
        // el camino se deduce de la fila y no se guarda en la base de datos. Servirlos
        // pasa siempre por este disco, nunca por storage_path() a pelo.
        'boletines' => [
            'driver' => 'local',
            'root' => storage_path(env('BOLETINES_ROOT', 'app/boletines')),
            'visibility' => 'private',
            'permissions' => $permisosPrivados,
            'throw' => false,
            'report' => false,
        ],

        // Copias de seguridad (spatie/laravel-backup): zips con BD + ficheros.
        'backups' => [
            'driver' => 'local',
            'root' => storage_path(env('BACKUPS_ROOT', 'app/backups')),
            'visibility' => 'private',
            'permissions' => $permisosPrivados,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
