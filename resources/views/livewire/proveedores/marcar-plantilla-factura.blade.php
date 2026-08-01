<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Marcar plantilla de factura') }}
    </x-slot>

    <x-slot name="content">
        @if ($etiquetaCampoActual)
            @if ($valorDetectadoActual)
                <div class="mb-3 flex items-center justify-between border rounded p-2 bg-gray-50 dark:bg-gray-900">
                    <span class="text-sm">
                        {{ __('Se detectó automáticamente:') }}
                        <span class="mayusculas font-medium">{{ $valorDetectadoActual }}</span>
                    </span>
                    <x-button type="button" class="btn" wire:click="usarValorDetectado">
                        {{ __('Es correcto, usar este valor') }}
                    </x-button>
                </div>
                <p class="mb-2">
                    {!! __('¿No es correcto? Selecciona con el ratón <strong>:campo</strong> en el texto de abajo.', ['campo' => mb_strtoupper($etiquetaCampoActual)]) !!}
                </p>
            @else
                <p class="mb-2">
                    {!! __('Selecciona con el ratón <strong>:campo</strong> en el texto de abajo.', ['campo' => mb_strtoupper($etiquetaCampoActual)]) !!}
                </p>
            @endif

            <pre x-data x-on:mouseup="
                    const sel = window.getSelection();
                    if (sel.rangeCount === 0 || sel.isCollapsed) return;
                    const range = sel.getRangeAt(0);
                    if (!$el.contains(range.commonAncestorContainer)) return;
                    $wire.marcar(range.startOffset, range.endOffset);
                    sel.removeAllRanges();
                " class="border rounded p-3 text-xs whitespace-pre overflow-auto select-text bg-gray-50 dark:bg-gray-900" style="max-height: 50vh;">{{ $texto }}</pre>
        @endif

        @if (count($valores))
            <div class="mt-4 text-sm">
                <div class="font-medium mb-1">{{ __('Marcado hasta ahora:') }}</div>
                <ul class="divide-y border rounded">
                    @foreach ($valores as $tipoCampoId => $datos)
                        <li class="px-3 py-1 flex justify-between">
                            <span>{{ $etiquetas[$tipoCampoId] ?? $tipoCampoId }}</span>
                            <span class="mayusculas">{{ $datos['valor'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if ($etiquetaCampoActual)
            <button type="button" class="btn" wire:click="marcarComoNoEncontrado">
                {{ __('No encontrado, siguiente') }}
            </button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>
