<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Robopath - @yield('page_title')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 for Modern Alerts & Confirmations -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        html, body {
            background-color: #f9fafb;
        }
    </style>

    <!-- Three.js 3D Rendering Engine & Draco Loaders (Served Locally for Instant Load) -->
    <script src="{{ asset('js/three.min.js') }}"></script>
    <script src="{{ asset('js/OrbitControls.js') }}"></script>
    <script src="{{ asset('js/GLTFLoader.js') }}"></script>
    <script src="{{ asset('js/DRACOLoader.js') }}"></script>

    <!-- Global Persistent 3D Asset Cache & Service Worker Registration (Active on both 2D & 3D) -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register("{{ asset('sw.js') }}").catch(err => {
                console.warn('[Robopath SW] Register bypass:', err);
            });
        });
    }
    // Unified Persistent GLB Cache System (Memory + CacheStorage + IndexedDB with In-Flight Deduplication)
    (function() {
        const DB_NAME = 'robopath-glb-db-v2';
        const STORE_NAME = 'glb_models';
        const CACHE_NAME = 'robopath-models-v1';
        const memoryCache = window.__ROBOPATH_GLB_MEM__ || new Map();
        window.__ROBOPATH_GLB_MEM__ = memoryCache;

        // In-flight active download map to deduplicate parallel requests for the same URL
        // Map<url, { promise: Promise<ArrayBuffer>, listeners: Set<Function> }>
        const inFlightRequests = new Map();

        // Safe IndexedDB with strict timeout so it can NEVER block the app
        let dbPromise = null;
        function getDB(timeoutMs = 400) {
            if (!dbPromise) {
                dbPromise = new Promise((resolve) => {
                    if (!window.indexedDB) return resolve(null);
                    const timer = setTimeout(() => resolve(null), timeoutMs);
                    try {
                        const req = indexedDB.open(DB_NAME, 1);
                        req.onblocked = () => { clearTimeout(timer); resolve(null); };
                        req.onupgradeneeded = (e) => {
                            try {
                                const db = e.target.result;
                                if (!db.objectStoreNames.contains(STORE_NAME)) {
                                    db.createObjectStore(STORE_NAME);
                                }
                            } catch (err) { clearTimeout(timer); resolve(null); }
                        };
                        req.onsuccess = () => { clearTimeout(timer); resolve(req.result); };
                        req.onerror = () => { clearTimeout(timer); resolve(null); };
                    } catch (e) {
                        clearTimeout(timer);
                        resolve(null);
                    }
                });
            }
            return dbPromise;
        }

        async function getFromIDB(key) {
            try {
                const db = await getDB();
                if (!db) return null;
                return new Promise((resolve) => {
                    const timer = setTimeout(() => resolve(null), 300);
                    try {
                        const tx = db.transaction(STORE_NAME, 'readonly');
                        const store = tx.objectStore(STORE_NAME);
                        const req = store.get(key);
                        req.onsuccess = () => { clearTimeout(timer); resolve(req.result || null); };
                        req.onerror = () => { clearTimeout(timer); resolve(null); };
                    } catch (err) {
                        clearTimeout(timer);
                        resolve(null);
                    }
                });
            } catch (e) {
                return null;
            }
        }

        async function putToIDB(key, val) {
            try {
                const db = await getDB();
                if (!db) return;
                const tx = db.transaction(STORE_NAME, 'readwrite');
                const store = tx.objectStore(STORE_NAME);
                store.put(val, key);
            } catch (e) {}
        }

        async function getFromCacheStorage(url) {
            if (!('caches' in window)) return null;
            try {
                const cache = await caches.open(CACHE_NAME);
                const res = await cache.match(url);
                if (res) return await res.arrayBuffer();
            } catch (e) {}
            return null;
        }

        async function putToCacheStorage(url, buffer) {
            if (!('caches' in window)) return;
            try {
                const cache = await caches.open(CACHE_NAME);
                const headers = new Headers();
                headers.append('Content-Type', 'model/gltf-binary');
                headers.append('Content-Length', String(buffer.byteLength));
                await cache.put(url, new Response(buffer.slice(0), { headers }));
            } catch (e) {}
        }

        window.RobopathGLBCache = {
            // Fast multi-layer read: Memory (0ms) -> CacheStorage (<10ms) -> IndexedDB (<300ms)
            async get(url) {
                if (memoryCache.has(url)) return memoryCache.get(url);
                const csBuf = await getFromCacheStorage(url);
                if (csBuf) {
                    memoryCache.set(url, csBuf);
                    putToIDB(url, csBuf); // async background backup
                    return csBuf;
                }
                const idbBuf = await getFromIDB(url);
                if (idbBuf) {
                    memoryCache.set(url, idbBuf);
                    putToCacheStorage(url, idbBuf); // sync to cacheStorage
                    return idbBuf;
                }
                return null;
            },

            async put(url, buffer) {
                memoryCache.set(url, buffer);
                await Promise.allSettled([
                    putToCacheStorage(url, buffer),
                    putToIDB(url, buffer)
                ]);
            },

            async isCached(url) {
                if (memoryCache.has(url)) return true;
                if ('caches' in window) {
                    try {
                        const cache = await caches.open(CACHE_NAME);
                        const match = await cache.match(url);
                        if (match) return true;
                    } catch (e) {}
                }
                const buf = await getFromIDB(url);
                if (buf) {
                    memoryCache.set(url, buf);
                    return true;
                }
                return false;
            },

            async fetchWithProgress(url, onProgress) {
                // 1. Check persistent caches first
                const cached = await this.get(url);
                if (cached) {
                    if (typeof onProgress === 'function') {
                        try { onProgress(cached.byteLength, cached.byteLength, true); } catch (e) {}
                    }
                    return cached;
                }

                // 2. In-flight request deduplication: if download already active, subscribe to it!
                if (inFlightRequests.has(url)) {
                    const entry = inFlightRequests.get(url);
                    if (typeof onProgress === 'function') {
                        entry.listeners.add(onProgress);
                    }
                    return entry.promise;
                }

                // 3. Initiate single network request
                const listeners = new Set();
                if (typeof onProgress === 'function') listeners.add(onProgress);

                const fetchPromise = (async () => {
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error(`HTTP ${response.status} loading ${url}`);

                        const contentLength = response.headers.get('content-length');
                        const totalBytes = contentLength ? parseInt(contentLength, 10) : 10000000;
                        let loadedBytes = 0;
                        const chunks = [];

                        if (response.body && response.body.getReader) {
                            const reader = response.body.getReader();
                            while (true) {
                                const { done, value } = await reader.read();
                                if (done) break;
                                chunks.push(value);
                                loadedBytes += value.length;
                                listeners.forEach(fn => {
                                    try { fn(loadedBytes, totalBytes, false); } catch (err) {}
                                });
                            }
                        } else {
                            const raw = await response.arrayBuffer();
                            chunks.push(new Uint8Array(raw));
                            loadedBytes = raw.byteLength;
                            listeners.forEach(fn => {
                                try { fn(loadedBytes, totalBytes, false); } catch (err) {}
                            });
                        }

                        const allChunks = new Uint8Array(loadedBytes);
                        let pos = 0;
                        for (const c of chunks) {
                            allChunks.set(c, pos);
                            pos += c.length;
                        }
                        const finalBuffer = allChunks.buffer;

                        // Persist to memory and storage
                        await this.put(url, finalBuffer);
                        return finalBuffer;
                    } finally {
                        inFlightRequests.delete(url);
                    }
                })();

                inFlightRequests.set(url, { promise: fetchPromise, listeners });
                return fetchPromise;
            },

            // Sequential background preload to prevent starving active viewer & server
            async preload(urls) {
                const list = Array.isArray(urls) ? urls : [urls];
                for (const u of list) {
                    if (!u) continue;
                    try {
                        const cached = await this.isCached(u);
                        if (!cached) {
                            await this.fetchWithProgress(u, null);
                        }
                    } catch (e) {}
                }
            }
        };

        // Otomatis preload seluruh model 3D di background saat idle (secara SEQUENTIAL)
        const glbToPreload = [
            "{{ asset('models/Denah_Lantai_1-opt.glb') }}",
            "{{ asset('models/Lantai_2-final.glb') }}",
            "{{ asset('models/robot.glb') }}"
        ];
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => window.RobopathGLBCache.preload(glbToPreload), { timeout: 4000 });
        } else {
            setTimeout(() => window.RobopathGLBCache.preload(glbToPreload), 1500);
        }
    })();
    </script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            blue: '#3b4cb8',
                            light: '#e0e7ff',
                        }
                    }
                }
            }
        }
    </script>
    @yield('styles')
