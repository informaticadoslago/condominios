<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Horario') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-8 sm:p-12">
                <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Horario') }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100 mayusculas">
                    {{ $horario?->nombre }}
                </h1>
            </div>
        </div>
    </div>
</x-app-layout>
