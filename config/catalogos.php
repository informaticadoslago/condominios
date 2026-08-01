<?php

// Catálogos simples (id + descripción + estado) servidos por un único par de
// componentes Livewire (App\Livewire\Catalogos\Lista / Formulario), parametrizado
// por la clave de esta config. La clave es la que se usa en la ruta y en el menú.
return [
    'tipo-ocupaciones' => [
        'modelo'    => \App\Models\TipoOcupacion::class,
        'titulo'    => 'Tipos de ocupación',
        'subtitulo' => 'Ocupación de los inmuebles (alquilado, propietario...)',
    ],
    'tipo-inmuebles' => [
        'modelo'    => \App\Models\TipoInmueble::class,
        'titulo'    => 'Tipos de inmueble',
        'subtitulo' => 'Piso, garaje, trastero...',
    ],
    'tipo-cuenta-contables' => [
        'modelo'    => \App\Models\TipoCuentaContable::class,
        'titulo'    => 'Tipos de cuenta contable',
        'subtitulo' => 'Clasificación del plan de cuentas (Activo, Pasivo, Ingreso...)',
    ],
    'tipo-estado-presupuestos' => [
        'modelo'    => \App\Models\TipoEstadoPresupuesto::class,
        'titulo'    => 'Estados de presupuesto',
        'subtitulo' => 'Provisional, Presentado, Aprobado...',
    ],

    // Bloqueado: lo usan como estado_id muchos otros catálogos (incluidos los 4 de
    // arriba). Sin alta ni baja/borrado, solo lectura de las filas que ya existen.
    'estados' => [
        'modelo'     => \App\Models\Estado::class,
        'titulo'     => 'Estados',
        'subtitulo'  => 'Activo / Inactivo, usados como estado de otros catálogos',
        'bloqueado'  => true,
    ],
];