</head>
<body class="bg-gray-50 text-gray-800 font-sans h-screen flex overflow-hidden">
    
    <!-- Sidebar Navigation -->
    <aside id="main-sidebar" class="w-64 bg-brand-blue text-white flex flex-col justify-between shrink-0 shadow-lg z-20">
        <div>
            <!-- Sidebar Header / Logo -->
            <div class="h-20 flex items-center px-6 border-b border-white/20 gap-3 bg-brand-blue">
                <div class="w-10 h-10 rounded bg-white flex items-center justify-center text-brand-blue shadow">
                    <i class="fa-solid fa-robot text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-wide text-white">ROBOPATH</h1>
                    <span class="text-xs text-white/80 font-medium block">Sistem Pelacakan</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-2 mt-2">
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('dashboard') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-chart-line text-lg {{ Route::is('dashboard') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Dashboard</span>
                </a>

                @if(auth()->check() && auth()->user()->isAdmin())
                <a href="{{ route('deliveries') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('deliveries') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-route text-lg {{ Route::is('deliveries') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Pengiriman</span>
                </a>
                @endif

                <a href="{{ route('reports') }}" 
                   class="flex items-center justify-between px-4 py-3 rounded transition duration-200 group {{ Route::is('reports') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center gap-4 min-w-0">
                        <i class="fa-solid fa-triangle-exclamation text-lg {{ Route::is('reports') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                        <span class="text-sm truncate">Laporan</span>
                    </div>
                    <span id="sidebar-reports-count" class="hidden bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm animate-pulse">0</span>
                </a>

                @if(auth()->check() && auth()->user()->isAdmin())
                <a href="{{ route('history') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('history') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-clock-rotate-left text-lg {{ Route::is('history') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Riwayat</span>
                </a>

                <a href="{{ route('bot-control') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('bot-control') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-sliders text-lg {{ Route::is('bot-control') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Kontrol Robot</span>
                </a>
                @endif
            </nav>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main id="main-content" class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        <!-- Topbar -->
        <header class="h-20 border-b border-gray-200 bg-white shadow-sm flex items-center justify-between px-6 lg:px-8 shrink-0 z-10 gap-4">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-gray-800 leading-tight">@yield('page_title')</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5 truncate max-w-xl">@yield('page_subtitle')</p>
            </div>

            <div class="flex items-center gap-3 md:gap-4 shrink-0">
                @hasSection('topbar_actions')
                    <div class="shrink-0">
                        @yield('topbar_actions')
                    </div>
                    <div class="h-8 w-px bg-gray-200 hidden sm:block"></div>
                @endif
                
                @if(auth()->check() && auth()->user()->isAdmin())
                <div class="relative">
                    <a href="{{ route('reports') }}" id="topbar-bell-btn" class="relative w-9 h-9 rounded-xl bg-gray-100 hover:bg-amber-50 text-gray-600 hover:text-amber-600 border border-gray-200 flex items-center justify-center transition" title="Laporan Kendala Aktif">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <span id="topbar-alert-badge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 bg-rose-500 text-white text-[9px] font-extrabold px-1 rounded-full flex items-center justify-center animate-bounce shadow-xs">0</span>
                    </a>
                </div>
                @endif

                <div class="flex items-center gap-3 shrink-0">
                    <div class="text-right hidden md:block">
                        <p class="text-sm font-semibold text-gray-800 leading-tight">{{ (auth()->check() && auth()->user()->isAdmin()) ? 'Admin' : (auth()->user()->name ?? 'Pengguna') }}</p>
                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider {{ (auth()->check() && auth()->user()->isAdmin()) ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' }}">
                                {{ (auth()->check() && auth()->user()->isAdmin()) ? 'Admin' : 'Staf Karyawan' }}
                            </span>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-full {{ (auth()->check() && auth()->user()->isAdmin()) ? 'bg-brand-light text-brand-blue border-brand-blue/30' : 'bg-emerald-100 text-emerald-700 border-emerald-300' }} border flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid {{ (auth()->check() && auth()->user()->isAdmin()) ? 'fa-user-shield' : 'fa-user' }}"></i>
                    </div>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="button" title="Keluar" 
                                class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-rose-50 text-gray-500 hover:text-rose-600 border border-gray-200 hover:border-rose-200 flex items-center justify-center transition"
                                onclick="confirmLogout()">
                            <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 custom-scrollbar">
            @yield('content')
        </div>
    </main>

    <style>
        .swal2-container {
            z-index: 100000 !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f3f4f6; 
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
    </style>

    <script>
        window.currentUserRole = "{{ auth()->user()->role ?? 'karyawan' }}";
        window.isAdmin = {{ (auth()->check() && auth()->user()->isAdmin()) ? 'true' : 'false' }};

        // Custom SweetAlert2 Theme for Robopath
        const RobopathSwal = Swal.mixin({
            customClass: {
                popup: 'rounded-2xl shadow-2xl border border-gray-100 font-sans p-6 text-gray-800 bg-white',
                title: 'text-lg font-extrabold text-gray-900 mb-1',
                htmlContainer: 'text-sm text-gray-600 leading-relaxed',
                confirmButton: 'bg-brand-blue hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition duration-200 shadow-sm mx-1 focus:ring-4 focus:ring-indigo-100',
                cancelButton: 'bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-5 py-2.5 rounded-xl text-xs transition duration-200 mx-1',
                denyButton: 'bg-rose-600 hover:bg-rose-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition duration-200 shadow-sm mx-1',
                actions: 'gap-2 mt-4',
            },
            buttonsStyling: false,
        });

        window.showConfirmDialog = function(options = {}) {
            const isDanger = options.isDanger !== false;
            return RobopathSwal.fire({
                title: options.title || 'Konfirmasi Tindakan',
                text: options.text || 'Apakah Anda yakin ingin melanjutkan?',
                html: options.html || undefined,
                icon: options.icon || (isDanger ? 'warning' : 'question'),
                showCancelButton: true,
                confirmButtonText: options.confirmText || (isDanger ? 'Ya, Lanjutkan' : 'Ya, Konfirmasi'),
                cancelButtonText: options.cancelText || 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-gray-100 font-sans p-6 text-gray-800 bg-white',
                    title: 'text-lg font-extrabold text-gray-900 mb-1',
                    htmlContainer: 'text-sm text-gray-600 leading-relaxed',
                    confirmButton: (isDanger 
                        ? 'bg-rose-600 hover:bg-rose-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition duration-200 shadow-sm mx-1 focus:ring-4 focus:ring-rose-200' 
                        : 'bg-brand-blue hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition duration-200 shadow-sm mx-1 focus:ring-4 focus:ring-indigo-100'),
                    cancelButton: 'bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-5 py-2.5 rounded-xl text-xs transition duration-200 mx-1',
                    actions: 'gap-2 mt-4'
                },
                buttonsStyling: false
            }).then(result => result.isConfirmed);
        };

        window.showSuccessAlert = function(title, text) {
            return RobopathSwal.fire({
                icon: 'success',
                title: title || 'Berhasil!',
                text: text || '',
                confirmButtonText: 'Selesai',
                timer: 3500,
                timerProgressBar: true
            });
        };

        window.showErrorAlert = function(title, text) {
            return RobopathSwal.fire({
                icon: 'error',
                title: title || 'Terjadi Kesalahan',
                text: text || '',
                confirmButtonText: 'Tutup'
            });
        };

        window.showWarningAlert = function(title, text) {
            return RobopathSwal.fire({
                icon: 'warning',
                title: title || 'Peringatan',
                text: text || '',
                confirmButtonText: 'Mengerti'
            });
        };

        window.showToast = function(title, icon = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                },
                customClass: {
                    popup: 'rounded-xl shadow-lg border border-gray-100 font-sans text-sm',
                }
            });
            return Toast.fire({
                icon: icon,
                title: title
            });
        };

        async function confirmLogout() {
            const confirmed = await window.showConfirmDialog({
                title: 'Konfirmasi Keluar',
                text: 'Apakah Anda yakin ingin keluar dari sesi Robopath?',
                confirmText: '<i class="fa-solid fa-arrow-right-from-bracket mr-1.5"></i> Keluar',
                cancelText: 'Batal',
                icon: 'question',
                isDanger: true
            });
            if (confirmed) {
                document.getElementById('logout-form').submit();
            }
        }

        // Global Telemetry & Incident Notification Poller for Admin
        if (window.isAdmin) {
            let lastKnownAlertCount = null;
            let notifiedAlertIds = new Set();

            function checkGlobalAdminAlerts() {
                fetch('/api/telemetry')
                    .then(res => res.json())
                    .then(data => {
                        const activeAlerts = data.active_alerts || [];
                        const count = activeAlerts.length;

                        // Update badges
                        const sbBadge = document.getElementById('sidebar-reports-count');
                        const tbBadge = document.getElementById('topbar-alert-badge');
                        if (sbBadge) {
                            if (count > 0) {
                                sbBadge.textContent = count;
                                sbBadge.classList.remove('hidden');
                            } else {
                                sbBadge.classList.add('hidden');
                            }
                        }
                        if (tbBadge) {
                            if (count > 0) {
                                tbBadge.textContent = count;
                                tbBadge.classList.remove('hidden');
                            } else {
                                tbBadge.classList.add('hidden');
                            }
                        }

                        // Check for new unnotified alerts
                        activeAlerts.forEach(a => {
                            if (!notifiedAlertIds.has(a.id)) {
                                notifiedAlertIds.add(a.id);
                                if (lastKnownAlertCount !== null) {
                                    // New alert came in while admin is viewing!
                                    window.showToast(`⚠️ Laporan Baru: Robot ${a.robot?.name || ''} mengalami ${a.issue_type}! Cek Kontrol Bot.`, 'warning');
                                }
                            }
                        });
                        lastKnownAlertCount = count;
                    })
                    .catch(() => {});
            }

            // Check immediately and poll periodically
            checkGlobalAdminAlerts();
            setInterval(checkGlobalAdminAlerts, 5000);
        }

        // Global fallback override for native window.alert
        window.alert = function(message) {
            window.showWarningAlert('Pemberitahuan', message);
        };

        // --- Persistent 3D Model Caching via Browser Cache Storage API ---
        window.Robopath3DCache = {
            CACHE_NAME: 'robopath-models-v1',

            async getCache() {
                if ('caches' in window) {
                    try {
                        return await caches.open(this.CACHE_NAME);
                    } catch (e) {
                        console.warn('[Robopath3DCache] Failed to open CacheStorage:', e);
                    }
                }
                return null;
            },

            // Resolves model URL to a local Blob URL:
            // Checks CacheStorage first. If hit -> 0-byte instant return from local disk/memory.
            // If miss -> fetches from network, tracks download progress, puts in CacheStorage, and returns Blob URL.
            async getModelBlobUrl(url, onProgress) {
                const cache = await this.getCache();
                if (cache) {
                    try {
                        const match = await cache.match(url);
                        if (match) {
                            console.log('[Robopath3DCache] Serving from cache (0 byte network transfer):', url);
                            if (typeof onProgress === 'function') onProgress(100, 100);
                            const blob = await match.blob();
                            return URL.createObjectURL(blob);
                        }
                    } catch (e) {
                        console.warn('[Robopath3DCache] Cache match error:', e);
                    }
                }

                console.log('[Robopath3DCache] Fetching asset from network (first time download):', url);
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status} when fetching ${url}`);
                }

                const contentLengthHeader = response.headers.get('content-length');
                const total = contentLengthHeader ? parseInt(contentLengthHeader, 10) : 0;
                let loaded = 0;
                let blob;

                if (response.body && total > 0 && typeof ReadableStream !== 'undefined') {
                    const reader = response.body.getReader();
                    const chunks = [];
                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;
                        chunks.push(value);
                        loaded += value.length;
                        if (typeof onProgress === 'function') {
                            onProgress(loaded, total);
                        }
                    }
                    blob = new Blob(chunks);
                } else {
                    blob = await response.blob();
                    if (typeof onProgress === 'function') onProgress(100, 100);
                }

                // Cache for all future route changes (deliveries, dashboard, bot control)
                if (cache) {
                    try {
                        const cacheResponse = new Response(blob.slice(0), {
                            headers: {
                                'Content-Type': 'model/gltf-binary',
                                'Content-Length': blob.size.toString(),
                                'Cache-Control': 'public, max-age=31536000'
                            }
                        });
                        await cache.put(url, cacheResponse);
                        console.log('[Robopath3DCache] Stored in CacheStorage:', url, `${(blob.size / 1024 / 1024).toFixed(2)} MB`);
                    } catch (e) {
                        console.warn('[Robopath3DCache] Failed to write cache:', e);
                    }
                }

                return URL.createObjectURL(blob);
            },

            async clear() {
                if ('caches' in window) {
                    await caches.delete(this.CACHE_NAME);
                    console.log('[Robopath3DCache] Cache cleared');
                }
            }
        };
    </script>

    @yield('scripts')
</body>
</html>
