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
    
    <!-- Three.js 3D Rendering Engine & Draco Loaders -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/DRACOLoader.js"></script>

    <!-- Google Model Viewer for 3D GLB with WebAssembly Draco Decompression -->
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>

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
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" title="Logout" 
                            class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-rose-50 text-gray-500 hover:text-rose-600 border border-gray-200 hover:border-rose-200 flex items-center justify-center transition"
                            onclick="return confirm('Apakah Anda yakin ingin logout?');">
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
    </script>
    @yield('scripts')
</body>
</html>
