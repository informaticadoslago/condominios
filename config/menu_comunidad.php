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
                ['icon' => 'fa-regular fa-house', 'label' => trans_key('menu.dashboard'), 'route' => 'dashboard'],
                [
                    'type'  => 'group',
                    'icon'  => 'fa-solid fa-building-user',
                    'label' => trans_key('menu.Gestión administrativa'),
                    'items' => [
                        ['icon' => 'fa-solid fa-user-tie', 'label' => trans_key('menu.Propietarios'), 'route' => 'propietarios.index'],
                        ['icon' => 'fa-solid fa-truck', 'label' => trans_key('menu.Proveedores'), 'route' => 'proveedores.index'],
                        ['icon' => 'fa-solid fa-door-open', 'label' => trans_key('menu.Inmuebles'), 'route' => 'inmuebles.index'],
                    ],
                ],
                [
                    'type'  => 'group',
                    'icon'  => 'fa-solid fa-calculator',
                    'label' => trans_key('menu.Gestión contable'),
                    'items' => [
                        ['icon' => 'fa-solid fa-calendar', 'label' => trans_key('menu.Ejercicios contables'), 'route' => 'ejercicios-contables.index'],
                    ],
                ],
            ],
        ],
        ['type' => 'spacer'],
        [
            'type'  => 'nav',
            'items' => [
                ['icon' => 'fa-solid fa-right-from-bracket', 'label' => trans_key('menu.Cerrar comunidad'), 'route' => 'comunidad.salir'],
            ],
        ],
    ],
];
