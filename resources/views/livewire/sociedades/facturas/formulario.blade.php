<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Nueva factura') }}
    </x-slot>

    <x-slot name="content">
        <div x-data
            x-on:foco-numero-factura-sociedad.window="setTimeout(() => document.getElementById('cap-numero-factura-sociedad')?.focus(), 100)"
            x-on:foco-razon-social-sociedad.window="setTimeout(() => document.getElementById('cap-razon-social-sociedad')?.focus(), 100)"
            x-on:foco-nombre-persona-sociedad.window="setTimeout(() => document.getElementById('cap-nombre-persona-sociedad')?.focus(), 100)">

            <div class="flex items-end gap-3">
                <div class="w-1/5">
                    <x-label :value="__('País')" />
                    <x-select class="block mt-1 w-full mayusculas"
                        wire:model.live="documento_pais_id" :disabled="$documentoComprobado">
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="w-1/5">
                    <x-label :value="__('Tipo documento')" />
                    <x-select class="block mt-1 w-full mayusculas"
                        wire:model.live="tipo_documento_id" :disabled="$documentoComprobado">
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1">
                    <x-label :value="__('Documento Id.')" />
                    <x-input class="block mt-1 w-full mayusculas" type="text" maxlength="40"
                        wire:model="documento" forzar-may autofocus
                        wire:blur="comprobarDocumento" wire:keydown.enter="comprobarDocumento"
                        :disabled="$documentoComprobado" />
                    <x-input-error for="documento" class="mt-1" />
                </div>
            </div>

            @if ($documentoComprobado)
                <div class="text-right mt-1">
                    <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                        {{ __('Cambiar documento') }}
                    </button>
                </div>

                @if ($proveedorExistente)
                    <div class="mt-2">
                        <x-label :value="__('Proveedor')" />
                        <div class="mt-1 py-2 mayusculas text-green-700 dark:text-green-400">{{ $proveedorNombre }}</div>
                    </div>
                @elseif ($esTipoDocumentoCif)
                    <div class="grid grid-cols-2 gap-3 mt-2">
                        <div>
                            <x-label :value="__('Razón social')" />
                            <x-input id="cap-razon-social-sociedad" class="block mt-1 w-full mayusculas" type="text" wire:model="razon_social" />
                            <x-input-error for="razon_social" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Nombre comercial')" />
                            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="nombre_comercial" />
                            <x-input-error for="nombre_comercial" class="mt-1" />
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-3 mt-2">
                        <div>
                            <x-label :value="__('Nombre')" />
                            <x-input id="cap-nombre-persona-sociedad" class="block mt-1 w-full" type="text" wire:model="nombre" />
                            <x-input-error for="nombre" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Apellido 1')" />
                            <x-input class="block mt-1 w-full" type="text" wire:model="apellido1" />
                            <x-input-error for="apellido1" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Apellido 2')" />
                            <x-input class="block mt-1 w-full" type="text" wire:model="apellido2" />
                            <x-input-error for="apellido2" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-2">
                        <div>
                            <x-label :value="__('Género')" />
                            <x-select class="block mt-1 w-full mayusculas" wire:model="genero_id">
                                <option value="">{{ __('--') }}</option>
                                @foreach ($generos as $genero)
                                    <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error for="genero_id" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Fecha de nacimiento')" />
                            <x-input class="block mt-1 w-full" type="date" wire:model="fecha_nacimiento" />
                            <x-input-error for="fecha_nacimiento" class="mt-1" />
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                    <div>
                        <x-label :value="__('Número de factura')" />
                        <x-input id="cap-numero-factura-sociedad" class="block mt-1 w-full" type="text" wire:model="numero_factura" />
                        <x-input-error for="numero_factura" class="mt-1" />
                    </div>
                    <div>
                        <x-label :value="__('Fecha')" />
                        <x-input class="block mt-1 w-full" type="date" wire:model="fecha" />
                        <x-input-error for="fecha" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-label :value="__('Base imponible')" />
                    <x-input class="block mt-1 w-full md:w-1/2 text-right" type="number" step="0.01" wire:model="importe_base" />
                    <x-input-error for="importe_base" class="mt-1" />
                </div>

                <div class="mt-4">
                    <x-label :value="__('Cuotas de IVA')" />
                    <div class="mt-1 space-y-2">
                        @foreach ($cuotas as $i => $cuota)
                            <div class="flex items-center gap-2" wire:key="cuota-{{ $i }}">
                                <div class="w-28">
                                    <x-input type="number" step="0.01" class="block w-full" placeholder="%"
                                        wire:model="cuotas.{{ $i }}.tipo_iva" />
                                </div>
                                <div class="w-40">
                                    <x-input type="number" step="0.01" class="block w-full text-right" placeholder="{{ __('Importe') }}"
                                        wire:model="cuotas.{{ $i }}.importe" />
                                </div>
                                <button type="button" class="text-gray-400 hover:text-red-600" wire:click="quitarCuota({{ $i }})">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <x-input-error for="cuotas.*.tipo_iva" class="mt-1" />
                    <x-input-error for="cuotas.*.importe" class="mt-1" />
                    <button type="button" class="btn text-sm mt-2" wire:click="addCuota">
                        <i class="fa-solid fa-plus mr-1"></i>{{ __('Añadir cuota de IVA') }}
                    </button>
                </div>

                <div class="mt-4">
                    <x-label :value="__('Importe total')" />
                    <x-input class="block mt-1 w-full md:w-1/2 text-right" type="number" step="0.01" wire:model="importe_total" />
                    <x-input-error for="importe_total" class="mt-1" />
                </div>

                <div class="mt-4">
                    <input type="file" id="factura-sociedad-fichero" wire:model="fichero" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                    <x-button type="button" class="btn {{ $fichero ? 'btn-guardar' : '' }}"
                        onclick="document.getElementById('factura-sociedad-fichero').click()"
                        title="{{ $fichero ? $fichero->getClientOriginalName() : __('Adjuntar fichero (opcional, no se analiza)') }}">
                        <i class="fa-solid fa-paperclip mr-1"></i>{{ __('Adjuntar fichero') }}
                    </x-button>
                    <x-input-error for="fichero" class="mt-1" />
                </div>
            @endif
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if ($documentoComprobado)
            <x-button type="button" class="btn btn-guardar" wire:click="guardar">
                {{ __('Guardar') }}
            </x-button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>
