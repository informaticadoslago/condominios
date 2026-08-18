<x-botonera-page>
    <x-slot name="title">
        {{ __('Reparto') }} — {{ $presupuesto->nombre }} ({{ $presupuesto->anho }})
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Importe por inmueble en cada grupo de reparto, y el total a cobrar de cada uno') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('presupuestos.index') }}" wire:navigate class="btn btn-cerrar px-2" title="{{ __('Volver') }}">
            {{ __('Volver') }}
        </a>
    </x-slot>

    <x-slot name="content">
        <div class="space-y-4">
        <div class="space-y-4 max-w-4xl">
        <x-dosl.tabla>
            <x-slot name="titulo">
                <span class="text-base font-normal">
                    {{ __('Estado') }}:
                    <span class="font-semibold">{{ $presupuesto->estado?->descripcion }}</span>
                </span>
            </x-slot>
            <x-slot name="botonera">
                @unless ($aprobado)
                    {{-- :disabled y no @disabled: la directiva dentro de la etiqueta de
                         un componente Blade descuadra la plantilla al compilarla. --}}
                    @if ($fijado)
                        <x-secondary-button type="button" wire:click="confirmarDesfijar" title="{{ __('Desfijar y volver a calcular en vivo') }}">
                            <i class="fa-solid fa-lock-open mr-1"></i>{{ __('Desfijar') }}
                        </x-secondary-button>
                    @else
                        <x-secondary-button type="button" wire:click="confirmarFijar" title="{{ __('Fijar') }}"
                            :disabled="! $puedeFijar">
                            <i class="fa-solid fa-lock mr-1"></i>{{ __('Fijar') }}
                        </x-secondary-button>
                    @endif
                    <x-secondary-button type="button" wire:click="confirmarAprobar" title="{{ __('Aprobar') }}"
                        :disabled="! $puedeAprobar">
                        <i class="fa-solid fa-circle-check mr-1"></i>{{ __('Aprobar') }}
                    </x-secondary-button>
                @endunless
                <a href="{{ route('presupuestos.reparto.pdf', $presupuesto) }}" target="_blank">
                    <x-secondary-button type="button" title="{{ __('Imprimir / Descargar PDF') }}">
                        <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir') }}
                    </x-secondary-button>
                </a>
            </x-slot>

            <div class="py-3 px-6 flex items-center justify-between">
                <span class="font-semibold">{{ __('Total del presupuesto') }} ({{ $presupuesto->anho }})</span>
                <span class="font-semibold">{{ number_format($totalPresupuesto, 2, ',', '.') }}</span>
            </div>
        </x-dosl.tabla>

        @unless ($datosPagoCompletos)
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center gap-2 text-red-700 dark:text-red-400">
                    <i class="fa-solid fa-bell"></i>
                    <span>{{ __('A este presupuesto le falta el número de pagos, la periodicidad o la fecha del primer pago. Edítalo para poder ver el desglose de pagos.') }}</span>
                </div>
            </x-dosl.tabla>
        @endunless

        @forelse ($grupos as $datosGrupo)
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center justify-between">
                    <span class="font-semibold">{{ $datosGrupo['grupo']->nombre }}</span>
                    <span>{{ __('Total') }}: {{ number_format($datosGrupo['total'], 2, ',', '.') }}</span>
                </div>
                @if ($datosGrupo['sumaCoeficientes'] <= 0)
                    <div class="px-6 pb-3 text-red-700 dark:text-red-400">
                        {{ __('Este grupo no tiene inmuebles o su coeficiente suma 0: no se puede repartir.') }}
                    </div>
                @endif
                <table class="table-striped table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-2 px-6">{{ __('Inmueble') }}</th>
                            <th class="py-2 px-6 text-right">{{ __('Coeficiente') }}</th>
                            <th class="py-2 px-6 text-right">{{ __('Importe') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($datosGrupo['lineas'] as $linea)
                            <tr wire:key="grupo-{{ $datosGrupo['grupo']->id }}-inmueble-{{ $linea['inmueble']->id }}">
                                <td class="py-2 px-6">{{ $linea['inmueble']->planta }} / {{ $linea['inmueble']->puerta }}</td>
                                <td class="py-2 px-6 text-right">{{ $linea['coeficiente'] }}%</td>
                                <td class="py-2 px-6 text-right">{{ number_format($linea['importe'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-dosl.tabla>
        @empty
            <x-dosl.tabla>
                <div class="py-3 px-6">{{ __('Este presupuesto todavía no tiene conceptos con grupo de reparto.') }}</div>
            </x-dosl.tabla>
        @endforelse
        </div>

        <x-dosl.tabla>
            <div class="py-3 px-6 font-semibold">
                {{ __('Reparto global (lo que se cobra a cada inmueble)') }}
            </div>
            @if (count($global))
                <div class="overflow-x-auto">
                    <table class="table-striped table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="py-2 px-6">{{ __('Inmueble') }}</th>
                                <th class="py-2 px-6 text-right">{{ __('Total') }}</th>
                                @if ($datosPagoCompletos)
                                    @foreach ($fechasPagos as $i => $fecha)
                                        <th class="py-2 px-6 text-right whitespace-nowrap">
                                            {{ __('Pago') }} {{ $i + 1 }}
                                            <div class="font-normal text-xs">{{ $fecha->format('d/m/Y') }}</div>
                                        </th>
                                    @endforeach
                                @endif
                                @if ($editable)
                                    <th class="py-2 px-6 text-right">{{ __('Diferencia') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($global as $fila)
                                @php
                                    $inmuebleId = $fila['inmueble']->id;
                                    $diferencia = $editable
                                        ? round(array_sum(array_map('floatval', $pagosEditados[$inmuebleId] ?? [])) - $fila['total'], 2)
                                        : 0;
                                @endphp
                                <tr wire:key="global-{{ $inmuebleId }}">
                                    <td class="py-2 px-6">{{ $fila['inmueble']->planta }} / {{ $fila['inmueble']->puerta }}</td>
                                    <td class="py-2 px-6 text-right">{{ number_format($fila['total'], 2, ',', '.') }}</td>
                                    @if ($datosPagoCompletos)
                                        @foreach ($fila['pagos'] as $i => $importePago)
                                            <td class="py-2 px-6 text-right">
                                                @if ($editable)
                                                    <x-input type="number" step="0.01"
                                                        class="w-28 h-10 text-sm px-1 text-right [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                        wire:model.live="pagosEditados.{{ $inmuebleId }}.{{ $i }}" />
                                                @else
                                                    {{ number_format($importePago, 2, ',', '.') }}
                                                @endif
                                            </td>
                                        @endforeach
                                    @endif
                                    @if ($editable)
                                        <td class="py-2 px-6 text-right @if ($diferencia != 0) text-red-700 dark:text-red-400 font-semibold @endif">
                                            @if ($diferencia != 0)
                                                {{ $diferencia > 0 ? '+' : '' }}{{ number_format($diferencia, 2, ',', '.') }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t">
                                <td class="py-2 px-6">{{ __('Total') }}</td>
                                <td class="py-2 px-6 text-right">{{ number_format($global->sum('total'), 2, ',', '.') }}</td>
                                @if ($datosPagoCompletos)
                                    @foreach ($fechasPagos as $i => $fecha)
                                        <td class="py-2 px-6 text-right">
                                            @if ($editable)
                                                {{ number_format(collect($pagosEditados)->sum(fn ($pagos) => (float) ($pagos[$i] ?? 0)), 2, ',', '.') }}
                                            @else
                                                {{ number_format($global->sum(fn ($f) => $f['pagos'][$i] ?? 0), 2, ',', '.') }}
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                                @if ($editable)
                                    @php
                                        $diferenciaTotal = round(collect($pagosEditados)->sum(fn ($pagos) => array_sum(array_map('floatval', $pagos))) - $global->sum('total'), 2);
                                    @endphp
                                    <td class="py-2 px-6 text-right @if ($diferenciaTotal != 0) text-red-700 dark:text-red-400 @endif">
                                        @if ($diferenciaTotal != 0)
                                            {{ $diferenciaTotal > 0 ? '+' : '' }}{{ number_format($diferenciaTotal, 2, ',', '.') }}
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
            @if ($editable)
                <div class="py-3 px-6 flex justify-end border-t">
                    <x-secondary-button type="button" wire:click="guardarReparto" title="{{ __('Guardar reparto') }}">
                        <i class="fa-solid fa-floppy-disk mr-1"></i>{{ __('Guardar reparto') }}
                    </x-secondary-button>
                </div>
            @endif
        </x-dosl.tabla>
        </div>
    </x-slot>
</x-botonera-page>
