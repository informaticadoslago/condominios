// Lo que pasa en cada navegador con las notificaciones push. Lo usan el aviso del layout
// (livewire/notificaciones-push) y la sección del perfil; el servidor solo guarda la
// suscripción que se le manda desde aquí.
//
// El "No en este navegador" se guarda en localStorage: es de ese navegador y no de la
// cuenta, así que en los demás se sigue preguntando.

const CLAVE_RECHAZO = 'notificaciones-push-no-aqui';

/** Clave pública VAPID (base64 url) → los bytes que pide pushManager.subscribe(). */
function clavePublicaEnBytes(clave) {
    const base64 = (clave + '='.repeat((4 - (clave.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
}

function registro() {
    return navigator.serviceWorker.register('/sw.js');
}

window.notificacionesPush = {
    soportado() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    },

    /** iPhone/iPad desde Safari: solo hay push si se entra desde el icono de la pantalla de inicio. */
    iosSinInstalar() {
        return /iPhone|iPad|iPod/.test(navigator.userAgent) && ! navigator.standalone;
    },

    /** 'default' (sin decidir), 'granted' o 'denied'; null si el navegador no tiene push. */
    permiso() {
        return this.soportado() ? Notification.permission : null;
    },

    rechazadoAqui() {
        try {
            return localStorage.getItem(CLAVE_RECHAZO) === '1';
        } catch {
            return false;
        }
    },

    rechazarAqui() {
        try {
            localStorage.setItem(CLAVE_RECHAZO, '1');
        } catch {}
    },

    olvidarRechazo() {
        try {
            localStorage.removeItem(CLAVE_RECHAZO);
        } catch {}
    },

    /** Hay que llamarla desde el clic del usuario: si no, el navegador no pregunta. */
    pedirPermiso() {
        return Notification.requestPermission();
    },

    /**
     * La suscripción de este navegador (la crea si no la hay) con lo que guarda el servidor.
     * Solo con el permiso ya concedido.
     */
    async suscribir(clavePublica) {
        const { pushManager } = await registro();
        const suscripcion = await pushManager.getSubscription()
            ?? await pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: clavePublicaEnBytes(clavePublica) });

        return {
            ...suscripcion.toJSON(),
            contentEncoding: (PushManager.supportedContentEncodings ?? ['aesgcm'])[0],
        };
    },

    /** Anula la suscripción de este navegador y devuelve su endpoint (null si no había). */
    async anular() {
        const suscripcion = await (await registro()).pushManager.getSubscription();

        if (! suscripcion) return null;

        await suscripcion.unsubscribe();

        return suscripcion.endpoint;
    },
};
