@php
    $total = collect($conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));
@endphp

{{-- x-on:concepto-linea-anadida: tras "Añadir línea", el foco salta al campo Concepto
     de la línea nueva (el evento lo dispara agregarLinea() con la _key de esa línea).
     x-on:keydown "+": esta página usa layouts.foco (sin el atajo global "+" de
     layouts.app que pulsa .btn-nuevo), así que aquí el "+" añade línea directamente. --}}
<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-zinc-900 p-4"
    x-on:concepto-linea-anadida.window="$nextTick(() => document.getElementById('concepto-' + $event.detail.key)?.focus())"
    x-on:keydown.window="
        if ($event.key !== '+' || $event.ctrlKey || $event.metaKey || $event.altKey) return;
        const activo = document.activeElement;
        const escribiendo = activo && (activo.tagName === 'INPUT' || activo.tagName === 'TEXTAREA' || activo.tagName === 'SELECT' || activo.isContentEditable);
        if (escribiendo) return;
        $event.preventDefault();
        $wire.agregarLinea();
    ">
    <div class="w-[92vw] max-w-6xl h-[88vh] max-h-[920px] flex flex-col bg-white dark:bg-zinc-800
        border border-gray-50 dark:border-zinc-900 rounded-xl shadow-sm overflow-hidden">
    <header class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
        <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">
            {{ __('Conceptos del presupuesto') }} — {{ $presupuesto->nombre }} ({{ $presupuesto->anho }})
        </h1>
    </header>

    <div class="flex-1 overflow-y-auto overflow-x-hidden px-6 py-6">
        <div class="flex gap-6 items-start">
            <div class="flex-1 min-w-0">
                <x-input-error for="conceptos" class="mb-2" />
                <table class="w-full table-fixed text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 pr-2 w-[40%]">{{ __('Concepto') }}</th>
                            <th class="py-2 pr-2 w-[32%]">{{ __('Grupo de reparto') }}</th>
                            <th class="py-2 pr-2 text-right w-32">{{ __('Importe') }}</th>
                            <th class="py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conceptos as $index => $linea)
                            <tr wire:key="linea-{{ $linea['_key'] }}" class="border-b">
                                <td class="py-1 pr-2 align-top">
                                    <x-input id="concepto-{{ $linea['_key'] }}" class="block w-full" type="text"
                                        wire:model="conceptos.{{ $index }}.concepto" />
                                    <x-input-error for="conceptos.{{ $index }}.concepto" class="mt-1" />
                                </td>
                                <td class="py-1 pr-2 align-top">
                                    <x-select class="block w-full" wire:model="conceptos.{{ $index }}.grupo_de_reparto_id">
                                        <option value="">{{ __('--') }}</option>
                                        @foreach ($grupos as $grupo)
                                            <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                                        @endforeach
                                    </x-select>
                                    <x-input-error for="conceptos.{{ $index }}.grupo_de_reparto_id" class="mt-1" />
                                </td>
                                <td class="py-1 pr-2 align-top">
                                    <x-input class="block w-full text-right" type="number" step="0.01" min="0"
                                        wire:model.live.debounce.400ms="conceptos.{{ $index }}.importe" />
                                    <x-input-error for="conceptos.{{ $index }}.importe" class="mt-1" />
                                </td>
                                <td class="py-1 text-center align-middle">
                                    <button type="button" tabindex="-1" wire:click="quitarLinea({{ $index }})"
                                        class="text-red-600 hover:text-red-800" title="{{ __('Quitar línea') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="2" class="py-2 text-right">{{ __('Total') }}</td>
                            <td class="py-2 pr-2 text-right">{{ number_format($total, 2, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <button type="button" wire:click="agregarLinea" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                    <i class="fa-solid fa-plus"></i> {{ __('Añadir línea') }}
                </button>
            </div>

            <div class="w-72 shrink-0 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                <h2 class="font-medium mb-3">{{ __('Pagos') }}</h2>

                <div>
                    <x-label :value="__('Periodicidad')" />
                    <x-select class="block mt-1 w-full" wire:model.live="periodicidad_id">
                        <option value="">{{ __('--') }}</option>
                        @foreach ($periodicidades as $periodicidad)
                            <option value="{{ $periodicidad->id }}">{{ $periodicidad->descripcion }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="periodicidad_id" class="mt-1" />
                </div>
                <div class="mt-3">
                    <x-label :value="__('Fecha del primer pago')" />
                    <x-input class="block mt-1 w-full" type="date" wire:model.live="fecha_primer_pago" />
                    <x-input-error for="fecha_primer_pago" class="mt-1" />
                </div>
                <div class="mt-3">
                    <x-label :value="__('Número de pagos')" />
                    <x-input class="block mt-1 w-full" type="number" min="1" wire:model.live="numero_pagos" />
                    <x-input-error for="numero_pagos" class="mt-1" />
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Sugerido según la periodicidad; puedes cambiarlo sin que cambie la separación entre pagos.') }}
                    </p>
                </div>

                @if (count($this->previsionPagos))
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-zinc-700 space-y-1">
                        @foreach ($this->previsionPagos as $pago)
                            <div class="flex items-center justify-between text-xs">
                                <span>{{ __('Pago') }} {{ $loop->iteration }} · {{ $pago['fecha']->format('d/m/Y') }}</span>
                                <span class="font-medium">{{ number_format($pago['importe'], 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <footer class="flex justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900">
        <a href="{{ route('presupuestos.index') }}" wire:navigate tabindex="-1"
            class="btn btn-cerrar px-2 mr-3" title="{{ __('Cancelar') }}">{{ __('Cancelar') }}</a>
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </footer>
    </div>
</div>
