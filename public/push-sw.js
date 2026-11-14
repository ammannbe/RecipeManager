// Web Push only (RecipeManager is no PWA): registered from the profile (filament/push-toggle.blade.php).
// Payload: WebPushMessage of the server (App\Notifications\AnnouncementPush). Same handlers as Chorelist.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let message = {};
    try {
        message = event.data?.json() ?? {};
    } catch {
        message = { title: event.data?.text() ?? 'RecipeManager' };
    }
    const { title = 'RecipeManager', ...options } = message;

    event.waitUntil(self.registration.showNotification(title, options));
});

// Opens the notification's page ("News"), reusing an open window of the app.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/admin', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const open = windows.find((client) => new URL(client.url).origin === self.location.origin);
        if (open) {
            await open.focus();
            return open.navigate(url).catch(() => self.clients.openWindow(url));
        }

        return self.clients.openWindow(url);
    })());
});
