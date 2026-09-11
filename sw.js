'use strict';

// Compatibility for obsolete root-scope registrations. Do not cache private
// pages or reload active exams. The current worker is registered at public/sw.js.
self.addEventListener('install', function (event) {
    event.waitUntil(self.skipWaiting());
});
self.addEventListener('activate', function (event) {
    event.waitUntil(self.registration.unregister());
});
