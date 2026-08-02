@if (count($facturas))
    <ul class="mt-1 divide-y border rounded">
        @foreach ($facturas as $factura)
            <li class="px-3 py-2 flex items-center justify-between text-sm" wire:key="factura-{{ $factura->id }}">
                <span class="truncate">
                    <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>
                    {{ $factura->numero_factura ?? $factura->documento->nombre_mostrado }}
                    <span class="text-gray-400">
                        — {{ $factura->fecha_factura ?? $factura->documento->fechaalta->format('d/m/Y') }}
                        @if ($factura->importe !== null)
                            — {{ number_format($factura->importe, 2, ',', '.') }} €
                        @endif
                        — {{ $factura->documento->tamano }}
                    </span>
                </span>
                <span class="flex items-center gap-2">
                    <a href="{{ route('documentos.ver', $factura->documento) }}" target="_blank" class="text-gray-500 hover:text-gray-800" title="{{ __('Ver') }}">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <a href="{{ route('documentos.download', $factura->documento) }}" class="text-gray-500 hover:text-gray-800" title="{{ __('Descargar') }}">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    @if ($permitirBorrar ?? false)
                        <button type="button" class="text-gray-400 hover:text-red-600" title="{{ __('Borrar') }}"
                            wire:click="borrarDocumento({{ $factura->id }})"
                            wire:confirm="{{ __('¿Borrar este documento? No se puede deshacer.') }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
@else
    <div class="mt-1 text-sm text-gray-500">{{ __('Todavía no hay facturas adjuntas.') }}</div>
@endif
