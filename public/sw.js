// Robopath 3D Service Worker - High Performance Cache-First for 3D Models & Assets
const MODEL_CACHE_NAME = 'robopath-models-v1';

// Install event: activate immediately without waiting for existing tabs to close
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// Activate event: claim all open clients immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Fetch event: Cache-First strategy for GLB models, Draco decoders, and core 3D libraries
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Identify 3D assets: /models/*.glb, /draco/*, and Three.js core files
    const isModelAsset = url.pathname.startsWith('/models/') && url.pathname.endsWith('.glb');
    const isDracoAsset = url.pathname.startsWith('/draco/');
    const isThreeLibrary = url.pathname.includes('/js/three.min.js') || 
                           url.pathname.includes('/js/OrbitControls.js') || 
                           url.pathname.includes('/js/GLTFLoader.js') || 
                           url.pathname.includes('/js/DRACOLoader.js');

    if (isModelAsset || isDracoAsset || isThreeLibrary) {
        event.respondWith(
            caches.open(MODEL_CACHE_NAME).then(async (cache) => {
                const cachedResponse = await cache.match(event.request);
                if (cachedResponse) {
                    // Return instantly from local cache (0 byte network transfer)
                    return cachedResponse;
                }

                // If not cached yet, fetch from network and store in cache
                try {
                    const networkResponse = await fetch(event.request);
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                } catch (err) {
                    return cachedResponse || new Response('Network error while loading 3D asset', { status: 408 });
                }
            })
        );
    }
});
