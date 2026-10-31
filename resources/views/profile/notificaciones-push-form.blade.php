<x-action-section>
    <x-slot name="title">
        {{ __('Notificaciones') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Avisos en el móvil o en el ordenador, aunque la aplicación esté cerrada.') }}
    </x-slot>

    <x-slot name="content">
        <label class="flex items-center">
            <x-checkbox wire:model.live="activas" />
            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Quiero recibir notificaciones') }}</span>
        </label>

        @if ($activas)
            {{-- Estado de este navegador: el permiso y su suscripción solo los sabe el propio
                 navegador; si esa suscripción está entre las guardadas ($wire.endpoints), activo. --}}
            <div class="mt-5 text-sm text-gray-600 dark:text-gray-400"
                x-data="{
                    estado: '',
                    actual: null,
                    async refrescar() {
                        const push = window.notificacionesPush;
                        const permiso = push.permiso();

                        this.actual = await push.endpointActual();

                        if (permiso === null) this.estado = push.iosSinInstalar() ? 'ios' : 'no-soportado';
                        else if (permiso === 'denied') this.estado = 'bloqueado';
                        else if (this.actual && $wire.endpoints.includes(this.actual)) this.estado = 'activo';
                        else this.estado = 'inactivo';
                    },
                    async desactivar() {
                        window.notificacionesPush.rechazarAqui();
                        const endpoint = await window.notificacionesPush.anular();

                        if (endpoint) await $wire.quitarEndpoint(endpoint);

                        this.refrescar();
                    },
                }"
                x-init="refrescar()"
                x-on:notificaciones-push-cambiadas.window="refrescar()"
                x-on:notificaciones-push-estado.window="$wire.cargar().then(() => refrescar())"
            >
                <div x-show="estado === 'activo'" class="flex flex-wrap items-center gap-3">
                    <span><i class="fa-solid fa-bell mr-1"></i>{{ __('Activadas en este navegador.') }}</span>
                    <x-secondary-button type="button" x-on:click="desactivar()">
                        {{ __('Desactivar en este navegador') }}
                    </x-secondary-button>
                </div>

                <div x-show="estado === 'inactivo'" class="flex flex-wrap items-center gap-3">
                    <span><i class="fa-solid fa-bell-slash mr-1"></i>{{ __('No activadas en este navegador.') }}</span>
                    <x-secondary-button type="button" x-on:click="$dispatch('notificaciones-push-activar-aqui')">
                        {{ __('Activar en este navegador') }}
                    </x-secondary-button>
                </div>

                <p x-show="estado === 'bloqueado'">
                    <i class="fa-solid fa-ban mr-1"></i>{{ __('Bloqueadas en este navegador. Para recibirlas, permítelas en los ajustes del navegador.') }}
                </p>

                <p x-show="estado === 'ios'">
                    <i class="fa-solid fa-mobile-screen mr-1"></i>{{ __('En el iPhone, añade esta web a la pantalla de inicio y entra desde ese icono.') }}
                </p>

                <p x-show="estado === 'no-soportado'">
                    {{ __('Este navegador no admite notificaciones.') }}
                </p>

                <div class="mt-5">
                    <x-secondary-button type="button" wire:click="configurar">
                        <i class="fa-solid fa-gear mr-1"></i>{{ __('Configurar') }}
                    </x-secondary-button>
                </div>

                {{-- Dentro del x-data de arriba: usa "actual" para marcar este navegador y su
                     "Quitar" es el mismo "Desactivar en este navegador". --}}
                <x-dosl.dialog-modal wire:model.live="abrirConfigurar" maxWidth="2xl">
                    <x-slot name="title">
                        {{ __('Dispositivos') }}
                    </x-slot>

                    <x-slot name="subtitulo">
                        {{ __('Los navegadores en los que tienes activadas las notificaciones. Ponles un nombre para distinguirlos.') }}
                    </x-slot>

                    <x-slot name="content">
                        @forelse ($this->dispositivos as $dispositivo)
                            <div class="py-3 border-b border-gray-200 dark:border-gray-700" wire:key="dispositivo-{{ $dispositivo->id }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-input type="text" class="flex-1 min-w-40" maxlength="60"
                                        wire:model.blur="nombres.{{ $dispositivo->id }}" />

                                    <div class="flex items-center gap-2">
                                        <x-secondary-button type="button" wire:click="probar({{ $dispositivo->id }})"
                                            wire:loading.attr="disabled" title="{{ __('Probar') }}">
                                            <i class="fa-solid fa-bell mr-1"></i>{{ __('Probar') }}
                                        </x-secondary-button>

                                        <button type="button" class="btn btn-borrar" title="{{ __('Quitar') }}"
                                            x-show="actual === @js($dispositivo->endpoint)" x-on:click="desactivar()">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <button type="button" class="btn btn-borrar" title="{{ __('Quitar') }}"
                                            x-show="actual !== @js($dispositivo->endpoint)"
                                            wire:click="quitar({{ $dispositivo->id }})"
                                            wire:confirm="{{ __('¿Quitar este dispositivo? Si vuelves a entrar desde él, se te preguntará otra vez.') }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Activado el :fecha', ['fecha' => $dispositivo->created_at->format('d/m/Y')]) }}
                                    <span x-show="actual === @js($dispositivo->endpoint)" class="font-semibold">· {{ __('este navegador') }}</span>
                                </div>

                                <x-input-error for="nombres.{{ $dispositivo->id }}" class="mt-1" />
                            </div>
                        @empty
                            <p>{{ __('Todavía no has activado las notificaciones en ningún navegador.') }}</p>
                        @endforelse
                    </x-slot>

                    <x-slot name="footer">
                        <x-dosl.boton-cerrar accion="close" />
                        @if ($this->dispositivos->isNotEmpty())
                            <x-secondary-button type="button" wire:click="probarTodos" wire:loading.attr="disabled">
                                <i class="fa-solid fa-bell mr-1"></i>{{ __('Probar en todos') }}
                            </x-secondary-button>
                        @endif
                    </x-slot>
                </x-dosl.dialog-modal>
            </div>
        @endif
    </x-slot>
</x-action-section>
