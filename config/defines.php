<?php

return [

    'lineas_x_pagina' => 25,
    'len_movilespañol' => 9,
    'minutos_unidad' => 15,

    /*
     * T-L9-L12: raíces de 'tipos_de_tipos' (el cajón de L9) de las que cuelga cada familia
     * de tipos. CADA INSTALACIÓN TIENE LOS SUYOS y no se pueden escribir en el código: en
     * Bembrive el descuento tarifable es el 300 y en Chapela el 301; el e-Boletín es el
     * 305 y el 675. Los valores de cada una están en su .env (.env.chapela, .env.bembrive).
     * Tampoco valen por nombre: unas escriben "Tipo Sin Grupo" y otras "Tipo Sin grupo".
     *
     * Los usa la migración que llena 'categorias'. Se van con L9.
     */
    'tipos_l9' => [
        'asignatura'         => env('TIPO_ASIGNATURA_TARIFABLE'),
        'asignatura_singrupo' => env('TIPO_ASIGNATURA_SINGRUPO'),
        'descuento'          => env('TIPO_DESCUENTO_TARIFABLE'),
        'descuento_singrupo' => env('TIPO_DESCUENTO_SINGRUPO'),
        'cuota_matricula'    => env('TIPO_CUOTA_MATRICULA'),
    ],

    'salida' => [
        'PANTALLA' => 1,
        'IMPRESORA' => 2,
        'FACTURAR' => 3,
    ],

    'autores' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'conservacionestados' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'contactos' => [
        'tipos' => [
            'movil' => 188,
            'telefono' => 189,
            'email' => 190,
            'e_facturacion' => 287,
            'e_boletin' => env('TIPO_CONTACTO_E_BOLETIN', 305),
        ],
        'estado' => [
            'active' => 1,
            'baja' => 2,
        ],

    ],
    'K_CUENTAACREEDORES_ESTADO' => [
        'ACTIVA' => 1,
        'BAJA' => 2,
    ],
    'paises' => [
        'españa' => 67,
        'estado' => [
            'active' => 1,
        ],
    ],

    'direcciones' => [
        'tipos' => [
            'domicilio' => 185,
            'facturacion' => 186,
        ],
        'estado' => [
            'active' => 1,
            'baja' => 2,
        ],

    ],

    'documentos_identificacion' => [
        'nif' => 2,
        'nif eu' => 4,
        'cif' => 6,
        'nie' => 3,
        'pasaporte' => 5,
    ],

    'tiposdepersonas' => [
        'personfisica' => [2, 3, 4, 5],
        'personajuridica' => ['6'],
    ],

    'edad' => [
        'mayordeedad' => 18,
        'minima' => 1,
        // Edad máxima para admitir un alumno SIN documento identificativo.
        'max_sin_nif' => 14,
    ],

    // Reglas de alta de alumno (calca L9). Configurables por .env:
    //  - requiere_socio: si ==1, el alumno debe tener socio responsable.
    //  - menor_requiere_tutor: si ==1, un alumno menor de edad debe tener ≥1 tutor.
    'alumno' => [
        'requiere_socio'       => (int) env('ALUMNO_REQUIERE_SOCIO', 1),
        'menor_requiere_tutor' => (int) env('ALUMNO_MENOR_REQUIERE_TUTOR', 1),
    ],

    // Días en los que se dan clases (ISO: lunes = 1). En esta escuela el sábado sí es
    // lectivo; el domingo no. Si otra instalación no da clase en sábado, se quita de aquí.
    'dias_lectivos' => [1, 2, 3, 4, 5, 6],

    // Con qué entra una asignatura al matricularse (calca L9): nivel inicial y una
    // hora de clase (las unidades son de 'minutos_unidad' minutos). Los tipos de
    // 'unidades_uno' (préstamo de instrumento) van siempre de una en una.
    // OJO: los ids de tipo dependen de la instalación; por eso salen del .env.
    'matriculacion' => [
        'nivel_inicial'  => 1,
        'unidades_1hora' => 4,
        'unidades_uno'   => array_filter(explode(',', (string) env('TIPOS_ASIGNATURA_UNIDADES_UNO', ''))),
    ],

    'editorial' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'ejemplares' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'etiquetas' => [
        'tipo' => [ // categoria
            1 => 'Genero',
            2 => 'Tematica',
            3 => 'Periodo',
        ],
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],
    'facturaspago' => [
        'accion' => [
            'generada' => 1,
            'recibo' => 2,
            'remesada' => 3,
            'enviada' => 4,
            'devuelto' => 5,
            'rectificada' => 6,
        ],
        'estado' => [
            'pendiente' => 1,
            'pagado' => 2,
            'anulado' => 3,
        ],
    ],
    'formatos' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'grupodetipos' => [
        'documentos_identificacion' => 1,
    ],

    'instrumentos' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'obras' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
        ],
    ],

    'orden' => [
        'ascendente' => 1,
        'descendente' => 2,
    ],

    'personas' => [
        'excluir_busqueda' => [1],
        'estado' => [
            'todos' => 0,
            'active' => 1,
            'baja' => 2,
            'autoregistrado' => 3,
        ],
        'genero' => [
            'hombre' => 1,
            'mujer' => 2,
            'otro' => 3,
        ],
        'ngenero' => [
            1 => 'Hombre',
            2 => 'Mujer',
            3 => 'Otro',
        ],
        'edad_minima' => env('EDAD_MINIMA_PERSONAS', 4),

    ],

    'K_PERSONAS_GENERO_HOMBRE' => 1,
    'K_PERSONAS_GENERO_MUJER' => 2,
    'K_PERSONAS_GENERO_OTRO' => 3,
    'K_PERSONAS_GENERO_NODEFINIDO' => 4,

    'registroemail' => [
        'estado' => [
            'pendiente' => 1,
            'validado' => 2,
            'caducado' => 3,
            'cancelado' => 4,
        ],
    ],

    'superadmin' => [
        'nombre_rol' => 'super-admin',
        'email' => env('SUPERADMIN_EMAIL', 'admin@ejemplo.com'),
        'login' => env('SUPERADMIN_LOGIN', 'superadmin'),
        'password' => env('SUPERADMIN_PASSWORD', 'Aa123456'),
    ],

    'tipos' => [
        'estado' => [
            'active' => 1,
            'baja' => 2,
        ],
    ],

    'usuario' => [
        'estado' => [
            'active' => 1,
            'inactive' => 2,
            'autoregistrado' => 3,
        ],
    ],

    'K_TIPOS_TIPO_0' => 0,
    'K_TIPO_GRUPOTIPOS' => 0,

    'K_MOSTRARMASFILTRO_NO' => 0,
    'K_MOSTRARMASFILTRO_SI' => 1,

    'K_NOMBRE_COMPLETO' => 0,
    'K_NOMBRE_INICIALES' => 1,

    'K_TIPO_DOCUMENTOID' => 1,
    'K_TIPO_ENTIDADESBANCA' => 7,
    'K_TIPO_FORMADEPAGO' => 152,

    'K_TIPO_DIASSEMANA' => 173,
    'K_TIPO_DIASEMANA_LUNES' => 174,
    'K_TIPO_DIASEMANA_DOMINGO' => 180,

    'K_TIPO_FOTOS' => 181,
    'K_TIPO_FOTO_FICHA' => 182,
    'K_TIPO_FOTO_AVATAR' => 183,

    'K_FOTOS_ESTADO_ACTIVE' => 1,
    'K_FOTOS_ESTADO_BAJA' => 2,

    'K_DOCUMENTOS_ESTADO_ACTIVE' => 1,
    'K_DOCUMENTOS_ESTADO_BAJA' => 2,

    'K_EMPRESAS_ESTADO_TODOS' => 0,
    'K_EMPRESAS_ESTADO_ACTIVE' => 1,
    'K_EMPRESAS_ESTADO_BAJA' => 2,

];
