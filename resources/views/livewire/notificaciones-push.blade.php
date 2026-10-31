{{-- Se pregunta una vez por navegador, solo con el sí general del perfil. Con el permiso
     ya concedido (p. ej. al volver a activarlas en el perfil) se suscribe sin preguntar.
     "No en este navegador" no vuelve a preguntar ahí; se deshace desde el perfil, que
     lanza notificaciones-push-activar-aqui desde su clic (el navegador solo pide permiso
     dentro de un clic). --}}
<div
    x-data="{
        mostrar: false,
        ios: false,
        clave: @js(config('webpush.vapid.public_key')),
        async comprobar() {
            const push = window.notificacionesPush;
            this.mostrar = false;

            if (! $wire.activas || ! this.clave || push.rechazadoAqui()) return;

            if (! push.soportado()) {
                this.ios = push.iosSinInstalar();
                this.mostrar = this.ios;
                return;
            }

            if (push.permiso() === 'granted') {
                await this.suscribir();
                return;
            }

            this.mostrar = push.permiso() === 'default';
        },
        async suscribir() {
            const suscripcion = await window.notificacionesPush.suscribir(this.clave);

            if (! $wire.endpoints.includes(suscripcion.endpoint)) {
                await $wire.guardar(suscripcion);
            }
        },
        async activar() {
            this.mostrar = false;
            const permiso = await window.notificacionesPush.pedirPermiso();

            if (permiso === 'granted') await this.suscribir();

            $dispatch('notificaciones-push-estado');
        },
        noAqui() {
            window.notificacionesPush.rechazarAqui();
            this.mostrar = false;
            $dispatch('notificaciones-push-estado');
        },
    }"
    x-init="comprobar()"
    x-on:notificaciones-push-cambiadas.window="$wire.cargar().then(() => comprobar())"
    x-on:notificaciones-push-activar-aqui.window="window.notificacionesPush.olvidarRechazo(); activar()"
>
    <div x-show="mostrar" x-cloak
        class="fixed bottom-4 inset-x-4 sm:left-auto sm:right-4 sm:w-96 z-50 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg p-4">
        <p class="text-sm text-gray-700 dark:text-gray-200">
            <i class="fa-solid fa-bell mr-1"></i>
            <span x-show="! ios">{{ __('¿Activar las notificaciones en este navegador?') }}</span>
            <span x-show="ios">{{ __('Para recibir notificaciones en el iPhone, añade esta web a la pantalla de inicio y entra desde ese icono.') }}</span>
        </p>

        <div class="mt-3 flex justify-end gap-2">
            <x-secondary-button type="button" x-on:click="noAqui()">
                {{ __('No en este navegador') }}
            </x-secondary-button>
            <x-button type="button" x-show="! ios" x-on:click="activar()">
                {{ __('Activar') }}
            </x-button>
        </div>
    </div>
</div>
