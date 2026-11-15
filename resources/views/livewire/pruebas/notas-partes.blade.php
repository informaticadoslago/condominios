<x-botonera-page>
    <x-slot name="title">
        {{ __('Notas') }}: {{ $prueba->nombre }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ $prueba->asignatura->nombreConModulo() }} · {{ $prueba->evaluacion->curso->nombre }} · {{ $prueba->evaluacion->nombre }}
        · {{ __('peso') }} {{ \App\Models\Prueba::numero($prueba->peso) }} %
    </x-slot>
    <x-slot name="botonera">
        {{-- Mismas clases de caja que x-button, para que mida lo mismo que los demás botones de cabecera. --}}
        <a href="{{ route('asignaturas.pruebas', $prueba->asignatura_id) }}" wire:navigate id="btn-volver-pruebas"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest btn btn-cerrar"
            title="{{ __('Volver a pruebas') }}">
            <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('Pruebas') }}
        </a>
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            @if (! count($partes))
                <div class="py-3 px-6">{{ __('Esta prueba todavía no tiene partes.') }}</div>
            @elseif (! count($apuntes))
                <div class="py-3 px-6">{{ __('La asignatura no tiene alumnos.') }}</div>
            @else
                <p class="py-3 px-6 text-sm border-b">
                    {{ __('La nota de cada parte se pone en la propia parte (pulsa su nombre). La nota final es la media según el peso de cada parte, y se puede retocar a mano o con las flechas.') }}
                    @if ($prueba->nota_minima > 0)
                        {{ __('Con alguna parte por debajo de :minima, una media aprobada se queda en :tope.', ['minima' => \App\Models\Prueba::numero($prueba->nota_minima), 'tope' => \App\Models\Prueba::NOTA_CON_PARTE_SUSPENSA]) }}
                    @endif
                </p>
                {{-- Las partes tienen que sumar 100; mientras no, la media se reparte entre lo que hay. --}}
                @if (abs($sumaPartes - 100) > 0.001)
                    <p class="py-2 px-6 text-sm border-b" id="aviso-suma-partes">
                        {{ __('Las partes suman') }}
                        <span class="font-semibold text-red-600">{{ \App\Models\Prueba::numero($sumaPartes) }} %</span>:
                        {{ __('la media se calcula con el peso que hay.') }}
                    </p>
                @endif
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Alumno') }}</th>
                            <th class="py-3 px-6">{{ __('Grupo') }}</th>
                            @foreach ($partes as $parte)
                                <th class="py-3 px-6 text-right">
                                    <a href="{{ route('asignaturas.pruebas.notas', [$prueba->asignatura_id, $parte->id]) }}" wire:navigate
                                        class="underline" title="{{ __('Poner las notas de esta parte') }}">{{ $parte->nombre }}</a>
                                    <span class="font-normal">({{ \App\Models\Prueba::numero($parte->peso) }} %)</span>
                                </th>
                            @endforeach
                            <th class="py-3 px-6">{{ __('Nota final') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($apuntes as $apunte)
                            @php($delAlumno = $notas[$apunte->id] ?? null)
                            <tr wire:key="final-{{ $apunte->id }}">
                                <td class="px-6 py-2">
                                    {{ $apunte->alumno->nombreCompleto }}
                                    @if ($apunte->estado_id != \App\Models\AsignaturaAlumno::ESTADO_ACTIVO)
                                        <span class="text-xs text-gray-500">({{ __('baja') }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-2">{{ $apunte->grupo }}</td>
                                @foreach ($partes as $parte)
                                    @php($nota = $delAlumno['partes'][$parte->id] ?? null)
                                    {{-- En rojo, la que no llega al mínimo (también la que falta, que cuenta como 0). --}}
                                    <td @class(['px-6 py-2 text-right', 'text-red-600' => $delAlumno && $prueba->nota_minima > 0 && (float) $nota < $prueba->nota_minima])>
                                        {{ $delAlumno ? \App\Models\Prueba::numero($nota ?? 0) : '' }}
                                    </td>
                                @endforeach
                                <td class="px-6 py-2">
                                    @if ($delAlumno)
                                        <div class="flex items-center gap-1">
                                            <x-input id="final-{{ $apunte->id }}" type="text" inputmode="decimal" class="w-24 !h-10 !px-3 !text-base"
                                                wire:model.live.blur="finales.{{ $apunte->id }}" />
                                            <button type="button" tabindex="-1" id="btn-subir-final-{{ $apunte->id }}"
                                                wire:click="redondear({{ $apunte->id }}, 1)"
                                                class="px-2 text-gray-500 hover:text-gray-800" title="{{ __('Al entero siguiente') }}">
                                                <i class="fa-solid fa-caret-up"></i>
                                            </button>
                                            <button type="button" tabindex="-1" id="btn-bajar-final-{{ $apunte->id }}"
                                                wire:click="redondear({{ $apunte->id }}, -1)"
                                                class="px-2 text-gray-500 hover:text-gray-800" title="{{ __('Al entero anterior') }}">
                                                <i class="fa-solid fa-caret-down"></i>
                                            </button>
                                            @if ($delAlumno['retocada'])
                                                <span class="text-xs text-gray-500" title="{{ __('Retocada a mano') }}">({{ __('media') }} {{ \App\Models\Prueba::numero($delAlumno['calculada']) }})</span>
                                            @endif
                                        </div>
                                        <x-input-error for="finales.{{ $apunte->id }}" class="mt-1" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
