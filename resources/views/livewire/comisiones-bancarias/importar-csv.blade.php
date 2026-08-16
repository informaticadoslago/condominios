<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Importar comisiones bancarias') }}
    </x-slot>

    <x-slot name="content">
        @unless ($analizado)
            <div>
                <x-label for="cb-fichero" :value="__('Extracto de movimientos (CSV)')" />
                <input id="cb-fichero" type="file" wire:model="fichero" accept=".csv,.txt"
                    class="block mt-1 w-full text-sm" />
                <x-input-error for="fichero" class="mt-2" />
                @if ($error)
                    <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
                @endif
            </div>
        @else
            <div class="max-h-[60vh] overflow-y-auto">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Para importar (:count)', ['count' => count($candidatas)]) }}
                </h3>
                @if (count($candidatas))
                    <table class="w-full text-sm text-left mt-2">
                        <thead class="border-b">
                            <tr>
                                <th class="py-1 pr-2 w-8"></th>
                                <th class="py-1 pr-2">{{ __('Fecha') }}</th>
                                <th class="py-1 pr-2">{{ __('Tipo') }}</th>
                                <th class="py-1 pr-2">{{ __('Concepto') }}</th>
                                <th class="py-1 text-right">{{ __('Importe') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($candidatas as $indice => $c)
                                <tr wire:key="candidata-{{ $indice }}">
                                    <td class="py-1 pr-2">
                                        <input type="checkbox" wire:model="seleccionadas" value="{{ $indice }}" />
                                    </td>
                                    <td class="py-1 pr-2">{{ \Illuminate\Support\Carbon::parse($c['fecha'])->format('d/m/Y') }}</td>
                                    <td class="py-1 pr-2">{{ $c['codigo'] === 'remesa' ? __('Remesa') : __('Mantenimiento') }}</td>
                                    <td class="py-1 pr-2">{{ $c['concepto'] }}</td>
                                    <td class="py-1 text-right">{{ number_format(array_sum(array_column($c['lineas'], 'importe')), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-gray-500 mt-1">{{ __('Nada nuevo que importar.') }}</p>
                @endif

                @if (count($yaProcesadas))
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-5">
                        {{ __('Ya importadas antes (:count)', ['count' => count($yaProcesadas)]) }}
                    </h3>
                    <table class="w-full text-sm text-left mt-2 text-gray-500">
                        <tbody class="divide-y">
                            @foreach ($yaProcesadas as $c)
                                <tr>
                                    <td class="py-1 pr-2">{{ \Illuminate\Support\Carbon::parse($c['fecha'])->format('d/m/Y') }}</td>
                                    <td class="py-1 pr-2">{{ $c['concepto'] }}</td>
                                    <td class="py-1 text-right">{{ number_format(array_sum(array_column($c['lineas'], 'importe')), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if (count($descartadas))
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-5">
                        {{ __('Descartadas, no son de este módulo (:count)', ['count' => count($descartadas)]) }}
                    </h3>
                    <table class="w-full text-sm text-left mt-2 text-gray-500">
                        <tbody class="divide-y">
                            @foreach ($descartadas as $d)
                                <tr>
                                    <td class="py-1 pr-2">{{ $d['fecha'] }}</td>
                                    <td class="py-1 pr-2">{{ $d['tipo'] }} — {{ $d['concepto'] }}</td>
                                    <td class="py-1 text-right">{{ $d['importe'] }}</td>
                                    <td class="py-1 pl-2 text-xs italic">{{ $d['motivo'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endunless
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @unless ($analizado)
            <button type="button" class="btn btn-guardar px-2" wire:click="procesar"
                title="{{ __('Procesar') }}">{{ __('Procesar') }}</button>
        @else
            <button type="button" class="btn btn-guardar px-2" wire:click="importar"
                title="{{ __('Importar seleccionadas') }}">{{ __('Importar seleccionadas') }}</button>
        @endunless
    </x-slot>
</x-dosl.dialog-modal>
