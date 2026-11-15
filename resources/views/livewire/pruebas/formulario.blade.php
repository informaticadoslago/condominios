<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar prueba') : __('Nueva prueba') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="pr-evaluacion" :value="__('Evaluación')" />
            <x-select id="pr-evaluacion" class="block mt-1 w-full py-3" wire:model="evaluacion_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($evaluaciones as $evaluacion)
                    <option value="{{ $evaluacion->id }}">{{ $evaluacion->curso->nombre }} · {{ $evaluacion->nombre }}</option>
                @endforeach
            </x-select>
            <x-input-error for="evaluacion_id" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="pr-nombre" :value="__('Nombre')" />
            <x-input id="pr-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" maxlength="100" autofocus />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="pr-fecha" :value="__('Fecha')" />
            <x-input id="pr-fecha" class="block mt-1 w-full" type="date" wire:model="fecha" />
            <x-input-error for="fecha" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="pr-maxima" :value="__('Nota sobre (puntuación máxima)')" />
            <x-input id="pr-maxima" class="block mt-1 w-full" type="text" inputmode="decimal" wire:model="puntuacion_maxima" placeholder="30" />
            <x-input-error for="puntuacion_maxima" class="mt-2" />
            @if ($tieneNotas)
                <p class="mt-2 text-sm text-red-600">
                    {{ __('Esta prueba ya tiene notas: si cambias la puntuación máxima se recalculan todas las notas sobre 10 y se pierden las retocadas a mano.') }}
                </p>
            @endif
        </div>
        <div class="mt-4">
            <x-label for="pr-peso" :value="__('Peso en la evaluación (%)')" />
            <x-input id="pr-peso" class="block mt-1 w-full" type="text" inputmode="decimal" wire:model="peso" placeholder="20" />
            <x-input-error for="peso" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
