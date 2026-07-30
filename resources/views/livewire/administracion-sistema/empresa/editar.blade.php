<x-botonera-page>
    <x-slot name="title">
        {{ __('Datos de empresa') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Datos fiscales y bancarios de la organización') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-guardar" id="btn-guardar-empresa" wire:click="guardar"
            title="{{ __('Guardar') }}">
            <i class="fa-solid fa-floppy-disk"> </i>{{ __('Guardar') }}
        </x-button>
    </x-slot>

    <x-slot name="content">
        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            @if (session('mensaje'))
                <div class="mb-4 rounded-lg bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:border-green-700 dark:text-green-200">
                    {{ session('mensaje') }}
                </div>
            @endif

            @include('livewire.administracion-sistema.empresa.parciales.formulario')

            @if ($formulario->empresa?->exists)
                <div class="mt-4 w-2/5">
                    <x-label :value="__('Estado')" />
                    <x-input class="block mt-1 w-full bg-gray-100 dark:bg-gray-700" type="text"
                        :value="$formulario->empresa?->estado?->descripcion" readonly tabindex="-1" />
                </div>

                <div class="mt-8">
                    @livewire('administracion-sistema.empresa.cuentas',
                        ['empresaId' => $formulario->empresa->id],
                        key('cuentas-' . $formulario->empresa->id))
                </div>
            @endif
        </div>
    </x-slot>
</x-botonera-page>
