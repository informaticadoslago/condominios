<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        @if ($contenedoraId)
            {{ $itemId ? __('Modificar parte de :prueba', ['prueba' => $contenedoraNombre]) : __('Nueva parte de :prueba', ['prueba' => $contenedoraNombre]) }}
        @else
            {{ $itemId ? __('Modificar prueba') : __('Nueva prueba') }}
        @endif
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="pr-evaluacion" :value="__('Evaluación')" />
            {{-- La de una parte es la de su prueba, y la de una prueba con partes dentro no se cambia. --}}
            <x-select id="pr-evaluacion" class="block mt-1 w-full py-3 disabled:opacity-50" wire:model="evaluacion_id"
                :disabled="$contenedoraId || $tienePartes">
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
        {{-- Una parte no lleva partes: la casilla solo sale en las pruebas sueltas. --}}
        @unless ($contenedoraId)
            <div class="mt-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer" for="pr-con-partes">
                    <input type="checkbox" id="pr-con-partes" wire:model.live="con_partes" class="rounded disabled:opacity-50"
                        @disabled($tienePartes || $tieneNotas) />
                    {{ __('Tiene partes (teoría, práctica…): su nota es la media de ellas') }}
                </label>
                <x-input-error for="con_partes" class="mt-2" />
            </div>
        @endunless
        @if ($con_partes)
            <div class="mt-4">
                <x-label for="pr-minima" :value="__('Nota mínima en cada parte para hacer media (0 = sin mínimo)')" />
                <x-input id="pr-minima" class="block mt-1 w-full" type="text" inputmode="decimal" wire:model="nota_minima" placeholder="5" />
                <x-input-error for="nota_minima" class="mt-2" />
            </div>
        @else
            <div class="mt-4">
                <x-label for="pr-calificacion" :value="__('Se califica con')" />
                <x-select id="pr-calificacion" class="block mt-1 w-full py-3 disabled:opacity-50" wire:model.live="tipo_calificacion"
                    :disabled="$tieneNotas">
                    @foreach (\App\Models\Prueba::tiposCalificacion() as $tipo => $nombreTipo)
                        <option value="{{ $tipo }}">{{ $nombreTipo }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="tipo_calificacion" class="mt-2" />
            </div>
        @endif
        {{-- La que se califica con palabras no tiene puntuación máxima que escribir. --}}
        @if (! $con_partes && $numerica)
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
        @endif
        <div class="mt-4">
            <x-label for="pr-peso" :value="$contenedoraId ? __('Peso dentro de :prueba (%)', ['prueba' => $contenedoraNombre]) : __('Peso en la evaluación (%)')" />
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
