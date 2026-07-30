<x-botonera-page>
    <x-slot name="title">
        {{ __('Usuarios') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Usuarios/as del sistema') }}
    </x-slot>
    <x-slot name="botonera">
        @can('user-create')
            <x-ui-button type="button" class="btn btn-nuevo" id="btn-nuevo-usuario" wire:click="$dispatch('abrir-crear')"
                title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </x-ui-button>
        @endcan
    </x-slot>

    <x-slot name="content">
        
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center">
                    @include('livewire.parciales.lineas_x_pagina')
                    @include('livewire.parciales.buscador', ['placeholder' => "Algo que buscar"])
                </div>
                @include('livewire.parciales.filtros')
                @if (count($usuarios) ?? false)
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                    {{ __('Nombre') }}
                                    @if ($sort == 'nombre')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="py-3 px-6">{{ __('Login') }}</th>
                                <th class="py-3 px-6">{{ __('Email') }}</th>
                                <th class="py-3 px-6">{{ __('Roles') }}</th>
                                <th class="py-3 px-6">{{ __('Estado') }}</th>
                                <th class="py-3 px-6">{{ __('Accion') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">

                            @foreach ($usuarios as $usuario)
                                <tr wire:key="{{ $usuario->id }}">
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $usuario->nombreCompleto }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $usuario->login }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $usuario->email }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1">
                                            @forelse ($usuario->getRoleNames() as $role)
                                                <x-dosl.badge color="platano">
                                                    {{ $role }}
                                                </x-dosl.badge>
                                            @empty
                                                <span class="text-sm text-zinc-400 italic">
                                                    sin roles
                                                </span>
                                            @endforelse
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $usuario->estado->descripcion }}</span>
                                        @if ($usuario->historial_estados_count > 1)
                                            <button type="button" wire:click="verHistorial({{ $usuario->id }})"
                                                class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 whitespace-nowrap">
                                        {{-- Un botón por acción se salía de madre (hasta 5 por fila): un menú, como
                                             el de "..." de acciones en lote de Matriculaciones. --}}
                                        <x-dropdown align="right" width="60">
                                            <x-slot name="trigger">
                                                <button type="button" title="{{ __('Acciones') }}"
                                                    class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                            </x-slot>
                                            <x-slot name="content">
                                                @can('user-edit')
                                                    <x-dropdown-link href="#" id="btn-editar-usuario-{{ $usuario->id }}"
                                                        wire:click="$dispatch('usuarioeditar', {usuario_id: {{ $usuario->id }}})">
                                                        <i class="fa-solid fa-pen mr-1"></i>{{ __('Modificar') }}
                                                    </x-dropdown-link>
                                                @endcan
                                                @if ($usuario->estado_id == \App\Models\EstadoUsuario::USUARIO_INICIAL)
                                                    @can('user-sendwelcomeemails')
                                                        <x-dropdown-link href="#" wire:click="confirmarActivar({{ $usuario->id }})">
                                                            <i class="fa-solid fa-play mr-1"></i>{{ __('Activar (envía correo de confirmación)') }}
                                                        </x-dropdown-link>
                                                    @endcan
                                                @else
                                                    @can('user-delete')
                                                        @if ($usuario->estado_id == \App\Models\EstadoUsuario::USUARIO_ACTIVO)
                                                            <x-dropdown-link href="#" wire:click="confirmarBaja({{ $usuario->id }})">
                                                                <i class="fa-solid fa-trash mr-1"></i>{{ __('Dar de baja') }}
                                                            </x-dropdown-link>
                                                        @else
                                                            <x-dropdown-link href="#" wire:click="confirmarReactivar({{ $usuario->id }})">
                                                                <i class="fa-solid fa-arrow-rotate-left mr-1"></i>{{ __('Reactivar') }}
                                                            </x-dropdown-link>
                                                        @endif
                                                    @endcan
                                                    @if ($usuario->estado_id == \App\Models\EstadoUsuario::USUARIO_ACTIVO)
                                                        @can('user-sendwelcomeemails')
                                                            <x-dropdown-link href="#" wire:click="reenviarBienvenida({{ $usuario->id }})">
                                                                <i class="fa-solid fa-envelope mr-1"></i>{{ __('Reenviar correo de bienvenida') }}
                                                            </x-dropdown-link>
                                                        @endcan
                                                    @endif
                                                @endif
                                                @can('user-password-reset')
                                                    <x-dropdown-link href="#" wire:click="abrirModalResetPassword({{ $usuario->id }})">
                                                        <i class="fa-solid fa-key mr-1"></i>{{ __('Cambiar contraseña') }}
                                                    </x-dropdown-link>
                                                @endcan
                                            </x-slot>
                                        </x-dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($usuarios->hasPages())
                        <div class="px-6 py-3">
                            {{ $usuarios->links() }}
                        </div>
                    @endif
                @else
                    <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
                @endif

            </x-dosl.tabla>
        
        @include('livewire.parciales.modal-historial-estado')

        <x-dosl.dialog-modal wire:model.live="abrirResetPassword" class="backdrop-blur" maxWidth="md">
            <x-slot name="title">
                {{ __('Cambiar contraseña') }} — {{ $resetUsuarioNombre }}
            </x-slot>

            <x-slot name="content">
                <div x-data="{ verPassword: false }">
                    <x-label for="input-reset-password" :value="__('Contraseña nueva')" />
                    <div class="mt-1 flex gap-1">
                        <div class="relative flex-1">
                            <x-input id="input-reset-password" class="block w-full pr-10"
                                x-bind:type="verPassword ? 'text' : 'password'" name="nuevaPassword"
                                wire:model="nuevaPassword" autocomplete="new-password" />
                            <button type="button" class="absolute inset-y-0 right-0 px-3 text-gray-500"
                                @click="verPassword = !verPassword" title="{{ __('Ver/ocultar contraseña') }}">
                                <i class="fa-solid" :class="verPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-cerrar px-3" wire:click="generarPasswordAdmin"
                            title="{{ __('Generar contraseña') }}">
                            <i class="fa-solid fa-dice"></i>
                        </button>
                    </div>
                    <x-input-error for="nuevaPassword" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-label for="input-reset-password-confirmation" :value="__('Repetir contraseña')" />
                    <x-input id="input-reset-password-confirmation" class="block mt-1 w-full" type="password"
                        name="nuevaPassword_confirmation" wire:model="nuevaPassword_confirmation" autocomplete="new-password" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-dosl.boton-cerrar :accion="'cerrarResetPassword'" />
                <button type="button" class="btn btn-guardar px-2" wire:click="guardarResetPassword"
                    title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
            </x-slot>
        </x-dosl.dialog-modal>

        @livewire('administracion-sistema.usuarios.crear')
        @livewire('administracion-sistema.usuarios.editar')
    </x-slot>
</x-botonera-page>
