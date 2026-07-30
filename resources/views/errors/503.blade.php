{{-- Pantalla de mantenimiento: la que se ve con la aplicación parada (php artisan down). --}}
<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
        <x-authentication-card-logo />

        <div class="mt-8 w-full max-w-md text-center">
            <p class="text-7xl font-semibold text-gray-300 dark:text-gray-600">503</p>

            <h1 class="mt-2 text-xl font-medium text-gray-900 dark:text-gray-100">
                {{ __('Volvemos enseguida') }}
            </h1>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Estamos haciendo tareas de mantenimiento. Prueba otra vez dentro de unos minutos.') }}
            </p>

            {{-- Sin botón de vuelta a propósito: con la aplicación parada, cualquier enlace
                 devolvería otro 503. --}}
        </div>
    </div>
</x-guest-layout>
