@php
    $definiciones = $this->definicionesFiltro();
    $hayTexto     = collect($definiciones)->contains(fn ($filtro) => $filtro['tipo'] === 'texto');
@endphp

@if (count($definiciones))
    <div class="py-3 px-6 flex flex-wrap items-center gap-3 border-b">
        @foreach ($definiciones as $filtro)
            <div class="flex items-center gap-2">
                {{-- Un punto más grande y en negrita: si no, la etiqueta se pierde al lado del select. --}}
                <x-label class="font-semibold text-base">{{ $filtro['etiqueta'] }}:</x-label>

                @if ($filtro['tipo'] === 'select')
                    <select wire:model.live="filtros.{{ $filtro['clave'] }}"
                        @disabled($verSoloSeleccionados ?? false)
                        class="disabled:opacity-50 disabled:cursor-not-allowed">
                        @foreach ($filtro['opciones'] as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                @else
                    <x-input wire:model="filtros.{{ $filtro['clave'] }}" wire:keydown.enter="aplicarFiltro"
                        :disabled="$verSoloSeleccionados ?? false"
                        class="disabled:opacity-50 disabled:cursor-not-allowed" />
                @endif
            </div>
        @endforeach

        @if ($hayTexto)
            <x-button type="button" wire:click="aplicarFiltro" title="{{ __('Aplicar') }}"
                :disabled="$verSoloSeleccionados ?? false" class="disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-filter"></i>{{ __('Aplicar') }}
            </x-button>
        @endif
    </div>
@endif
