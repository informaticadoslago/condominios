<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar empresa contable') : __('Nueva empresa contable') }}
    </x-slot>

    <x-slot name="content">
        <div x-data="{ pestana: 'datos' }">
            <div class="flex border-b border-gray-200 dark:border-zinc-700">
                <button type="button" x-on:click="pestana = 'datos'"
                    class="px-3 py-2 text-sm font-medium border-b-2"
                    :class="pestana === 'datos'
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'">
                    {{ __('Datos') }}
                </button>
                <button type="button" x-on:click="pestana = 'configuracion'"
                    class="px-3 py-2 text-sm font-medium border-b-2"
                    :class="pestana === 'configuracion'
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'">
                    {{ __('Configuración') }}
                </button>
            </div>

            <div x-show="pestana === 'datos'" class="mt-4">
                <div>
                    <x-label for="ec-razon-social" :value="__('Razón social')" />
                    <x-input id="ec-razon-social" class="block mt-1 w-full mayusculas" type="text"
                        wire:model="razon_social" forzar-may autofocus />
                    <x-input-error for="razon_social" class="mt-2" />
                </div>
                <div class="mt-3">
                    <x-label for="ec-cif" :value="__('CIF')" />
                    <x-input id="ec-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="cif"
                        forzar-may />
                    <x-input-error for="cif" class="mt-2" />
                </div>
            </div>

            <div x-show="pestana === 'configuracion'" class="mt-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cuentas de comisiones bancarias') }}</h3>
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('Se resuelven solas al enlazar una comunidad con esta empresa; aquí solo se consultan.') }}
                </p>
                @if ($itemId)
                    <table class="w-full text-sm text-left mt-2">
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                            @forelse ($tiposComisionBancaria as $tipo)
                                <tr>
                                    <td class="py-2 pr-2">{{ $tipo->descripcion }}</td>
                                    <td class="py-2 text-right">
                                        @if ($tipo->cuentaContable)
                                            {{ $tipo->cuentaContable->codigo }} - {{ $tipo->cuentaContable->nombre }}
                                        @else
                                            <span class="text-red-600">{{ __('sin asignar') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-2 text-gray-500" colspan="2">{{ __('Todavía no se ha enlazado ninguna comunidad con esta empresa.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('Disponible al guardar la empresa.') }}</p>
                @endif
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
