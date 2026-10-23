@props(['titulo' => '', 'botonera' => null])

<div class="max-w-screen-2xl mx-auto">
    {{-- bg-gray-800 dark:bg-white --}}
    {{-- Título y botonera en el flujo normal (no absolutos): si en el móvil los botones
         no caben en una línea, bajan a la siguiente y empujan la rejilla en vez de
         montarse sobre su cabecera. min-h-12 guarda el hueco de siempre aunque no haya
         botonera. --}}
    <div class="min-h-12 flex flex-wrap items-start justify-between gap-x-4">
        <div>
            <span class="text-gray-800 text-lg font-bold sm:text-2xl  dark:text-gray-100">
                {{ $titulo }}
            </span>
            @isset($subtitulo)
                <x-dosl.tabla-descripcion>
                    {{ $subtitulo }}
                </x-dosl.tabla-descripcion>
            @endisset

        </div>
        <div class="ml-auto pt-2 pb-1.5 flex flex-wrap items-center justify-end gap-1">
            {{ $botonera }}
        </div>
    </div>
    <div class="shadow-sm border rounded-lg overflow-x-auto">
        {{ $slot }}
    </div>
</div>
