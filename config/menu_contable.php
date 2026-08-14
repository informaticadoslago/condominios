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
                ['icon' => 'fa-regular fa-house', 'label' => trans_key('menu.dashboard'), 'route' => 'dashboard-contable'],
                ['icon' => 'fa-solid fa-book-open', 'label' => trans_key('menu.Asientos contables'), 'route' => 'asientos-contables.index'],
                ['icon' => 'fa-solid fa-list-ol', 'label' => trans_key('menu.Libro mayor'), 'route' => 'mayor-contable.index'],
                ['icon' => 'fa-solid fa-scale-balanced', 'label' => trans_key('menu.Sumas y saldos'), 'route' => 'sumas-y-saldos.index'],
                ['icon' => 'fa-solid fa-table-columns', 'label' => trans_key('menu.Movimientos'), 'route' => 'movimientos-contables.index'],
                ['type' => 'spacer'],
                ['icon' => 'fa-solid fa-book', 'label' => trans_key('menu.Cuentas contables'), 'route' => 'plan-de-cuentas.index'],
                ['icon' => 'fa-solid fa-calendar', 'label' => trans_key('menu.Ejercicios contables'), 'route' => 'ejercicios-contables.index'],
                ['icon' => 'fa-solid fa-diagram-project', 'label' => trans_key('menu.Proyectos'), 'route' => 'proyectos-contables.index'],
            ],
        ],
        ['type' => 'spacer'],
        [
            'type'  => 'nav',
            'items' => [
                ['icon' => 'fa-solid fa-right-from-bracket', 'label' => trans_key('menu.Cerrar empresa contable'), 'route' => 'empresa-contable.salir'],
            ],
        ],
    ],
];
