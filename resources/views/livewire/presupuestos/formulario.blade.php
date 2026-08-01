<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar presupuesto') : __('Nuevo presupuesto') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="p-nombre" :value="__('Nombre')" />
            <x-input id="p-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-3">
            <x-label for="p-anho" :value="__('Año')" />
            <x-input id="p-anho" class="block mt-1 w-full" type="number" wire:model="anho" />
            <x-input-error for="anho" class="mt-2" />
        </div>
        <div class="flex w-full mt-3">
            <div class="w-1/2 mr-4">
                <x-label for="p-periodicidad" :value="__('Periodicidad')" />
                <x-select id="p-periodicidad" class="block mt-1 w-full" wire:model.live="periodicidad_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($periodicidades as $periodicidad)
                        <option value="{{ $periodicidad->id }}">{{ $periodicidad->descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="periodicidad_id" class="mt-2" />
            </div>
            <div class="w-1/2">
                <x-label for="p-fecha-primer-pago" :value="__('Fecha del primer pago')" />
                <x-input id="p-fecha-primer-pago" class="block mt-1 w-full" type="date" wire:model.live="fecha_primer_pago" />
                <x-input-error for="fecha_primer_pago" class="mt-2" />
            </div>
        </div>

        @if (count($this->fechasPago))
            <div class="mt-3">
                <x-label :value="__('Fechas de pago resultantes')" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($this->fechasPago as $fecha)
                        <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-xs">
                            {{ __('Pago') }} {{ $loop->iteration }}: {{ $fecha->format('d/m/Y') }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
