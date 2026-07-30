{{-- Pantalla de «sin permiso». Laravel la busca sola ante un abort(403) o una
     AuthorizationException (la que lanzan los permisos): no hay que registrar nada,
     basta con que se llame 403 y viva en errors/. --}}
<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
        <x-authentication-card-logo />

        <div class="mt-8 w-full max-w-md text-center">
            <p class="text-7xl font-semibold text-gray-300 dark:text-gray-600">403</p>

            <h1 class="mt-2 text-xl font-medium text-gray-900 dark:text-gray-100">
                {{ __('No tienes permiso para ver esto') }}
            </h1>

            {{-- El mensaje de la excepción es el que dice qué permiso falta; cuando viene
                 vacío (un abort(403) pelado), se explica en general. --}}
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ $exception->getMessage() ?: __('Tu usuario no tiene permiso para entrar en esta página.') }}
            </p>

            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                class="mt-8 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                {{ auth()->check() ? __('Volver al inicio') : __('Identificarse') }}
            </a>
        </div>
    </div>
</x-guest-layout>
