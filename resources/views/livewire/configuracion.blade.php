<div>
    <x-dosl.dialog-modal wire:model.live="show" maxWidth="full">
        <x-slot name="title">
            {{ __('Configuración') }}
        </x-slot>

        <x-slot name="content">
            {{-- Pestañas: agrupan las variables del .env por prefijo. --}}
            <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
                @foreach ($pestanas as $id => $etiqueta)
                    <button type="button" wire:click="$set('tab', '{{ $id }}')"
                        @class([
                            'px-3 py-2 text-sm font-medium border-b-2 -mb-px transition',
                            'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $tab === $id,
                            'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $tab !== $id,
                        ])>
                        {{ __($etiqueta) }}
                        <span class="ml-1 text-xs text-gray-400">({{ count($grupos[$id]) }})</span>
                    </button>
                @endforeach
            </div>

            {{-- Panel de la pestaña activa: por ahora SOLO LECTURA (los secretos van enmascarados). --}}
            <div class="mt-4 max-h-[55vh] overflow-y-auto pr-1">
                @php($vars = $grupos[$tab] ?? [])

                @if (empty($vars))
                    <p class="text-sm text-gray-500 py-4">{{ __('Sin variables en este grupo.') }}</p>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($vars as $clave => $valor)
                            <div class="rounded border border-gray-100 dark:border-gray-700 px-2 py-1.5">
                                <dt class="font-mono text-xs text-gray-600 dark:text-gray-300 break-all">
                                    {{ $clave }}
                                </dt>
                                <dd class="font-mono text-xs text-gray-900 dark:text-gray-100 break-all">
                                    @if ($valor === '')
                                        <span class="text-gray-400 italic">{{ __('(vacío)') }}</span>
                                    @else
                                        {{ $valor }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar accion="close">{{ __('Cerrar') }}</x-dosl.boton-cerrar>
        </x-slot>
    </x-dosl.dialog-modal>
</div>
