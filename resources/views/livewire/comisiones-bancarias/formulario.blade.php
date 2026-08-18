<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-zinc-900 p-4">
    <div class="w-[92vw] max-w-3xl h-[88vh] max-h-[820px] flex flex-col bg-white dark:bg-zinc-800
        border border-gray-50 dark:border-zinc-900 rounded-xl shadow-sm overflow-hidden">
    <header class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
        <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">
            {{ __('Comisión bancaria') }}
        </h1>
    </header>

    <div class="flex-1 overflow-y-auto overflow-x-hidden px-6 py-6">
        <div class="w-full">
            <div class="flex w-full items-end gap-4">
                <div class="w-1/3">
                    <x-label :value="__('Tipo')" />
                    <x-select class="block mt-1 w-full" wire:model.live="tipo_comision_bancaria_id">
                        @foreach ($tiposComisionBancaria as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="tipo_comision_bancaria_id" class="mt-2" />
                </div>
                <div class="w-1/3">
                    <x-label :value="__('Cuenta bancaria')" />
                    <x-select class="block mt-1 w-full" wire:model="cuenta_bancaria_id">
                        <option value="">{{ __('Seleccione...') }}</option>
                        @foreach ($cuentasBancarias as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->alias ?: $cuenta->iban }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="cuenta_bancaria_id" class="mt-2" />
                </div>
                @if ($this->esRemesa)
                    <div class="w-1/3">
                        <x-label :value="__('Remesa (opcional)')" />
                        <x-select class="block mt-1 w-full" wire:model="remesa_id">
                            <option value="">{{ __('Ninguna / fuera de esta gestión') }}</option>
                            @foreach ($remesas as $remesa)
                                <option value="{{ $remesa->id }}">{{ $remesa->referencia }} ({{ $remesa->fecha_cargo->format('d/m/Y') }})</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="remesa_id" class="mt-2" />
                    </div>
                @endif
            </div>

            <div class="flex w-full items-end gap-4 mt-4">
                <div class="w-1/4">
                    <x-label :value="__('Fecha')" />
                    <x-input class="block mt-1 w-full" type="date" wire:model="fecha" />
                    <x-input-error for="fecha" class="mt-2" />
                </div>
                <div class="w-1/2">
                    <x-label :value="__('Concepto')" />
                    <x-input class="block mt-1 w-full" type="text" wire:model="concepto"
                        placeholder="{{ __('p.ej. Liquidación remesa 31/07/2026') }}" />
                    <x-input-error for="concepto" class="mt-2" />
                </div>
                <div class="w-1/4">
                    <x-label :value="__('Referencia (nº operación banco)')" />
                    <x-input class="block mt-1 w-full" type="text" wire:model="referencia" />
                    <x-input-error for="referencia" class="mt-2" />
                </div>
            </div>

            <div class="mt-6">
                <x-input-error for="lineas" class="mb-2" />
                <table class="w-full table-fixed text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 pr-2">{{ __('Concepto de la línea') }}</th>
                            <th class="py-2 pr-2 text-right w-36">{{ __('Importe') }}</th>
                            <th class="py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lineas as $index => $linea)
                            <tr wire:key="linea-{{ $linea['_key'] }}" class="border-b">
                                <td class="py-1 pr-2">
                                    <x-input class="block w-full" type="text" wire:model="lineas.{{ $index }}.concepto"
                                        placeholder="{{ __('p.ej. Comisión, IVA comisión...') }}" />
                                    <x-input-error for="lineas.{{ $index }}.concepto" class="mt-1" />
                                </td>
                                <td class="py-1 pr-2">
                                    <x-input class="block w-full text-right" type="number" step="0.01" min="0"
                                        wire:model.live.debounce.400ms="lineas.{{ $index }}.importe" />
                                    <x-input-error for="lineas.{{ $index }}.importe" class="mt-1" />
                                </td>
                                <td class="py-1 text-center">
                                    <button type="button" wire:click="quitarLinea({{ $index }})"
                                        @disabled(count($lineas) <= 1)
                                        class="text-red-600 hover:text-red-800 disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="{{ __('Quitar línea') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td class="py-2 text-right">{{ __('Total') }}</td>
                            <td class="py-2 pr-2 text-right">{{ number_format($this->total, 2, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <button type="button" wire:click="agregarLinea" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                    <i class="fa-solid fa-plus"></i> {{ __('Añadir línea') }}
                </button>
            </div>
        </div>
    </div>

    <footer class="flex justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900">
        <a href="{{ route('comisiones-bancarias.index') }}" wire:navigate tabindex="-1"
            class="btn btn-cerrar px-2 mr-3" title="{{ __('Cancelar') }}">{{ __('Cancelar') }}</a>
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </footer>
    </div>
</div>
