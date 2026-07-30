@props(['titulo' => '', 'botonera' => null])

<div class="max-w-screen-2xl mx-auto">
    {{-- bg-gray-800 dark:bg-white --}}
    <div class="items-start justify-between md:flex">         
        <div class="relative w-full">
            <div class="absolute left-0">
                <span class="text-gray-800 text-lg font-bold sm:text-2xl  dark:text-gray-100">
                    {{ $titulo }}
                </span>
                @isset($subtitulo)
                    <x-dosl.tabla-descripcion>
                        {{ $subtitulo }}
                    </x-dosl.tabla-descripcion>
                @endisset

            </div>
            <div class="absolute right-0 py-2">
                {{ $botonera }}
            </div>
        </div>

    </div>
    <div class="mt-12 shadow-sm border rounded-lg overflow-x-auto">
        {{ $slot }}
    </div>
</div>
