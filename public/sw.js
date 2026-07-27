'use strict';

const CACHE_NAME = 'openvsosh-public-static-v1';
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
        event.respondWith(fetch(request, {cache: 'no-store'}));
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
