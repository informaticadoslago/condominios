<x-botonera-page>
    <x-slot name="title">
        {{ __('Tokens de API') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Caducidad de los nuevos y revocación de los de cualquier usuario') }}
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex flex-wrap items-end gap-6 border-b">
                <div>
                    <x-label for="ta-caducidad" :value="__('Los tokens nuevos caducan a los')" />
                    <x-select id="ta-caducidad" class="block mt-1 py-3 text-sm h-auto px-3"
                        wire:model.live="caducidad">
                        @foreach ($duraciones as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ __($etiqueta) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="caducidad" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('Solo afecta a los que se creen a partir de ahora.') }}
                    </p>
                </div>
            </div>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Usuario o nombre del token'])
            </div>

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Usuario') }}</th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('name')">
                                {{ __('Token') }}
                                <i class="fa-solid fa-sort{{ $sort == 'name' ? ($direction == 'asc' ? '-up' : '-down') : '' }} float-right mt-1"></i>
                            </th>
                            <th class="py-3 px-6">{{ __('Empresa contable') }}</th>
                            <th class="py-3 px-6">{{ __('Puede') }}</th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('created_at')">
                                {{ __('Creado') }}
                                <i class="fa-solid fa-sort{{ $sort == 'created_at' ? ($direction == 'asc' ? '-up' : '-down') : '' }} float-right mt-1"></i>
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('last_used_at')">
                                {{ __('Último uso') }}
                                <i class="fa-solid fa-sort{{ $sort == 'last_used_at' ? ($direction == 'asc' ? '-up' : '-down') : '' }} float-right mt-1"></i>
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('expires_at')">
                                {{ __('Caduca') }}
                                <i class="fa-solid fa-sort{{ $sort == 'expires_at' ? ($direction == 'asc' ? '-up' : '-down') : '' }} float-right mt-1"></i>
                            </th>
                            <th class="py-3 px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">{{ $item->tokenable?->login ?? $item->tokenable?->email }}</td>
                                <td class="px-6 py-4">{{ $item->name }}</td>
                                <td class="px-6 py-4">
                                    @if ($item->empresaContable)
                                        {{ $item->empresaContable }}
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">
                                            {{ __('Sin empresa (token antiguo)') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    {{ $item->escribe ? __('Leer y escribir') : __('Solo leer') }}
                                </td>
                                <td class="px-6 py-4">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    {{ $item->last_used_at?->format('d/m/Y H:i') ?? __('Nunca') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if (! $item->expires_at)
                                        {{ __('No caduca') }}
                                    @elseif ($item->expires_at->isPast())
                                        <span class="text-red-600 dark:text-red-400">
                                            {{ __('Caducado') }} ({{ $item->expires_at->format('d/m/Y') }})
                                        </span>
                                    @else
                                        {{ $item->expires_at->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" class="btn btn-borrar" wire:click="revocar({{ $item->id }})"
                                        wire:confirm="{{ __('¿Revocar este token? Quien lo esté usando dejará de entrar.') }}"
                                        title="{{ __('Revocar') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @else
                <div class="py-3 px-6">{{ __('No hay ningún token creado.') }}</div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
