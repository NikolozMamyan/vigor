const CACHE_NAME = 'vigor-v6';
const APP_SHELL_URLS = [
    '/manifest.webmanifest',
    '/icons/vigor-icon.svg',
    '/icons/vigor-notification.svg',
    '/icons/vigor-notification-96.png',
    '/icons/vigor-notification-192.png',
    '/icons/vigor-icon-192.png',
    '/icons/vigor-icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(APP_SHELL_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkOnlyPage(request));
        return;
    }

    if (url.pathname === '/manifest.webmanifest') {
        event.respondWith(networkFirst(request));
        return;
    }

    if (!isCacheableAsset(request)) {
        return;
    }

    event.respondWith(
        staleWhileRevalidate(request),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/app/workout';
    const url = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});

function networkFirst(request) {
    return fetch(request)
        .then((response) => {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

            return response;
        })
        .catch(() => caches.match(request));
}

function networkOnlyPage(request) {
    return fetch(request, { cache: 'no-store' })
        .catch(() => new Response('Connexion indisponible. Recharge la page quand le reseau revient.', {
            status: 503,
            headers: {
                'Content-Type': 'text/plain; charset=utf-8',
                'Cache-Control': 'no-store',
            },
        }));
}

function staleWhileRevalidate(request) {
    return caches.match(request).then((cached) => {
        const networkPromise = fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
            }

            return response;
        });

        return cached || networkPromise;
    });
}

function isCacheableAsset(request) {
    return ['style', 'script', 'worker', 'font', 'image', 'audio'].includes(request.destination);
}
