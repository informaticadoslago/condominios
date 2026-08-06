<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Miembros de :grupo', ['grupo' => $grupoNombre]) }}
    </x-slot>

    <x-slot name="content">
        <p class="mb-3">
            {{ __('Marca los inmuebles que pertenecen a este grupo. Si dejas el coeficiente en blanco, se usa el propio del inmueble.') }}
        </p>
        <div class="max-h-96 overflow-y-auto">
            <table class="table-striped w-full table-auto text-sm text-left">
                <thead class="font-medium border-b">
                    <tr>
                        <th class="py-2 px-4">{{ __('Pertenece') }}</th>
                        <th class="py-2 px-4">{{ __('Planta') }}</th>
                        <th class="py-2 px-4">{{ __('Puerta') }}</th>
                        <th class="py-2 px-4">{{ __('Coeficiente propio') }}</th>
                        <th class="py-2 px-4">{{ __('Coeficiente en el grupo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($inmuebles as $inmueble)
                        <tr wire:key="miembro-{{ $inmueble->id }}">
                            <td class="py-2 px-4">
                                <input type="checkbox" wire:model="miembros.{{ $inmueble->id }}.seleccionado" />
                            </td>
                            <td class="py-2 px-4">{{ $inmueble->planta }}</td>
                            <td class="py-2 px-4">{{ $inmueble->puerta }}</td>
                            <td class="py-2 px-4">{{ $inmueble->coeficiente }}%</td>
                            <td class="py-2 px-4">
                                <x-input type="number" step="0.001" class="w-28"
                                    placeholder="{{ $inmueble->coeficiente }}"
                                    wire:model="miembros.{{ $inmueble->id }}.coeficiente" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
