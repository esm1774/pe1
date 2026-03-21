const CACHE_NAME = 'pe-smart-v3.1.3';
const ASSETS_TO_CACHE = [
    'app.html',
    'manifest.json',
    'css/style.css',
    'js/common.js',
    'js/dashboard.js',
    'js/grades.js',
    'js/students.js',
    'js/profile.js',
    'js/attendance.js',
    'js/fitness.js',
    'js/competition.js',
    'js/reports.js',
    'js/users.js',
    'js/user_profile.js',
    'js/tournaments.js',
    'js/sports_teams.js',
    'js/notifications.js',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
    'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap'
];

// Install Service Worker
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('Caching assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// Activate & Cleanup Old Caches — then take control immediately
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        }).then(() => {
            // Take control of all open pages immediately
            return self.clients.claim();
        })
    );

    // Notify all open tabs that a new version is ready
    self.clients.matchAll({ type: 'window' }).then(clients => {
        clients.forEach(client => client.postMessage({ type: 'SW_UPDATED' }));
    });
});

// Fetch Strategy: Network First, falling back to cache
self.addEventListener('fetch', event => {
    // Only cache GET requests
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clone the response to store in cache
                const resClone = response.clone();
                caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, resClone);
                });
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
