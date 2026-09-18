<?php

return [
    'class'   => 'shadow-lg',
    'content' => [
        [
            'type'  => 'header',
            'name'  => 'menu.main',
            'brand' => [
                'href'      => '#',
                'logo'      => '',
                'logo_dark' => '',
            ],
        ],
        [
            'type'  => 'nav',
            'items' => [
                ['icon' => 'fa-regular fa-house', 'label' => trans_key('menu.dashboard'), 'route' => 'dashboard-horario'],
                ['icon' => 'fa-solid fa-book', 'label' => trans_key('menu.Asignaturas'), 'route' => 'asignaturas.index'],
                ['icon' => 'fa-solid fa-calendar-week', 'label' => trans_key('menu.Días y sesiones'), 'route' => 'dias-sesiones.edit'],
                ['icon' => 'fa-solid fa-table-cells', 'label' => trans_key('menu.Horario semanal'), 'route' => 'rejilla.edit'],
            ],
        ],
        ['type' => 'spacer'],
        [
            'type'  => 'nav',
            'items' => [
                ['icon' => 'fa-solid fa-right-from-bracket', 'label' => trans_key('menu.Cerrar horario'), 'route' => 'horario.salir'],
            ],
        ],
    ],
];
