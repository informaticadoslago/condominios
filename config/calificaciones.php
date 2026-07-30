<?php

return [
    /*
     * Sistema de calificación con el que trabaja esta instalación. Es solo el valor
     * por defecto de los cursos nuevos: cada curso guarda el suyo en
     * cursos.sistema_calificacion y es él quien manda.
     *
     *   evaluacion → los criterios son los de la evaluación (en Bembrive, los meses)
     *                y valen para todas las asignaturas; la nota final la pone el
     *                profesor, y además se califican el trabajo y la actitud.
     *   asignatura → los criterios son los del curso más los de cada asignatura (así
     *                en Chapela); la nota final sale de los pesos de los criterios.
     */
    'sistema' => env('SISTEMA_CALIFICACION', 'evaluacion'),

    /*
     * Con qué nota se califica cada criterio (en esta casa, de 1 a 10).
     */
    'criterio' => [
        'min' => (int) env('VALORACION_CRITERIO_MIN', 1),
        'max' => (int) env('VALORACION_CRITERIO_MAX', 5),

        /* La que se imprime al pie del boletín. El índice es la nota: el 0 es "sin calificar". */
        'leyenda' => [
            'SC = Sen calificar', 'Moi mal', 'Mal', 'Regular', 'Ben', 'Moi ben',
        ],

        /*
         * La nota final es la media de los criterios ponderada por sus pesos, así que
         * sale en la misma escala que ellos. Con esto activado se reescala a otra base
         * (por ejemplo, criterios de 1 a 5 y nota final sobre 100).
         */
        'reescalar' => (bool) env('VALORACION_CRITERIO_100', false),
        'base' => (int) env('VALORACION_BASE_100', 100),
    ],

    /*
     * Trabajo y actitud, que solo se califican en el sistema por evaluación. La leyenda
     * es la que se imprime en el boletín; el índice es la nota (1 = la primera).
     */
    'trabajo_actitud' => [
        'min' => 1,
        'max' => 5,
        'leyenda' => ['Moi mal', 'Mal', 'Regular', 'Ben', 'Moi ben'],
    ],

    'boletines' => [
        /* Los PDF se archivan en el disco 'boletines' (ver config/filesystems.php), por id
           de curso/evaluación/matrícula; el camino no se guarda, se deduce de la fila. */

        /* El logo que se pinta en la cabecera del boletín. Lo deja ahí 'app:install' al
           copiar los logos de storage/doslago/logo a public (ver AppInstaller). */
        'logo' => public_path('storage/images/logo/dosLago.png'),
    ],
];
