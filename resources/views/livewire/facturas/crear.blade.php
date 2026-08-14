<x-botonera-page>
    <x-slot name="title">
        {{ __('Facturas') }}
    </x-slot>

    <x-slot name="content">
        {{-- Los focos van con retraso a propósito: Livewire repinta al responder, y sin
             esperar el cursor iría al campo que está a punto de cambiar. La pregunta de
             «el proveedor no existe» la hace SweetAlert (swalConfirm, en mysweetalert2.js). --}}
        <div x-data
            x-on:foco-documento.window="setTimeout(() => { const c = document.getElementById('cap-documento'); c?.focus(); c?.select(); }, 100)"
            x-on:foco-numero.window="setTimeout(() => document.getElementById('cap-numero')?.focus(), 100)">

            <div class="w-full shadow-sm border rounded-lg overflow-x-auto">
                <table class="w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-3">{{ __('País') }}</th>
                            <th class="py-3 px-3">{{ __('Tipo') }}</th>
                            <th class="py-3 px-3">{{ __('Documento') }}</th>
                            <th class="py-3 px-3">{{ __('Proveedor') }}</th>
                            <th class="py-3 px-3">{{ __('Número factura') }}</th>
                            <th class="py-3 px-3">{{ __('Fecha') }}</th>
                            <th class="py-3 px-3 text-right">{{ __('Importe') }}</th>
                            @if (count($actividades))
                                <th class="py-3 px-3">{{ __('Actividad') }}</th>
                            @endif
                            <th class="py-3 px-3">{{ __('Adjunto') }}</th>
                            <th class="py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {{-- Las ya insertadas, en el orden en que se metieron. --}}
                        @foreach ($metidas as $fila)
                            <tr wire:key="metida-{{ $loop->index }}" class="text-gray-600 dark:text-gray-400">
                                <td class="px-3 py-2">{{ $fila['pais'] }}</td>
                                <td class="px-3 py-2">{{ $fila['tipo'] }}</td>
                                <td class="px-3 py-2">{{ $fila['documento'] }}</td>
                                <td class="px-3 py-2 min-w-52 mayusculas">{{ $fila['proveedor'] }}</td>
                                <td class="px-3 py-2">{{ $fila['numero'] }}</td>
                                <td class="px-3 py-2">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($fila['importe'], 2, ',', '.') }} €</td>
                                @if (count($actividades))
                                    <td class="px-3 py-2">{{ $fila['actividad'] }}</td>
                                @endif
                                <td class="px-3 py-2">
                                    @if ($fila['adjunto'])
                                        <i class="fa-solid fa-paperclip text-green-600"
                                            title="{{ $fila['adjunto'] }}"></i>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-green-600"><i class="fa-solid fa-check"></i></td>
                            </tr>
                        @endforeach

                        {{-- La línea en curso: todo en una, del documento al botón. --}}
                        <tr class="bg-gray-50 dark:bg-gray-900/40 align-top">
                            <td class="px-3 py-2">
                                <x-select id="cap-pais" class="block w-28 h-10 py-0 text-sm px-2 mayusculas"
                                    wire:model.live="documento_pais_id" :disabled="$documentoValido">
                                    @foreach ($paises as $pais)
                                        <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                                    @endforeach
                                </x-select>
                            </td>
                            <td class="px-3 py-2">
                                <x-select id="cap-tipo" class="block w-40 h-10 py-0 text-sm px-2 mayusculas"
                                    wire:model.live="tipo_documento_id" :disabled="$documentoValido">
                                    @foreach ($tipos as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </x-select>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <x-input id="cap-documento" class="block w-36 mayusculas h-10 text-sm px-2"
                                        type="text" maxlength="40" wire:model="documento" forzar-may autofocus
                                        wire:keydown.enter="comprobarDocumento" :disabled="$documentoValido" />
                                    @if ($documentoValido)
                                        <x-button type="button" class="btn h-10" wire:click="cambiarDocumento"
                                            title="{{ __('Cambiar') }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="btn btn-nuevo h-10" id="cap-comprobar"
                                            wire:click="comprobarDocumento" title="{{ __('Comprobar') }}">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                        </x-button>
                                    @endif
                                </div>
                                <x-input-error for="documento" class="mt-1" />
                            </td>
                            <td class="px-3 py-2 min-w-52 mayusculas text-green-700 dark:text-green-400">
                                {{ $proveedorNombre }}
                            </td>
                            <td class="px-3 py-2">
                                <x-input id="cap-numero" class="block w-40 h-10 text-sm px-2" type="text"
                                    wire:model="numero_factura" :disabled="! $documentoValido"
                                    wire:keydown.enter="anadir" />
                                <x-input-error for="numero_factura" class="mt-1" />
                            </td>
                            <td class="px-3 py-2">
                                <x-input id="cap-fecha" class="block w-44 h-10 text-sm px-2" type="date"
                                    wire:model="fecha" :disabled="! $documentoValido"
                                    wire:keydown.enter="anadir" />
                                <x-input-error for="fecha" class="mt-1" />
                            </td>
                            <td class="px-3 py-2">
                                <x-input id="cap-importe" class="block w-28 h-10 text-sm px-2 text-right" type="number"
                                    step="0.01" wire:model="importe" :disabled="! $documentoValido"
                                    wire:keydown.enter="anadir" />
                                <x-input-error for="importe" class="mt-1" />
                            </td>
                            @if (count($actividades))
                                <td class="px-3 py-2">
                                    <x-select id="cap-actividad" class="block w-36 h-10 py-0 text-sm px-2" wire:model="actividad_id">
                                        <option value="">{{ __('Sin actividad') }}</option>
                                        @foreach ($actividades as $id => $nombre)
                                            <option value="{{ $id }}">{{ $nombre }}</option>
                                        @endforeach
                                    </x-select>
                                </td>
                            @endif
                            {{-- El papel de esta factura, si está a mano: el clip abre el selector y se
                                 pone verde cuando ya hay fichero. Cambiar de clave en cada vuelta es
                                 lo que deja el input vacío para la línea siguiente. --}}
                            <td class="px-3 py-2">
                                <input type="file" id="cap-fichero" wire:key="cap-fichero-{{ $vuelta }}"
                                    wire:model="fichero" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                                <x-button type="button" class="btn h-10 {{ $fichero ? 'btn-guardar' : '' }}"
                                    onclick="document.getElementById('cap-fichero').click()"
                                    :disabled="! $documentoValido"
                                    title="{{ $fichero ? $fichero->getClientOriginalName() : __('Adjuntar fichero') }}">
                                    <i class="fa-solid fa-paperclip"></i>
                                </x-button>
                                <x-input-error for="fichero" class="mt-1" />
                            </td>
                            <td class="px-3 py-2">
                                <x-button type="button" class="btn btn-guardar h-10" id="cap-anadir" wire:click="anadir"
                                    :disabled="! $documentoValido" title="{{ __('Añadir') }}">
                                    <i class="fa-solid fa-plus"></i>
                                </x-button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- El alta de proveedor es la de siempre, con el documento ya comprobado. --}}
        @livewire('proveedores.formulario')
    </x-slot>

    <x-slot name="footer">
        <x-button type="button" class="btn btn-cerrar" wire:click="cerrar">{{ __('Cerrar') }}</x-button>
    </x-slot>
</x-botonera-page>
