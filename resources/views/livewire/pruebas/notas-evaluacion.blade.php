<x-botonera-page>
    <x-slot name="title">
        {{ __('Evaluación') }}: {{ $cuadro->evaluacion->nombre }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ $cuadro->asignatura->nombreConModulo() }} · {{ $cuadro->evaluacion->curso->nombre }}
    </x-slot>
    <x-slot name="botonera">
        {{-- Mismas clases de caja que x-button, para que mida lo mismo que los demás botones de cabecera. --}}
        <a href="{{ route('asignaturas.pruebas', $cuadro->asignatura->id) }}" wire:navigate id="btn-volver-pruebas"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest btn btn-cerrar"
            title="{{ __('Volver a pruebas') }}">
            <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('Pruebas') }}
        </a>
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                {{-- El mismo PDF que el Imprimir de la lista de pruebas, en otra pestaña. --}}
                <x-secondary-button type="button" id="btn-imprimir-evaluacion" title="{{ __('Imprimir las notas de la evaluación') }}"
                    onclick="window.open('{{ route('asignaturas.pruebas.pdf', ['asignatura' => $cuadro->asignatura->id, 'evaluacion_id' => $cuadro->evaluacion->id]) }}', '_blank')">
                    <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir') }}
                </x-secondary-button>
            </x-slot>

            @if ($cuadro->pruebas->isEmpty())
                <div class="py-3 px-6">{{ __('Esta evaluación todavía no tiene pruebas.') }}</div>
            @elseif ($cuadro->apuntes->isEmpty())
                <div class="py-3 px-6">{{ __('La asignatura no tiene alumnos.') }}</div>
            @else
                <p class="py-3 px-6 text-sm border-b">
                    {{ __('La nota de cada prueba se pone en la propia prueba (pulsa su nombre). La columna Evaluación es la media según el peso de cada prueba, contando como 0 la que no tiene nota; la Nota parte de ella y se puede retocar a mano o con las flechas. Las partes salen en gris: cuentan a través de su prueba.') }}
                </p>
                {{-- Los pesos tendrían que sumar 100; mientras no, la media se reparte entre lo que hay. --}}
                @if (abs($cuadro->sumaPesos - 100) > 0.001)
                    <p class="py-2 px-6 text-sm border-b" id="aviso-suma-pesos">
                        {{ __('Las pruebas suman') }}
                        <span class="font-semibold text-red-600">{{ \App\Models\Prueba::numero($cuadro->sumaPesos) }} %</span>:
                        {{ __('la media se calcula con el peso que hay.') }}
                    </p>
                @endif
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Alumno') }}</th>
                            <th class="py-3 px-6">{{ __('Grupo') }}</th>
                            @foreach ($cuadro->columnas as $prueba)
                                <th @class(['py-3 px-4 text-right', 'text-gray-500' => $prueba->prueba_id])>
                                    <a href="{{ route($prueba->con_partes ? 'asignaturas.pruebas.partes' : 'asignaturas.pruebas.notas', [$cuadro->asignatura->id, $prueba->id]) }}" wire:navigate
                                        class="underline" title="{{ __('Ver las notas de esta prueba') }}">{{ $prueba->nombre }}</a>
                                    <span class="font-normal whitespace-nowrap">
                                        @if ($prueba->prueba_id)
                                            ({{ \App\Models\Prueba::numero($prueba->peso) }} % {{ __('de') }} {{ $cuadro->pruebas->firstWhere('id', $prueba->prueba_id)?->nombre }})
                                        @else
                                            ({{ \App\Models\Prueba::numero($prueba->peso) }} %)
                                        @endif
                                    </span>
                                </th>
                            @endforeach
                            <th class="py-3 px-4 text-right">{{ __('Evaluación') }}</th>
                            <th class="py-3 px-6">{{ __('Nota') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($cuadro->apuntes as $apunte)
                            @php($final = $cuadro->finales[$apunte->id])
                            <tr wire:key="evaluacion-{{ $apunte->id }}">
                                <td class="px-6 py-2">
                                    {{ $apunte->alumno->nombreCompleto }}
                                    @if ($apunte->estado_id != \App\Models\AsignaturaAlumno::ESTADO_ACTIVO)
                                        <span class="text-xs text-gray-500">({{ __('baja') }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-2">{{ $apunte->grupo }}</td>
                                @foreach ($cuadro->columnas as $prueba)
                                    @php($nota = $cuadro->notas[$apunte->id][$prueba->id])
                                    <td @class(['px-4 py-2 text-right', 'text-gray-500' => $prueba->prueba_id])>
                                        {{ $prueba->con_partes ? \App\Models\Prueba::numero($nota) : $prueba->notaVisible($nota) }}
                                    </td>
                                @endforeach
                                <td class="px-4 py-2 text-right font-semibold">{{ \App\Models\Prueba::numero($final['calculada']) }}</td>
                                <td class="px-6 py-2">
                                    @if ($final['calculada'] !== null)
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
                                        </div>
                                        <x-input-error for="finales.{{ $apunte->id }}" class="mt-1" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- La media de cada columna, de los alumnos activos con nota en ella; en las pruebas
                         con palabras, la buena cuenta 10 y la mala 0. La de la Nota no se retoca. --}}
                    <tfoot class="border-t-2 font-semibold">
                        <tr id="fila-medias">
                            <td class="px-6 py-2" colspan="2">{{ __('Media') }}</td>
                            @foreach ($cuadro->columnas as $prueba)
                                <td @class(['px-4 py-2 text-right', 'text-gray-500' => $prueba->prueba_id])>{{ \App\Models\Prueba::numero($cuadro->medias[$prueba->id]) }}</td>
                            @endforeach
                            <td class="px-4 py-2 text-right">{{ \App\Models\Prueba::numero($cuadro->mediaCalculada) }}</td>
                            <td class="px-6 py-2"><span class="px-3">{{ \App\Models\Prueba::numero($cuadro->mediaFinal) }}</span></td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
