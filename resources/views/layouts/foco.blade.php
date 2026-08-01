<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('storage/images/logo/logo-circulo-blanco.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @fluxAppearance
</head>

{{-- Sin sidebar ni cabecera a propósito: pantallas de "foco" (p.ej. el asiento
     contable) donde la única salida deliberada es Guardar o Cancelar dentro
     del propio formulario, no un enlace del menú. --}}
<body class="font-sans min-h-screen antialiased bg-white dark:bg-zinc-800">
    {{ $slot }}

    @stack('modals')

    @livewireScripts
    @fluxScripts
</body>

</html>
