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
                ['icon' => 'fa-regular fa-house', 'label' => trans_key('menu.dashboard'), 'route' => 'dashboard-comunidad'],
                ['icon' => 'fa-solid fa-file-invoice', 'label' => trans_key('menu.Facturas'), 'route' => 'facturas.index'],
                ['icon' => 'fa-solid fa-door-open', 'label' => trans_key('menu.Inmuebles'), 'route' => 'inmuebles.index'],
                ['icon' => 'fa-solid fa-file-invoice-dollar', 'label' => trans_key('menu.Presupuestos'), 'route' => 'presupuestos.index'],
                ['icon' => 'fa-solid fa-receipt', 'label' => trans_key('menu.Recibos'), 'route' => 'recibos.index'],
                ['icon' => 'fa-solid fa-building-columns', 'label' => trans_key('menu.Remesas'), 'route' => 'remesas.index'],
                ['type' => 'spacer'],
                ['icon' => 'fa-solid fa-people-roof', 'label' => trans_key('menu.Grupos de reparto'), 'route' => 'grupos-de-reparto.index'],
                ['icon' => 'fa-solid fa-diagram-project', 'label' => trans_key('menu.Actividades'), 'route' => 'actividades.index'],
                ['icon' => 'fa-solid fa-user-tie', 'label' => trans_key('menu.Propietarios'), 'route' => 'propietarios.index'],
                ['icon' => 'fa-solid fa-truck', 'label' => trans_key('menu.Proveedores'), 'route' => 'proveedores.index'],
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
