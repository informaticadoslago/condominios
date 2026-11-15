<x-botonera-page>
    <x-slot name="title">
        {{ __('Pruebas evaluables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ $asignatura->nombreConModulo() }}
    </x-slot>
    <x-slot name="botonera">
        {{-- La cabecera apila en vertical lo que recibe: los dos botones van en una fila propia.
             Sin items-*: una fila flex estira a sus hijos, así que miden lo mismo de alto. --}}
        <div class="flex flex-row gap-2">
            {{-- Mismas clases de caja que x-button, para que mida lo mismo que «Nuevo». --}}
            <a href="{{ route('asignaturas.index') }}" wire:navigate id="btn-volver-asignaturas"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest btn btn-cerrar"
                title="{{ __('Volver a asignaturas') }}">
                <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('Asignaturas') }}
            </a>
            <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-prueba"
                wire:click="$dispatch('abrir-crear-prueba', {evaluacionId: {{ (int) ($filtros['evaluacion_id'] ?? 0) }}})" title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </x-button>
        </div>
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
                {{-- Las notas de la evaluación elegida en el filtro, en otra pestaña; sin evaluación no hay informe. --}}
                @if ($filtros['evaluacion_id'] ?? null)
                    <x-secondary-button type="button" id="btn-imprimir-notas" title="{{ __('Imprimir las notas de la evaluación') }}"
                        onclick="window.open('{{ route('asignaturas.pruebas.pdf', ['asignatura' => $asignatura->id, 'evaluacion_id' => $filtros['evaluacion_id']]) }}', '_blank')">
                        <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir') }}
                    </x-secondary-button>
                @endif
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre"])
            </div>
            @include('livewire.parciales.filtros')
            {{-- Con una evaluación elegida: lo que suman los pesos de sus pruebas activas. No tiene
                 que llegar a 100 (pueden faltar pruebas por crear); si pasa, en rojo. --}}
            @if ($sumaPesos !== null)
                <div class="py-2 px-6 text-sm border-b" id="suma-pesos">
                    {{ __('Suma de pesos de la evaluación') }}:
                    <span @class(['font-semibold', 'text-red-600' => $sumaPesos > 100])>{{ \App\Models\Prueba::numero($sumaPesos) }} %</span>
                </div>
            @endif
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            {{-- Orden manual: clic para volver a él; con él en pantalla, las filas se arrastran. --}}
                            <th class="cursor-pointer py-3 pl-6 w-8" wire:click="ordenar('orden')"
                                title="{{ $arrastrar ? __('Arrastra las filas para cambiar el orden de impresión o para meterlas en una prueba con partes') : __('Ver en el orden de impresión, para poder arrastrar') }}">
                                <i class="fa-solid fa-grip-lines {{ $arrastrar ? '' : 'text-gray-300' }}"></i>
                            </th>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('nombre')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                            {{ __('Nombre') }}
                                            @if ($sort == 'nombre')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('fecha')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha')">
                                            {{ __('Fecha') }}
                                            @if ($sort == 'fecha')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('evaluacion')
                                        <th class="py-3 px-6">{{ __('Evaluación') }}</th>
                                        @break
                                    @case('puntuacion_maxima')
                                        <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('puntuacion_maxima')">
                                            {{ __('Nota sobre') }}
                                            @if ($sort == 'puntuacion_maxima')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('peso')
                                        <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('peso')">
                                            {{ __('Peso') }}
                                            @if ($sort == 'peso')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('notas')
                                        <th class="py-3 px-6 text-right">{{ __('Notas') }}</th>
                                        @break
                                    @case('estado')
                                        <th class="py-3 px-6">{{ __('Estado') }}</th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    {{-- sobre y zona: la fila sobre la que está el arrastre y en qué parte de ella (arriba,
                         abajo o, si es una prueba con partes, en medio), para marcar dónde quedará la
                         arrastrada al soltar: antes, después o dentro. --}}
                    <tbody class="divide-y" x-data="{
                        sobre: null,
                        zona: null,
                        encima(evento, id, admiteDentro) {
                            const caja = evento.currentTarget.getBoundingClientRect();
                            const y = (evento.clientY - caja.top) / caja.height;
                            this.sobre = id;
                            this.zona = admiteDentro
                                ? (y < 0.25 ? 'antes' : (y > 0.75 ? 'despues' : 'dentro'))
                                : (y < 0.5 ? 'antes' : 'despues');
                        },
                        marca(id) {
                            if (this.sobre !== id) return '';
                            if (this.zona === 'dentro') return 'outline: 2px solid #3b82f6; outline-offset: -2px';
                            return (this.zona === 'antes' ? 'border-top' : 'border-bottom') + ': 3px solid #3b82f6';
                        },
                    }">
                        @foreach ($items as $item)
                            @include('livewire.pruebas.fila', ['item' => $item, 'contenedora' => null])
                            @foreach ($item->partes as $parte)
                                @include('livewire.pruebas.fila', ['item' => $parte, 'contenedora' => $item])
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>

        @livewire('pruebas.formulario', ['asignaturaId' => $asignaturaId])
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>
