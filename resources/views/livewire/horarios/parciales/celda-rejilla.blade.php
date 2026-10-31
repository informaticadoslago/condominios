{{-- Lo que se pinta de un slot de la rejilla (recreo, asignatura o hueco sin asignar):
     lo comparten la tabla de la semana y la vista de un día del móvil. --}}
@if ($slot['tipo'] === 'recreo')
    <div class="rounded px-2 py-1 text-xs text-center bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
        {{ __('Recreo') }}
    </div>
@elseif (isset($asignaciones[$dia][$slot['numero']]))
    <div class="rounded px-2 py-1 text-xs"
        style="background-color: {{ $asignaciones[$dia][$slot['numero']]['fondo'] }}; color: {{ $asignaciones[$dia][$slot['numero']]['texto'] }};">
        {{ $asignaciones[$dia][$slot['numero']]['nombre'] }}
        @if ($asignaciones[$dia][$slot['numero']]['modulo'])
            <div class="italic">{{ $asignaciones[$dia][$slot['numero']]['modulo'] }}</div>
        @endif
    </div>
@else
    <div class="rounded px-2 py-1 text-xs text-gray-400 dark:text-gray-500">
        —
    </div>
@endif
