<x-botonera-page>
    <x-slot name="title">
        {{ __('Días y sesiones') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Las jornadas del horario: a qué hora empiezan, cuántas sesiones tienen y qué días se dan') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-editar" id="btn-editar-dias-sesiones"
            wire:click="$dispatch('abrir-editar-dias-sesiones')" title="{{ __('Editar') }}">
            <i class="fa-solid fa-pen"> </i>{{ __('Editar') }}
        </x-button>
    </x-slot>

    <x-slot name="content">
        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <x-label :value="__('Duración de la sesión')" />
                <div class="mt-1">
                    {{ $horario?->duracion_sesion_minutos ? $horario->duracion_sesion_minutos.' '.__('minutos') : '—' }}
                </div>
            </div>

            @if ($jornadas->isEmpty())
                <div class="py-3 text-gray-500 dark:text-gray-400">{{ __('Todavía no hay ninguna jornada configurada.') }}</div>
            @else
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Jornada') }}</th>
                            <th class="py-3 px-6">{{ __('Hora de inicio') }}</th>
                            <th class="py-3 px-6">{{ __('Número de sesiones') }}</th>
                            <th class="py-3 px-6">{{ __('Recreo') }}</th>
                            <th class="py-3 px-6">{{ __('Días') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($jornadas as $jornada)
                            <tr wire:key="jornada-{{ $jornada->id }}">
                                <td class="px-6 py-4">{{ $jornada->nombre }}</td>
                                <td class="px-6 py-4">{{ substr($jornada->hora_inicio, 0, 5) }}</td>
                                <td class="px-6 py-4">{{ $jornada->num_sesiones }}</td>
                                <td class="px-6 py-4">
                                    @if ($jornada->tieneRecreo())
                                        {{ __('Antes de la sesión :n (:min min)', ['n' => $jornada->recreo_antes_de_sesion, 'min' => $jornada->recreo_duracion_minutos]) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    {{ collect($jornada->dias_semana)->sort()->map(fn ($dia) => \App\Support\DiaSemana::from($dia)->abreviatura())->implode(' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @livewire('horarios.dias-sesiones-formulario')
    </x-slot>
</x-botonera-page>
