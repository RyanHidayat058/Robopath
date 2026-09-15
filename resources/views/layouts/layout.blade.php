<!DOCTYPE html>
<html lang="en">
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

    @if(($viewMode ?? '2d') === '3d')
    <!-- Three.js 3D Rendering Engine & Draco Loaders (Served Locally for Instant Load) -->
    <script src="{{ asset('js/three.min.js') }}"></script>
    <script src="{{ asset('js/OrbitControls.js') }}"></script>
    <script src="{{ asset('js/GLTFLoader.js') }}"></script>
    <script src="{{ asset('js/DRACOLoader.js') }}"></script>
    @endif

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
    <aside class="w-64 bg-brand-blue text-white flex flex-col justify-between shrink-0 shadow-lg z-20">
        <div>
            <!-- Sidebar Header / Logo -->
            <div class="h-20 flex items-center px-6 border-b border-white/20 gap-3 bg-brand-blue">
                <div class="w-10 h-10 rounded bg-white flex items-center justify-center text-brand-blue shadow">
                    <i class="fa-solid fa-robot text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-wide text-white">ROBOPATH</h1>
                    <span class="text-xs text-white/80 font-medium block">Tracking System</span>
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
                    <span class="text-sm">Deliveries</span>
                </a>

                <a href="{{ route('reports') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('reports') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-triangle-exclamation text-lg {{ Route::is('reports') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Alerts</span>
                </a>

                <a href="{{ route('history') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('history') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-clock-rotate-left text-lg {{ Route::is('history') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">History</span>
                </a>

                <a href="{{ route('bot-control') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('bot-control') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-sliders text-lg {{ Route::is('bot-control') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Bot Control</span>
                </a>
                @endif
            </nav>

            <!-- Mode Tampilan Switcher (2D / 3D) -->
            <div class="px-4 pb-2">
                <div class="p-3 bg-white/10 backdrop-blur-sm rounded-2xl border border-white/15 shadow-inner">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-100 flex items-center gap-1.5">
                            <i class="fa-solid fa-layer-group text-sky-300"></i> Mode Tampilan
                        </span>
                        <span id="global-mode-badge" data-testid="view-mode-badge" class="view-mode-badge text-[9px] font-bold px-1.5 py-0.5 rounded-full {{ ($viewMode ?? '2d') === '3d' ? 'bg-emerald-400 text-slate-900' : 'bg-white/20 text-white' }} font-mono">
                            {{ strtoupper($viewMode ?? '2d') }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 p-1 bg-black/20 rounded-xl gap-1">
                        <button type="button" id="btn-view-mode-2d" data-testid="toggle-view-mode-2d" onclick="switchGlobalViewMode('2d')" 
                                class="toggle-view-mode-2d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 {{ ($viewMode ?? '2d') === '2d' ? 'shadow-sm bg-white text-brand-blue' : 'text-white/80 hover:text-white hover:bg-white/10' }}">
                            <i class="fa-solid fa-map text-[10px]"></i> 2D
                        </button>
                        <button type="button" id="btn-view-mode-3d" data-testid="toggle-view-mode-3d" onclick="switchGlobalViewMode('3d')" 
                                class="toggle-view-mode-3d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 {{ ($viewMode ?? '2d') === '3d' ? 'shadow-sm bg-white text-brand-blue' : 'text-white/80 hover:text-white hover:bg-white/10' }}">
                            <i class="fa-solid fa-cube text-[10px]"></i> 3D
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Footer / System Health -->
        <div class="p-4 border-t border-white/20 bg-brand-blue">
            <div class="flex items-center gap-3 p-3 bg-black/10 rounded">
                <div class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-300 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-400"></span>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-xs font-semibold text-white">System Online</p>
                    <p class="text-[10px] text-white/70 truncate mt-0.5">Connected to Server</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative z-10">
        <!-- Topbar -->
        <header class="h-20 border-b border-gray-200 bg-white shadow-sm flex items-center justify-between px-8 shrink-0 z-10">
            <div>
                <h2 class="text-xl font-bold text-gray-800">@yield('page_title')</h2>
                <p class="text-sm text-gray-500 mt-0.5">@yield('page_subtitle')</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="text-right hidden md:block">
                    <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name ?? 'User' }}</p>
                    <div class="flex items-center justify-end gap-1.5 mt-0.5">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider {{ (auth()->check() && auth()->user()->isAdmin()) ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' }}">
                            {{ (auth()->check() && auth()->user()->isAdmin()) ? 'Admin Supervisor' : 'Karyawan Staff' }}
                        </span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-full {{ (auth()->check() && auth()->user()->isAdmin()) ? 'bg-brand-light text-brand-blue border-brand-blue/30' : 'bg-emerald-100 text-emerald-700 border-emerald-300' }} border flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid {{ (auth()->check() && auth()->user()->isAdmin()) ? 'fa-user-shield' : 'fa-user' }}"></i>
                </div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="button" title="Logout" 
                            class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-rose-50 text-gray-500 hover:text-rose-600 border border-gray-200 hover:border-rose-200 flex items-center justify-center transition"
                            onclick="confirmLogout()">
                        <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                    </button>
                </form>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 custom-scrollbar">
            @yield('content')
        </div>
    </main>

    <style>
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
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin keluar dari sesi Robopath?',
                confirmText: '<i class="fa-solid fa-arrow-right-from-bracket mr-1.5"></i> Logout',
                cancelText: 'Batal',
                icon: 'question',
                isDanger: true
            });
            if (confirmed) {
                document.getElementById('logout-form').submit();
            }
        }

        // Global fallback override for native window.alert
        window.alert = function(message) {
            window.showWarningAlert('Pemberitahuan', message);
        };

        // --- Persistent 3D Model Caching via Browser Cache Storage API ---
        window.Robopath3DCache = {
            CACHE_NAME: 'robopath-3d-cache-v1',

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

        // --- Global View Mode State & Switcher ---
        window.getGlobalViewMode = function() {
            const saved = localStorage.getItem('robopath_view_mode');
            return (saved === '3d') ? '3d' : '2d';
        };

        window.switchGlobalViewMode = function(mode) {
            if (mode !== '2d' && mode !== '3d') mode = '2d';

            // Show smooth transition overlay immediately (eliminates white flicker/blink)
            const overlay = document.getElementById('global-mode-switch-overlay');
            const title = document.getElementById('mode-switch-title');
            const desc = document.getElementById('mode-switch-desc');
            const icon = document.getElementById('mode-switch-icon');
            if (overlay) {
                if (title) title.textContent = mode === '3d' ? 'Mengaktifkan Mode 3D' : 'Mengaktifkan Mode 2D';
                if (desc) desc.textContent = mode === '3d' ? 'Memuat visualisasi 3D...' : 'Menyiapkan layout 2D...';
                if (icon) icon.className = mode === '3d' ? 'fa-solid fa-cube text-indigo-600 absolute text-xs' : 'fa-solid fa-map text-indigo-600 absolute text-xs';
                overlay.classList.remove('pointer-events-none');
                overlay.classList.remove('opacity-0');
            }

            // Immediately update sidebar buttons & badge for 0ms visual feedback
            const btn2d = document.getElementById('btn-view-mode-2d');
            const btn3d = document.getElementById('btn-view-mode-3d');
            const badge = document.getElementById('global-mode-badge');
            if (badge) {
                badge.textContent = mode.toUpperCase();
                badge.className = 'view-mode-badge text-[9px] font-bold px-1.5 py-0.5 rounded-full font-mono ' + (mode === '3d' ? 'bg-emerald-400 text-slate-900' : 'bg-white/20 text-white');
            }
            if (btn2d && btn3d) {
                if (mode === '3d') {
                    btn3d.className = 'toggle-view-mode-3d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm bg-white text-brand-blue';
                    btn2d.className = 'toggle-view-mode-2d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 text-white/80 hover:text-white hover:bg-white/10';
                } else {
                    btn2d.className = 'toggle-view-mode-2d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm bg-white text-brand-blue';
                    btn3d.className = 'toggle-view-mode-3d py-1.5 px-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 text-white/80 hover:text-white hover:bg-white/10';
                }
            }

            localStorage.setItem('robopath_view_mode', mode);
            document.cookie = "robopath_view_mode=" + mode + "; path=/; max-age=31536000; SameSite=Lax";

            // Navigate using URL parameter to ensure atomic server response and prevent blank reload
            const targetUrl = new URL(window.location.href);
            targetUrl.searchParams.set('view_mode', mode);
            window.location.href = targetUrl.toString();
        };

        // Sync initial mode on page load without double reload loops
        document.addEventListener('DOMContentLoaded', () => {
            const currentServerMode = "{{ $viewMode ?? '2d' }}";
            const urlParams = new URLSearchParams(window.location.search);

            // If arrived via view_mode parameter, sync localStorage & clean URL silently
            if (urlParams.has('view_mode')) {
                localStorage.setItem('robopath_view_mode', currentServerMode);
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('view_mode');
                window.history.replaceState({}, document.title, cleanUrl.pathname + (cleanUrl.search ? cleanUrl.search : ''));
                return;
            }

            const localMode = localStorage.getItem('robopath_view_mode');
            if (localMode && (localMode === '2d' || localMode === '3d') && localMode !== currentServerMode) {
                // Out of sync: navigate cleanly with query parameter
                document.cookie = "robopath_view_mode=" + localMode + "; path=/; max-age=31536000; SameSite=Lax";
                const targetUrl = new URL(window.location.href);
                targetUrl.searchParams.set('view_mode', localMode);
                window.location.href = targetUrl.toString();
            } else if (!localMode) {
                localStorage.setItem('robopath_view_mode', currentServerMode);
            }
        });
    </script>

    <!-- Global Mode Transition Overlay (Prevents white flash/blink on switch) -->
    <div id="global-mode-switch-overlay" class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex flex-col items-center justify-center transition-opacity duration-200 opacity-0 pointer-events-none">
        <div class="bg-white/95 backdrop-blur-md rounded-2xl p-6 shadow-2xl flex flex-col items-center max-w-xs text-center border border-white/40">
            <div class="relative flex items-center justify-center w-12 h-12 mb-3">
                <div class="w-10 h-10 rounded-full border-4 border-indigo-200 border-t-indigo-600 animate-spin"></div>
                <i id="mode-switch-icon" class="fa-solid fa-cube text-indigo-600 absolute text-xs"></i>
            </div>
            <h4 id="mode-switch-title" class="text-sm font-bold text-gray-800">Mengalihkan Mode...</h4>
            <p id="mode-switch-desc" class="text-xs text-gray-500 mt-1">Menyiapkan tampilan sistem...</p>
        </div>
    </div>

    @yield('scripts')
</body>
</html>
