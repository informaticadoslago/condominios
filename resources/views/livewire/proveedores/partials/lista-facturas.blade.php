@if (count($facturas))
    <ul class="mt-1 divide-y border rounded">
        @foreach ($facturas as $factura)
            <li class="px-3 py-2 flex items-center justify-between text-sm" wire:key="factura-{{ $factura->id }}">
                <span class="truncate">
                    <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>
                    {{ $factura->nombre_mostrado }}
                    <span class="text-gray-400">— {{ $factura->fechaalta->format('d/m/Y') }} — {{ $factura->tamano }}</span>
                </span>
                <a href="{{ route('documentos.download', $factura) }}" class="text-gray-500 hover:text-gray-800" title="{{ __('Descargar') }}">
                    <i class="fa-solid fa-download"></i>
                </a>
            </li>
        @endforeach
    </ul>
@else
    <div class="mt-1 text-sm text-gray-500">{{ __('Todavía no hay facturas adjuntas.') }}</div>
@endif
