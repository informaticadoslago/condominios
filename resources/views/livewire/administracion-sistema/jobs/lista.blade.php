<x-botonera-page>
    <x-slot name="title">
        {{ __('Colas de trabajos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Pendientes y fallidos de cada cola') }}
    </x-slot>

    <x-slot name="content">
        <div wire:poll.10s></div>

        @if ($colas->isEmpty())
            <div class="py-3 px-6 text-gray-500">{{ __('No hay ningún trabajo en cola ni fallido.') }}</div>
        @else
            {{-- Una pestaña por cola. --}}
            <div class="flex gap-1 border-b mb-4">
                @foreach ($colas as $cola)
                    <button type="button" wire:click="cambiarCola('{{ $cola }}')"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $cola === $colaActiva
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        {{ $cola }}
                    </button>
                @endforeach
            </div>

            {{-- Pendientes --}}
            <h3 class="font-medium mb-2">{{ __('Pendientes') }} ({{ count($pendientes) }})</h3>
            <x-dosl.tabla>
                @if (count($pendientes))
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="py-2 px-3 w-px">
                                    <input type="checkbox"
                                        wire:click="toggleTodosPendientes({{ $pendientes->pluck('id')->values()->toJson() }})"
                                        @checked(count($pendientes) > 0 && count($seleccionPendientes) === count($pendientes)) />
                                </th>
                                <th class="py-2 px-3">{{ __('Trabajo') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Intentos') }}</th>
                                <th class="py-2 px-3">{{ __('Encolado') }}</th>
                                <th class="py-2 px-3">{{ __('Disponible desde') }}</th>
                                <th class="py-2 px-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($pendientes as $job)
                                <tr wire:key="pendiente-{{ $job['id'] }}" class="{{ $job['pausado'] ? 'opacity-60' : '' }}">
                                    <td class="py-2 px-3">
                                        <input type="checkbox" wire:model.live="seleccionPendientes" value="{{ $job['id'] }}" />
                                    </td>
                                    <td class="py-2 px-3">{{ $job['job'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ $job['attempts'] }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">{{ $job['created_at']->format('d/m/Y H:i:s') }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        @if ($job['pausado'])
                                            <span class="text-amber-600 dark:text-amber-400">
                                                <i class="fa-solid fa-pause mr-1"></i>{{ __('Pausado') }}
                                            </span>
                                        @else
                                            {{ $job['available_at']->format('d/m/Y H:i:s') }}
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        @if ($job['pausado'])
                                            <x-button type="button" class="bg-teal-600 hover:bg-teal-700 text-white"
                                                wire:click="reanudarPendiente({{ $job['id'] }})"
                                                title="{{ __('Reanudar') }}">
                                                <i class="fa-solid fa-play"></i>
                                            </x-button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-3">
                        <x-button type="button" class="bg-amber-600 hover:bg-amber-700 text-white"
                            wire:click="pausarPendientesSeleccionados"
                            title="{{ __('Pausar los marcados sin borrarlos') }}">
                            <i class="fa-solid fa-pause"></i> {{ __('Pausar marcados') }}
                        </x-button>
                        <x-button type="button" class="ml-1 bg-red-600 hover:bg-red-700 text-white"
                            wire:click="borrarPendientesSeleccionados"
                            title="{{ __('Borrar los marcados') }}">
                            <i class="fa-solid fa-trash"></i> {{ __('Borrar marcados') }}
                        </x-button>
                    </div>
                @else
                    <div class="py-3 px-6 text-gray-500">{{ __('No hay nada pendiente en esta cola.') }}</div>
                @endif
            </x-dosl.tabla>

            {{-- Fallidos --}}
            <h3 class="font-medium mt-6 mb-2">{{ __('Fallidos') }} ({{ count($fallidos) }})</h3>
            <x-dosl.tabla>
                @if (count($fallidos))
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="py-2 px-3">{{ __('Trabajo') }}</th>
                                <th class="py-2 px-3">{{ __('Error') }}</th>
                                <th class="py-2 px-3">{{ __('Falló') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($fallidos as $job)
                                <tr wire:key="fallido-{{ $job['id'] }}">
                                    <td class="py-2 px-3">{{ $job['job'] }}</td>
                                    <td class="py-2 px-3 text-red-600 dark:text-red-400">{{ $job['exception'] }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($job['failed_at'])->format('d/m/Y H:i:s') }}</td>
                                    <td class="py-2 px-3 text-right whitespace-nowrap">
                                        <x-button type="button" class="bg-teal-600 hover:bg-teal-700 text-white"
                                            wire:click="reintentarFallido('{{ $job['uuid'] }}')"
                                            title="{{ __('Reintentar') }}">
                                            <i class="fa-solid fa-rotate-right"></i>
                                        </x-button>
                                        <x-button type="button" class="ml-1 bg-red-600 hover:bg-red-700 text-white"
                                            wire:click="borrarFallido({{ $job['id'] }})"
                                            title="{{ __('Borrar') }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="py-3 px-6 text-gray-500">{{ __('No hay nada fallido en esta cola.') }}</div>
                @endif
            </x-dosl.tabla>
        @endif
    </x-slot>
</x-botonera-page>
