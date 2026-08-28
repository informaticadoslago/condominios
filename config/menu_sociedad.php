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
                ['icon' => 'fa-regular fa-house', 'label' => trans_key('menu.dashboard'), 'route' => 'dashboard-sociedad'],
                [
                    'type'  => 'group',
                    'icon'  => 'fa-solid fa-cart-shopping',
                    'label' => trans_key('menu.Compras'),
                    'items' => [
                        ['icon' => 'fa-solid fa-truck', 'label' => trans_key('menu.Proveedores'), 'route' => 'sociedad-proveedores.index'],
                        ['icon' => 'fa-solid fa-file-invoice', 'label' => trans_key('menu.Facturas'), 'route' => 'sociedad-facturas.index'],
                    ],
                ],
            ],
        ],
        ['type' => 'spacer'],
        [
            'type'  => 'nav',
            'items' => [
                ['icon' => 'fa-solid fa-right-from-bracket', 'label' => trans_key('menu.Cerrar sociedad'), 'route' => 'sociedad.salir'],
            ],
        ],
    ],
];
