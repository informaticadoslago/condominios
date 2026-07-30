{{-- Pantalla de «sesión caducada»: sale cuando el token CSRF ya no vale, casi siempre por
     haber dejado la pantalla abierta demasiado tiempo. --}}
<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
        <x-authentication-card-logo />

        <div class="mt-8 w-full max-w-md text-center">
            <p class="text-7xl font-semibold text-gray-300 dark:text-gray-600">419</p>

            <h1 class="mt-2 text-xl font-medium text-gray-900 dark:text-gray-100">
                {{ __('La sesión ha caducado') }}
            </h1>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Has estado demasiado tiempo sin actividad. Identifícate otra vez para seguir.') }}
            </p>

            {{-- Siempre a login: si el token ha caducado, la sesión ya no sirve. --}}
            <a href="{{ route('login') }}"
                class="mt-8 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                {{ __('Identificarse') }}
            </a>
        </div>
    </div>
</x-guest-layout>
