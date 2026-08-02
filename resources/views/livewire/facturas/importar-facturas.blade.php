<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="7xl">
    <x-slot name="title">
        {{ __('Importar facturas desde carpeta') }}
    </x-slot>

    <x-slot name="content">
        <div x-data="{
            async procesarCarpeta(event) {
                const pdfs = Array.from(event.target.files).filter(f => /\.pdf$/i.test(f.name));
                event.target.value = '';

                if (! pdfs.length) {
                    $wire.dispatch('toast-error', { title: '{{ __('La carpeta no tiene ningún PDF') }}' });
                    return;
                }

                await $wire.iniciarLote(pdfs.length);

                for (const pdf of pdfs) {
                    await new Promise((resolve, reject) => {
                        $wire.upload('fichero', pdf, () => resolve(), (error) => reject(error));
                    });
                    await $wire.procesarUno();
                }
            }
        }">
            @if ($total === 0)
                <label for="input-carpeta-facturas"
                    class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors border-gray-300 dark:border-gray-600">
                    <i class="fa-solid fa-folder-open text-3xl text-gray-400 mb-2"></i>
                    <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                        {{ __('Haz clic para elegir la carpeta de facturas (se incluyen sus subcarpetas)') }}
                    </span>
                    <input type="file" id="input-carpeta-facturas" webkitdirectory multiple
                        x-on:change="procesarCarpeta($event)" class="hidden" />
                </label>

                <x-input-error for="fichero" class="mt-2" />
            @else
                <div class="mb-3 text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                    @if ((count($completos) + count($incompletos) + count($noFacturas)) < $total)
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        {{ __(':procesados de :total...', ['procesados' => count($completos) + count($incompletos) + count($noFacturas), 'total' => $total]) }}
                    @else
                        <i class="fa-solid fa-circle-check text-green-600"></i>
                        {{ __(':total PDF procesados.', ['total' => $total]) }}
                    @endif
                </div>

                @if (count($completos))
                    <div class="font-medium text-sm mb-1">{{ __('Listas para importar') }}</div>
                    <div class="border rounded-lg overflow-x-auto mb-4">
                        <table class="w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="py-2 px-4">{{ __('Fichero') }}</th>
                                    <th class="py-2 px-4">{{ __('CIF') }}</th>
                                    <th class="py-2 px-4">{{ __('Razón social') }}</th>
                                    <th class="py-2 px-4">{{ __('Fecha') }}</th>
                                    <th class="py-2 px-4">{{ __('Nº factura') }}</th>
                                    <th class="py-2 px-4">{{ __('Importe') }}</th>
                                    <th class="py-2 px-4">{{ __('Acción') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($completos as $indice => $resultado)
                                    <tr wire:key="completo-{{ $indice }}">
                                        <td class="px-4 py-2"><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>{{ $resultado['nombrelocal'] }}</td>
                                        <td class="px-4 py-2">{{ $resultado['plantilla']['cif'] }}</td>
                                        <td class="px-4 py-2"><span class="mayusculas">{{ $resultado['plantilla']['razon_social'] }}</span></td>
                                        <td class="px-4 py-2">{{ $resultado['plantilla']['fecha'] }}</td>
                                        <td class="px-4 py-2">{{ $resultado['plantilla']['numero_factura'] }}</td>
                                        <td class="px-4 py-2">{{ $resultado['plantilla']['importe'] }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if ($resultado['duplicada'] ?? false)
                                                <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1"
                                                    wire:click="darDeAlta({{ $indice }}, true)" title="{{ __('Sobrescribir la factura ya existente') }}">
                                                    {{ __('Sobrescribir') }}
                                                </x-button>
                                                <x-secondary-button type="button" class="text-xs px-2 py-1 ml-1"
                                                    wire:click="descartar({{ $indice }})" title="{{ __('Descartar esta') }}">
                                                    {{ __('Descartar') }}
                                                </x-secondary-button>
                                            @else
                                                <x-button type="button" class="btn text-xs px-2 py-1" wire:click="darDeAlta({{ $indice }})">
                                                    {{ __('Importar') }}
                                                </x-button>
                                            @endif
                                            <button type="button" class="text-gray-400 hover:text-gray-800 ml-2" title="{{ __('Algún dato mal: corregir en Analizar factura') }}"
                                                wire:click="analizarEnVentanaProveedores({{ $indice }})">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" class="text-gray-400 hover:text-indigo-600 ml-1" title="{{ __('Refrescar: releer tras corregir') }}"
                                                wire:click="refrescarConPlantilla({{ $indice }})"
                                                wire:loading.attr="disabled" wire:target="refrescarConPlantilla({{ $indice }})">
                                                <i class="fa-solid fa-rotate" wire:loading.remove wire:target="refrescarConPlantilla({{ $indice }})"></i>
                                                <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="refrescarConPlantilla({{ $indice }})"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (count($incompletos))
                    <div class="font-medium text-sm mb-1">{{ __('Factura sin plantilla (o con algún dato sin resolver)') }}</div>
                    <div class="border rounded-lg overflow-x-auto mb-4">
                        <table class="w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="py-2 px-4">{{ __('Fichero') }}</th>
                                    <th class="py-2 px-4">{{ __('Detectado') }}</th>
                                    <th class="py-2 px-4">{{ __('Acción') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($incompletos as $indice => $resultado)
                                    <tr wire:key="incompleto-{{ $indice }}">
                                        <td class="px-4 py-2 align-top"><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>{{ $resultado['nombrelocal'] }}</td>
                                        <td class="px-4 py-2 align-top text-xs text-gray-500">
                                            {{ __('CIF') }}: {{ $resultado['datos']['cif'] ?? __('no detectado') }}
                                            @if ($resultado['datos']['razon_social'] ?? null)
                                                · {{ $resultado['datos']['razon_social'] }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 align-top whitespace-nowrap">
                                            <x-button type="button" class="btn text-xs px-2 py-1"
                                                wire:click="analizarEnVentanaProveedores({{ $indice }})" title="{{ __('Abrir en Analizar factura (Proveedores)') }}">
                                                {{ __('Analizar factura') }}
                                            </x-button>
                                            <button type="button" class="text-gray-400 hover:text-indigo-600 ml-2" title="{{ __('Refrescar: releer con la plantilla ya creada') }}"
                                                wire:click="refrescarConPlantilla({{ $indice }})"
                                                wire:loading.attr="disabled" wire:target="refrescarConPlantilla({{ $indice }})">
                                                <i class="fa-solid fa-rotate" wire:loading.remove wire:target="refrescarConPlantilla({{ $indice }})"></i>
                                                <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="refrescarConPlantilla({{ $indice }})"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (count($noFacturas))
                    <div class="font-medium text-sm mb-1">{{ __('No son facturas') }}</div>
                    <ul class="text-xs text-gray-500 divide-y border rounded">
                        @foreach ($noFacturas as $indice => $resultado)
                            <li class="px-3 py-1.5" wire:key="nofactura-{{ $indice }}">{{ $resultado['nombrelocal'] }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
    </x-slot>
</x-dosl.dialog-modal>
