'use strict';

const CACHE_NAME = 'openvsosh-public-static-v3';
const PUBLIC_SCOPE = new URL(self.registration.scope).pathname;
const APP_ROOT = PUBLIC_SCOPE.replace(/public\/$/, '');
const STATIC_PATHS = new Set([
    PUBLIC_SCOPE + 'pwa-icon.svg',
    APP_ROOT + 'images/vsosh-logo.png'
]);
const PRIVATE_PREFIXES = [
    APP_ROOT + 'admin/',
    PUBLIC_SCOPE + 'code/',
    APP_ROOT + 'shared/',
    APP_ROOT + 'install/',
    APP_ROOT + 'cache/'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return cache.addAll(Array.from(STATIC_PATHS));
            })
            .then(function () {
                return self.skipWaiting();
            })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (names) {
                return Promise.all(names
                    .filter(function (name) {
                        return name.startsWith('openvsosh-public-static-') && name !== CACHE_NAME;
                    })
                    .map(function (name) {
                        return caches.delete(name);
                    }));
            })
            .then(function () {
                return self.clients.claim();
            })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }
    const isPrivate = PRIVATE_PREFIXES.some(function (prefix) {
        return url.pathname.includes(prefix);
    });
    if (isPrivate || url.search !== '' || !STATIC_PATHS.has(url.pathname)) {
        // Leave navigation and API requests to the browser. Re-fetching a
        // navigation here changes its mode, destination and referrer, and can
        // change browser-managed headers used by the session/CSRF context.
        // Dynamic responses already carry no-store from the application/server.
        return;
    }
    event.respondWith(
        caches.match(request).then(function (cached) {
            return cached || fetch(request).then(function (response) {
                if (!response.ok || response.type !== 'basic') {
                    return response;
                }
                const copy = response.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(request, copy);
                });
                return response;
            });
        })
    );
});
