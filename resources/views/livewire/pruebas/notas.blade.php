<x-botonera-page>
    <x-slot name="title">
        {{ __('Notas') }}: {{ $prueba->nombre }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ $prueba->asignatura->nombreConModulo() }} · {{ $prueba->evaluacion->curso->nombre }} · {{ $prueba->evaluacion->nombre }}
        · {{ __('sobre') }} {{ \App\Models\Prueba::numero($prueba->puntuacion_maxima) }}
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
            @if (count($apuntes))
                {{-- saltarNota10: con la casilla marcada, el tabulador no para en la nota sobre 10 y va
                     de nota en nota. Solo vive en el navegador; no se guarda. --}}
                <div x-data="{ saltarNota10: false }">
                <label class="py-3 px-6 flex items-center gap-2 text-sm border-b cursor-pointer" for="chk-saltar-nota10">
                    <input type="checkbox" id="chk-saltar-nota10" x-model="saltarNota10" tabindex="-1" class="rounded" />
                    {{ __('El tabulador no para en la nota sobre 10') }}
                </label>
                <p class="py-3 px-6 text-sm border-b">
                    {{ __('Cada nota se guarda al salir de su casilla. Al escribir la nota sobre :maxima se calcula la de 10; la de 10 se puede retocar a mano o con las flechas.', ['maxima' => \App\Models\Prueba::numero($prueba->puntuacion_maxima)]) }}
                </p>
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Alumno') }}</th>
                            <th class="py-3 px-6">{{ __('Grupo') }}</th>
                            <th class="py-3 px-6">{{ __('Nota sobre') }} {{ \App\Models\Prueba::numero($prueba->puntuacion_maxima) }}</th>
                            <th class="py-3 px-6">{{ __('Nota sobre 10') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($apuntes as $apunte)
                            <tr wire:key="nota-{{ $apunte->id }}">
                                <td class="px-6 py-2">
                                    {{ $apunte->alumno->nombreCompleto }}
                                    @if ($apunte->estado_id != \App\Models\AsignaturaAlumno::ESTADO_ACTIVO)
                                        <span class="text-xs text-gray-500">({{ __('baja') }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-2">{{ $apunte->grupo }}</td>
                                <td class="px-6 py-2">
                                    <x-input id="nota-{{ $apunte->id }}" type="text" inputmode="decimal" class="w-24 !h-10 !px-3 !text-base"
                                        wire:model.live.blur="notas.{{ $apunte->id }}.nota" />
                                    <x-input-error for="notas.{{ $apunte->id }}.nota" class="mt-1" />
                                </td>
                                <td class="px-6 py-2">
                                    <div class="flex items-center gap-1">
                                        <x-input id="nota10-{{ $apunte->id }}" type="text" inputmode="decimal" class="w-24 !h-10 !px-3 !text-base"
                                            x-bind:tabindex="saltarNota10 ? -1 : 0"
                                            wire:model.live.blur="notas.{{ $apunte->id }}.nota10" />
                                        @if (($notas[$apunte->id]['nota10'] ?? '') !== '')
                                            {{-- Fuera del orden de tabulación: tabulando se va de nota en nota. --}}
                                            <button type="button" tabindex="-1" id="btn-subir-nota-{{ $apunte->id }}"
                                                wire:click="redondear({{ $apunte->id }}, 1)"
                                                class="px-2 text-gray-500 hover:text-gray-800" title="{{ __('Al entero siguiente') }}">
                                                <i class="fa-solid fa-caret-up"></i>
                                            </button>
                                            <button type="button" tabindex="-1" id="btn-bajar-nota-{{ $apunte->id }}"
                                                wire:click="redondear({{ $apunte->id }}, -1)"
                                                class="px-2 text-gray-500 hover:text-gray-800" title="{{ __('Al entero anterior') }}">
                                                <i class="fa-solid fa-caret-down"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <x-input-error for="notas.{{ $apunte->id }}.nota10" class="mt-1" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @else
                <div class="py-3 px-6">{{ __('La asignatura no tiene alumnos.') }}</div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
