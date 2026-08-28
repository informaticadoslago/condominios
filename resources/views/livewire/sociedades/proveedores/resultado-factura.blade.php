<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Factura procesada') }}
    </x-slot>

    <x-slot name="content">
        @foreach ($resultados as $resultado)
            <div class="border rounded-lg p-4 mb-4 last:mb-0">
                <div class="font-medium mb-3 truncate flex items-center justify-between">
                    <span><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i> {{ $resultado['nombrelocal'] }}</span>
                    @if ($resultado['verifactu'] ?? false)
                        <span class="text-xs font-normal text-green-700"><i class="fa-solid fa-qrcode mr-1"></i>{{ __('VeriFactu') }}</span>
                    @elseif ($resultado['con_plantilla'] ?? false)
                        <span class="text-xs font-normal flex items-center gap-2">
                            <span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>{{ __('Con plantilla') }}</span>
                            <button type="button" class="text-gray-400 hover:text-indigo-600" title="{{ __('Regenerar plantilla con IA') }}"
                                wire:click="generarPlantillaConIA({{ $loop->index }})"
                                wire:loading.attr="disabled" wire:target="generarPlantillaConIA({{ $loop->index }})">
                                <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="generarPlantillaConIA({{ $loop->index }})"></i>
                                <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="generarPlantillaConIA({{ $loop->index }})"></i>
                            </button>
                            <button type="button" class="text-gray-400 hover:text-red-600" title="{{ __('Borrar plantilla y volver a marcarla de cero') }}"
                                wire:click="borrarPlantilla({{ $loop->index }})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </span>
                    @endif
                </div>

                @php
                    $conPlantilla = $resultado['con_plantilla'] ?? false;
                    $tipoCampo = [
                        'razon_social'   => \App\Models\TipoCampoPlantillaFactura::RAZON_SOCIAL,
                        'cif'            => \App\Models\TipoCampoPlantillaFactura::CIF,
                        'numero_factura' => \App\Models\TipoCampoPlantillaFactura::NUMERO_FACTURA,
                        'fecha'          => \App\Models\TipoCampoPlantillaFactura::FECHA,
                        'importe_base'   => \App\Models\TipoCampoPlantillaFactura::IMPORTE_BASE,
                        'importe_total'  => \App\Models\TipoCampoPlantillaFactura::IMPORTE_TOTAL,
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
                        <div>
                            <x-label :value="__('Base imponible')" />
                            <div class="mt-1 flex items-center justify-between">
                                <span>{{ $resultado['plantilla']['importe_base'] ?? __('No encontrada') }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['importe_base'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <x-label :value="__('Importe total')" />
                            <div class="mt-1 flex items-center justify-between">
                                <span>{{ $resultado['plantilla']['importe_total'] ?? __('No encontrado') }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Corregir') }}"
                                    wire:click="corregirCampo({{ $loop->index }}, {{ $tipoCampo['importe_total'] }})">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-span-2">
                            <x-label :value="__('Cuotas de IVA')" />
                            <div class="mt-1">
                                @forelse ($resultado['plantilla']['cuotas_iva'] ?? [] as $cuota)
                                    <span class="inline-block border rounded px-2 py-0.5 mr-1 mb-1">{{ $cuota['tipo_iva'] }}% : {{ $cuota['importe'] }}</span>
                                @empty
                                    <span class="text-gray-500">{{ __('Sin cuotas de IVA (exenta, o no se han marcado)') }}</span>
                                @endforelse
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
                            <x-button type="button" class="btn" wire:click="generarPlantillaConIA({{ $loop->index }})"
                                wire:loading.attr="disabled" wire:target="generarPlantillaConIA({{ $loop->index }})">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1" wire:loading.remove wire:target="generarPlantillaConIA({{ $loop->index }})"></i>
                                <i class="fa-solid fa-spinner fa-spin mr-1" wire:loading wire:target="generarPlantillaConIA({{ $loop->index }})"></i>
                                {{ __('Generar plantilla con IA') }}
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
                @else
                    <div class="mt-3 flex items-end justify-end gap-2">
                        @if (! $conPlantilla)
                            <x-button type="button" class="btn" wire:click="completarPlantilla({{ $loop->index }})">
                                {{ __('Completar plantilla') }}
                            </x-button>
                        @endif
                        @if ($documentoParaAlta)
                            @unless ($proveedorExiste[$loop->index] ?? false)
                                <div class="w-1/3">
                                    <x-label :value="__('Tipo')" />
                                    <x-select class="block mt-1 w-full mayusculas"
                                        wire:model="tipoProveedor.{{ $loop->index }}">
                                        <option value="">{{ __('--') }}</option>
                                        @foreach ($tiposProveedor as $tipo)
                                            <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                                        @endforeach
                                    </x-select>
                                </div>
                            @endunless
                            <x-button type="button" class="btn" wire:click="darDeAlta({{ $loop->index }})">
                                {{ __('Dar de alta / adjuntar factura') }}
                            </x-button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
    </x-slot>
</x-dosl.dialog-modal>
