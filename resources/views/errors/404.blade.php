{{-- Pantalla de «no existe». Laravel la busca sola ante un abort(404) o un modelo que no
     aparece: no hay que registrar nada, basta con que se llame 404 y viva en errors/. --}}
<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
        <x-authentication-card-logo />

        <div class="mt-8 w-full max-w-md text-center">
            <p class="text-7xl font-semibold text-gray-300 dark:text-gray-600">404</p>

            <h1 class="mt-2 text-xl font-medium text-gray-900 dark:text-gray-100">
                {{ __('Aquí no hay nada') }}
            </h1>

            {{-- Aquí no se enseña el mensaje de la excepción a propósito: en un 404 suele traer
                 el modelo o la ruta que se buscaba, y eso es dar pistas de más. --}}
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ __('La dirección no existe, o lo que buscabas ya no está.') }}
            </p>

            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                class="mt-8 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                {{ auth()->check() ? __('Volver al inicio') : __('Identificarse') }}
            </a>
        </div>
    </div>
</x-guest-layout>
