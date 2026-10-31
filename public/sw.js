// Service worker de las notificaciones push. Va en la raíz de public/ para que su ámbito
// sea toda la web. El navegador lo despierta cuando llega un push, aunque la web esté
// cerrada: lo único que hace es enseñar la notificación y abrir la web al pulsarla.

// El servidor (laravel-notification-channels/webpush) manda el WebPushMessage como JSON:
// "title" y, con los mismos nombres que espera showNotification, body, icon, data…
self.addEventListener('push', (event) => {
    const { title = '', ...opciones } = event.data ? event.data.json() : {};

    event.waitUntil(self.registration.showNotification(title, opciones));
});

// Al pulsar la notificación: si la web ya está abierta en esa dirección se le da el foco;
// si no, se abre. La dirección viene en data.url (si no, la portada).
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((ventanas) => {
            const abierta = ventanas.find((ventana) => ventana.url === url);

            return abierta ? abierta.focus() : clients.openWindow(url);
        })
    );
});
