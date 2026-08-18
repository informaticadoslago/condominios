<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ $this->remesa
            ? __('Importar comisiones bancarias · remesa :referencia', ['referencia' => $this->remesa->referencia])
            : __('Importar comisiones bancarias') }}
    </x-slot>

    <x-slot name="content">
        @unless ($analizado)
            <div>
                <x-label :value="__('Extracto de movimientos (CSV o Q43/Norma 43)')" />
                <label for="cb-fichero"
                    x-data="{ arrastrando: false }"
                    x-on:dragover.prevent="arrastrando = true"
                    x-on:dragleave.prevent="arrastrando = false"
                    x-on:drop.prevent="arrastrando = false; $wire.upload('fichero', $event.dataTransfer.files[0])"
                    :class="arrastrando ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-300 dark:border-gray-600'"
                    class="flex flex-col items-center justify-center w-full h-32 mt-1 border-2 border-dashed rounded-lg cursor-pointer transition-colors">
                    <i class="fa-solid fa-file-csv text-3xl text-gray-400 mb-2"></i>
                    <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                        @if ($fichero)
                            {{ $fichero->getClientOriginalName() }}
                        @else
                            {{ __('Arrastra aquí el extracto o haz clic para buscarlo') }}
                        @endif
                    </span>
                    <input type="file" id="cb-fichero" wire:model="fichero" accept=".csv,.txt,.q43" class="hidden" />
                </label>

                <div wire:loading wire:target="fichero" class="text-sm text-gray-500 mt-2">
                    {{ __('Cargando...') }}
                </div>

                <x-input-error for="fichero" class="mt-2" />
                @if ($error)
                    <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
                @endif
            </div>
        @else
            @php
                $dentroEjercicio = collect($candidatas)->reject(fn ($c) => $c['fuera_ejercicio'])->all();
                $fueraEjercicio  = collect($candidatas)->filter(fn ($c) => $c['fuera_ejercicio'])->all();
            @endphp
            <div class="max-h-[60vh] overflow-y-auto">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Para importar (:count)', ['count' => count($dentroEjercicio)]) }}
                </h3>
                @if (count($dentroEjercicio))
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
                            @foreach ($dentroEjercicio as $indice => $c)
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

                @if (count($fueraEjercicio))
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-5">
                        {{ __('Fuera del ejercicio en curso (:count)', ['count' => count($fueraEjercicio)]) }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        {{ __('Sin marcar: si en su día no se importó el fichero, seguramente ya se metieron a mano. Revise antes de marcar alguna.') }}
                    </p>
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
                            @foreach ($fueraEjercicio as $indice => $c)
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
