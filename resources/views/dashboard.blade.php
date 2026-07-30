<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-8 sm:p-12 text-center">
                <img src="{{ asset('storage/images/logo/logo-circulo.png') }}" alt="dosLago"
                    class="mx-auto h-24 w-auto mb-6">

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100">
                    {{ __('Bienvenido a Informática dosLago') }}
                </h1>

                <p class="mt-4 max-w-2xl mx-auto text-gray-600 dark:text-gray-300 leading-relaxed">
                    En <strong>Informática dosLago</strong> creemos que la mejor tecnología es la que se nota poco y ayuda mucho.
                    Por eso escuchamos a cada cliente, entendemos su día a día y nos comprometemos a encontrar la
                    <strong>mejor solución a sus necesidades informáticas</strong>: práctica, fiable y a su medida.
                    Estamos a tu lado en cada paso, porque tu proyecto es también el nuestro.
                </p>
            </div>

            @livewire('dashboard.accesos-directos')
        </div>
    </div>
</x-app-layout>
