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
            {{-- Estado de este navegador: lo sabe solo el propio navegador. --}}
            <div class="mt-5 text-sm text-gray-600 dark:text-gray-400"
                x-data="{
                    estado: '',
                    refrescar() {
                        const push = window.notificacionesPush;
                        const permiso = push.permiso();

                        if (permiso === null) this.estado = push.iosSinInstalar() ? 'ios' : 'no-soportado';
                        else if (permiso === 'denied') this.estado = 'bloqueado';
                        else if (permiso === 'granted' && ! push.rechazadoAqui()) this.estado = 'activo';
                        else this.estado = 'inactivo';
                    },
                    async desactivar() {
                        window.notificacionesPush.rechazarAqui();
                        const endpoint = await window.notificacionesPush.anular();

                        if (endpoint) await $wire.quitar(endpoint);

                        this.refrescar();
                    },
                }"
                x-init="refrescar()"
                x-on:notificaciones-push-estado.window="refrescar()"
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
            </div>
        @endif
    </x-slot>
</x-action-section>
