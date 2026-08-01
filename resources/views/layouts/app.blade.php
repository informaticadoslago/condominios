<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('storage/images/logo/logo-circulo-blanco.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
    @fluxAppearance
</head>

<body class="font-sans min-h-screen antialiased bg-white dark:bg-zinc-800"
    data-tab-style="{{ config('doslago.tab_style') ? '1' : '0' }}">

    {{-- ===== SIDEBAR ===== --}}
    {{-- @include('layouts.menu_flux_sidebar') --}}
    @php
        $comunidadActual = session('comunidad_actual_id')
            ? \App\Models\Comunidad::find(session('comunidad_actual_id'))
            : null;

        if ($comunidadActual) {
            $menuLateral = config('menu_comunidad');
        } else {
            $menuLateral = config('sidebar');
            $comunidadesAccesibles = auth()->user()->comunidadesAccesibles();

            if ($comunidadesAccesibles->count()) {
                // Se inserta en la posición 1: justo debajo del header "Menú
                // principal" (posición 0 en config/sidebar.php), no por delante.
                array_splice($menuLateral['content'], 1, 0, [
                    [
                        'type'  => 'nav',
                        'items' => [
                            [
                                'type'  => 'group',
                                'icon'  => 'fa-solid fa-city',
                                'label' => trans_key('menu.Comunidades'),
                                'items' => $comunidadesAccesibles->map(fn ($c) => [
                                    'icon'  => 'fa-solid fa-city',
                                    'label' => $c->nombre,
                                    'href'  => route('comunidad.entrar', $c),
                                ])->all(),
                            ],
                        ],
                    ],
                ]);
            }
        }
    @endphp
    <x-dosl.menu-sidebar :menu="$menuLateral" />
    {{-- ===== HEADER SUPERIOR ===== --}}
    <flux:header class="block! bg-white lg:bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        {{-- Menú móvil --}}
        @include('layouts.menu_movil')
        {{-- Menú superior escritorio --}}
        @include('layouts.menu_superior_escritorio')
    </flux:header>

    {{-- ===== CONTENIDO PRINCIPAL ===== --}}
    <flux:main>
        {{ $slot }}
    </flux:main>

    @canImpersonate
        @livewire('impersonar')
    @endCanImpersonate

    @can('global-configuracion')
        @livewire('configuracion')
    @endcan

    @stack('modals')

    @livewireScripts
    {{-- Sin <tallstackui:script />: en v3 inyecta tambien su CSS (un segundo build
         de Tailwind que rompe estilos propios, p.ej. los dropdowns de Jetstream).
         Solo usamos botones estaticos de TallStackUI, que no necesitan su JS. --}}
    @fluxScripts

    <script>
        // Foco genérico tras abrir un modal o comprobar un documento: hace
        // scroll hasta el campo y lo enfoca, reintentando porque x-trap
        // (al abrir un modal) le roba el foco al primer enfocable (la X).
        // Uso: $this->dispatch('foco-campo', id: 'mi-input');
        document.addEventListener('livewire:init', () => {
            Livewire.on('foco-campo', (e) => {
                const id = Array.isArray(e) ? e[0]?.id : e?.id;
                if (! id) return;
                const focar = (intentos) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.focus({ preventScroll: true });
                        if (document.activeElement === el) return;
                    }
                    if (intentos > 0) setTimeout(() => focar(intentos - 1), 100);
                };
                setTimeout(() => focar(8), 50);
            });
        });

        // Atajo global "+": pulsa el botón "Nuevo" (clase btn-nuevo) de la pantalla
        // actual. Se ignora si se está escribiendo en un campo, con modificador
        // (Ctrl/Cmd/Alt, para no robarle el zoom del navegador) o si el botón no
        // existe (sin permiso) o está disabled (p.ej. filtro sin elegir).
        document.addEventListener('keydown', (e) => {
            if (e.key !== '+' || e.ctrlKey || e.metaKey || e.altKey) return;

            const activo = document.activeElement;
            const escribiendo = activo && (
                activo.tagName === 'INPUT' ||
                activo.tagName === 'TEXTAREA' ||
                activo.tagName === 'SELECT' ||
                activo.isContentEditable
            );
            if (escribiendo) return;

            const boton = document.querySelector('.btn-nuevo');
            if (boton && !boton.disabled) {
                e.preventDefault();
                boton.click();
            }
        });
    </script>

</body>

</html>
