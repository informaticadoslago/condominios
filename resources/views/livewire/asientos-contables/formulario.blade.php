@php
    $totalDebe  = collect($apuntes)->sum(fn ($a) => (float) ($a['debe'] ?? 0));
    $totalHaber = collect($apuntes)->sum(fn ($a) => (float) ($a['haber'] ?? 0));
    $cuadra     = round($totalDebe, 2) === round($totalHaber, 2);
@endphp

{{-- Página a pantalla completa (sin sidebar ni cabecera, ver layouts/foco.blade.php),
     pero el trabajo ocurre en un recuadro central de tamaño fijo (proporcional a la
     pantalla, no la ocupa entera): sin scroll horizontal, con scroll vertical si hace
     falta. Borde del mismo color que el fondo de la página, no un marco visible. --}}
<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-zinc-900 p-4">
    <div class="w-[92vw] max-w-5xl h-[88vh] max-h-[920px] flex flex-col bg-white dark:bg-zinc-800
        border border-gray-50 dark:border-zinc-900 rounded-xl shadow-sm overflow-hidden">
    <header class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
        <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">
            {{ __('Nuevo asiento') }} — {{ $ejercicio->nombre }}
        </h1>
    </header>

    <div class="flex-1 overflow-y-auto overflow-x-hidden px-6 py-6">
        <div class="w-full">
            <div class="flex w-full items-end">
                <div class="w-1/4 mr-4">
                    <x-label :value="__('Fecha')" />
                    <x-input class="block mt-1 w-full" type="date" wire:model="fecha" autofocus
                        min="{{ $ejercicio->fecha_inicio->toDateString() }}"
                        max="{{ $ejercicio->fecha_fin->toDateString() }}" />
                    <x-input-error for="fecha" class="mt-2" />
                </div>
                <div class="w-3/4">
                    <x-label :value="__('Concepto')" />
                    <x-input class="block mt-1 w-full" type="text" wire:model="concepto" />
                    <x-input-error for="concepto" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-error for="apuntes" class="mb-2" />
                <table class="w-full table-fixed text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 pr-2 w-[38%]">{{ __('Cuenta') }}</th>
                            <th class="py-2 pr-2 w-[32%]">{{ __('Concepto línea') }}</th>
                            <th class="py-2 pr-2 text-right w-24">{{ __('Debe') }}</th>
                            <th class="py-2 pr-2 text-right w-24">{{ __('Haber') }}</th>
                            <th class="py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($apuntes as $index => $apunte)
                            <tr wire:key="linea-{{ $apunte['_key'] }}" class="border-b">
                                <td class="py-1 pr-2 align-top">
                                    {{-- El "+" solo actúa con el foco dentro de este buscador (burbujeo del
                                         keydown del input); el atajo global "+" del layout no aplica aquí
                                         (esta página no tiene botón .btn-nuevo). --}}
                                    <div x-on:keydown="if ($event.key === '+') { $event.preventDefault(); $wire.abrirNuevaCuenta('{{ $apunte['_key'] }}') }">
                                        <x-dosl.input-autocomplete
                                            wire:model="apuntes.{{ $index }}._cuenta_texto"
                                            source="buscarCuentas"
                                            items="resultadosCuentas"
                                            valorCampo="valor"
                                            etiquetaCampo="etiqueta"
                                            valorModel="apuntes.{{ $index }}.cuenta_contable_id"
                                            clave="{{ $apunte['_key'] }}"
                                            placeholder="{{ __('Código o nombre...') }}" />
                                    </div>
                                    <x-input-error for="apuntes.{{ $index }}.cuenta_contable_id" class="mt-1" />
                                    <button type="button" tabindex="-1" wire:click="abrirNuevaCuenta('{{ $apunte['_key'] }}')"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 mt-1">
                                        <i class="fa-solid fa-plus"></i> {{ __('Crear cuenta nueva') }}
                                    </button>
                                </td>
                                <td class="py-1 pr-2">
                                    <x-input class="block w-full" type="text" wire:model="apuntes.{{ $index }}.concepto" />
                                </td>
                                <td class="py-1 pr-2">
                                    <x-input class="block w-full text-right" type="number" step="0.01" min="0"
                                        wire:model.live.debounce.400ms="apuntes.{{ $index }}.debe" />
                                    <x-input-error for="apuntes.{{ $index }}.debe" class="mt-1" />
                                </td>
                                <td class="py-1 pr-2">
                                    <x-input class="block w-full text-right" type="number" step="0.01" min="0"
                                        wire:model.live.debounce.400ms="apuntes.{{ $index }}.haber" />
                                    <x-input-error for="apuntes.{{ $index }}.haber" class="mt-1" />
                                </td>
                                <td class="py-1 text-center">
                                    <button type="button" wire:click="quitarLinea({{ $index }})"
                                        @disabled(count($apuntes) <= 2)
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
                            <td colspan="2" class="py-2 text-right">{{ __('Totales') }}</td>
                            <td class="py-2 pr-2 text-right">{{ number_format($totalDebe, 2, ',', '.') }}</td>
                            <td class="py-2 pr-2 text-right">{{ number_format($totalHaber, 2, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <button type="button" wire:click="agregarLinea" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                    <i class="fa-solid fa-plus"></i> {{ __('Añadir línea') }}
                </button>

                <div class="mt-2 text-sm {{ $cuadra ? 'text-green-600' : 'text-red-600' }}">
                    @if ($cuadra)
                        {{ __('El asiento cuadra.') }}
                    @else
                        {{ __('El asiento no cuadra: diferencia de :diff', ['diff' => number_format(abs($totalDebe - $totalHaber), 2, ',', '.')]) }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <footer class="flex justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900">
        {{-- tabindex="-1": igual que x-dosl.boton-cerrar en el resto de la app — tabulando
             se recorren los campos hasta Guardar, sin tropezar con Cancelar. --}}
        <a href="{{ route('asientos-contables.index') }}" wire:navigate tabindex="-1"
            class="btn btn-cerrar px-2 mr-3" title="{{ __('Cancelar') }}">{{ __('Cancelar') }}</a>
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar" @disabled(! $cuadra)
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </footer>
    </div>

    @livewire('plan-de-cuentas.formulario')
</div>
