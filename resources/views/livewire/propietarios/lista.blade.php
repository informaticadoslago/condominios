<x-botonera-page>
    <x-slot name="title">
        {{ __('Propietarios') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Propietarios de inmuebles') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('propietarios.crear') }}" wire:navigate class="btn btn-nuevo" id="btn-nuevo-propietario" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @if ($borradores->count())
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center gap-2 text-red-700 dark:text-red-400">
                    <i class="fa-solid fa-bell"></i>
                    <span class="font-semibold">{{ __('Propietarios sin terminar') }}</span>
                </div>
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Persona') }}</th>
                            <th class="py-3 px-6">{{ __('Modificado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($borradores as $borrador)
                            <tr wire:key="borrador-{{ $borrador->id }}">
                                <td class="px-6 py-4">{{ $borrador->nombreBorrador ?: __('(sin datos todavía)') }}</td>
                                <td class="px-6 py-4">{{ $borrador->updated_at->format('d-m-Y H:i') }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('propietarios.crear', ['borrador' => $borrador->id]) }}" wire:navigate
                                        class="btn-editar" id="btn-retomar-borrador-{{ $borrador->id }}" title="{{ __('Retomar') }}">
                                        <i class="fa-solid fa-pen"> </i> {{ __('Retomar') }}
                                    </a>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        wire:click="confirmarDescartarBorrador({{ $borrador->id }})" title="{{ __('Descartar') }}">
                                        <i class="fa-solid fa-trash"> </i>
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-dosl.tabla>
        @endif

        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" wire:click="invertirSeleccion"
                    title="{{ __('Invierte la selección dentro de lo que cumple el filtro actual') }}">
                    <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i>{{ __('Invertir selección') }}
                </x-secondary-button>
                @if (count($seleccionados))
                    <x-secondary-button type="button" wire:click="limpiarSeleccion" class="ml-1"
                        title="{{ __('Quitar toda la selección') }}">
                        <i class="fa-solid fa-xmark mr-1"></i>{{ __('Quitar selección') }} ({{ count($seleccionados) }})
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="toggleVerSoloSeleccionados"
                        @class(['ml-1' => true, '!bg-blue-600 dark:!bg-blue-800 !text-white hover:!bg-blue-700 dark:hover:!bg-blue-700' => $verSoloSeleccionados])
                        title="{{ __('Ver solo las filas seleccionadas') }}">
                        <i class="fa-solid fa-check-double mr-1"></i>{{ __('Ver solo seleccionados') }}
                    </x-secondary-button>
                @endif
                {{-- Solo en las comunidades que llevan contabilidad: donde no la llevan,
                     estas acciones no tendrían nada que hacer. --}}
                @if (contabilidad_activa())
                    <span class="ml-1 inline-block align-middle">
                        <x-dropdown align="right" width="60">
                            <x-slot name="trigger">
                                <button type="button" title="{{ __('Acciones en lote') }}"
                                    class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                {{-- Los que ya tienen cuenta se saltan solos. --}}
                                <x-dropdown-link href="#" wire:click="enlazarContabilidad">
                                    <i class="fa-solid fa-link mr-1"></i>{{ __('Enlazar con contabilidad') }}
                                    @if (count($seleccionados))
                                        ({{ count($seleccionados) }})
                                    @endif
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </span>
                @endif
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre o documento"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6 w-px">
                                <input type="checkbox" wire:model.live="marcarTodosVisibles"
                                    title="{{ __('Marcar/desmarcar toda la página') }}" />
                            </th>
                            <th class="py-3 px-6">{{ __('Nombre') }}</th>
                            <th class="py-3 px-6">{{ __('Documento') }}</th>
                            <th class="py-3 px-6">{{ __('Correo') }}</th>
                            @if (contabilidad_activa())
                                <th class="py-3 px-6">{{ __('Cuenta') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Estado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" wire:model.live="seleccionados" value="{{ $item->id }}" />
                                </td>
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->persona->nombreCompleto ?? '' }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->persona->documento_identificativo ?? '' }}</td>
                                @php($correo = $item->correo())
                                <td class="px-6 py-4">
                                    @if ($correo)
                                        <span class="whitespace-nowrap">{{ $correo->valor }}</span>
                                        @if ($correo->estaValidado())
                                            <i class="fa-solid fa-circle-check text-green-600 ml-1"
                                                title="{{ __('Validado el :fecha', ['fecha' => $correo->verified_at->format('d-m-Y')]) }}"></i>
                                        @else
                                            <i class="fa-solid fa-circle-exclamation text-amber-500 ml-1"
                                                title="{{ __('Sin validar') }}"></i>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                @if (contabilidad_activa())
                                    {{-- Su subcuenta de cliente, 43000001. --}}
                                    <td class="px-6 py-4">{{ $item->cuenta_contable ?? '—' }}</td>
                                @endif
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->estado?->descripcion }}</span>
                                    @if ($item->historial_estados_count > 1)
                                        <button type="button" wire:click="verHistorial({{ $item->id }})"
                                            class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('propietarios.editar', $item) }}" wire:navigate class="btn-editar"
                                        id="btn-editar-propietario-{{ $item->id }}" title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </a>
                                    {{-- Solo si hay dirección y está sin confirmar: a quien ya
                                         contestó no hay nada que mandarle. --}}
                                    @if ($correo && ! $correo->estaValidado())
                                        <x-button type="button" class="bg-blue-600 hover:bg-blue-700 text-white ml-1"
                                            wire:click="enviarVerificacionCorreo({{ $item->id }})"
                                            title="{{ __('Enviar correo de verificación') }}">
                                            <i class="fa-solid fa-envelope"> </i>
                                        </x-button>
                                    @endif
                                    @if ($item->estado_id == \App\Models\Propietario::ESTADO_ACTIVO)
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})" title="{{ __('Dar de baja') }}">
                                            <i class="fa-solid fa-ban"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                                            wire:click="confirmarReactivar({{ $item->id }})" title="{{ __('Reactivar') }}">
                                            <i class="fa-solid fa-arrow-rotate-left"> </i>
                                        </x-button>
                                    @endif
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
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>

        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>
