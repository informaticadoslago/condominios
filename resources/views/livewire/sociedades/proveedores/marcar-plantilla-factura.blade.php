<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Marcar plantilla de factura') }}
    </x-slot>

    <x-slot name="content">
        @if ($enCuotas)
            @if (count($cuotasIva))
                <div class="mb-3 text-sm">
                    <div class="font-medium mb-1">{{ __('Cuotas de IVA marcadas:') }}</div>
                    <ul class="divide-y border rounded">
                        @foreach ($cuotasIva as $i => $cuota)
                            <li class="px-3 py-1 flex justify-between items-center" wire:key="cuota-iva-{{ $i }}">
                                <span>{{ __(':tipo % IVA', ['tipo' => $cuota['tipo_iva']]) }}</span>
                                <span class="flex items-center gap-2">
                                    <span>{{ $cuota['valor'] }}</span>
                                    <button type="button" wire:click="quitarCuota({{ $i }})" class="text-gray-400 hover:text-red-600">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($subEtapaCuota === 'porcentaje')
                <p class="mb-2">
                    {{ __('Si esta factura trae IVA, indica primero el % de la siguiente cuota. Si no trae ninguna (o ya las has marcado todas), pulsa "Sin más cuotas, continuar".') }}
                </p>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-label :value="__('% de IVA de la siguiente cuota (21, 10, 4, 0...)')" />
                        <x-input type="text" class="block mt-1 w-full" wire:model="tipoIvaManual" wire:keydown.enter.prevent="confirmarPorcentajeCuota" autofocus />
                    </div>
                    <x-button type="button" class="btn" wire:click="confirmarPorcentajeCuota">
                        {{ __('Continuar') }}
                    </x-button>
                </div>
            @else
                <p class="mb-2">
                    @if ($subEtapaCuota === 'valor')
                        {{ __('Etiqueta marcada:') }} <strong class="mayusculas">{{ $textoEtiquetaCuota }}</strong>.
                        {{ __('Ahora selecciona con el ratón el IMPORTE de esa cuota (IVA :tipo%).', ['tipo' => $tipoIvaManual]) }}
                    @else
                        {{ __('IVA :tipo%: selecciona con el ratón la ETIQUETA de esa cuota (ej. "Cuota IVA 21%") en el texto de abajo.', ['tipo' => $tipoIvaManual]) }}
                    @endif
                    <button type="button" class="ml-2 text-gray-500 underline" wire:click="cambiarPorcentajeCuota">
                        {{ __('(no es este %)') }}
                    </button>
                </p>

                <pre x-data x-on:mouseup="
                        const sel = window.getSelection();
                        if (sel.rangeCount === 0 || sel.isCollapsed) return;
                        const range = sel.getRangeAt(0);
                        if (!$el.contains(range.commonAncestorContainer)) return;
                        $wire.marcar(range.startOffset, range.endOffset);
                        sel.removeAllRanges();
                    " class="border rounded p-3 text-xs whitespace-pre overflow-auto select-text bg-gray-50 dark:bg-gray-900" style="max-height: 50vh;">{{ $texto }}</pre>
            @endif
        @elseif ($etiquetaCampoActual)
            @if ($valorDetectadoActual && ! $pidiendoValorConEtiqueta)
                <div class="mb-3 flex items-center justify-between border rounded p-2 bg-gray-50 dark:bg-gray-900">
                    <span class="text-sm">
                        {{ __('Se detectó automáticamente:') }}
                        <span class="mayusculas font-medium">{{ $valorDetectadoActual }}</span>
                    </span>
                    <x-button type="button" class="btn" wire:click="usarValorDetectado">
                        {{ __('Es correcto, usar este valor') }}
                    </x-button>
                </div>
            @endif

            @if ($usarPosicion)
                <p class="mb-2">
                    {!! __('No hay etiqueta: selecciona con el ratón el VALOR de <strong>:campo</strong> en el texto de abajo. Se anclará por su posición en la página, no por texto.', ['campo' => mb_strtoupper($etiquetaCampoActual)]) !!}
                </p>
            @elseif ($pidiendoValorConEtiqueta)
                <p class="mb-2">
                    {{ __('Etiqueta marcada:') }} <strong class="mayusculas">{{ $textoEtiquetaMarcada }}</strong>.
                    {!! __('Ahora selecciona con el ratón el VALOR de <strong>:campo</strong> en el texto de abajo.', ['campo' => mb_strtoupper($etiquetaCampoActual)]) !!}
                </p>
            @elseif ($pidiendoEtiqueta)
                <p class="mb-2">
                    {!! __('Selecciona con el ratón la ETIQUETA (el texto de referencia, no el valor) de <strong>:campo</strong> en el texto de abajo.', ['campo' => mb_strtoupper($etiquetaCampoActual)]) !!}
                </p>
            @elseif ($valorDetectadoActual)
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

            @if ($permiteValorManual)
                @if ($imagenCabecera)
                    <div class="mt-3">
                        <img src="{{ $imagenCabecera }}" alt="{{ __('Cabecera del PDF') }}" class="border rounded max-w-full" style="max-height: 30vh;" />
                    </div>
                @endif

                <div class="mt-3 flex items-end gap-2">
                    <div class="flex-1">
                        <x-label :value="__('¿No está en el texto (solo aparece en un logo/imagen)? Escríbela a mano:')" />
                        <x-input type="text" class="block mt-1 w-full" wire:model="valorManual" wire:keydown.enter.prevent="marcarManual" />
                    </div>
                    <x-button type="button" class="btn" wire:click="marcarManual">
                        {{ __('Guardar') }}
                    </x-button>
                </div>
            @endif

            @if ($puedeAnclarPorPosicion)
                <div class="mt-3">
                    <button type="button" class="btn text-sm" wire:click="activarPosicion">
                        {{ __('No hay ninguna etiqueta de texto cerca: anclar por posición en la página') }}
                    </button>
                </div>
            @endif
        @endif

        @if (count($valores))
            <div class="mt-4 text-sm">
                <div class="font-medium mb-1">{{ __('Marcado hasta ahora:') }}</div>
                <ul class="divide-y border rounded">
                    @foreach ($valores as $tipoCampoId => $datos)
                        <li class="px-3 py-1 flex justify-between items-center">
                            <span>{{ $etiquetas[$tipoCampoId] ?? $tipoCampoId }}</span>
                            <span class="flex items-center gap-2">
                                <span class="mayusculas">{{ $datos['valor'] }}</span>
                                @if (is_null($campoEnCorreccion))
                                    <button type="button" wire:click="remarcar({{ $tipoCampoId }})" class="text-gray-400 hover:text-gray-800" title="{{ __('Volver a marcar') }}">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if ($enCuotas)
            @if ($subEtapaCuota === 'porcentaje')
                <button type="button" class="btn" wire:click="terminarCuotas">
                    {{ __('Sin más cuotas, continuar') }}
                </button>
            @endif
        @elseif ($etiquetaCampoActual)
            <button type="button" class="btn" wire:click="marcarComoNoEncontrado">
                {{ __('No encontrado, siguiente') }}
            </button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>
