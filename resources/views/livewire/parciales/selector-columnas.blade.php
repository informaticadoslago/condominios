{{--
    Botón + panel de checkboxes para elegir qué columnas se ven. Solo aparece si la
    Lista declara columnasDisponibles(). El panel se abre y cierra en el navegador
    (Alpine); marcar una columna sí va al servidor, que la recuerda por usuario.
--}}
@php($columnasDisponibles = $this->columnasDisponibles())

@if (count($columnasDisponibles))
    <div x-data="{ abierto: false }" class="relative inline-block text-left">
        <x-secondary-button type="button" x-on:click="abierto = ! abierto" title="{{ __('Columnas') }}">
            <i class="fa-solid fa-table-columns mr-1"></i>{{ __('Columnas') }}
        </x-secondary-button>

        <div x-show="abierto" x-on:click.outside="abierto = false" x-cloak
            class="absolute right-0 z-20 mt-1 w-60 rounded-md border bg-white p-3 shadow-lg dark:bg-gray-800">
            <div class="flex justify-end border-b pb-1 mb-1">
                <button type="button" wire:click="marcarTodasColumnas"
                    class="text-sm text-blue-600 hover:underline">{{ __('Marcar todo') }}</button>
            </div>
            @foreach ($columnasDisponibles as $clave => $etiqueta)
                <label class="flex items-center gap-2 py-1 cursor-pointer">
                    <input type="checkbox" wire:model.live="columnas" value="{{ $clave }}" />
                    <span>{{ $etiqueta }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endif
