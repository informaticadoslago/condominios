<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Factura procesada') }}
    </x-slot>

    <x-slot name="content">
        @foreach ($resultados as $resultado)
            <div class="border rounded-lg p-4 mb-4 last:mb-0">
                <div class="font-medium mb-3 truncate flex items-center justify-between">
                    <span><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i> {{ $resultado['nombrelocal'] }}</span>
                    @if ($resultado['con_plantilla'] ?? false)
                        <span class="text-xs font-normal text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>{{ __('Con plantilla') }}</span>
                    @endif
                </div>

                @php
                    $conPlantilla = $resultado['con_plantilla'] ?? false;
                    $tipoCampo = [
                        'razon_social'   => \App\Models\TipoCampoPlantillaFactura::RAZON_SOCIAL,
                        'cif'            => \App\Models\TipoCampoPlantillaFactura::CIF,
                        'numero_factura' => \App\Models\TipoCampoPlantillaFactura::NUMERO_FACTURA,
                        'fecha'          => \App\Models\TipoCampoPlantillaFactura::FECHA,
                        'importe'        => \App\Models\TipoCampoPlantillaFactura::IMPORTE,
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-label :value="__('Razón social')" />
                        <div class="mt-1 flex items-center justify-between">
                            <span class="mayusculas">{{ ($conPlantilla ? $resultado['plantilla']['razon_social'] : $resultado['datos']['razon_social']) ?? __('No encontrada') }}</span>
                            @if ($conPlantilla)
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['razon_social'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <x-label :value="__('CIF')" />
                        <div class="mt-1 flex items-center justify-between">
                            <span>{{ ($conPlantilla ? $resultado['plantilla']['cif'] : $resultado['datos']['cif']) ?? __('No encontrado') }}</span>
                            @if ($conPlantilla)
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['cif'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($conPlantilla)
                        <div>
                            <x-label :value="__('Nº de factura')" />
                            <div class="mt-1 flex items-center justify-between">
                                <span>{{ $resultado['plantilla']['numero_factura'] ?? __('No encontrado') }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['numero_factura'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <x-label :value="__('Fecha')" />
                            <div class="mt-1 flex items-center justify-between">
                                <span>{{ $resultado['plantilla']['fecha'] ?? __('No encontrada') }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['fecha'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-span-2">
                            <x-label :value="__('Importe')" />
                            <div class="mt-1 flex items-center justify-between">
                                <span>{{ $resultado['plantilla']['importe'] ?? __('No encontrado') }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['importe'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @else
                        <div>
                            <x-label :value="__('Fecha')" />
                            <div class="mt-1">{{ $resultado['datos']['fecha'] ?? __('No encontrada') }}</div>
                        </div>
                        <div>
                            <x-label :value="__('Importes')" />
                            <div class="mt-1">
                                @forelse ($resultado['datos']['importes'] ?? [] as $importe)
                                    <span class="inline-block border rounded px-2 py-0.5 mr-1 mb-1">{{ $importe }}</span>
                                @empty
                                    {{ __('No encontrados') }}
                                @endforelse
                            </div>
                        </div>
                        <div class="col-span-2 text-right">
                            <x-button type="button" class="btn" wire:click="completarPlantilla({{ $loop->index }})">
                                {{ __('Completar plantilla') }}
                            </x-button>
                        </div>
                    @endif
                </div>

                @php
                    $documentoParaAlta = $conPlantilla ? ($resultado['plantilla']['cif'] ?? null) : ($resultado['datos']['cif'] ?? null);
                @endphp
                @if ($resultado['dado_de_alta'] ?? false)
                    <div class="mt-3 text-sm text-green-700">
                        <i class="fa-solid fa-circle-check mr-1"></i>
                        {{ $resultado['dado_de_alta']['creado']
                            ? __('Proveedor ":proveedor" creado y factura adjuntada.', ['proveedor' => $resultado['dado_de_alta']['proveedor']])
                            : __('Factura adjuntada al proveedor existente ":proveedor".', ['proveedor' => $resultado['dado_de_alta']['proveedor']]) }}
                    </div>
                @elseif ($documentoParaAlta)
                    <div class="mt-3 text-right">
                        <x-button type="button" class="btn" wire:click="darDeAlta({{ $loop->index }})">
                            {{ __('Dar de alta / adjuntar factura') }}
                        </x-button>
                    </div>
                @endif
            </div>
        @endforeach
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
    </x-slot>
</x-dosl.dialog-modal>
