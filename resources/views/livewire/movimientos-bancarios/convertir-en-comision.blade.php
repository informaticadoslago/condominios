<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ __('Convertir en comisión bancaria') }}
    </x-slot>

    <x-slot name="content">
        @if ($movimiento)
            <dl class="text-sm grid grid-cols-3 gap-y-1 mb-4">
                <dt class="text-gray-500">{{ __('Fecha') }}</dt>
                <dd class="col-span-2">{{ $movimiento->fecha_valor?->format('d/m/Y') }}</dd>
                <dt class="text-gray-500">{{ __('Descripción') }}</dt>
                <dd class="col-span-2">{{ $movimiento->descripcion }}</dd>
                <dt class="text-gray-500">{{ __('Importe') }}</dt>
                <dd class="col-span-2">{{ number_format(abs($movimiento->importe), 2, ',', '.') }}</dd>
            </dl>

            @if ($tipoConocido)
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Tipo reconocido: :tipo', ['tipo' => $this->tiposComisionExistentes()->firstWhere('codigo', $tipoConocido)?->descripcion ?? $tipoConocido]) }}
                </p>
            @else
                <p class="text-sm text-gray-500 mb-2">
                    {{ __('El texto ":tipo" no está catalogado todavía: se dará de alta con el tipo que elijas, así la próxima comisión igual se reconoce sola.', ['tipo' => $movimiento->tipo_operacion]) }}
                </p>

                <x-label for="cb-tipo-elegido" :value="__('Tipo de comisión')" />
                <x-select id="cb-tipo-elegido" class="block mt-1 w-full py-3" wire:model.live="tipoElegido">
                    @foreach ($this->tiposComisionExistentes() as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                    @endforeach
                    <option value="{{ \App\Livewire\MovimientosBancarios\ConvertirEnComision::NUEVO_TIPO }}">{{ __('+ Nuevo tipo…') }}</option>
                </x-select>

                @if ($tipoElegido === \App\Livewire\MovimientosBancarios\ConvertirEnComision::NUEVO_TIPO)
                    <div class="mt-3">
                        <x-label for="cb-nuevo-nombre" :value="__('Nombre del tipo nuevo')" />
                        <x-input id="cb-nuevo-nombre" type="text" class="block mt-1 w-full" wire:model="nuevoNombre"
                            placeholder="{{ __('p. ej. Comisión transferencia') }}" />
                    </div>
                    <div class="mt-3">
                        <x-label for="cb-nueva-cuenta" :value="__('Cuenta de gasto')" />
                        <x-select id="cb-nueva-cuenta" class="block mt-1 w-full py-3" wire:model="nuevaCuentaContableId">
                            <option value="">{{ __('Elige una cuenta') }}</option>
                            @foreach ($this->cuentasDeGasto() as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                            @endforeach
                        </x-select>
                    </div>
                @endif
            @endif

            @if ($error)
                <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
            @endif
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="confirmar"
            title="{{ __('Convertir') }}">{{ __('Convertir') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
