self.addEventListener('push', (event) => {
    const { title = '', ...opciones } = event.data ? event.data.json() : {};

    event.waitUntil(self.registration.showNotification(title, opciones));
});

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
