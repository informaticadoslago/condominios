{{-- Una fila de la lista de pruebas. $contenedora es la prueba de la que $item es parte, o null
     si $item es una prueba suelta (con partes o sin ellas). --}}
@php
    $esParte    = $contenedora !== null;
    $evaluacion = ($contenedora ?? $item)->evaluacion;
    // Lo que suman sus partes activas: tiene que ser 100; si no, en rojo.
    $sumaPartes = $item->con_partes ? (float) $item->partes->where('estado_id', \App\Models\Prueba::ESTADO_ACTIVO)->sum('peso') : null;
@endphp
<tr wire:key="{{ $item->id }}" id="fila-prueba-{{ $item->id }}"
    @if ($arrastrar)
        draggable="true"
        x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item->id }}')"
        x-on:dragend="sobre = null"
        x-on:dragover.prevent="encima($event, {{ $item->id }}, {{ $item->con_partes ? 'true' : 'false' }})"
        x-on:dragleave="if (sobre === {{ $item->id }}) sobre = null"
        x-on:drop.prevent="sobre = null; $wire.soltar($event.dataTransfer.getData('text/plain'), {{ $item->id }}, zona)"
        :style="marca({{ $item->id }})"
    @endif>
    <td class="pl-6 py-4 {{ $arrastrar ? 'cursor-move text-gray-400' : 'text-gray-300' }}">
        <i class="fa-solid fa-grip-lines"></i>
    </td>
    @foreach ($this->columnas as $clave)
        @switch($clave)
            @case('nombre')
                <td class="px-6 py-4">
                    @if ($esParte)
                        {{-- La parte, sangrada debajo de su prueba. --}}
                        <span style="padding-left: 1.5rem"><i class="fa-solid fa-arrow-turn-up fa-rotate-90 text-gray-400 mr-2"></i>{{ $item->nombre }}</span>
                    @else
                        <span @class(['font-semibold' => $item->con_partes])>{{ $item->nombre }}</span>
                    @endif
                    @if ($item->con_partes)
                        <span class="ml-2 text-xs" id="suma-partes-{{ $item->id }}">
                            {{ __('partes') }}:
                            <span @class(['font-semibold', 'text-red-600' => abs($sumaPartes - 100) > 0.001])>{{ \App\Models\Prueba::numero($sumaPartes) }} %</span>
                        </span>
                    @endif
                </td>
                @break
            @case('fecha')
                <td class="px-6 py-4">{{ $item->fecha->format('d/m/Y') }}</td>
                @break
            @case('evaluacion')
                <td class="px-6 py-4">{{ $evaluacion->nombre.' ('.$evaluacion->curso->nombre.')' }}</td>
                @break
            @case('puntuacion_maxima')
                <td class="px-6 py-4 text-right">{{ match (true) {
                    $item->con_partes  => '',
                    $item->esNumerica() => \App\Models\Prueba::numero($item->puntuacion_maxima),
                    default            => \App\Models\Prueba::tiposCalificacion()[$item->tipo_calificacion] ?? '',
                } }}</td>
                @break
            @case('peso')
                {{-- El de una parte es dentro de su prueba, no en la evaluación. --}}
                <td class="px-6 py-4 text-right {{ $esParte ? 'text-gray-500' : '' }}"
                    @if ($esParte) title="{{ __('Peso dentro de :prueba', ['prueba' => $contenedora->nombre]) }}" @endif>
                    {{ \App\Models\Prueba::numero($item->peso).' %' }}
                </td>
                @break
            @case('notas')
                <td class="px-6 py-4 text-right">{{ $item->con_partes ? '' : $item->notas_count }}</td>
                @break
            @case('estado')
                <td class="px-6 py-4">
                    <span class="mayusculas">{{ $item->estado?->descripcion }}</span>
                    @if ($item->historial_estados_count > 1)
                        <button type="button" wire:click="verHistorial({{ $item->id }})"
                            class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </button>
                    @endif
                </td>
                @break
        @endswitch
    @endforeach
    <td class="px-4 whitespace-nowrap">
        {{-- De una prueba con partes, el resumen con la nota final; de las demás, sus notas. --}}
        <a href="{{ route($item->con_partes ? 'asignaturas.pruebas.partes' : 'asignaturas.pruebas.notas', [$asignaturaId, $item->id]) }}" wire:navigate
            id="btn-notas-prueba-{{ $item->id }}" title="{{ $item->con_partes ? __('Notas de las partes y nota final') : __('Notas') }}"
            class="inline-flex items-center px-4 py-2 mr-1 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
            <i class="fa-solid {{ $item->con_partes ? 'fa-table-list' : 'fa-pen-to-square' }}"> </i>
        </a>
        <x-button type="button" class="btn-editar" id="btn-editar-prueba-{{ $item->id }}"
            wire:click="$dispatch('prueba-editar', {id: {{ $item->id }}})"
            title="{{ __('Modificar') }}">
            <i class="fa-solid fa-pen"> </i>
        </x-button>
        @if ($item->estado_id == \App\Models\Prueba::ESTADO_ACTIVO)
            {{-- Clic normal: baja por estado. Mayús+clic: borrado físico (irreversible),
                 que solo sale adelante si ningún otro registro lo usa. --}}
            <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                x-on:click="$event.shiftKey ? $wire.confirmarEliminarFisico({{ $item->id }}) : $wire.confirmarBaja({{ $item->id }})"
                title="{{ __('Dar de baja (Mayús+clic: borrar definitivamente)') }}">
                <i class="fa-solid fa-ban"> </i>
            </x-button>
        @else
            <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                x-on:click="$event.shiftKey ? $wire.confirmarEliminarFisico({{ $item->id }}) : $wire.confirmarReactivar({{ $item->id }})"
                title="{{ __('Reactivar (Mayús+clic: borrar definitivamente)') }}">
                <i class="fa-solid fa-arrow-rotate-left"> </i>
            </x-button>
        @endif
        @if ($item->con_partes)
            <x-button type="button" class="btn btn-nuevo ml-1" id="btn-nueva-parte-{{ $item->id }}"
                wire:click="$dispatch('abrir-crear-prueba', {evaluacionId: {{ $item->evaluacion_id }}, pruebaId: {{ $item->id }}})"
                title="{{ __('Nueva parte') }}">
                <i class="fa-solid fa-plus"> </i>
            </x-button>
        @endif
    </td>
</tr>
