{{--
    Botón + panel de checkboxes para elegir qué columnas se ven. Solo aparece si la
    Lista declara columnasDisponibles(). El panel se abre y cierra en el navegador
    (Alpine); marcar una columna sí va al servidor, que la recuerda por usuario.
    "Mover" (si la lista lo permite: ver permiteReordenarColumnas) abre aparte una
    ventana solo con el arrastre, porque este panel estrecho no da sitio para soltar
    con fiabilidad. Las listas contables no lo ofrecen: Debe/Haber/Saldo van siempre
    al final y su tfoot de totales depende de esa posición fija.
--}}
@php($columnasDisponibles = $this->columnasDisponibles())

@if (count($columnasDisponibles))
    <div x-data="{ abierto: false }" class="relative inline-block text-left">
        <x-secondary-button type="button" x-on:click="abierto = ! abierto" title="{{ __('Columnas') }}">
            <i class="fa-solid fa-table-columns mr-1"></i>{{ __('Columnas') }}
        </x-secondary-button>

        <div x-show="abierto" x-on:click.outside="abierto = false" x-cloak
            class="absolute right-0 z-20 mt-1 w-60 rounded-md border bg-white p-3 shadow-lg dark:bg-gray-800">
            <div class="flex justify-between items-center border-b pb-1 mb-1">
                @if ($this->permiteReordenarColumnas())
                    <button type="button" x-on:click="abierto = false" wire:click="$set('columnasAbierto', true)"
                        class="text-sm text-blue-600 hover:underline">{{ __('Mover') }}</button>
                @endif
                <button type="button" wire:click="marcarTodasColumnas"
                    class="text-sm text-blue-600 hover:underline ml-auto">{{ __('Marcar todo') }}</button>
            </div>
            @foreach ($columnasDisponibles as $clave => $etiqueta)
                <label class="flex items-center gap-2 py-1 cursor-pointer">
                    <input type="checkbox" wire:model.live="columnas" value="{{ $clave }}" />
                    <span>{{ $etiqueta }}</span>
                </label>
            @endforeach
        </div>
    </div>

    @if ($this->permiteReordenarColumnas())
        <x-dosl.dialog-modal wire:model.live="columnasAbierto" maxWidth="sm">
            <x-slot name="title">
                {{ __('Mover columnas') }}
            </x-slot>

            <x-slot name="content">
                <div class="flex justify-end border-b pb-2 mb-2">
                    <button type="button" wire:click="resetOrdenColumnas"
                        class="text-sm text-blue-600 hover:underline">{{ __('Reset') }}</button>
                </div>
                {{-- sobreClave marca sobre qué fila está el arrastre, para pintar la línea
                     de esa fila hacia arriba (ahí es donde se soltaría, como hace
                     moverColumnaAntesDe). --}}
                <div x-data="{ sobreClave: null }">
                    @foreach ($this->columnas as $clave)
                        <div wire:key="col-{{ $clave }}" draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $clave }}')"
                            x-on:dragend="sobreClave = null"
                            x-on:dragover.prevent="sobreClave = '{{ $clave }}'"
                            x-on:dragleave="if (sobreClave === '{{ $clave }}') sobreClave = null"
                            x-on:drop.prevent="sobreClave = null; $wire.moverColumnaAntesDe($event.dataTransfer.getData('text/plain'), '{{ $clave }}')"
                            :class="sobreClave === '{{ $clave }}' ? 'border-blue-500' : 'border-transparent'"
                            class="flex items-center gap-2 py-1 cursor-move border-t-2">
                            <i class="fa-solid fa-grip-lines text-gray-400"></i>
                            <span>{{ $columnasDisponibles[$clave] ?? $clave }}</span>
                        </div>
                    @endforeach
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('columnasAbierto', false)">
                    {{ __('Cerrar') }}
                </x-secondary-button>
            </x-slot>
        </x-dosl.dialog-modal>
    @endif
@endif
