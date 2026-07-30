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
    {{-- <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /> --}}
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
    @fluxAppearance
</head>

<body>
    <!-- Selector de tema en esquina superior derecha -->
    @if (config('flux.appearance.enabled'))
        <div class="absolute top-4 right-4 z-50">
            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="!$flux.dark" icon="moon" variant="subtle"
                aria-label="Toggle dark mode" />
            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="$flux.dark" icon="sun" variant="subtle"
                aria-label="Toggle dark mode" />
        </div>
    @endif

    <!-- Contenido principal -->
    <div class="font-sans text-gray-900 dark:text-gray-100 antialiased">
        {{ $slot }}
    </div>

    @livewireScripts
    @fluxScripts
</body>

</html>
