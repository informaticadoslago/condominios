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
        <div class="space-y-4 max-w-4xl">
        <x-dosl.tabla>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($global as $fila)
                                <tr wire:key="global-{{ $fila['inmueble']->id }}">
                                    <td class="py-2 px-6">{{ $fila['inmueble']->planta }} / {{ $fila['inmueble']->puerta }}</td>
                                    <td class="py-2 px-6 text-right">{{ number_format($fila['total'], 2, ',', '.') }}</td>
                                    @if ($datosPagoCompletos)
                                        @foreach ($fila['pagos'] as $importePago)
                                            <td class="py-2 px-6 text-right">{{ number_format($importePago, 2, ',', '.') }}</td>
                                        @endforeach
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
                                            {{ number_format($global->sum(fn ($f) => $f['pagos'][$i] ?? 0), 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>
        </div>
    </x-slot>
</x-botonera-page>
