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
        <div class="mt-3">
            {{-- De cuotas o de derrama: en un ejercicio hay un presupuesto de cuotas y
                 puede haber varias derramas, cada una con su propia cuenta de ingresos. --}}
            <x-label for="p-tipo" :value="__('Tipo')" />
            <x-select id="p-tipo" class="block mt-1 w-full py-3" wire:model="tipo_presupuesto_id">
                @foreach ($tipos as $id => $descripcion)
                    <option value="{{ $id }}">{{ $descripcion }}</option>
                @endforeach
            </x-select>
            <x-input-error for="tipo_presupuesto_id" class="mt-2" />
        </div>
        @if (count($actividades))
            <div class="mt-3">
                {{-- Solo aparece en comunidades que se dividen en varias actividades
                     (dos torres, dos negocios bajo el mismo CIF). En blanco, este
                     presupuesto no separa nada. --}}
                <x-label for="p-actividad" :value="__('Actividad')" />
                <x-select id="p-actividad" class="block mt-1 w-full py-3" wire:model="actividad_id">
                    <option value="">{{ __('Sin actividad') }}</option>
                    @foreach ($actividades as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="actividad_id" class="mt-2" />
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
