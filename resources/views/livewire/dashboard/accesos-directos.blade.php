<div>
    @if ($this->accesos->count())
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                    {{ __('Accesos directos') }}
                </h2>
                <button type="button" wire:click="toggleOrdenar"
                    class="flex items-center gap-1 text-sm font-medium {{ $ordenando ? 'text-green-600 dark:text-green-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    <i class="fa-solid {{ $ordenando ? 'fa-check' : 'fa-arrows-up-down-left-right' }}"></i>
                    {{ $ordenando ? __('Hecho') : __('Ordenar') }}
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach ($this->accesos as $a)
                    @if ($ordenando)
                        <div draggable="true" wire:key="acc-{{ $a->id }}"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $a->id }}')"
                            x-on:dragover.prevent
                            x-on:drop.prevent="$wire.moverAntesDe($event.dataTransfer.getData('text/plain'), {{ $a->id }})"
                            class="cursor-move flex flex-col items-center justify-center gap-2 p-4 rounded-lg bg-white dark:bg-gray-800 shadow border-2 border-dashed border-blue-300 dark:border-blue-700 text-center">
                            <i class="{{ $a->icono ?? 'fa-regular fa-star' }} text-2xl text-blue-600 dark:text-blue-400"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $a->nombre }}</span>
                        </div>
                    @else
                        <a href="{{ $a->url }}" wire:navigate wire:key="acc-{{ $a->id }}"
                            class="flex flex-col items-center justify-center gap-2 p-4 rounded-lg bg-white dark:bg-gray-800 shadow hover:shadow-lg border border-gray-100 dark:border-gray-700 text-center transition">
                            <i class="{{ $a->icono ?? 'fa-regular fa-star' }} text-2xl text-blue-600 dark:text-blue-400"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $a->nombre }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            @if ($ordenando)
                <div x-on:dragover.prevent
                    x-on:drop.prevent="$wire.eliminar($event.dataTransfer.getData('text/plain'))"
                    class="mt-4 flex items-center justify-center gap-2 p-6 rounded-lg border-2 border-dashed border-red-400 dark:border-red-600 text-red-600 dark:text-red-400">
                    <i class="fa-solid fa-trash text-xl"></i>
                    {{ __('Suelta aquí un acceso para eliminarlo') }}
                </div>
            @endif
        </div>
    @endif
</div>
