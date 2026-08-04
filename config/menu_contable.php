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
                ['type' => 'spacer'],
                ['icon' => 'fa-solid fa-book', 'label' => trans_key('menu.Cuentas contables'), 'route' => 'plan-de-cuentas.index'],
                ['icon' => 'fa-solid fa-calendar', 'label' => trans_key('menu.Ejercicios contables'), 'route' => 'ejercicios-contables.index'],
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
