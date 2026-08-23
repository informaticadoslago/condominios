<x-botonera-page>
    <x-slot name="title">
        {{ __('Correos enviados') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Rastro de los correos enviados o encolados por la aplicación') }}
    </x-slot>
    <x-slot name="botonera"></x-slot>

    <x-slot name="content">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center">
                    @include('livewire.parciales.lineas_x_pagina')
                    @include('livewire.parciales.buscador', ['placeholder' => __('Destinatario, asunto o tipo')])
                </div>
                @if(count($correos) ?? false)
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('enviado_at')">{{ __('Fecha') }}
                                    @if ($sort == 'enviado_at')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('tipo')">{{ __('Tipo') }}
                                    @if ($sort == 'tipo')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('asunto')">{{ __('Asunto') }}
                                    @if ($sort == 'asunto')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('destinatario')">{{ __('Destinatario') }}
                                    @if ($sort == 'destinatario')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="py-3 px-6">{{ __('Enviado por') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($correos as $correo)
                                <tr wire:key="{{ $correo->id }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $correo->enviado_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ class_basename($correo->tipo) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $correo->asunto }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $correo->destinatario }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $correo->user?->nombreCompleto ?? __('Automático') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($correos->hasPages())
                        <div class="px-6 py-3">
                            {{ $correos->links() }}
                        </div>
                    @endif
                @else
                    <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
                @endif
            </x-dosl.tabla>
        </div>
    </x-slot>
</x-botonera-page>
