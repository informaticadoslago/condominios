<div x-data="{ sobreCubo: false }">
    @if ($this->fichas->count() || $this->accesos->count())
        <div class="mt-6">
            <div class="flex items-center justify-end mb-3">
                <button type="button" wire:click="toggleOrdenar"
                    class="flex items-center gap-1 text-sm font-medium {{ $ordenando ? 'text-green-600 dark:text-green-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    <i class="fa-solid {{ $ordenando ? 'fa-check' : 'fa-arrows-up-down-left-right' }}"></i>
                    {{ $ordenando ? __('Hecho') : __('Ordenar') }}
                </button>
            </div>

            {{-- Fichas de entrada: comunidades en azul, sociedades en verde, empresas
                 contables en ámbar. Se arrastran siempre (al cubo para quitarlas, sobre
                 otra para colocarla antes), sin necesidad de entrar en el modo Ordenar. --}}
            @if ($this->fichas->count())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    @foreach ($this->fichas as $f)
                        @php
                            [$etiquetaFicha, $colorFicha, $colorIcono] = match ($f->tipo) {
                                \App\Models\AccesoDirecto::TIPO_COMUNIDAD => [
                                    __('Comunidad'),
                                    'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800 hover:border-blue-400',
                                    'text-blue-600 dark:text-blue-300',
                                ],
                                \App\Models\AccesoDirecto::TIPO_SOCIEDAD => [
                                    __('Sociedad'),
                                    'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800 hover:border-green-400',
                                    'text-green-600 dark:text-green-300',
                                ],
                                default => [
                                    __('Contabilidad'),
                                    'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-800 hover:border-amber-400',
                                    'text-amber-600 dark:text-amber-300',
                                ],
                            };
                        @endphp
                        <a href="{{ $f->url }}" draggable="true" wire:key="ficha-{{ $f->id }}"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $f->id }}')"
                            x-on:dragover.prevent
                            x-on:drop.prevent="$wire.moverAntesDe($event.dataTransfer.getData('text/plain'), {{ $f->id }})"
                            class="flex items-center gap-4 p-5 rounded-lg border-2 shadow-sm hover:shadow-lg transition {{ $colorFicha }}">
                            <i class="{{ $f->icono ?? 'fa-solid fa-city' }} text-3xl {{ $colorIcono }}"></i>
                            <span class="flex flex-col">
                                {{-- El color solo no dice de qué es la ficha: el titulillo sí. --}}
                                <span class="text-xs font-medium uppercase tracking-wide {{ $colorIcono }}">
                                    {{ $etiquetaFicha }}
                                </span>
                                <span class="text-base font-semibold text-gray-800 dark:text-gray-100 mayusculas">
                                    {{ $f->nombre }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($this->accesos->count())
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    {{ __('Accesos directos') }}
                </h2>

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
                            <a href="{{ $a->url }}" wire:navigate draggable="true" wire:key="acc-{{ $a->id }}"
                                x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $a->id }}')"
                                class="flex flex-col items-center justify-center gap-2 p-4 rounded-lg bg-white dark:bg-gray-800 shadow hover:shadow-lg border border-gray-100 dark:border-gray-700 text-center transition">
                                <i class="{{ $a->icono ?? 'fa-regular fa-star' }} text-2xl text-blue-600 dark:text-blue-400"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ $a->nombre }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- El cubo está siempre, discreto; solo se remarca cuando le pasas algo
                 por encima. Suelta aquí cualquier recuadro y desaparece del inicio. --}}
            <div x-on:dragover.prevent="sobreCubo = true"
                x-on:dragleave="sobreCubo = false"
                x-on:drop.prevent="sobreCubo = false; $wire.eliminar($event.dataTransfer.getData('text/plain'))"
                x-bind:class="sobreCubo
                    ? 'border-red-500 dark:border-red-500 text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 scale-105'
                    : 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500'"
                class="mt-6 flex items-center justify-center gap-2 p-4 rounded-lg border-2 border-dashed text-sm transition-all">
                <i class="fa-solid fa-trash"></i>
                {{ __('Suelta aquí un recuadro para quitarlo del inicio') }}
            </div>
        </div>
    @endif
</div>
