<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ $this->remesa
            ? __('Importar comisiones bancarias · remesa :referencia', ['referencia' => $this->remesa->referencia])
            : __('Importar comisiones bancarias') }}
    </x-slot>

    <x-slot name="content">
        @unless ($analizado)
            <div>
                <x-label for="cb-cuenta-bancaria" :value="__('Cuenta bancaria')" />
                <x-select id="cb-cuenta-bancaria" class="block mt-1 w-full py-3" wire:model="cuentaBancariaId"
                    :disabled="(bool) $this->remesa">
                    @foreach ($this->cuentasBancariasComunidad() as $cuenta)
                        <option value="{{ $cuenta->id }}">{{ $cuenta->alias ?: $cuenta->iban }}</option>
                    @endforeach
                </x-select>

                <p class="mt-2 text-xs text-gray-500">
                    {{ __('Se clasifican los movimientos ya importados de esa cuenta (pantalla "Movimientos bancarios").') }}
                </p>

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
                                    <td class="py-1 pl-2">
                                        @if ($d['id'])
                                            <button type="button" class="text-blue-600 hover:text-blue-800 text-xl px-3 py-2 -my-1"
                                                wire:click="convertirDescartada({{ $d['id'] }})"
                                                title="{{ __('Convertir en comisión bancaria') }}">
                                                <i class="fa-solid fa-money-check-dollar"></i>
                                            </button>
                                        @endif
                                    </td>
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
