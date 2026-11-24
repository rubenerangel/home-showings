// Shows the push notifications, also with every tab closed.
self.addEventListener('push', (event) => {
    const { title, body, data } = event.data.json();

    event.waitUntil(self.registration.showNotification(title, { body, data, icon: '/favicon.ico' }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data.url, self.location.origin).href;

    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            const open = windows.find((client) => client.url === url);

            return open ? open.focus() : self.clients.openWindow(url);
        })(),
    );
});
