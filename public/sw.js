// Minimal service worker — required for PWA installability.
// Network passthrough (no offline caching yet; the app needs live data).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => { /* let the network handle it */ });
