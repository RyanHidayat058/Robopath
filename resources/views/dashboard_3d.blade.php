@extends('layouts.layout')

@section('title', 'ROBOPATH - Pelacakan Robot Langsung')
@section('page_title', 'Ringkasan Sistem')
@section('page_subtitle', 'Pelacakan Robot Multi-Lantai & Metrik Sistem Waktu Nyata')

@section('styles')
<style>
    .floor-map-card {
        position: relative;
        width: 100%;
        aspect-ratio: 16/9;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(59, 76, 184, 0.08), inset 0 0 0 1px rgba(0,0,0,0.06);
    }

    #std-3d-canvas-container,
    #fullview-3d-canvas-f2 {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        overflow: hidden;
    }
    #std-3d-canvas-container canvas,
    #fullview-3d-canvas-f2 canvas {
        display: block;
    }
    /* Scrollable 3D panels */
    #panel-3d-robot-control::-webkit-scrollbar,
    #panel-3d-camera::-webkit-scrollbar,
    #panel-3d-light::-webkit-scrollbar,
    #panel-3d-label-size::-webkit-scrollbar {
        width: 6px;
    }
    #panel-3d-robot-control::-webkit-scrollbar-thumb,
    #panel-3d-camera::-webkit-scrollbar-thumb,
    #panel-3d-light::-webkit-scrollbar-thumb,
    #panel-3d-label-size::-webkit-scrollbar-thumb {
        background: rgba(148,163,184,0.4);
        border-radius: 3px;
    }
    #panel-3d-robot-control::-webkit-scrollbar-track,
    #panel-3d-camera::-webkit-scrollbar-track,
    #panel-3d-light::-webkit-scrollbar-track,
    #panel-3d-label-size::-webkit-scrollbar-track {
        background: transparent;
    }
    
    /* Full View 3D Mode Card Expansion (Matching Bot Control Full Map: Fixed Inset-0, Sleek Floating Bar, 0 duplicate scenes) */
    .dashboard-fullview-card {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        z-index: 9999 !important;
        margin: 0 !important;
        border-radius: 0 !important;
        background-color: #0b1120 !important;
        padding: 0.75rem !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .dashboard-fullview-canvas {
        flex: 1 1 0% !important;
        height: 100% !important;
        aspect-ratio: auto !important;
        border-radius: 0.75rem !important;
    }
    /* Floating Collapsible Inspector in Full View (Matching Bot Control) */
    .dashboard-inspector-floating {
        position: fixed !important;
        top: 4.5rem !important;
        right: 1rem !important;
        z-index: 10001 !important;
        width: min(25rem, calc(100vw - 2rem)) !important;
        max-width: calc(100vw - 2rem) !important;
        max-height: calc(100vh - 5.5rem) !important;
        overflow-y: auto !important;
        background: rgba(15, 23, 42, 0.95) !important;
        backdrop-filter: blur(20px) !important;
        -webkit-backdrop-filter: blur(20px) !important;
        border-radius: 1.25rem !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(59, 130, 246, 0.15) !important;
        color: #f8fafc !important;
    }
    .dashboard-inspector-floating::-webkit-scrollbar {
        width: 6px;
    }
    .dashboard-inspector-floating::-webkit-scrollbar-track {
        background: transparent;
    }
    .dashboard-inspector-floating::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 3px;
    }
    body.body-in-fullview > aside,
    body.body-in-fullview aside,
    body.body-in-fullview #main-sidebar,
    #main-sidebar.fullview-hidden {
        display: none !important;
        visibility: hidden !important;
        width: 0px !important;
        min-width: 0px !important;
        max-width: 0px !important;
        opacity: 0 !important;
        pointer-events: none !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    body.body-in-fullview > main,
    body.body-in-fullview main,
    body.body-in-fullview #main-content {
        z-index: 99999 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .control-modal-panel-3d {
        z-index: 10005 !important;
        max-width: calc(100vw - 2rem) !important;
        right: 1rem !important;
    }
</style>
@endsection

@section('content')
<!-- Container 1: Standard Dashboard View (Stat Cards + Active Floor Map + Robot Roster) -->
<div id="standard-view" class="space-y-6">

    <!-- Emergency Alert Banner (Shown when any robot has an incident / paused task) -->
    <div id="emergency-alert-banner" class="hidden px-4 py-2.5 bg-gradient-to-r from-rose-600 to-red-700 rounded-xl shadow-lg text-white flex flex-wrap items-center justify-between gap-3 border border-rose-400">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center text-sm shrink-0 shadow">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider bg-white text-rose-700 px-2 py-0.5 rounded-full shadow-sm shrink-0">Darurat</span>
                    <span class="text-xs font-semibold text-rose-100 truncate" id="emergency-banner-text">Robot terhenti akibat kendala di jalur. Cepat benerin!</span>
                </div>
            </div>
        </div>
        <div id="emergency-banner-actions" class="flex items-center gap-2 shrink-0">
            @if(auth()->check() && auth()->user()->isAdmin())
            <button id="emergency-fix-btn" onclick="fixActiveIssueRobot()" 
                    class="px-3 py-1.5 bg-white hover:bg-rose-50 text-rose-700 font-extrabold text-[11px] rounded-lg shadow transition duration-200 flex items-center gap-1.5">
                <i class="fa-solid fa-wrench"></i>
                <span>Benerin Sekarang</span>
            </button>
            @else
            <span class="text-[11px] bg-black/25 text-white px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-lock text-rose-200"></i> Menunggu Admin
            </span>
            @endif
        </div>
    </div>

    <!-- Top Stat Strip (4 Compact Chips) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        <!-- Chip 1: Active Units -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Unit Aktif</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800" id="stat-active-robots">{{ $activeRobotsCount }}/{{ $totalRobotsCount }}</span>
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-200">Aktif</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-[#3b4cb8] text-base shadow-sm shrink-0">
                <i class="fa-solid fa-robot"></i>
            </div>
        </div>

        <!-- Chip 2: Active Missions -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Misi Berjalan</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800" id="stat-active-deliveries">{{ $activeDeliveriesCount }}</span>
                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full border border-blue-200">Berlangsung</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-base shadow-sm shrink-0">
                <i class="fa-solid fa-route"></i>
            </div>
        </div>

        <!-- Chip 3: Completed Today -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Selesai Hari Ini</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800" id="stat-deliveries-today">{{ $deliveriesTodayCount }}</span>
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-200" id="stat-success-rate">{{ $successRate }}%</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-base shadow-sm shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Chip 4: System Alerts -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Peringatan Sistem</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black {{ $activeAlertsCount > 0 ? 'text-rose-600' : 'text-gray-800' }}" id="stat-active-alerts">{{ $activeAlertsCount }}</span>
                    <span class="text-[10px] font-bold {{ $activeAlertsCount > 0 ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-gray-500 bg-gray-100 border-gray-200' }} px-1.5 py-0.5 rounded-full border" id="stat-active-alerts-badge">
                        {{ $activeAlertsCount > 0 ? 'Perhatian' : 'Optimal' }}
                    </span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg {{ $activeAlertsCount > 0 ? 'bg-rose-50 border border-rose-100 text-rose-600' : 'bg-gray-100 border border-gray-200 text-gray-400' }} flex items-center justify-center text-base shadow-sm shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>

    <!-- Main Section: Balanced 2/3 Map View & 1/3 Robot Roster -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- Left Column: Interactive Floor Map View (2/3 width) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col justify-between" id="std-map-card">
                <!-- Header with Floor Switch Tabs & Full View Button -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-gray-100" id="std-header-bar">
                    <div>
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-[#3b4cb8]"></i> Pelacakan Lantai Langsung
                        </h3>
                        <p class="text-xs text-gray-500">Telemetri robot &amp; visualisasi rute pengiriman waktu nyata</p>
                    </div>

                    <!-- Floor Switcher & Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Floor Switcher -->
                        <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                            <button onclick="switchDashboardFloor(1)" id="std-tab-f1" class="px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition">
                                Lantai 1
                            </button>
                            <button onclick="switchDashboardFloor(2)" id="std-tab-f2" class="px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                                Lantai 2
                            </button>
                        </div>

                        @if(auth()->check() && auth()->user()->isAdmin())
                        <!-- Autopilot Button (Admin Only) -->
                        <button id="autopilot-btn" onclick="toggleAutopilot()" 
                                class="px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300">
                            <i class="fa-solid fa-wand-magic-sparkles" id="autopilot-icon"></i>
                            <span id="autopilot-text">Autopilot: NONAKTIF</span>
                        </button>

                        <!-- Simulate Issue Dropdown (Admin Only) -->
                        <div class="relative inline-block text-left" id="simulate-dropdown-container">
                            <button onclick="toggleSimulateMenu()" class="bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 font-bold px-3 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition">
                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                                <span>Simulasi Masalah</span>
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </button>
                            <div id="simulate-menu" class="hidden absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-2xl border border-gray-200 py-1.5 z-50">
                                <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">Simulasikan Insiden Robot</div>
                                <button onclick="simulateIssueAction(1, 'Collision')" class="w-full text-left px-3 py-2 text-xs hover:bg-rose-50 text-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-car-burst text-rose-500"></i> Tabrakan
                                </button>
                                <button onclick="simulateIssueAction(1, 'Low Battery')" class="w-full text-left px-3 py-2 text-xs hover:bg-rose-50 text-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-battery-empty text-amber-500"></i> Baterai Habis
                                </button>
                                <button onclick="simulateIssueAction(1, 'Sensor Error')" class="w-full text-left px-3 py-2 text-xs hover:bg-rose-50 text-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-orange-500"></i> Sensor Rusak
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- Full View 2 Lantai Button -->
                        <button onclick="toggleFullView(true)" class="bg-[#3b4cb8] hover:bg-blue-700 text-white font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-md hover:shadow-lg transition duration-200">
                            <i class="fa-solid fa-expand"></i> Layar Penuh
                        </button>
                    </div>
                </div>

                <!-- Active Floor Title Badge -->
                <div class="flex items-center justify-between mb-2" id="std-floor-title-bar">
                    <span class="text-xs font-bold text-[#3b4cb8] flex items-center gap-1.5" id="std-floor-title">
                        <i class="fa-solid fa-building-user"></i> Lantai 1
                    </span>
                    <span class="text-[10px] bg-blue-100 text-blue-700 font-bold px-2.5 py-0.5 rounded-full border border-blue-200" id="std-floor-badge">
                        Menampilkan Lantai 1
                    </span>
                </div>

                <!-- Top Floating Navigation Bar in Full View (Matching Bot Control Sleek Single Row) -->
                <div id="fullview-top-bar" class="hidden flex items-center justify-between gap-2 pb-2 mb-2 border-b border-slate-700/60 text-xs shrink-0 select-none w-full">
                    <!-- Left: Floor Switcher Tabs & View Badge -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                            <button type="button" onclick="switchDashboardFloor(1)" id="fullview-tab-f1" class="px-3 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 1
                            </button>
                            <button type="button" onclick="switchDashboardFloor(2)" id="fullview-tab-f2" class="px-3 py-1.5 rounded-lg font-bold transition text-gray-400 hover:text-white hover:bg-white/10">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 2
                            </button>
                        </div>
                        <span class="hidden sm:flex text-[11px] bg-slate-900/90 text-sky-400 font-bold px-2.5 py-1.5 rounded-xl border border-white/10 items-center gap-1.5 whitespace-nowrap">
                            <i class="fa-solid fa-eye text-sky-400"></i> Navigasi Bebas
                        </span>
                    </div>

                    <!-- Center: Quick View Tools (Scrollable smoothly on narrower screens) -->
                    <div class="flex-1 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5 min-w-0 mx-1">
                        <button type="button" onclick="toggle3DRoomLabels()" id="fullview-btn-labels" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shrink-0" title="Tampilkan / Sembunyikan Label Ruangan">
                            <i class="fa-solid fa-tag text-emerald-400" id="fullview-icon-labels"></i> <span id="fullview-text-labels">Label: AKTIF</span>
                        </button>
                        @if(auth()->check() && auth()->user()->isAdmin())
                        <button type="button" onclick="toggle3DControlPanel('label-size')" id="fullview-btn-label-size" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shrink-0" title="Pengaturan Ukuran Label & Model 3D">
                            <i class="fa-solid fa-text-height text-indigo-400"></i> <span>Ukuran Label</span>
                        </button>
                        <button type="button" onclick="toggle3DControlPanel('camera')" id="fullview-btn-camera" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shrink-0" title="Pengaturan Sudut & Zoom Kamera">
                            <i class="fa-solid fa-video text-sky-400"></i> <span>Kamera</span>
                        </button>
                        <button type="button" onclick="toggle3DControlPanel('light')" id="fullview-btn-light" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shrink-0" title="Pengaturan Pencahayaan, Shader & Bayangan Ruangan">
                            <i class="fa-solid fa-sun text-amber-400"></i> <span>Cahaya</span>
                        </button>
                        @endif
                        <!-- Fullview Follow Button & View Mode Group -->
                        <div class="inline-flex items-center rounded-xl bg-slate-900/90 border border-white/10 p-0.5 shrink-0" id="fullview-group-follow">
                            <button type="button" onclick="toggleFollowMode()" id="fullview-btn-follow" class="hover:bg-slate-800 text-gray-200 font-bold px-2.5 py-1.5 rounded-lg text-xs flex items-center gap-1.5 transition whitespace-nowrap" title="Kamera Mengikuti Robot Aktif">
                                <i class="fa-solid fa-crosshairs text-sky-400" id="fullview-icon-follow"></i> <span id="fullview-text-follow">Ikuti: NONAKTIF</span>
                            </button>
                            <button type="button" id="fullview-btn-cycle-follow" onclick="cycleFollowCameraMode()" class="hidden px-2.5 py-1.5 rounded-lg text-xs font-bold transition text-sky-300 hover:text-white hover:bg-white/10 border-l border-white/10 flex items-center gap-1.5" title="Ganti Mode Pandangan (Klik untuk beralih antara Mata Robot, Belakang, dan Orbit)">
                                <i class="fa-solid fa-eye text-emerald-400" id="fullview-icon-follow-mode"></i>
                                <span id="fullview-follow-mode-badge" class="font-mono text-[11px]">Mata Robot</span>
                            </button>
                        </div>
                        <button type="button" onclick="reset3DCamera()" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-300 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition shrink-0" title="Pusatkan Kembali Kamera">
                            <i class="fa-solid fa-arrows-to-dot text-amber-400"></i> <span>Pusatkan</span>
                        </button>

                        @if(auth()->check() && auth()->user()->isAdmin())
                        <!-- Autopilot Button in Full View -->
                        <button type="button" id="fullview-autopilot-btn" onclick="toggleAutopilot()" 
                                class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-2.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shrink-0">
                            <i class="fa-solid fa-wand-magic-sparkles text-amber-400" id="fullview-autopilot-icon"></i>
                            <span id="fullview-autopilot-text">Autopilot: NONAKTIF</span>
                        </button>

                        <!-- Manual Dispatch Inspector Button in Full View -->
                        <button type="button" id="fullview-btn-dispatch" onclick="toggleFullViewDispatchPanel()" 
                                class="bg-indigo-600 hover:bg-indigo-500 border border-indigo-400/30 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-indigo-600/20 transition whitespace-nowrap active:scale-95 shrink-0" 
                                title="Buka Panel Tugas Pengantaran Manual (Inspector)">
                            <i class="fa-solid fa-paper-plane text-sky-300"></i>
                            <span id="fullview-text-dispatch">Suruh Manual</span>
                            <span id="fullview-active-deliv-badge" class="hidden text-[10px] bg-white/20 px-1.5 py-0.2 rounded-md font-mono font-bold">0</span>
                        </button>
                        @endif
                    </div>

                    <!-- Right: Exit Fullscreen Button (Always pinned and fully visible) -->
                    <div class="flex items-center gap-2 shrink-0 ml-auto z-20">
                        <button type="button" onclick="toggleFullView(false)" class="bg-rose-600 hover:bg-rose-700 text-white font-black px-4 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-rose-600/30 transition active:scale-95 border border-rose-400/40 cursor-pointer" title="Keluar Layar Penuh (Esc)">
                            <i class="fa-solid fa-compress"></i> <span>Keluar</span>
                        </button>
                    </div>
                </div>

                <!-- Map Canvas Container (Proporsional 16:9) — kedua lantai murni 3D -->
                <div class="floor-map-card overflow-hidden shadow-inner border border-gray-200 relative" id="std-map-container" style="background-color: #0f172a;">
                    <!-- 3D WebGL Canvas Layer for Floor 2 -->
                    <div id="std-3d-canvas-container" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>
                    <!-- 3D WebGL Canvas Layer for Floor 1 -->
                    <div id="std-3d-canvas-f1" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>

                    <!-- 3D Loading Screen Overlay — visible by default, disembunyikan JS setelah GLB lantai aktif selesai load -->
                    <div id="std-3d-loader" class="absolute inset-0 z-30 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center text-white">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#3b4cb8] to-sky-400 p-0.5 shadow-2xl mb-4 animate-bounce">
                            <div class="w-full h-full bg-slate-900 rounded-2xl flex items-center justify-center">
                                <i class="fa-solid fa-cube text-2xl text-sky-400 animate-spin"></i>
                            </div>
                        </div>
                        <h4 class="font-bold text-sm tracking-wide text-gray-100 mb-1" id="std-3d-loader-title">Memuat Model 3D Lantai 1...</h4>
                        <p class="text-xs text-gray-400 mb-4" id="std-3d-loader-status">Mengunduh aset GLB (8 MB)...</p>
                        <div class="w-56 bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                            <div id="std-3d-loader-bar" class="bg-gradient-to-r from-[#3b4cb8] to-sky-400 h-2 rounded-full transition-all duration-200" style="width: 5%"></div>
                        </div>
                        <span id="std-3d-loader-pct" class="text-[11px] font-mono text-sky-400 font-bold mt-2">5%</span>
                    </div>

                    <!-- 3D Controls Panel Floating Toolbar (kedua lantai 3D) -->
                    <div id="std-3d-toolbar" class="hidden absolute top-3 right-3 z-30 flex flex-wrap items-center gap-1.5 justify-end max-w-[92%]">
                        <!-- Focus badge (shows when robot selected) -->
                        <div id="robot-focus-badge" class="hidden bg-sky-500 text-white px-2.5 py-1 rounded-full text-[10px] font-black border border-sky-400 shadow flex items-center gap-1.5">
                            <i class="fa-solid fa-crosshairs animate-pulse"></i> <span id="robot-focus-badge-text">Fokus: -</span>
                            <button onclick="clearRobotFocus()" class="ml-1 bg-white/20 hover:bg-white/30 rounded-full w-4 h-4 flex items-center justify-center"><i class="fa-solid fa-xmark text-[8px]"></i></button>
                        </div>
                        <!-- Monitoring controls (berlaku ke lantai aktif) -->
                        <button id="btn-toggle-network" onclick="toggleNetworkLines()" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition opacity-60" title="Garis ke semua ruangan (graph adj)">
                            <i class="fa-solid fa-share-nodes text-violet-400"></i> <span id="text-network">Jaringan: NONAKTIF</span>
                        </button>
                        <!-- Follow Button & View Mode Group -->
                        <div class="inline-flex items-center rounded-xl bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md border border-white/10 shadow-lg p-0.5 transition" id="group-follow-controls">
                            <button id="btn-toggle-follow" onclick="toggleFollowMode()" class="text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition" title="Kamera ikut robot yang difokuskan">
                                <i class="fa-solid fa-eye text-sky-400" id="icon-follow"></i> <span id="text-follow">Ikuti: NONAKTIF</span>
                            </button>
                            <button id="btn-cycle-follow-mode" onclick="cycleFollowCameraMode()" class="hidden px-2.5 py-1.5 rounded-lg text-xs font-bold transition text-sky-300 hover:text-white hover:bg-white/10 border-l border-white/10 flex items-center gap-1.5" title="Ganti Mode Pandangan (Klik untuk beralih antara Mata Robot, Belakang, dan Orbit)">
                                <i class="fa-solid fa-eye text-emerald-400" id="icon-follow-mode"></i>
                                <span id="text-follow-mode-badge" class="font-mono text-[11px]">Mata Robot</span>
                            </button>
                        </div>
                        <!-- Room Labels Toggle -->
                        <button id="btn-toggle-3d-labels" onclick="toggle3DRoomLabels()" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-tag text-emerald-400" id="icon-3d-labels"></i> <span id="text-3d-labels">Label: AKTIF</span>
                        </button>
                        @if(auth()->check() && auth()->user()->isAdmin())
                        <!-- Room Label Size Panel Button -->
                        <button onclick="toggle3DControlPanel('label-size')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-text-height text-indigo-400"></i> Ukuran Label
                        </button>
                        <!-- Camera Preset / Edit Button -->
                        <button onclick="toggle3DControlPanel('camera')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-video text-sky-400"></i> Kamera
                        </button>
                        <!-- Lighting Control Button -->
                        <button onclick="toggle3DControlPanel('light')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition" title="Pengaturan Pencahayaan, Shader & Bayangan Ruangan">
                            <i class="fa-solid fa-sun text-amber-400"></i> Cahaya & Shadow
                        </button>
                        <!-- Robot Position Control Button -->
                        <button onclick="toggle3DControlPanel('robot')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition" title="Gerak & rotasi robot 3D (pilih robot dulu)">
                            <i class="fa-solid fa-robot text-emerald-400"></i> Posisi Robot
                        </button>
                        @endif
                    </div>

                    <!-- 3D Controls hint / badge -->
                    <div id="std-3d-hint" class="hidden absolute bottom-2 right-2 z-20 bg-slate-900/80 backdrop-blur-md text-white px-2.5 py-1 rounded-lg text-[10px] font-semibold border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                        <i class="fa-solid fa-cube text-sky-400"></i> Model 3D Aktif &bull; Putar (Drag) &bull; Zoom (Scroll)
                    </div>
                </div>

                <!-- Status Indicator Legends -->
                <div class="flex flex-wrap gap-4 pt-4 mt-4 border-t border-gray-200 text-xs text-gray-600 font-semibold">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-sm"></span>
                        <span>Siaga</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-sky-500 shadow-sm"></span>
                        <span>Mengantar</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-500 shadow-sm"></span>
                        <span>Mengisi Daya</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500 shadow-sm"></span>
                        <span>Perbaikan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Active Robots Roster (1/3 width, Matches Left Height) -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2 pb-3 border-b border-gray-200">
                <i class="fa-solid fa-robot text-[#3b4cb8]"></i> Daftar Robot Aktif
            </h3>

            <div class="space-y-3.5 flex-1" id="robot-cards-container">
                @foreach($robots as $robot)
                <div class="bg-slate-50 border border-gray-200 p-4 rounded-xl flex flex-col justify-between hover:border-[#3b4cb8] transition duration-200 cursor-pointer" id="robot-card-{{ $robot->id }}" onclick="focusRobotOnMap({{ $robot->id }})">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-white border border-blue-200 flex items-center justify-center text-[#3b4cb8] text-sm shadow-sm">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <span class="font-bold text-sm text-gray-800">{{ $robot->name }}</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider" id="robot-status-badge-{{ $robot->id }}">
                            {{ $robot->status === 'Idle' ? 'Siaga' : ($robot->status === 'Delivering' ? 'Mengantar' : ($robot->status === 'Charging' ? 'Mengisi Daya' : 'Perbaikan')) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-2">
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Baterai</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full" id="robot-battery-bar-{{ $robot->id }}" style="width: {{ $robot->battery_level }}%"></div>
                                </div>
                                <span class="font-mono font-bold text-gray-700 text-[11px]" id="robot-battery-text-{{ $robot->id }}">{{ $robot->battery_level }}%</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Lokasi</span>
                            <span class="font-semibold text-gray-700 text-[11px]" id="robot-location-text-{{ $robot->id }}">
                                Ruangan Kosong 2 (Lantai 1)
                            </span>
                        </div>
                    </div>

                    <div class="text-[11px] text-gray-500 pt-2 border-t border-gray-200/60" id="robot-task-text-{{ $robot->id }}">
                        Siaga di markas pangkalan
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>


@if(auth()->check() && auth()->user()->isAdmin())
<!-- Camera, Light, Label Size & Robot Control Modal Panels (Admin Only) -->
<!-- 0. Label Size & Model Scale Panel -->
<div id="panel-3d-label-size" class="hidden fixed top-16 right-4 control-modal-panel-3d z-[10005] w-76 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs select-none">
    <div class="flex items-center justify-between pb-2 border-b border-white/10">
        <span class="font-bold flex items-center gap-1.5 text-indigo-400">
            <i class="fa-solid fa-text-height"></i> Ukuran Label & Model
        </span>
        <button type="button" onclick="toggle3DControlPanel('label-size')" class="text-gray-400 hover:text-white p-1">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
    <div class="space-y-2">
        <div class="flex justify-between text-[11px]">
            <span class="text-gray-300">Skala Label Teks (Kecil - Besar)</span>
            <span id="val-label-scale" class="font-mono text-indigo-400 font-bold">{{ number_format($labelScale ?? 1.0, 1) }}x</span>
        </div>
        <input id="input-label-scale" type="range" min="0.3" max="2.5" step="0.1" value="{{ $labelScale ?? 1.0 }}" oninput="update3DLabelScale(this.value)" class="w-full accent-indigo-400">
        <div class="flex justify-between text-[10px] text-gray-400 px-0.5">
            <span>Kecil (0.3x)</span>
            <span>Normal (1.0x)</span>
            <span>Besar (2.5x)</span>
        </div>
    </div>
    <div class="space-y-2 pt-2 border-t border-white/10">
        <div class="flex justify-between text-[11px]">
            <span class="text-gray-300">Skala Objek Gedung 3D</span>
            <span id="val-model-scale" class="font-mono text-indigo-400 font-bold">{{ number_format($settings3D['model_scale'] ?? 1.0, 1) }}x</span>
        </div>
        <input id="input-model-scale" type="range" min="0.5" max="3.0" step="0.1" value="{{ $settings3D['model_scale'] ?? 1.0 }}" oninput="update3DModelScale(this.value)" class="w-full accent-indigo-400">
        <div class="flex justify-between text-[10px] text-gray-400 px-0.5">
            <span>0.5x</span>
            <span>1.0x (Normal)</span>
            <span>3.0x (Besar)</span>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-1.5 pt-1">
        <button type="button" onclick="setLabelScaleQuick(0.6)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Kecil</button>
        <button type="button" onclick="setLabelScaleQuick(1.0)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Normal</button>
        <button type="button" onclick="setLabelScaleQuick(1.6)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Besar</button>
    </div>
    <p id="label-scale-status" class="text-[10px] text-emerald-400 font-mono text-center pt-1"></p>
</div>

<!-- 1. Camera Panel -->
<div id="panel-3d-camera" class="hidden fixed top-16 right-4 control-modal-panel-3d z-[10005] w-76 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs select-none">
    <div class="flex items-center justify-between pb-2 border-b border-white/10">
        <span class="font-bold flex items-center gap-1.5 text-sky-400">
            <i class="fa-solid fa-video"></i> Pengaturan Kamera
        </span>
        <button type="button" onclick="toggle3DControlPanel('camera')" class="text-gray-400 hover:text-white p-1">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
    <div>
        <label class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block mb-1">Preset Sudut Pandang</label>
        <div class="grid grid-cols-3 gap-1.5">
            <button type="button" onclick="setCameraPreset('iso')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Iso</button>
            <button type="button" onclick="setCameraPreset('top')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Top (Atas)</button>
            <button type="button" onclick="setCameraPreset('front')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5 transition">Front</button>
        </div>
    </div>
    <div>
        <div class="flex justify-between text-[11px] mb-1">
            <span class="text-gray-300">Jarak Zoom (0 - 10)</span>
            <span id="val-cam-dist" class="font-mono text-sky-400">{{ number_format($settings3D['camera']['dist'] ?? 5.0, 1) }}</span>
        </div>
        <input id="input-cam-dist" type="range" min="0" max="10" step="0.1" value="{{ $settings3D['camera']['dist'] ?? 5.0 }}" oninput="updateCameraDistance(this.value)" class="w-full accent-sky-400">
    </div>
    <div>
        <div class="flex justify-between text-[11px] mb-1">
            <span class="text-gray-300">Field of View (FOV: 0 - 10)</span>
            <span id="val-cam-fov" class="font-mono text-sky-400">{{ number_format($settings3D['camera']['fov'] ?? 5.0, 1) }}</span>
        </div>
        <input id="input-cam-fov" type="range" min="0" max="10" step="0.1" value="{{ $settings3D['camera']['fov'] ?? 5.0 }}" oninput="updateCameraFov(this.value)" class="w-full accent-sky-400">
    </div>
    <button type="button" onclick="reset3DCamera()" class="w-full bg-slate-800 hover:bg-slate-700 py-1.5 rounded-lg text-[11px] font-bold border border-white/10 text-gray-300 transition">
        <i class="fa-solid fa-rotate-left mr-1"></i> Reset Kamera Bawaan
    </button>
    <p id="camera-settings-status" class="text-[10px] text-sky-400 font-mono text-center pt-0.5"></p>
</div>

<!-- 2. Light, Shader & Shadow Panel -->
<div id="panel-3d-light" class="hidden fixed top-16 right-4 control-modal-panel-3d z-[10005] w-80 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3.5 text-xs select-none">
    <div class="flex items-center justify-between pb-2 border-b border-white/10">
        <div>
            <span class="font-bold flex items-center gap-1.5 text-amber-400 text-sm">
                <i class="fa-solid fa-sun"></i> Cahaya, Shader & Shadow
            </span>
            <p class="text-[10px] text-gray-400 mt-0.5">Pengaturan cahaya, bayangan & shader tone mapping</p>
        </div>
        <button type="button" onclick="toggle3DControlPanel('light')" class="text-gray-400 hover:text-white p-1">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- Shadow Toggle Card -->
    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/80 border border-white/10 shadow-inner">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-amber-400/10 flex items-center justify-center text-amber-400">
                <i class="fa-solid fa-cloud-moon"></i>
            </div>
            <div>
                <span class="text-gray-200 font-bold text-[11px] block">Bayangan (Shadow)</span>
                <span class="text-[9px] text-gray-400">Bayangan gedung & robot 3D</span>
            </div>
        </div>
        <button type="button" id="btn-toggle-3d-shadow" onclick="toggle3DShadow()" class="px-3 py-1.5 rounded-lg text-xs font-black transition bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm active:scale-95">
            ON
        </button>
    </div>

    <!-- Preset Mood Ruangan -->
    <div>
        <label class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block mb-1.5 flex items-center gap-1">
            <i class="fa-solid fa-wand-magic-sparkles text-amber-400"></i> Preset Suasana Ruangan
        </label>
        <div class="grid grid-cols-4 gap-1.5">
            <button type="button" onclick="setLightingPreset('siang')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-1 rounded-lg text-[10px] font-bold text-center border border-white/5 text-gray-200 hover:text-white transition">
                <i class="fa-solid fa-sun text-amber-300 block mb-0.5 text-xs"></i> Siang
            </button>
            <button type="button" onclick="setLightingPreset('sore')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-1 rounded-lg text-[10px] font-bold text-center border border-white/5 text-gray-200 hover:text-white transition">
                <i class="fa-solid fa-cloud-sun text-orange-400 block mb-0.5 text-xs"></i> Sore
            </button>
            <button type="button" onclick="setLightingPreset('malam')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-1 rounded-lg text-[10px] font-bold text-center border border-white/5 text-gray-200 hover:text-white transition">
                <i class="fa-solid fa-moon text-indigo-400 block mb-0.5 text-xs"></i> Malam
            </button>
            <button type="button" onclick="setLightingPreset('studio')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-1 rounded-lg text-[10px] font-bold text-center border border-white/5 text-gray-200 hover:text-white transition">
                <i class="fa-solid fa-lightbulb text-emerald-400 block mb-0.5 text-xs"></i> Studio
            </button>
        </div>
    </div>

    <!-- Sliders -->
    <div class="space-y-2.5 pt-1">
        <div>
            <div class="flex justify-between text-[11px] mb-1">
                <span class="text-gray-300">Ambient Light (Kecerahan Ruang)</span>
                <span id="val-light-ambient" class="font-mono text-amber-400 font-bold">{{ number_format($settings3D['lighting']['ambient'] ?? 1.4, 1) }}</span>
            </div>
            <input id="input-light-ambient" type="range" min="0.1" max="3.5" step="0.1" value="{{ $settings3D['lighting']['ambient'] ?? 1.4 }}" oninput="update3DLight('ambient', this.value)" class="w-full accent-amber-400">
        </div>
        <div>
            <div class="flex justify-between text-[11px] mb-1">
                <span class="text-gray-300">Sun Light (Cahaya Utama / Direct)</span>
                <span id="val-light-sun" class="font-mono text-amber-400 font-bold">{{ number_format($settings3D['lighting']['sun'] ?? 1.8, 1) }}</span>
            </div>
            <input id="input-light-sun" type="range" min="0.0" max="4.5" step="0.1" value="{{ $settings3D['lighting']['sun'] ?? 1.8 }}" oninput="update3DLight('sun', this.value)" class="w-full accent-amber-400">
        </div>
        <div>
            <div class="flex justify-between text-[11px] mb-1">
                <span class="text-gray-300">Exposure (Shader Tone Mapping)</span>
                <span id="val-light-exp" class="font-mono text-amber-400 font-bold">{{ number_format($settings3D['lighting']['exposure'] ?? 1.0, 2) }}</span>
            </div>
            <input id="input-light-exp" type="range" min="0.2" max="2.5" step="0.05" value="{{ $settings3D['lighting']['exposure'] ?? 1.0 }}" oninput="update3DLight('exposure', this.value)" class="w-full accent-amber-400">
        </div>
        <div>
            <div class="flex justify-between text-[11px] mb-1">
                <span class="text-gray-300">Fill Sky Light (Aksen Biru)</span>
                <span id="val-light-fill" class="font-mono text-amber-400 font-bold">{{ number_format($settings3D['lighting']['fill'] ?? 0.8, 1) }}</span>
            </div>
            <input id="input-light-fill" type="range" min="0.0" max="2.0" step="0.1" value="{{ $settings3D['lighting']['fill'] ?? 0.8 }}" oninput="update3DLight('fill', this.value)" class="w-full accent-amber-400">
        </div>
    </div>

    <!-- Reset Button -->
    <div class="pt-1">
        <button type="button" onclick="reset3DLighting()" class="w-full bg-slate-800 hover:bg-slate-700 py-1.5 rounded-lg text-[11px] font-bold border border-white/10 text-gray-300 hover:text-white transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-rotate-left text-xs"></i> Reset Pencahayaan Bawaan
        </button>
    </div>
    <p id="light-settings-status" class="text-[10px] text-amber-400 font-mono text-center pt-0.5"></p>
</div>

<!-- 3. Robot Position Control Panel (D-pad + Rotasi + World XZ) -->
<div id="panel-3d-robot-control" class="hidden fixed top-16 right-4 control-modal-panel-3d z-[10005] w-76 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs select-none">
    <div class="flex items-center justify-between pb-2 border-b border-white/10">
        <span class="font-bold flex items-center gap-1.5 text-emerald-400">
            <i class="fa-solid fa-robot"></i> Kontrol Posisi Robot
        </span>
        <button type="button" onclick="toggle3DControlPanel('robot')" class="text-gray-400 hover:text-white p-1">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
    <!-- Selected Robot Selector & Info -->
    <div class="space-y-1.5">
        <label class="block font-bold text-gray-400 uppercase tracking-wider text-[10px]">Pilih Robot</label>
        <select id="select-3d-robot" onchange="select3DRobotFromDropdown(this.value)" class="w-full bg-slate-800 border border-white/15 rounded-xl px-2.5 py-2 font-bold text-white focus:border-emerald-400 focus:outline-none transition text-xs">
            <option value="">-- Pilih Robot --</option>
            @foreach($robots as $robot)
                <option value="{{ $robot->id }}">{{ $robot->name }} (Lantai {{ $robot->floor ?? 1 }})</option>
            @endforeach
        </select>
        <div id="selected-robot-3d-info" class="bg-slate-800/80 border border-white/10 rounded-xl px-3 py-1.5 font-mono text-[11px] text-gray-300 truncate">Pilih robot dari dropdown atau klik avatar</div>
    </div>
    <!-- D-Pad 4 Arah (X/Z World) -->
    <div>
        <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1.5 text-[10px]">Gerak Datar (X/Z World)</label>
        <div class="grid grid-cols-3 gap-1.5 max-w-[180px] mx-auto">
            <div></div>
            <button type="button" onclick="move3DRobot(0, -0.3)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Depan (-Z)">
                <i class="fa-solid fa-arrow-up"></i>
            </button>
            <div></div>
            <button type="button" onclick="move3DRobot(-0.3, 0)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kiri (-X)">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <button type="button" onclick="move3DRobot(0, 0.3)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Belakang (+Z)">
                <i class="fa-solid fa-arrow-down"></i>
            </button>
            <button type="button" onclick="move3DRobot(0.3, 0)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kanan (+X)">
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
    <!-- D-Pad Vertikal (Y World) -->
    <div>
        <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1.5 text-[10px]">Gerak Vertikal (Y World)</label>
        <div class="flex items-center justify-center gap-2 max-w-[180px] mx-auto">
            <button type="button" onclick="move3DRobotY(0.3)" class="bg-sky-900/60 hover:bg-sky-800 border border-sky-400/30 text-sky-300 font-bold py-2 px-4 rounded-lg transition flex items-center justify-center" title="Naik (+Y)">
                <i class="fa-solid fa-arrow-up mr-1"></i> <span class="text-[10px]">Naik</span>
            </button>
            <button type="button" onclick="move3DRobotY(-0.3)" class="bg-sky-900/60 hover:bg-sky-800 border border-sky-400/30 text-sky-300 font-bold py-2 px-4 rounded-lg transition flex items-center justify-center" title="Turun (-Y)">
                <i class="fa-solid fa-arrow-down mr-1"></i> <span class="text-[10px]">Turun</span>
            </button>
        </div>
    </div>
    <!-- Rotasi Y -->
    <div>
        <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1.5 text-[10px]">Rotasi Y</label>
        <div class="flex items-center gap-2 mb-2">
            <button type="button" onclick="rotate3DRobot(-15)" class="bg-indigo-900/60 hover:bg-indigo-800 border border-indigo-400/30 text-indigo-300 font-bold py-2 px-3 rounded-lg transition" title="Putar CCW -15°">
                <i class="fa-solid fa-rotate-left"></i>
            </button>
            <button type="button" onclick="rotate3DRobot(15)" class="bg-indigo-900/60 hover:bg-indigo-800 border border-indigo-400/30 text-indigo-300 font-bold py-2 px-3 rounded-lg transition" title="Putar CW +15°">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
            <span id="val-robot-3d-rotation" class="text-[11px] font-mono font-bold text-indigo-300 ml-1">0°</span>
        </div>
        <input type="range" min="0" max="360" step="1" value="0" id="slider-robot-3d-rotation" oninput="set3DRobotRotation(this.value)" class="w-full accent-emerald-400">
    </div>
    <!-- World Coords Input -->
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1 text-[10px]">World X</label>
            <input type="number" step="0.1" id="input-robot-3d-x" oninput="set3DRobotWorldX(this.value)" class="w-full bg-slate-800 border border-white/10 rounded-xl px-2 py-2 font-mono font-bold text-white focus:border-emerald-400 focus:outline-none transition">
        </div>
        <div>
            <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1 text-[10px]">World Z</label>
            <input type="number" step="0.1" id="input-robot-3d-z" oninput="set3DRobotWorldZ(this.value)" class="w-full bg-slate-800 border border-white/10 rounded-xl px-2 py-2 font-mono font-bold text-white focus:border-emerald-400 focus:outline-none transition">
        </div>
        <div>
            <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1 text-[10px]">World Y</label>
            <input type="number" step="0.1" id="input-robot-3d-y" oninput="set3DRobotWorldY(this.value)" class="w-full bg-slate-800 border border-white/10 rounded-xl px-2 py-2 font-mono font-bold text-white focus:border-sky-400 focus:outline-none transition">
        </div>
    </div>
    <!-- Robot Object Scale (Custom Size) -->
    <div>
        <div class="flex justify-between text-[11px] mb-1">
            <span class="text-gray-300">Ukuran Object Robot</span>
            <span id="val-robot-scale" class="font-mono text-emerald-400 font-bold">{{ number_format($settings3D['robot_scale'] ?? 0.6, 1) }}x</span>
        </div>
        <input id="input-robot-scale" type="range" min="0.3" max="3.0" step="0.1" value="{{ $settings3D['robot_scale'] ?? 0.6 }}" oninput="set3DRobotScale(this.value)" class="w-full accent-emerald-400">
        <div class="flex justify-between text-[10px] text-gray-400 px-0.5 mt-0.5">
            <span>Kecil (0.3x)</span>
            <span>Normal (1.0x)</span>
            <span>Besar (3.0x)</span>
        </div>
    </div>
    <!-- Actions -->
    <div class="flex gap-2 pt-1">
        <button type="button" onclick="reset3DRobotPosition()" class="flex-1 bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-xl transition text-[11px]">
            <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset
        </button>
        <button type="button" onclick="save3DRobotToGraph()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl transition text-[11px]">
            <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan
        </button>
    </div>
    <p id="robot-3d-status" class="text-[10px] text-emerald-400 font-mono text-center pt-0.5"></p>
</div>

<!-- 4. Floating Dispatch Inspector Panel (Pop Up Kanan Full View) -->
<div id="fullview-dispatch-panel" class="hidden dashboard-inspector-floating p-5 select-none text-slate-100 z-[10002]">
    <!-- Header -->
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-700/80">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-sky-500 flex items-center justify-center text-white shadow-lg shadow-indigo-600/30">
                <i class="fa-solid fa-paper-plane text-sm"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                    Tugas Pengantaran Manual
                </h3>
                <p class="text-[10px] text-slate-400">Perintahkan robot mengantar barang langsung</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">PENGANTARAN</span>
            <button type="button" onclick="toggleFullViewDispatchPanel(false)" class="text-slate-400 hover:text-white w-7 h-7 rounded-lg hover:bg-white/10 flex items-center justify-center text-sm font-bold transition" title="Tutup Panel">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- Autopilot Quick Status Indicator / Toggle -->
    <div class="mb-4 p-3 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-between text-xs">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-robot text-emerald-400 text-sm" id="fv-dispatch-autopilot-icon"></i>
            <div>
                <div class="font-bold text-slate-200 text-[11px]" id="fv-dispatch-autopilot-title">Mode Autopilot: NONAKTIF</div>
                <div class="text-[10px] text-slate-400">Tugas manual langsung diprioritaskan</div>
            </div>
        </div>
        @if(auth()->check() && auth()->user()->isAdmin())
        <button type="button" onclick="toggleAutopilot()" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-700 hover:bg-slate-600 text-slate-200 border border-slate-600 transition shadow-sm">
            Ubah
        </button>
        @endif
    </div>

    <!-- Error Alert Box -->
    <div id="fv-dispatch-error" class="hidden mb-3.5 p-2.5 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-[11px] font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation text-rose-400 shrink-0"></i>
        <span id="fv-dispatch-error-text">Terjadi kesalahan.</span>
    </div>

    <!-- Success Alert Box -->
    <div id="fv-dispatch-success" class="hidden mb-3.5 p-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-[11px] font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-emerald-400 shrink-0"></i>
        <span id="fv-dispatch-success-text">Robot berhasil ditugaskan!</span>
    </div>

    <!-- Dispatch Form -->
    <form id="fv-dispatch-form" onsubmit="handleFullViewManualDispatch(event)" class="space-y-3 text-xs">
        <!-- Robot Selection -->
        <div>
            <label class="block font-bold text-slate-300 text-[10px] uppercase tracking-wider mb-1">Pilih Robot</label>
            <select id="fv-dispatch-robot" onchange="onFvDispatchRobotChange()" class="w-full bg-slate-800/95 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-medium" required>
                <!-- Options populated dynamically -->
            </select>
            <div id="fv-selected-robot-info" class="mt-1 flex items-center justify-between text-[10px] text-slate-400 px-1">
                <span id="fv-robot-pos-desc">Lokasi: -</span>
                <span id="fv-robot-bat-desc" class="font-bold text-emerald-400">Bat: -%</span>
            </div>
        </div>

        <!-- Item to Deliver -->
        <div>
            <label class="block font-bold text-slate-300 text-[10px] uppercase tracking-wider mb-1">Barang / Muatan</label>
            <select id="fv-dispatch-item" class="w-full bg-slate-800/95 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-medium" required>
                <option value="" disabled selected>Pilih barang yang diantar...</option>
                <option value="Dokumen">Dokumen</option>
                <option value="Makanan">Makanan</option>
                <option value="Kopi">Kopi</option>
                <option value="Paket">Paket</option>
                <option value="Sparepart">Sparepart</option>
                <option value="Handuk">Handuk</option>
                <option value="Botol Air">Botol Air</option>
            </select>
        </div>

        <!-- Starting Location -->
        <div>
            <label class="block font-bold text-slate-300 text-[10px] uppercase tracking-wider mb-1">Titik Jemput (Asal)</label>
            <select id="fv-dispatch-start" class="w-full bg-slate-800/95 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-medium" required>
                <!-- Dynamically populated -->
            </select>
        </div>

        <!-- Destination Location -->
        <div>
            <label class="block font-bold text-slate-300 text-[10px] uppercase tracking-wider mb-1">Titik Pengantaran (Tujuan)</label>
            <select id="fv-dispatch-dest" class="w-full bg-slate-800/95 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 font-medium" required>
                <!-- Dynamically populated -->
            </select>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="fv-dispatch-submit-btn" class="w-full mt-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-2.5 px-4 rounded-xl shadow-lg shadow-blue-600/30 transition flex items-center justify-center gap-2 active:scale-95">
            <i class="fa-solid fa-paper-plane"></i>
            <span id="fv-dispatch-btn-text">Tugaskan Robot Sekarang</span>
        </button>
    </form>

    <!-- Real-time Active Deliveries in Inspector -->
    <div class="mt-4 pt-3 border-t border-slate-700/80">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-300 flex items-center gap-1.5">
                <i class="fa-solid fa-route text-sky-400"></i> Pengantaran Berjalan
            </span>
            <span id="fv-active-count-badge" class="text-[9px] font-black px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/30">
                0 Aktif
            </span>
        </div>
        <div id="fv-active-deliv-container" class="space-y-2 max-h-44 overflow-y-auto pr-1">
            <!-- Populated dynamically -->
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    const floor2Img = "{{ asset('images/floor2.jpeg') }}";

    const locations = {
        @foreach($locations as $id => $loc)
        '{{ $id }}': { 
            id: '{{ $id }}',
            name: '{{ addslashes($loc['name'] ?? $id) }}',
            x: {{ $loc['x'] }}, 
            y: {{ $loc['y'] }}, 
            floor: {{ $loc['floor'] ?? 1 }},
            hidden: {{ ($loc['hidden'] ?? false) ? 'true' : 'false' }},
            is_destination: {{ ($loc['is_destination'] ?? false) ? 'true' : 'false' }},
            objectName: {!! isset($loc['objectName']) && $loc['objectName'] ? ("'" . addslashes($loc['objectName']) . "'") : 'null' !!}
        },
        @endforeach
    };

    const adj = {
        @foreach($adj as $node => $neighbors)
        '{{ $node }}': [ @foreach($neighbors as $nbr) '{{ $nbr }}', @endforeach ],
        @endforeach
    };

    // Auto-bridge isolated nodes like 1_Markas Robot to adjacent corridor nodes dynamically at runtime
    (function bridgeGraphNodes() {
        const baseId = '1_Markas Robot';
        if (locations[baseId]) {
            if (!adj[baseId] || adj[baseId].length === 0) {
                const targetNode = locations['1_N114'] ? '1_N114' : '1_N110';
                if (targetNode && locations[targetNode]) {
                    adj[baseId] = [targetNode];
                    if (!adj[targetNode]) adj[targetNode] = [];
                    if (!adj[targetNode].includes(baseId)) adj[targetNode].push(baseId);
                }
            }
        }
    })();

    // Parking slot calculation for Markas Robot so robots NEVER overlap
    function getBaseParkingOffset(robotIndex) {
        const i = Number(robotIndex) || 0;
        const col = i % 3; // 0, 1, 2
        const row = Math.floor(i / 3); // 0, 1
        const dx = (col - 1) * 1.8; // -1.8%, 0%, +1.8%
        const dy = (row - 0.5) * 1.6; // -0.8%, +0.8%
        return { dx, dy };
    }

    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);
    let activeAlerts = @json($activeAlerts ?? []);
    let isAutopilotEnabled = {{ Illuminate\Support\Facades\Cache::get('autopilot_enabled', false) ? 'true' : 'false' }};
    window.isAdmin = {{ (auth()->check() && auth()->user()->isAdmin()) ? 'true' : 'false' }};
    let settings3D = @json($settings3D ?? []);
    let current3DSettings = {
        camera: {
            dist: parseFloat(settings3D?.camera?.dist ?? 5.0),
            fov: parseFloat(settings3D?.camera?.fov ?? 5.0),
            preset: settings3D?.camera?.preset ?? 'iso'
        },
        lighting: {
            ambient: parseFloat(settings3D?.lighting?.ambient ?? 1.4),
            sun: parseFloat(settings3D?.lighting?.sun ?? 1.8),
            exposure: parseFloat(settings3D?.lighting?.exposure ?? 1.0),
            fill: parseFloat(settings3D?.lighting?.fill ?? 0.8),
            shadow: (settings3D?.lighting?.shadow !== undefined) ? !!settings3D.lighting.shadow : true
        },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0),
        robot_scale: parseFloat(settings3D?.robot_scale ?? 0.1),
        robot_elevation_f1: parseFloat(settings3D?.robot_elevation_f1 ?? 0.059),
        robot_elevation_f2: parseFloat(settings3D?.robot_elevation_f2 ?? 0.112),
        node_scale: parseFloat(settings3D?.node_scale ?? 0.6),
        node_color: settings3D?.node_color ?? '#ff0000'
    };
    let settings3DSaveTimeout = null;
    let serverClientOffset = 0;
    let currentDashboardFloor = 1;
    let isFullViewMode = false;
    let currentFullViewFloor = 1;

    // 3D Three.js State, Cache & Loader
    const floor2ModelUrl = "{{ asset('models/Lantai_2-final.glb') }}";
    const floor1ModelUrl = "{{ asset('models/Denah_Lantai_1-opt.glb') }}";
    const robotModelUrl = "{{ asset('models/robot.glb') }}";
    const MODEL_CACHE_NAME = 'robopath-models-v1';
    let threeStd = null;
    let threeStdF1 = null;
    let modelLoadedByFloor = { 1: false, 2: false };
    // Viewer 3D yang sedang tampil sesuai lantai aktif
    function activeStdViewer(){ return Number(currentDashboardFloor) === 1 ? threeStdF1 : threeStd; }
    function allViewers(){ return [threeStd, threeStdF1].filter(v => !!v); }
    // Koordinat parkir avatar (% denah) per lantai — dekat Stairs masing-masing
    function parkCoordsForFloor(f){ return Number(f) === 1 ? { x: 72.1, y: 85.71 } : { x: 72.3, y: 66.3 }; }
    function getBaseLocationId() {
        if (locations['1_Markas Robot']) return '1_Markas Robot';
        if (locations['1_N7']) return '1_N7';
        for (const [id, loc] of Object.entries(locations)) {
            if (Number(loc.floor) === 1 && (loc.name?.toLowerCase().includes('markas') || loc.name?.toLowerCase().includes('base'))) {
                return id;
            }
        }
        for (const [id, loc] of Object.entries(locations)) {
            if (Number(loc.floor) === 1) return id;
        }
        return '1_Markas Robot';
    }
    function getBaseLocation() {
        const id = getBaseLocationId();
        return locations[id] || { x: 85.48, y: 51.07, floor: 1, name: 'Markas Robot' };
    }
    // Cari viewer pemilik holder (utk kontrol manual D-pad)
    function viewerOfHolder(holder){
        for (const v of [threeStd, threeStdF1]) {
            if (v && v.robotMeshes) { for (const h of v.robotMeshes.values()) { if (h === holder) return v; } }
        }
        return activeStdViewer();
    }
    let active3DPanel = null;
    let show3DRoomLabels = true;
    let labelScaleMultiplier = {{ $labelScale ?? 1.0 }};
    let labelScaleSaveTimeout = null;
    // Lantai 2 Robot Monitoring state
    let focusedRobotId = null;
    let isFollowMode = false;
    let followCameraMode = 'fpv'; // 'fpv' (Mata Robot), 'chase' (Tampak Belakang), or 'orbit' (Orbit Bebas)
    let showNetworkLines = false;
    let isEditingRobot3D = false;
    let robotTemplate = null;
    let robotTemplateReady = false;
    let robotTemplateLoading = false;
    let robotTemplateCallbacks = [];
    let robotTemplateTries = 0;
    let robotTemplateFailed = false;

    // Helper: Create sleek 2D-style robot icon sprite (white card + vector robot icon + colored border, compact)
    function create2DRobotMarkerSprite(robotId, robotName, robotColor) {
        const cPin = document.createElement('canvas');
        cPin.width = 128;
        cPin.height = 128;
        const ctx = cPin.getContext('2d');

        // White rounded card
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(14, 14, 100, 100, 22);
        else ctx.rect(14, 14, 100, 100);
        ctx.fill();
        ctx.strokeStyle = robotColor;
        ctx.lineWidth = 7;
        ctx.stroke();

        // Vector robot icon (FontAwesome fa-robot style)
        ctx.fillStyle = robotColor;
        // Antenna
        ctx.beginPath();
        ctx.arc(64, 30, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillRect(62, 33, 4, 8);

        // Robot Head
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(36, 41, 56, 46, 8);
        else ctx.rect(36, 41, 56, 46);
        ctx.fill();

        // Ears
        ctx.fillRect(28, 53, 8, 18);
        ctx.fillRect(92, 53, 8, 18);

        // Eye Visor / Eyes
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(44, 51, 40, 14, 4);
        else ctx.rect(44, 51, 40, 14);
        ctx.fill();

        // Pupils
        ctx.fillStyle = robotColor;
        ctx.beginPath();
        ctx.arc(52, 58, 3.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(76, 58, 3.5, 0, Math.PI * 2);
        ctx.fill();

        // Mouth grill
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(48, 73, 32, 4);

        const pinTex = new THREE.CanvasTexture(cPin);
        pinTex.minFilter = THREE.LinearFilter;
        const pinMat = new THREE.SpriteMaterial({ map: pinTex, transparent: true, depthTest: false, depthWrite: false });
        const markerSprite = new THREE.Sprite(pinMat);
        markerSprite.scale.set(0.075, 0.075, 1);
        markerSprite.position.set(0, 0.13, 0);
        markerSprite.renderOrder = 1002;
        return markerSprite;
    }

    // Helper: Create compact, sleek robot name badge (not giant!)
    function create2DRobotNameSprite(robotName, robotColor) {
        const c = document.createElement('canvas');
        c.width = 256;
        c.height = 56;
        const ctx = c.getContext('2d');

        ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(6, 6, 244, 44, 10);
        else ctx.rect(6, 6, 244, 44);
        ctx.fill();
        ctx.strokeStyle = robotColor;
        ctx.lineWidth = 2.5;
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        let cleanName = String(robotName || 'Robot').replace(/^Robot\s*/i, '');
        ctx.fillText(cleanName, 128, 28);

        const tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        const sMat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false, depthWrite: false });
        const nameSprite = new THREE.Sprite(sMat);
        nameSprite.scale.set(0.155, 0.034, 1);
        nameSprite.position.set(0, 0.09, 0);
        nameSprite.renderOrder = 1001;
        nameSprite.userData = { canvas: c, texture: tex };
        return nameSprite;
    }

    // Helper: Create high-DPI room label sprite (compact & sleek)
    function createRoomLabelSprite(text, isDest = true, isStairs = false) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = 384;
        canvas.height = 80;

        const isMarkas = String(text).toLowerCase().includes('markas');
        const bgFill = isMarkas 
            ? 'rgba(16, 185, 129, 0.95)' 
            : (isStairs ? 'rgba(217, 119, 6, 0.92)' : (isDest ? 'rgba(15, 23, 42, 0.88)' : 'rgba(30, 41, 59, 0.80)'));
        const borderColor = isMarkas 
            ? '#34d399' 
            : (isStairs ? '#fbbf24' : (isDest ? '#38bdf8' : '#94a3b8'));

        const radius = 16;
        ctx.fillStyle = bgFill;
        ctx.strokeStyle = borderColor;
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.roundRect(6, 6, canvas.width - 12, canvas.height - 12, radius);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = borderColor;
        ctx.beginPath();
        ctx.arc(28, canvas.height / 2, 6, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';

        let cleanText = String(text).replace(/^[12]_/, '');
        if (cleanText.length > 18) cleanText = cleanText.substring(0, 16) + '...';
        ctx.fillText(cleanText, 46, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.minFilter = THREE.LinearFilter;
        texture.wrapS = THREE.ClampToEdgeWrapping;
        texture.wrapT = THREE.ClampToEdgeWrapping;

        const spriteMaterial = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthTest: true,
            depthWrite: false
        });

        const sprite = new THREE.Sprite(spriteMaterial);
        const baseW = 1.35 * labelScaleMultiplier;
        const baseH = 0.28 * labelScaleMultiplier;
        sprite.scale.set(baseW, baseH, 1);
        sprite.renderOrder = 900;
        return sprite;
    }

    // Helper: Unified Cached GLB loader leveraging window.RobopathGLBCache (Memory + IDB + CacheStorage)
    async function fetchGLBBufferWithCache(url, onProgress) {
        if (window.RobopathGLBCache && typeof window.RobopathGLBCache.fetchWithProgress === 'function') {
            return await window.RobopathGLBCache.fetchWithProgress(url, onProgress);
        }
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cachedResponse = await cache.match(url);
                if (cachedResponse) {
                    if (onProgress) onProgress(1, 1, true);
                    return await cachedResponse.arrayBuffer();
                }
            } catch (e) {
                console.warn('[Robopath Cache] CacheStorage read bypass:', e);
            }
        }

        // Fetch from network with custom progress tracker
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const contentLength = response.headers.get('content-length');
        const totalBytes = contentLength ? parseInt(contentLength, 10) : 14080452;
        let loadedBytes = 0;

        const reader = response.body.getReader();
        const chunks = [];

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
            loadedBytes += value.length;
            if (onProgress) onProgress(loadedBytes, totalBytes, false);
        }

        // Combine chunks
        const allChunks = new Uint8Array(loadedBytes);
        let pos = 0;
        for (const chunk of chunks) {
            allChunks.set(chunk, pos);
            pos += chunk.length;
        }
        const buffer = allChunks.buffer;

        // Store into CacheStorage asynchronously
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const headers = new Headers();
                headers.append('Content-Type', 'model/gltf-binary');
                headers.append('Content-Length', String(buffer.byteLength));
                const cacheResponse = new Response(buffer.slice(0), { headers });
                await cache.put(url, cacheResponse);
            } catch (e) {
                console.warn('[Robopath Cache] CacheStorage write bypass:', e);
            }
        }

        return buffer;
    }

    // Helper: mapping 2D percent -> 3D world (XZ plane + Y elevation)
    // _u/_v runtime (hasil resolveObjectAnchor dari Box3 GLB) diutamakan; fallback x/y persen.
    function worldPosForLoc(loc, size) {
        const u = (loc._u ?? loc.x / 100), v = (loc._v ?? loc.y / 100);
        const yElev = (loc.y_elev !== undefined && loc.y_elev !== null) ? Number(loc.y_elev) : (loc._fy ?? 0);
        return new THREE.Vector3((u - 0.5) * (size.x * 0.95), yElev, (v - 0.5) * (size.z * 0.95));
    }
    function locFromWorld(worldX, worldZ, size) {
        const xPct = ((worldX / (size.x*0.95)) + 0.5) * 100;
        const yPct = ((worldZ / (size.z*0.95)) + 0.5) * 100;
        return { x: Math.max(0, Math.min(100, xPct)), y: Math.max(0, Math.min(100, yPct)) };
    }
    // ObjectName anchor: posisi runtime dari Box3 center geometri GLB (GLB = source of truth).
    // Hasil di field runtime _u/_v/_fy — tidak pernah persist ke graph.json. Fallback x/y bila Not found.
    function resolveObjectAnchor(loc, model, size) {
        if (!loc || !loc.objectName || !model || !size || !(size.x > 0.1)) return false;
        const obj = model.getObjectByName(loc.objectName);
        if (!obj) { console.warn('[Robopath] objectName tidak ditemukan di GLB:', loc.objectName); return false; }
        const box = new THREE.Box3().setFromObject(obj);
        if (box.isEmpty()) return false;
        const c = box.getCenter(new THREE.Vector3());
        const clamp01 = v => Math.max(0, Math.min(1, v));
        loc._u = clamp01(c.x / (size.x * 0.95) + 0.5);
        loc._v = clamp01(c.z / (size.z * 0.95) + 0.5);
        try {
            const rc = new THREE.Raycaster(new THREE.Vector3(c.x, c.y + 5, c.z), new THREE.Vector3(0, -1, 0), 0, 20);
            const hits = rc.intersectObject(model, true);
            loc._fy = hits.length ? hits[0].point.y : 0.05;
        } catch (e) { loc._fy = 0.05; }
        return true;
    }
    function resolveAllObjectAnchors(store, model, size, floorNum) {
        let ok = 0; const miss = [];
        for (const id in store) {
            const loc = store[id];
            if (Number(loc.floor) !== Number(floorNum) || !loc.objectName) continue;
            if (resolveObjectAnchor(loc, model, size)) ok++;
            else miss.push(id + ' (' + loc.objectName + ')');
        }
        console.log('[Robopath] object anchors resolved:', ok, miss.length ? ('NOT FOUND: ' + miss.join(', ')) : '');
    }
    function ensureRobotTemplate(cb){
        if(robotTemplateReady){ cb(robotTemplate); return; }
        if(robotTemplateFailed){ cb(null); return; }
        robotTemplateCallbacks.push(cb);
        if(robotTemplateLoading) return;
        robotTemplateLoading = true;
        robotTemplateTries++;
        fetchGLBBufferWithCache(robotModelUrl).then(buf=>{
            const loader = new THREE.GLTFLoader();
            if(typeof THREE.DRACOLoader!=='undefined'){ const d=new THREE.DRACOLoader(); d.setDecoderPath("{{ asset('draco') }}/"); loader.setDRACOLoader(d); }
            loader.parse(buf,'', (gltf)=>{
                const root = gltf.scene;
                // normalize: center horizontally, sit on ground, scale to ~0.45m tall vs gedung
                const box = new THREE.Box3().setFromObject(root);
                const sz = box.getSize(new THREE.Vector3()); const ctr = box.getCenter(new THREE.Vector3());
                root.position.x -= ctr.x; root.position.z -= ctr.z; root.position.y -= box.min.y;
                // robot.glb height ~2.0 -> scale 0.28 gives ~0.56 world units (~roof 0.44)
                const targetH = 0.55; const s = sz.y>0.01 ? (targetH/sz.y) : 0.35;
                root.scale.set(s,s,s);
                root.traverse(c=>{ if(c.isMesh){ c.castShadow=true; c.receiveShadow=true; }});
                robotTemplate = root; robotTemplateReady=true; robotTemplateLoading=false;
                robotTemplateCallbacks.forEach(fn=>{ try{fn(robotTemplate);}catch(e){} }); robotTemplateCallbacks=[];
            }, ()=>{
                console.warn('[Robopath] robot.glb parse fail');
                robotTemplateLoading=false;
                retryRobotTemplate();
            });
        }).catch(e=>{
            console.warn('[Robopath] robot.glb fetch fail', e);
            robotTemplateLoading=false;
            retryRobotTemplate();
        });
    }
    function retryRobotTemplate(){
        if(robotTemplateTries < 3){
            setTimeout(()=>{ try{ ensureRobotTemplate(()=>{}); }catch(e){} }, 1500);
        } else {
            robotTemplateFailed=true;
            console.error('[Robopath] robot.glb gagal dimuat setelah 3x percobaan — avatar tetap placeholder box');
            robotTemplateCallbacks.forEach(fn=>{ try{fn(null);}catch(e){} }); robotTemplateCallbacks=[];
        }
    }

    function initThreeViewer(containerId, floorNum, onLoadedCallback) {
        const container = document.getElementById(containerId);
        if (!container) return null;
        // Lantai yg divisualkan viewer ini (default 2 agar panggilan lama tetap jalan)
        floorNum = Number(floorNum) === 1 ? 1 : 2;
        const modelUrl = floorNum === 1 ? floor1ModelUrl : floor2ModelUrl;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0f172a);

        const parent = container.parentElement;
        const width = container.offsetWidth || parent?.offsetWidth || 800;
        const height = container.offsetHeight || parent?.offsetHeight || 450;

        const initFovVal = parseFloat(current3DSettings.camera.fov ?? 5.0);
        const initFov = 20 + (initFovVal / 10) * 70;
        const camera = new THREE.PerspectiveCamera(initFov, width / height, 0.01, 1000);
        camera.position.set(0, 38, 48);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.25));
        renderer.outputEncoding = THREE.sRGBEncoding;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = parseFloat(current3DSettings.lighting.exposure ?? 1.0);
        const isShadowOn = current3DSettings.lighting.shadow !== false;
        renderer.shadowMap.enabled = isShadowOn;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        container.innerHTML = '';
        container.appendChild(renderer.domElement);

        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.maxPolarAngle = Math.PI / 2.05;
        controls.minDistance = 0.1;
        controls.maxDistance = 250;
        controls.enablePan = true;
        controls.screenSpacePanning = true;
        controls.panSpeed = 1.2;
        controls.zoomSpeed = 1.3;

        // Lights reference object for interactive sliders (inits from server graph.json)
        const ambientLight = new THREE.AmbientLight(0xffffff, parseFloat(current3DSettings.lighting.ambient ?? 1.4));
        scene.add(ambientLight);

        const sunLight = new THREE.DirectionalLight(0xffffff, parseFloat(current3DSettings.lighting.sun ?? 1.8));
        sunLight.position.set(35, 55, 35);
        sunLight.castShadow = isShadowOn;
        sunLight.shadow.mapSize.width = 1024;
        sunLight.shadow.mapSize.height = 1024;
        scene.add(sunLight);

        const fillLight = new THREE.DirectionalLight(0x93c5fd, parseFloat(current3DSettings.lighting.fill ?? 0.8));
        fillLight.position.set(-30, 25, -30);
        scene.add(fillLight);

        // Subtle ground grid
        const grid = new THREE.GridHelper(80, 40, 0x3b4cb8, 0x334155);
        grid.position.y = -0.05;
        scene.add(grid);

        // Labels + Monitoring Groups (per lantai viewer ini)
        const labelsGroup = new THREE.Group();
        labelsGroup.visible = show3DRoomLabels;
        scene.add(labelsGroup);
        const robotsGroup = new THREE.Group();
        scene.add(robotsGroup);
        const networkGroup = new THREE.Group();
        networkGroup.visible = showNetworkLines;
        scene.add(networkGroup);
        const activePathGroup = new THREE.Group();
        scene.add(activePathGroup);
        const robotMeshes = new Map();
        let networkBuilt = false;

        // Pre-load robot.glb early (saingan dengan fetch Lantai_2) supaya eager-create lebih cepat siap
        try { ensureRobotTemplate(() => {}); } catch (e) { console.warn('[Robopath] pre-load robot.glb fail', e); }

        function buildNetworkLines(){
            if(networkBuilt) return; networkBuilt=true;
            networkGroup.clear();
            const seen=new Set();
            const y = 0.03; // Menempel langsung di lantai 3D
            for(const a in adj){
                if(!locations[a] || Number(locations[a].floor)!==floorNum) continue;
                for(const b of (adj[a]||[])){
                    if(!locations[b] || Number(locations[b].floor)!==floorNum) continue;
                    const key=[a,b].sort().join('|'); if(seen.has(key)) continue; seen.add(key);
                    const pA=worldPosForLoc(locations[a], modelSize); pA.y=y;
                    const pB=worldPosForLoc(locations[b], modelSize); pB.y=y;
                    const geo=new THREE.BufferGeometry().setFromPoints([pA,pB]);
                    const mat=new THREE.LineBasicMaterial({color:0x38bdf8, transparent:true, opacity:0.65});
                    networkGroup.add(new THREE.Line(geo, mat));
                }
            }
            // Node pads di lantai 3D
            for(const id in locations){
                const loc = locations[id];
                if(Number(loc.floor) !== floorNum) continue;
                const isDest = !!loc.is_destination;
                const isStairs = id.includes('Stairs');
                const radius = (isDest || isStairs) ? 0.10 : 0.05;
                const color = isStairs ? 0xf59e0b : (isDest ? 0xff0000 : 0x64748b);
                const discGeo = new THREE.CylinderGeometry(radius, radius, 0.015, 24);
                const discMat = new THREE.MeshBasicMaterial({
                    color: color,
                    transparent: isHidden,
                    opacity: isHidden ? 0.6 : 1.0
                });
                const disc = new THREE.Mesh(discGeo, discMat);
                const wp = worldPosForLoc(loc, modelSize);
                disc.position.set(wp.x, wp.y + 0.015, wp.z);
                networkGroup.add(disc);
            }
        }
        function getOrCreateRobotMesh(robot){
            const id=Number(robot.id);
            if(robotMeshes.has(id)) return robotMeshes.get(id);
            const holder=new THREE.Group(); holder.userData.robotId=id;
            const rSc = parseFloat(current3DSettings.robot_scale ?? 0.1);

            // Sub-group untuk model fisik robot (di-scale rSc)
            const modelHolder=new THREE.Group();
            modelHolder.scale.set(rSc, rSc, rSc);
            modelHolder.rotation.y = Math.PI;
            holder.add(modelHolder);
            holder.userData.modelHolder = modelHolder;

            // placeholder box until glb ready
            const boxMesh=new THREE.Mesh(new THREE.BoxGeometry(0.35,0.5,0.35), new THREE.MeshStandardMaterial({color:getRobotColor(id)}));
            boxMesh.position.y=0.25; boxMesh.castShadow=true; boxMesh.receiveShadow=true;
            modelHolder.add(boxMesh); holder.userData.boxMesh=boxMesh;

            // 2D Style Robot Marker Card Sprite (sleek white card with vector robot icon & robot border)
            const markerSprite = create2DRobotMarkerSprite(id, robot.name, getRobotColor(id));
            markerSprite.position.set(0, 0.13, 0);
            holder.add(markerSprite);
            holder.userData.markerSprite = markerSprite;

            // Compact Badge Nama Robot (World space: proporsional, tajam & rapi)
            const nameSprite = create2DRobotNameSprite(robot.name, getRobotColor(id));
            nameSprite.position.set(0, 0.09, 0);
            holder.add(nameSprite); 
            holder.userData.nameSprite=nameSprite;

            // Compact Status Badge Sprite
            const c3=document.createElement('canvas'); c3.width=384; c3.height=56;
            const stMat=new THREE.SpriteMaterial({map:new THREE.CanvasTexture(c3), transparent:true, depthTest:false, depthWrite:false});
            const stSpr=new THREE.Sprite(stMat); 
            stSpr.scale.set(0.165, 0.026, 1); 
            stSpr.position.set(0, 0.06, 0); 
            stSpr.renderOrder=1000; 
            stSpr.visible=false;
            stSpr.userData={canvas:c3, texture:stMat.map}; 
            holder.add(stSpr); 
            holder.userData.statusSprite=stSpr;

            robotsGroup.add(holder); robotMeshes.set(id, holder);
            const swapBoxForGlb=(tpl)=>{
                if(!tpl || !holder.parent) return;
                if(holder.userData.glbClone) return;
                const clone=tpl.clone(true);
                const col=new THREE.Color(getRobotColor(id));
                clone.traverse(n=>{
                    if(n.isMesh && n.material){
                        n.material=n.material.clone();
                        if(n.material.color) n.material.color.lerp(col,0.25);
                        n.castShadow=true; n.receiveShadow=true;
                    }
                });
                clone.position.set(0,0,0);
                modelHolder.remove(boxMesh); boxMesh.geometry.dispose();
                modelHolder.add(clone); holder.userData.glbClone=clone;
            };
            if(robotTemplateReady){ swapBoxForGlb(robotTemplate); }
            else { try{ ensureRobotTemplate(swapBoxForGlb); }catch(e){} }
            return holder;
        }

        let loadedModel = null;
        let modelCenter = new THREE.Vector3();
        let modelSize = new THREE.Vector3();
        let defaultCamPos = new THREE.Vector3();
        let defaultCamTarget = new THREE.Vector3();

        // Loader UI elements (overlay milik std-view; dipakai bergantian per lantai aktif)
        const loaderEl = document.getElementById('std-3d-loader');
        const loaderBar = document.getElementById('std-3d-loader-bar');
        const loaderPct = document.getElementById('std-3d-loader-pct');
        const loaderStatus = document.getElementById('std-3d-loader-status');
        const loaderTitle = document.getElementById('std-3d-loader-title');

        if (loaderEl && !modelLoadedByFloor[floorNum]) {
            loaderEl.classList.remove('hidden');
            if (loaderTitle) loaderTitle.textContent = `Menyiapkan Model 3D Lantai ${floorNum}...`;
            if (loaderStatus) loaderStatus.textContent = `Memeriksa penyimpanan lokal...`;
            if (window.RobopathGLBCache && typeof window.RobopathGLBCache.isCached === 'function') {
                window.RobopathGLBCache.isCached(modelUrl).then(isCached => {
                    if (isCached && loaderStatus) {
                        loaderStatus.textContent = 'Memuat dari penyimpanan lokal (Instan)...';
                        if (loaderBar) loaderBar.style.width = '85%';
                        if (loaderPct) loaderPct.textContent = '85%';
                    } else if (loaderStatus) {
                        loaderStatus.textContent = `Mengunduh aset GLB (${floorNum === 1 ? '8' : '14'} MB)...`;
                    }
                }).catch(() => {});
            }
        }

        // Setup GLTF Loader with DRACO
        const gltfLoader = new THREE.GLTFLoader();
        if (typeof THREE.DRACOLoader !== 'undefined') {
            const dracoLoader = new THREE.DRACOLoader();
            dracoLoader.setDecoderPath("{{ asset('draco') }}/");
            gltfLoader.setDRACOLoader(dracoLoader);
        }

        // Safety watchdog: loader overlay cannot be stuck permanently (max 10s auto-dismiss)
        const watchdogTimer = setTimeout(() => {
            if (loaderEl && !loaderEl.classList.contains('hidden') && Number(currentDashboardFloor) === floorNum) {
                console.warn(`[Robopath 3D] Watchdog auto-dismiss loader for Floor ${floorNum}`);
                if (loaderBar) loaderBar.style.width = '100%';
                if (loaderPct) loaderPct.textContent = '100%';
                loaderEl.classList.add('hidden');
            }
        }, 10000);

        // Load GLB using cached ArrayBuffer
        fetchGLBBufferWithCache(modelUrl, (loadedBytes, totalBytes, fromCache) => {
            if (loaderEl && Number(currentDashboardFloor) === floorNum) {
                if (fromCache) {
                    if (loaderBar) loaderBar.style.width = '92%';
                    if (loaderPct) loaderPct.textContent = '92%';
                    if (loaderStatus) loaderStatus.textContent = 'Memuat dari Cache Lokal (Instan)...';
                } else {
                    const percent = Math.min(Math.round((loadedBytes / totalBytes) * 100), 99);
                    if (loaderBar) loaderBar.style.width = `${percent}%`;
                    if (loaderPct) loaderPct.textContent = `${percent}%`;
                    if (loaderStatus) loaderStatus.textContent = `Mengunduh: ${(loadedBytes / 1048576).toFixed(1)} MB / ${(totalBytes / 1048576).toFixed(1)} MB`;
                }
            }
        })
        .then(buffer => {
            gltfLoader.parse(buffer, '', (gltf) => {
                clearTimeout(watchdogTimer);
                try {
                    modelLoadedByFloor[floorNum] = true;
                    loadedModel = gltf.scene;

                    const box = new THREE.Box3().setFromObject(loadedModel);
                    box.getCenter(modelCenter);
                    box.getSize(modelSize);

                    loadedModel.position.x -= modelCenter.x;
                    loadedModel.position.y -= box.min.y;
                    loadedModel.position.z -= modelCenter.z;

                    loadedModel.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = true;
                            child.receiveShadow = true;
                        }
                    });

                    const mScale = parseFloat(current3DSettings.model_scale ?? 1.0);
                    loadedModel.scale.set(mScale, mScale, mScale);
                    // recompute size for scaled model
                    const scaledBox = new THREE.Box3().setFromObject(loadedModel);
                    const scaledSize = scaledBox.getSize(new THREE.Vector3());
                    // use scaledSize for labels/camera if available
                    if(scaledSize.x>0.1) modelSize.copy(scaledSize);
                    scene.add(loadedModel);

                    // Resolve posisi destination dari nama object Blender (Box3 center). Fallback x/y bila tak ketemu.
                    try { resolveAllObjectAnchors(locations, loadedModel, modelSize, floorNum); } catch (e) { console.warn('[Robopath] resolve anchors fail', e); }

                    // Build 3D Room Labels — hanya destinasi + stairs (transit disembunyikan agar bersih)
                    labelsGroup.clear();
                    const floorElev = (floorNum === 2)
                        ? parseFloat(current3DSettings.robot_elevation_f2 ?? 0.112)
                        : parseFloat(current3DSettings.robot_elevation_f1 ?? 0.059);

                    let labelIdx = 0;
                    for (let id in locations) {
                        const loc = locations[id];
                        if (Number(loc.floor) !== floorNum) continue;
                        const isStairs = id.includes('Stairs') || id.includes('Tangga');
                        if (!loc.is_destination && !isStairs) continue;
                        const sprite = createRoomLabelSprite(loc.name || id, loc.is_destination, isStairs);
                        const wp = worldPosForLoc(loc, modelSize);
                        // Posisikan tepat di atas lantai ruangan (bukan melayang di langit-langit!)
                        sprite.position.set(wp.x, floorElev + 0.16 + (labelIdx % 3) * 0.03, wp.z);
                        labelIdx++;
                        labelsGroup.add(sprite);
                    }
                    labelsGroup.visible = show3DRoomLabels;
                    // Build network lines (Lantai 2 adj) — garis ke semua ruangan
                    try{ buildNetworkLines(); }catch(e){}

                    // Eager-create semua robot mesh saat model lantai ready
                    try {
                        const baseLoc = getBaseLocation();
                        robots.forEach((r, idx) => {
                            const holder = getOrCreateRobotMesh(r);
                            const isIdleNearBase = (r.status === 'Idle' || !r.status || (Number(r.floor || 1) === 1 && Math.hypot((r.current_x || baseLoc.x) - baseLoc.x, (r.current_y || baseLoc.y) - baseLoc.y) < 3.0));
                            const parkOffset = isIdleNearBase ? getBaseParkingOffset(idx) : { dx: 0, dy: 0 };
                            const rx = (r.current_x !== undefined && r.current_x !== null && !isIdleNearBase) ? r.current_x : (baseLoc.x + parkOffset.dx);
                            const ry = (r.current_y !== undefined && r.current_y !== null && !isIdleNearBase) ? r.current_y : (baseLoc.y + parkOffset.dy);
                            const rf = Number(r.floor || 1);
                            const wp = worldPosForLoc({ x: rx, y: ry }, modelSize);
                            const rElev = (rf === 2)
                                ? parseFloat(current3DSettings.robot_elevation_f2 ?? 0.112)
                                : parseFloat(current3DSettings.robot_elevation_f1 ?? 0.059);
                            holder.position.set(wp.x, rElev, wp.z);
                            if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
                            holder.userData.targetWp.copy(holder.position);
                            holder.rotation.y = -((r.rotation || 0) * Math.PI / 180);
                            holder.visible = (rf === floorNum);
                        });
                        console.log('[Robopath] robotMeshes eager-created:', robotMeshes.size, `(Lantai ${floorNum})`);
                    } catch (e) { console.warn('[Robopath] eager-create robotMeshes fail', e); }

                    // Posisikan target kamera ke lantai (Markas Robot jika Lantai 1, atau tengah denah)
                    let focusTarget = new THREE.Vector3(0, floorElev, 0);
                    try {
                        const baseLoc = getBaseLocation();
                        if (floorNum === 1 && baseLoc) {
                            const baseWp = worldPosForLoc(baseLoc, modelSize);
                            focusTarget.set(baseWp.x, floorElev, baseWp.z);
                        } else {
                            const bbox = new THREE.Box3().setFromObject(loadedModel);
                            if (!bbox.isEmpty()) {
                                const c = bbox.getCenter(new THREE.Vector3());
                                focusTarget.set(c.x, floorElev, c.z);
                            }
                        }
                    } catch(e) {}

                    defaultCamTarget.copy(focusTarget);
                    controls.target.copy(focusTarget);

                    // Langsung zoom dekat ke lantai saat awal tampil (detail lantai dan robot langsung terlihat!)
                    camera.position.set(
                        focusTarget.x + 3.2,
                        floorElev + 5.2,
                        focusTarget.z + 6.2
                    );
                    camera.lookAt(focusTarget);
                    controls.minDistance = 0.1;
                    controls.maxDistance = 250;
                    controls.update();
                } catch (parseErr) {
                    console.error('[Robopath 3D] Error setting up model in scene:', parseErr);
                } finally {
                    // Hide loader with smooth fade unconditionally
                    if (loaderEl) {
                        if (loaderBar) loaderBar.style.width = '100%';
                        if (loaderPct) loaderPct.textContent = '100%';
                        if (loaderStatus) loaderStatus.textContent = 'Model siap!';
                        setTimeout(() => { loaderEl.classList.add('hidden'); }, 120);
                    }
                    if (typeof onLoadedCallback === 'function') onLoadedCallback();
                }
            }, (err) => {
                clearTimeout(watchdogTimer);
                console.error('[Robopath 3D] Error parsing GLB buffer:', err);
                if (loaderStatus && Number(currentDashboardFloor) === floorNum) loaderStatus.textContent = 'Gagal memproses model 3D! Coba pindah lantai dan kembali.';
                setTimeout(() => { if (loaderEl) loaderEl.classList.add('hidden'); }, 1500);
            });
        })
        .catch(err => {
            clearTimeout(watchdogTimer);
            console.error('[Robopath 3D] Error fetching model:', err);
            if (loaderStatus && Number(currentDashboardFloor) === floorNum) loaderStatus.textContent = 'Gagal mengunduh aset 3D! Coba refresh.';
            setTimeout(() => { if (loaderEl) loaderEl.classList.add('hidden'); }, 1500);
        });

        // Raycast klik avatar 3D untuk memilih/fokus robot (TIDAK menggeser posisi robot di dashboard)
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        let clickedRobotId = null;
        let pointerStartClient = { x: 0, y: 0 };

        renderer.domElement.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            pointerStartClient = { x: e.clientX, y: e.clientY };
            clickedRobotId = null;
            const rect = renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(mouse, camera);
            const targets = [];
            robotMeshes.forEach(h => { if (h.visible) targets.push(h); });
            const hits = raycaster.intersectObjects(targets, true);
            if (hits.length) {
                let obj = hits[0].object;
                while (obj && obj.parent && !obj.userData.robotId) obj = obj.parent;
                let holder = obj;
                while (holder && !robotMeshes.has(Number(holder.userData?.robotId))) holder = holder.parent;
                if (!holder || !holder.userData.robotId) {
                    const rid = obj?.userData?.robotId ?? hits[0].object?.parent?.userData?.robotId;
                    if (rid && robotMeshes.has(Number(rid))) holder = robotMeshes.get(Number(rid));
                }
                if (holder && holder.userData.robotId) {
                    clickedRobotId = Number(holder.userData.robotId);
                }
            }
        });

        renderer.domElement.addEventListener('pointerup', (e) => {
            if (clickedRobotId != null) {
                const distSq = Math.hypot(e.clientX - pointerStartClient.x, e.clientY - pointerStartClient.y);
                // Hanya fokus robot jika klik bersih (bukan gerakan geser/drag kamera)
                if (distSq < 6) {
                    focusRobotOnMap(clickedRobotId);
                }
                clickedRobotId = null;
            }
        });

        renderer.domElement.addEventListener('pointerleave', () => {
            clickedRobotId = null;
        });

        let animationFrameId = null;
        let viewerFollowCamSnap = true;
        function animate() {
            animationFrameId = requestAnimationFrame(animate);
            // Optimization: Stop rendering when tab or container is hidden (0% GPU load)
            if (document.hidden) return;
            if (!container || container.offsetParent === null) return;

            // Fase 2.1: lerp per-frame posisi robot 3D menuju target sim step -> gerak halus, bukan teleport
            robotMeshes.forEach(holder => {
                const tgt = holder.userData.targetWp;
                if (tgt) {
                    holder.position.x += (tgt.x - holder.position.x) * 0.25;
                    holder.position.z += (tgt.z - holder.position.z) * 0.25;
                    holder.position.y = tgt.y;
                }
            });
            // Follow mode: kamera mengikuti robot yang difokuskan (Mata Robot POV, Chase Cam, atau Orbit)
            if(isFollowMode && focusedRobotId!=null && robotMeshes.has(Number(focusedRobotId))){
                const holder = robotMeshes.get(Number(focusedRobotId));
                
                // Vektor hadap robot di koordinat 3D
                const forward = new THREE.Vector3(
                    -Math.sin(holder.rotation.y),
                    0,
                    -Math.cos(holder.rotation.y)
                ).normalize();

                const rSc = parseFloat(current3DSettings.robot_scale ?? 0.1);
                // Ketinggian mata/kamera robot (dapat disesuaikan di VS Code untuk masing-masing lantai)
                const eyeHeightF1 = Math.max(0.020, rSc * 0.10);
                const eyeHeightF2 = Math.max(0.020, rSc * 0.10);
                const eyeHeight = (Number(floorNum) === 2) ? eyeHeightF2 : eyeHeightF1;

                if (followCameraMode === 'fpv') {
                    // Nonaktifkan OrbitControls agar tidak menimpa posisi kamera mata robot
                    controls.enabled = false;

                    if (holder.userData.markerSprite) holder.userData.markerSprite.visible = false;
                    if (holder.userData.nameSprite) holder.userData.nameSprite.visible = false;
                    if (holder.userData.statusSprite) holder.userData.statusSprite.visible = false;

                    const eyeForward = Math.max(0.018, rSc * 0.22);
                    const lookAhead = 2.0;

                    // Posisi kamera tepat di mata robot
                    const camPos = holder.position.clone()
                        .add(new THREE.Vector3(0, eyeHeight, 0))
                        .addScaledVector(forward, eyeForward);

                    // Arah pandang lurus horizontal setinggi mata robot ke depan lorong/ruangan
                    const lookTarget = holder.position.clone()
                        .add(new THREE.Vector3(0, eyeHeight, 0))
                        .addScaledVector(forward, lookAhead);

                    if (viewerFollowCamSnap) {
                        camera.position.copy(camPos);
                        viewerFollowCamSnap = false;
                    } else {
                        camera.position.lerp(camPos, 0.25);
                    }
                    camera.lookAt(lookTarget);
                    controls.target.copy(lookTarget);
                } else if (followCameraMode === 'chase') {
                    // Nonaktifkan OrbitControls agar tidak menimpa sudut kamera belakang
                    controls.enabled = false;

                    if (holder.userData.markerSprite) holder.userData.markerSprite.visible = true;
                    if (holder.userData.nameSprite) holder.userData.nameSprite.visible = true;
                    if (holder.userData.statusSprite) holder.userData.statusSprite.visible = true;

                    const camDist = Math.max(0.28, rSc * 3.0);
                    const camHeight = Math.max(0.08, rSc * 1.0);
                    const lookAhead = 1.4;
                    const lookHeight = eyeHeight;

                    const camPos = holder.position.clone()
                        .addScaledVector(forward, -camDist)
                        .add(new THREE.Vector3(0, camHeight, 0));

                    const lookTarget = holder.position.clone()
                        .addScaledVector(forward, lookAhead)
                        .add(new THREE.Vector3(0, lookHeight, 0));

                    if (viewerFollowCamSnap) {
                        camera.position.copy(camPos);
                        viewerFollowCamSnap = false;
                    } else {
                        camera.position.lerp(camPos, 0.16);
                    }
                    camera.lookAt(lookTarget);
                    controls.target.copy(lookTarget);
                } else {
                    // Mode 3: Orbit Bebas (Top-Down Follow Klasik)
                    controls.enabled = true;
                    if (holder.userData.markerSprite) holder.userData.markerSprite.visible = true;
                    if (holder.userData.nameSprite) holder.userData.nameSprite.visible = true;
                    if (holder.userData.statusSprite) holder.userData.statusSprite.visible = true;

                    const tgt = holder.position.clone(); tgt.y += 0.3;
                    controls.target.lerp(tgt, 0.08);
                    const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                    const savedDist = 5 + (savedDistVal/10)*115;
                    const dir = camera.position.clone().sub(controls.target).normalize();
                    if(dir.length()<0.01) dir.set(0.35,0.55,0.75).normalize();
                    const desired = controls.target.clone().add(dir.multiplyScalar(savedDist));
                    camera.position.lerp(desired, 0.08);
                    controls.update();
                }
            } else {
                controls.enabled = true;
                controls.update();
            }
            renderer.render(scene, camera);
        }
        animate();

        function onResize() {
            const w = container.offsetWidth || parent?.offsetWidth || 800;
            const h = container.offsetHeight || parent?.offsetHeight || 450;
            if (!w || !h) return;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }
        window.addEventListener('resize', onResize);

        return {
            floor: floorNum,
            scene,
            camera,
            renderer,
            controls,
            labelsGroup,
            robotsGroup,
            networkGroup,
            activePathGroup,
            robotMeshes,
            getOrCreateRobotMesh,
            buildNetworkLines,
            lights: { ambient: ambientLight, sun: sunLight, fill: fillLight },
            get _model(){ return loadedModel; },
            getModelSize: () => modelSize,
            getDefaultCamPos: () => defaultCamPos,
            getDefaultCamTarget: () => defaultCamTarget,
            requestCamSnap: () => { viewerFollowCamSnap = true; },
            resize: onResize,
            destroy: () => {
                if (animationFrameId) cancelAnimationFrame(animationFrameId);
                window.removeEventListener('resize', onResize);
                renderer.dispose();
            }
        };
    }

    // --- Fase 2: gerak halus + badge status 3D robot ---
    // Setel posisi mesh 3D sekaligus target lerp per-frame (dipakai animate())
    function snapRobot3D(holder, worldPct, sz, floorNum) {
        const wp = worldPosForLoc({ x: worldPct.x, y: worldPct.y }, sz);
        const f = Number(floorNum || 1);
        const elev = (f === 2)
            ? parseFloat(current3DSettings.robot_elevation_f2 ?? 0.112)
            : parseFloat(current3DSettings.robot_elevation_f1 ?? 0.059);
        holder.position.set(wp.x, elev, wp.z);
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.copy(holder.position);
        return wp;
    }

    function hideRobot3DAvatar(viewer, robot) {
        if (!viewer || !viewer.robotMeshes) return;
        const holder = viewer.robotMeshes.get(Number(robot.id));
        if (holder) holder.visible = false;
    }

    // Perbarui badge status 3D di atas robot (idle / mengantar → tujuan / charging / maintenance / masalah)
    function updateRobotStatusSprite(holder, robot, delivery, hasIssue, destName) {
        const spr = holder.userData.statusSprite;
        if (!spr) return;
        const c = spr.userData.canvas, tex = spr.userData.texture;
        const ctx = c.getContext('2d');
        ctx.clearRect(0, 0, c.width, c.height);
        let label = '● SIAGA (Markas)', bg = 'rgba(16,185,129,0.94)';
        if (hasIssue) {
            let issue = 'KENDALA';
            if (robot.activeAlert && robot.activeAlert.issue_type) {
                const it = String(robot.activeAlert.issue_type).toUpperCase();
                if (it.includes('COLLISION') || it.includes('TABRAKAN')) issue = 'TABRAKAN';
                else if (it.includes('LOW BATTERY') || it.includes('BATERAI')) issue = 'BATERAI LEMAH';
                else if (it.includes('SENSOR')) issue = 'SENSOR RUSAK';
                else issue = it;
            } else if (robot.battery_level <= 10) {
                issue = 'BATERAI HABIS';
            } else {
                issue = 'PERBAIKAN';
            }
            label = '⚠ ' + issue; bg = 'rgba(225,29,72,0.94)';
        } else if (robot.status === 'Delivering' && delivery) {
            label = '▶ MENGANTAR → ' + (destName || '?'); bg = 'rgba(59,130,246,0.94)';
        } else if (robot.status === 'Returning' || robot.isReturning) {
            label = '◀ MENUJU MARKAS'; bg = 'rgba(99,102,241,0.94)';
        } else if (robot.status === 'Charging') {
            label = '⚡ MENGISI DAYA'; bg = 'rgba(249,115,22,0.94)';
        } else if (robot.status === 'Maintenance') {
            label = '🔧 PERBAIKAN'; bg = 'rgba(225,29,72,0.94)';
        }
        ctx.font = 'bold 18px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        const wRaw = ctx.measureText(label).width;
        const pad = 16, h = 36, tw = Math.min(c.width - 12, wRaw + pad * 2);
        const x = (c.width - tw) / 2, y = (c.height - h) / 2, r = h / 2;
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(x, y, tw, h, r);
        else {
            ctx.moveTo(x + r, y); ctx.arcTo(x + tw, y, x + tw, y + h, r); ctx.arcTo(x + tw, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + tw, y, r);
        }
        ctx.closePath(); ctx.fillStyle = bg; ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.lineWidth = 2; ctx.stroke();
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(label, c.width / 2, c.height / 2);
        tex.needsUpdate = true;
        spr.visible = true;
    }
    // Update avatar 3D robot di satu viewer (posisi + status sprite). Dipanggil std & full.
    function updateRobot3DAvatar(viewer, robot, coords, destName) {
        try {
            if (!viewer || !viewer.getModelSize || !viewer.robotMeshes) return false;
            const sz = viewer.getModelSize();
            if (!sz || sz.x <= 0.1) return false;
            const holder = viewer.getOrCreateRobotMesh(robot);
            const isEditingThis = isEditingRobot3D && Number(robot.id) === Number(focusedRobotId);
            if (!isEditingThis) {
                snapRobot3D(holder, coords, sz, robot.floor || viewer.floor);
                smoothFaceTowards(holder, robot.rotation);
            }
            holder.visible = true;
            const d = (robot.status === 'Delivering') ? (robot._activeDelivery || null) : null;
            updateRobotStatusSprite(holder, robot, d, robot.hasIssue, destName);
            return true;
        } catch(e){ return false; }
    }

    // Toggle 3D Room Labels (ON/OFF)
    function toggle3DRoomLabels() {
        show3DRoomLabels = !show3DRoomLabels;
        const icon = document.getElementById('icon-3d-labels');
        const text = document.getElementById('text-3d-labels');
        const btn = document.getElementById('btn-toggle-3d-labels');

        const f_icon = document.getElementById('fullview-icon-labels');
        const f_text = document.getElementById('fullview-text-labels');
        const f_btn = document.getElementById('fullview-btn-labels');

        allViewers().forEach(viewer => {
            if (viewer && viewer.labelsGroup) viewer.labelsGroup.visible = show3DRoomLabels;
        });

        if (show3DRoomLabels) {
            if (icon) icon.className = 'fa-solid fa-tag text-emerald-400';
            if (text) text.textContent = 'Label: AKTIF';
            if (btn) btn.className = 'bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition';
            if (f_icon) f_icon.className = 'fa-solid fa-tag text-emerald-400';
            if (f_text) f_text.textContent = 'Label: AKTIF';
            if (f_btn) f_btn.className = 'bg-slate-900/90 hover:bg-slate-800 border border-emerald-500/30 text-emerald-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap';
        } else {
            if (icon) icon.className = 'fa-solid fa-tag text-gray-500';
            if (text) text.textContent = 'Label: NONAKTIF';
            if (btn) btn.className = 'bg-slate-900/40 hover:bg-slate-900/80 backdrop-blur-md text-gray-400 px-3 py-1.5 rounded-xl text-xs font-bold border border-white/5 shadow-lg flex items-center gap-1.5 transition';
            if (f_icon) f_icon.className = 'fa-solid fa-tag text-gray-500';
            if (f_text) f_text.textContent = 'Label: NONAKTIF';
            if (f_btn) f_btn.className = 'bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap';
        }
    }

    // Toggle camera / light / label-size / robot interactive toolbars
    function toggle3DControlPanel(panelName) {
        if (!window.isAdmin) return;
        const camPanel = document.getElementById('panel-3d-camera');
        const lightPanel = document.getElementById('panel-3d-light');
        const labelPanel = document.getElementById('panel-3d-label-size');
        const robotPanel = document.getElementById('panel-3d-robot-control');

        if (panelName === 'camera') {
            if (camPanel.classList.contains('hidden')) {
                camPanel.classList.remove('hidden');
                if (lightPanel) lightPanel.classList.add('hidden');
                if (labelPanel) labelPanel.classList.add('hidden');
                if (robotPanel) robotPanel.classList.add('hidden');
            } else {
                camPanel.classList.add('hidden');
            }
        } else if (panelName === 'light') {
            if (lightPanel.classList.contains('hidden')) {
                lightPanel.classList.remove('hidden');
                if (camPanel) camPanel.classList.add('hidden');
                if (labelPanel) labelPanel.classList.add('hidden');
                if (robotPanel) robotPanel.classList.add('hidden');
            } else {
                lightPanel.classList.add('hidden');
            }
        } else if (panelName === 'label-size') {
            if (labelPanel.classList.contains('hidden')) {
                labelPanel.classList.remove('hidden');
                if (camPanel) camPanel.classList.add('hidden');
                if (lightPanel) lightPanel.classList.add('hidden');
                if (robotPanel) robotPanel.classList.add('hidden');
            } else {
                labelPanel.classList.add('hidden');
            }
        } else if (panelName === 'robot') {
            if (robotPanel.classList.contains('hidden')) {
                robotPanel.classList.remove('hidden');
                isEditingRobot3D = true;
                if (camPanel) camPanel.classList.add('hidden');
                if (lightPanel) lightPanel.classList.add('hidden');
                if (labelPanel) labelPanel.classList.add('hidden');
                if (focusedRobotId == null) {
                    const defaultRobot = robots.find(r => Number(r.floor) === Number(currentDashboardFloor)) || robots[0];
                    if (defaultRobot) {
                        focusRobotOnMap(Number(defaultRobot.id));
                    }
                }
                updateSelectedRobotUI();
            } else {
                robotPanel.classList.add('hidden');
                isEditingRobot3D = false;
            }
        }
    }

    // Lantai 2 Monitoring: focus / follow / jaringan
    function updateFocusBadge(){
        const badge=document.getElementById('robot-focus-badge');
        const txt=document.getElementById('robot-focus-badge-text');
        if(!badge) return;
        if(focusedRobotId==null){ badge.classList.add('hidden'); return; }
        const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
        badge.classList.remove('hidden');
        const modeBadgeNames = {
            'fpv': 'Mata Robot (POV)',
            'chase': 'Tampak Belakang',
            'orbit': 'Orbit Atas'
        };
        const modeName = modeBadgeNames[followCameraMode] || 'Mata Robot';
        if(txt) txt.textContent='Fokus: '+(r? r.name : ('Robot '+focusedRobotId))+(isFollowMode ? (' • ' + modeName) : '');
    }

    function cycleFollowCameraMode() {
        if (!isFollowMode) {
            toggleFollowMode();
            return;
        }
        if (followCameraMode === 'fpv') {
            setFollowCameraMode('chase');
        } else if (followCameraMode === 'chase') {
            setFollowCameraMode('orbit');
        } else {
            setFollowCameraMode('fpv');
        }
    }

    function setFollowCameraMode(mode) {
        followCameraMode = mode;
        if (!isFollowMode) {
            isFollowMode = true;
        }
        allViewers().forEach(v => {
            if (v && v.requestCamSnap) v.requestCamSnap();
        });
        updateFollowButton();
    }

    function updateFollowButton(){
        const t=document.getElementById('text-follow');
        const ic=document.getElementById('icon-follow');
        const btn=document.getElementById('btn-toggle-follow');
        const cycleBtn=document.getElementById('btn-cycle-follow-mode');
        const badgeMode=document.getElementById('text-follow-mode-badge');
        const iconMode=document.getElementById('icon-follow-mode');

        const modeLabels = {
            'fpv': 'Mata Robot',
            'chase': 'Belakang',
            'orbit': 'Orbit'
        };
        const modeIcons = {
            'fpv': 'fa-solid fa-eye text-emerald-400',
            'chase': 'fa-solid fa-video text-amber-400',
            'orbit': 'fa-solid fa-arrows-to-dot text-sky-400'
        };

        const curLabel = modeLabels[followCameraMode] || 'Mata Robot';
        const curIcon = modeIcons[followCameraMode] || 'fa-solid fa-eye text-emerald-400';

        if(t) t.textContent = isFollowMode ? 'Ikuti: AKTIF' : 'Ikuti: NONAKTIF';
        if(ic) ic.className = isFollowMode ? 'fa-solid fa-eye text-emerald-400 animate-pulse' : 'fa-solid fa-eye text-sky-400';
        if(btn) {
            btn.classList.toggle('text-emerald-400', isFollowMode);
            btn.classList.toggle('text-white', !isFollowMode);
        }
        if(cycleBtn) {
            cycleBtn.classList.toggle('hidden', !isFollowMode);
            cycleBtn.title = 'Ganti Sudut Pandang (Saat ini: ' + curLabel + ') - Klik untuk ganti';
        }
        if(badgeMode) badgeMode.textContent = curLabel;
        if(iconMode) iconMode.className = curIcon;

        const f_t = document.getElementById('fullview-text-follow');
        const f_ic = document.getElementById('fullview-icon-follow');
        const f_btn = document.getElementById('fullview-btn-follow');
        const f_cycleBtn = document.getElementById('fullview-btn-cycle-follow');
        const f_badgeMode = document.getElementById('fullview-follow-mode-badge');
        const f_iconMode = document.getElementById('fullview-icon-follow-mode');

        if (f_t) f_t.textContent = isFollowMode ? 'Ikuti: AKTIF' : 'Ikuti: NONAKTIF';
        if (f_ic) f_ic.className = isFollowMode ? 'fa-solid fa-crosshairs text-emerald-400 animate-pulse' : 'fa-solid fa-crosshairs text-sky-400';
        if (f_btn) {
            f_btn.classList.toggle('text-emerald-400', isFollowMode);
            f_btn.classList.toggle('text-gray-200', !isFollowMode);
        }
        if (f_cycleBtn) {
            f_cycleBtn.classList.toggle('hidden', !isFollowMode);
            f_cycleBtn.title = 'Ganti Sudut Pandang (Saat ini: ' + curLabel + ') - Klik untuk ganti';
        }
        if (f_badgeMode) f_badgeMode.textContent = curLabel;
        if (f_iconMode) f_iconMode.className = curIcon;

        // Restore sprites visibility if leaving FPV mode or follow mode
        allViewers().forEach(v => {
            if (v && v.robotMeshes) {
                for (const h of v.robotMeshes.values()) {
                    const isFpvOnThis = isFollowMode && followCameraMode === 'fpv' && Number(h.userData.robotId) === Number(focusedRobotId);
                    if (h.userData.markerSprite) h.userData.markerSprite.visible = !isFpvOnThis;
                    if (h.userData.nameSprite) h.userData.nameSprite.visible = !isFpvOnThis;
                    if (h.userData.statusSprite) h.userData.statusSprite.visible = !isFpvOnThis;
                }
            }
        });

        updateFocusBadge();
    }
    function updateNetworkButton(){
        const t=document.getElementById('text-network');
        const btn=document.getElementById('btn-toggle-network');
        if(t) t.textContent = showNetworkLines ? 'Jaringan: AKTIF' : 'Jaringan: NONAKTIF';
        if(btn) btn.classList.toggle('opacity-60', !showNetworkLines);
    }
    function clearRobotFocus(){
        focusedRobotId=null; isFollowMode=false; isEditingRobot3D=false;
        updateFocusBadge(); updateFollowButton();
        const sel = document.getElementById('select-3d-robot');
        if (sel) sel.value = '';
        // highlight cards
        document.querySelectorAll('[id^="robot-card-"]').forEach(c=>c.classList.remove('ring-2','ring-sky-400'));
    }

    // === Robot Position Control (D-pad + Rotasi + World XZ) ===
    function select3DRobotFromDropdown(val) {
        if (!val) {
            clearRobotFocus();
            updateSelectedRobotUI();
            return;
        }
        isEditingRobot3D = true;
        const rid = Number(val);
        const r = robots.find(x => Number(x.id) === rid);
        if (!r) return;
        r.returnMission = null;
        r.isReturning = false;
        r.needsReturnToBase = false;
        const targetFloor = Number(r.floor) === 1 ? 1 : 2;
        if (Number(currentDashboardFloor) !== targetFloor) {
            switchDashboardFloor(targetFloor);
            setTimeout(() => {
                focusRobotOnMap(rid);
                updateSelectedRobotUI();
            }, 300);
        } else {
            focusRobotOnMap(rid);
            updateSelectedRobotUI();
        }
    }

    // Ambil holder robot 3D yang sedang difokuskan (viewer lantai aktif dulu, lalu viewer lain)
    function getFocusedRobotHolder(){
        if(focusedRobotId==null) return null;
        const order = [activeStdViewer(), Number(currentDashboardFloor) === 1 ? threeStd : threeStdF1];
        for (const v of order) {
            if (v && v.robotMeshes && v.robotMeshes.has(Number(focusedRobotId))) return v.robotMeshes.get(Number(focusedRobotId));
        }
        return null;
    }
    function updateSelectedRobotUI(){
        const sel=document.getElementById('select-3d-robot');
        if(sel){
            if(focusedRobotId!=null && sel.value!==String(focusedRobotId)) sel.value=String(focusedRobotId);
            else if(focusedRobotId==null) sel.value='';
        }
        const info=document.getElementById('selected-robot-3d-info');
        const inpX=document.getElementById('input-robot-3d-x');
        const inpY=document.getElementById('input-robot-3d-y');
        const inpZ=document.getElementById('input-robot-3d-z');
        const inpScale=document.getElementById('input-robot-scale');
        const valScale=document.getElementById('val-robot-scale');
        const slider=document.getElementById('slider-robot-3d-rotation');
        const valRot=document.getElementById('val-robot-3d-rotation');
        const holder=getFocusedRobotHolder();
        const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
        if(!holder||!r){
            if(info) info.textContent='Pilih robot dari dropdown atau klik avatar';
            if(inpX) inpX.value='';
            if(inpY) inpY.value='';
            if(inpZ) inpZ.value='';
            const defSc = parseFloat(current3DSettings.robot_scale ?? 0.6);
            if(inpScale && document.activeElement!==inpScale) inpScale.value=defSc.toFixed(1);
            if(valScale) valScale.textContent=defSc.toFixed(1)+'x';
            if(slider) slider.value=0;
            if(valRot) valRot.textContent='0°';
            return;
        }
        if(info) info.textContent=r.name+' (#'+r.id+') - Lt. '+(r.floor||currentDashboardFloor);
        if(inpX && document.activeElement!==inpX) inpX.value=holder.position.x.toFixed(2);
        if(inpY && document.activeElement!==inpY) inpY.value=holder.position.y.toFixed(2);
        if(inpZ && document.activeElement!==inpZ) inpZ.value=holder.position.z.toFixed(2);
        const sc=holder.scale.x;
        if(inpScale && document.activeElement!==inpScale) inpScale.value=sc.toFixed(1);
        if(valScale) valScale.textContent=sc.toFixed(1)+'x';
        const rotDeg=Math.round((holder.rotation.y*180/Math.PI)%360);
        if(slider && document.activeElement!==slider) slider.value=((rotDeg%360)+360)%360;
        if(valRot) valRot.textContent=((rotDeg%360)+360)%360+'°';
    }
    function move3DRobot(dx, dz){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        isEditingRobot3D = true;
        holder.position.x+=dx;
        holder.position.z+=dz;
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.copy(holder.position);
        const vw=viewerOfHolder(holder);
        const sz=vw?vw.getModelSize():null;
        if(sz && sz.x>0.1){
            const pct=locFromWorld(holder.position.x, holder.position.z, sz);
            const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
            if(r){
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
        }
        updateSelectedRobotUI();
    }
    function move3DRobotY(dy){
        const holder=getFocusedRobotHolder();
        if(!holder){ alert('Pilih robot dulu (klik card atau avatar di canvas).'); return; }
        isEditingRobot3D = true;
        holder.position.y+=dy;
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.y = holder.position.y;
        updateSelectedRobotUI();
        const st=document.getElementById('robot-3d-status');
        if(st){ st.textContent='Posisi Y='+holder.position.y.toFixed(2); }
    }
    function rotate3DRobot(deltaDeg){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        holder.rotation.y += (deltaDeg * Math.PI / 180);
        updateSelectedRobotUI();
    }
    function set3DRobotRotation(val){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        const deg=parseFloat(val);
        holder.rotation.y = deg * Math.PI / 180;
        const valEl=document.getElementById('val-robot-3d-rotation');
        if(valEl) valEl.textContent=Math.round(deg)+'°';
    }
    function set3DRobotWorldX(val){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        const x=parseFloat(val); if(isNaN(x)) return;
        isEditingRobot3D = true;
        holder.position.x=x;
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.x=x;
        const vw=viewerOfHolder(holder);
        const sz=vw?vw.getModelSize():null;
        if(sz && sz.x>0.1){
            const pct=locFromWorld(x, holder.position.z, sz);
            const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
            if(r){
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
        }
    }
    function set3DRobotWorldZ(val){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        const z=parseFloat(val); if(isNaN(z)) return;
        isEditingRobot3D = true;
        holder.position.z=z;
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.z=z;
        const vw=viewerOfHolder(holder);
        const sz=vw?vw.getModelSize():null;
        if(sz && sz.x>0.1){
            const pct=locFromWorld(holder.position.x, z, sz);
            const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
            if(r){
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
        }
    }
    function set3DRobotWorldY(val){
        const holder=getFocusedRobotHolder();
        if(!holder) return;
        const y=parseFloat(val); if(isNaN(y)) return;
        isEditingRobot3D = true;
        holder.position.y=y;
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.y=y;
    }
    function set3DRobotScale(val){
        const s=parseFloat(val); if(isNaN(s)||s<0.1) return;
        current3DSettings.robot_scale = s;
        allViewers().forEach(vw => {
            if (vw && vw.robotMeshes) {
                vw.robotMeshes.forEach(h => h.scale.set(s, s, s));
            }
        });
        const holder=getFocusedRobotHolder();
        if(holder) holder.scale.set(s,s,s);
        const valEl=document.getElementById('val-robot-scale');
        if(valEl) valEl.textContent=s.toFixed(1)+'x';
        const st=document.getElementById('robot-3d-status');
        if(st) st.textContent='Skala robot: '+s.toFixed(1)+'x';
        save3DSettingsDebounced('robot-3d-status');
    }
    function reset3DRobotPosition(){
        const holder=getFocusedRobotHolder();
        if(!holder){ alert('Pilih robot dulu.'); return; }
        const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
        if (r) {
            r.customPosition = false;
            r.returnMission = null;
            r.isReturning = false;
            r.needsReturnToBase = false;
        }
        // reset ke posisi parkir dekat Stairs lantai viewer aktif
        const idx=robots.findIndex(r=>Number(r.id)===Number(focusedRobotId));
        const offX=(idx-(robots.length-1)/2)*2.0;
        const vw=viewerOfHolder(holder);
        const park=parkCoordsForFloor(vw && vw.floor ? vw.floor : currentDashboardFloor);
        if(vw && vw.getModelSize){
            const sz=vw.getModelSize();
            if(sz&&sz.x>0.1){
                const wp=worldPosForLoc({x:park.x+offX, y:park.y}, sz);
                holder.position.set(wp.x, 0.02, wp.z);
                if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
                holder.userData.targetWp.copy(holder.position);
                if (r) {
                    r.current_x = parseFloat((park.x+offX).toFixed(2));
                    r.current_y = parseFloat(park.y.toFixed(2));
                }
            }
        }
        holder.rotation.y=0;
        const defSc = 0.6;
        current3DSettings.robot_scale = defSc;
        holder.scale.set(defSc, defSc, defSc);
        const inpSc = document.getElementById('input-robot-scale');
        if (inpSc) inpSc.value = String(defSc);
        const valSc = document.getElementById('val-robot-scale');
        if (valSc) valSc.textContent = defSc.toFixed(1) + 'x';
        save3DSettingsDebounced('robot-3d-status');
        updateSelectedRobotUI();
        const st=document.getElementById('robot-3d-status');
        if(st) st.textContent='Posisi & ukuran di-reset ke default';
    }
    function save3DRobotToGraph(){
        const holder=getFocusedRobotHolder();
        if(!holder){ alert('Pilih robot dulu.'); return; }
        const r=robots.find(x=>Number(x.id)===Number(focusedRobotId));
        if(!r){ alert('Data robot tidak ditemukan.'); return; }
        const st=document.getElementById('robot-3d-status');
        if(st) st.textContent='Menyimpan...';
        const targetFloor = Number(r.floor) || Number(currentDashboardFloor) || 1;

        // Simpan settings_3d (termasuk robot_scale) ke /api/settings/label-scale
        fetch('/api/settings/label-scale', {
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'',
                'Accept':'application/json'
            },
            body:JSON.stringify({
                scale: labelScaleMultiplier,
                settings_3d: current3DSettings
            })
        }).catch(e => console.warn('Sync settings_3d fail:', e));

        fetch('/api/robots/'+r.id+'/telemetry', {
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'',
                'Accept':'application/json'
            },
            body:JSON.stringify({current_x:r.current_x, current_y:r.current_y, floor:targetFloor})
        }).then(res=>{
            if(!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(data=>{
            r.customPosition = true;
            r.returnMission = null;
            r.isReturning = false;
            r.needsReturnToBase = false;
            if(st) {
                st.textContent='✓ Posisi & skala ' + r.name + ' tersimpan!';
                setTimeout(()=>{ if(st && st.textContent.includes('tersimpan')) st.textContent=''; }, 3500);
            }
        }).catch(err=>{
            console.error('Error saving robot position:', err);
        });
    }
    function toggleNetworkLines(){
        showNetworkLines=!showNetworkLines;
        allViewers().forEach(viewer => { if(viewer && viewer.networkGroup) viewer.networkGroup.visible=showNetworkLines; });
        updateNetworkButton();
    }
    function toggleFollowMode(){
        if(focusedRobotId==null){
            // auto-pick robot di Lantai 2 dulu, else any
            const cand = robots.find(r=>Number(r.floor)===2) || robots[0];
            if(cand) focusedRobotId=Number(cand.id);
        }
        isFollowMode=!isFollowMode;
        if (isFollowMode) {
            allViewers().forEach(v => {
                if (v && v.requestCamSnap) v.requestCamSnap();
            });
        } else {
            allViewers().forEach(v => {
                if (v && v.controls) v.controls.enabled = true;
            });
        }
        updateFollowButton();
        if(isFollowMode && focusedRobotId!=null) focusRobotOnMap(focusedRobotId, true);
    }
    let focusRetryTimer = null;
    function focusRobotOnMap(robotId, isFollowClick=false){
        if (focusRetryTimer) {
            clearTimeout(focusRetryTimer);
            focusRetryTimer = null;
        }
        const rid=Number(robotId);
        const robot=robots.find(r=>Number(r.id)===rid);
        if(!robot) return;
        // highlight card
        document.querySelectorAll('[id^=\"robot-card-\"]').forEach(c=>c.classList.remove('ring-2','ring-sky-400'));
        const card=document.getElementById('robot-card-'+rid);
        if(card) card.classList.add('ring-2','ring-sky-400');
        focusedRobotId=rid;
        updateFocusBadge();
        updateSelectedRobotUI(); // sync panel posisi robot
        // if Follow button was ON, keep follow
        if(isFollowClick) isFollowMode=true, updateFollowButton();
        // Monitoring mode: fokus ke avatar 3D di lantai tempat robot berada
        const robotFloor = Number(robot.floor) === 1 ? 1 : 2;
        if (isFullViewMode) {
            if (Number(currentFullViewFloor) !== robotFloor) {
                switchFullViewFloor(robotFloor);
                focusRetryTimer = setTimeout(() => focusRobotOnMap(rid, isFollowClick), 250);
                return;
            }
        } else {
            if (Number(currentDashboardFloor) !== robotFloor) {
                switchDashboardFloor(robotFloor);
                // wait for viewer then focus
                focusRetryTimer = setTimeout(() => focusRobotOnMap(rid, isFollowClick), 250);
                return;
            }
        }
        const activeViewerInst = activeStdViewer();
        if (!activeViewerInst || !activeViewerInst.robotMeshes) {
            // viewer belum ready, retry
            focusRetryTimer = setTimeout(() => focusRobotOnMap(rid, isFollowClick), 300);
            return;
        }
        // ensure mesh exists (create if needed) then snap/follow
        let holder = null;
        try { holder = activeViewerInst.getOrCreateRobotMesh(robot); } catch(e) {}
        if (!holder) return;
        // paksa visible & ambil posisi aktual (parkir atau delivery)
        holder.visible = true;
        // Jika mode follow FPV (Mata Robot) atau Chase Cam aktif, langsung snap kamera ke posisi mata robot
        if (isFollowMode && (followCameraMode === 'fpv' || followCameraMode === 'chase')) {
            if (activeViewerInst.requestCamSnap) activeViewerInst.requestCamSnap();
            return;
        }
        // if follow, animate loop will lerp; if click once, lerp target instantly + keep offset
        const tgt = holder.position.clone(); tgt.y += 0.3;
        const cam = activeViewerInst.camera; const ctrl = activeViewerInst.controls;
        const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
        // Clamp jarak fokus relatif ukuran model — setting tersimpan bisa terlalu jauh/dekat utk lantai ini
        const fSize = activeViewerInst.getModelSize();
        const fMaxDim = Math.max(fSize.x || 30, fSize.z || 30);
        let savedDist = 5 + (savedDistVal / 10) * 115;
        savedDist = Math.min(Math.max(savedDist, fMaxDim * 0.5), fMaxDim * 2.2);
        const dir = cam.position.clone().sub(ctrl.target).normalize();
        if (dir.length() < 0.01) dir.set(0.35, 0.55, 0.75).normalize();
        const newTarget = tgt.clone();
        const newPos = newTarget.clone().add(dir.multiplyScalar(savedDist));
        // smooth snap 450ms
        const startPos = cam.position.clone(); const startTarget = ctrl.target.clone();
        const t0 = performance.now(); const dur = 450;
        function step(now){
            const p=Math.min(1,(now-t0)/dur); const e=1-Math.pow(1-p,3);
            cam.position.lerpVectors(startPos, newPos, e);
            ctrl.target.lerpVectors(startTarget, newTarget, e);
            ctrl.update();
            if(p<1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // Dynamic Label Size Adjustment & Direct Persistent Save to graph.json
    function update3DLabelScale(val) {
        const num = parseFloat(val);
        labelScaleMultiplier = num;
        const valEl = document.getElementById('val-label-scale');
        if (valEl) valEl.textContent = num.toFixed(1) + 'x';
        const inputEl = document.getElementById('input-label-scale');
        if (inputEl && inputEl.value !== String(val)) inputEl.value = val;
        [threeStd, threeStdF1].forEach(viewer => {
            if (viewer && viewer.labelsGroup) {
                viewer.labelsGroup.children.forEach(sprite => {
                    if (sprite && sprite.isSprite) sprite.scale.set(3.6 * num, 0.9 * num, 1);
                });
            }
        });
        save3DSettingsDebounced('label-scale-status');
    }

    function setLabelScaleQuick(scaleVal) {
        update3DLabelScale(scaleVal);
        const inp = document.getElementById('input-label-scale');
        if(inp) inp.value = scaleVal;
    }

    function save3DSettingsDebounced(statusElId) {
        clearTimeout(settings3DSaveTimeout);
        const statusEl = statusElId ? document.getElementById(statusElId) : null;
        if(statusEl) statusEl.textContent = 'Menyimpan...';
        settings3DSaveTimeout = setTimeout(() => {
            fetch('/api/settings/label-scale', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),'Accept':'application/json' },
                body: JSON.stringify({ scale: labelScaleMultiplier, settings_3d: current3DSettings })
            }).then(r=>r.json()).then(d=>{
                if(d.success && statusEl){ statusEl.textContent='✓ Tersimpan di kode/server'; setTimeout(()=>{statusEl.textContent='';},2000); }
                else if(statusEl) statusEl.textContent='';
            }).catch(e=>{ console.error(e); if(statusEl) statusEl.textContent='Gagal menyimpan';});
        }, 500);
    }

    function update3DModelScale(val){
        const num = parseFloat(val); current3DSettings.model_scale = num;
        const v = document.getElementById('val-model-scale'); if(v) v.textContent = num.toFixed(1)+'x';
        const inp = document.getElementById('input-model-scale'); if(inp && inp.value!==String(val)) inp.value=val;
        [threeStd, threeStdF1].forEach(viewer=>{
            if(viewer && viewer._model){ viewer._model.scale.set(num,num,num); }
            // also lift labels slightly with scale
            if(viewer && viewer.labelsGroup){
                const sz = viewer.getModelSize ? viewer.getModelSize() : new THREE.Vector3(4,0.22,1.2);
                viewer.labelsGroup.children.forEach(s=>{
                    // keep X/Z, adjust Y = scaled height + 0.55
                    s.position.y = (sz.y * num) + 0.55;
                });
            }
        });
        save3DSettingsDebounced('label-scale-status');
    }

    // Camera Presets (berlaku ke viewer 3D lantai yg sedang tampil)
    function setCameraPreset(type) {
        const v = activeStdViewer();
        if (!v) return;
        const size = v.getModelSize();
        const maxDim = Math.max(size.x || 30, size.z || 30);
        // Target = tengah bangunan aktual (Box3 center), fallback origin bila belum ada
        const ctr = (typeof v.getDefaultCamTarget === 'function')
            ? v.getDefaultCamTarget().clone()
            : new THREE.Vector3(0, (size.y || 5) * 0.1, 0);
        if (type === 'iso') {
            v.camera.position.set(ctr.x + maxDim * 0.45, ctr.y + maxDim * 0.45, ctr.z + maxDim * 0.55);
            v.controls.target.copy(ctr);
        } else if (type === 'top') {
            v.camera.position.set(ctr.x, ctr.y + maxDim * 0.85, ctr.z + 0.01);
            v.controls.target.copy(ctr);
        } else if (type === 'front') {
            v.camera.position.set(ctr.x, ctr.y + (size.y || 5) * 0.5, ctr.z + maxDim * 0.65);
            v.controls.target.set(ctr.x, ctr.y + (size.y || 5) * 0.2, ctr.z);
        }
        v.controls.update();
        current3DSettings.camera.preset = type;
        save3DSettingsDebounced('camera-settings-status');
    }

    function updateCameraDistance(val) {
        const v = activeStdViewer();
        if (!v) return;
        const num = parseFloat(val);
        current3DSettings.camera.dist = num;
        document.getElementById('val-cam-dist').textContent = num.toFixed(1);
        const actualDist = 5 + (num / 10) * 115;
        const dir = v.camera.position.clone().sub(v.controls.target).normalize();
        if (dir.length() < 0.001) dir.set(0,0.6,0.8);
        v.camera.position.copy(v.controls.target).add(dir.multiplyScalar(actualDist));
        v.controls.update();
        save3DSettingsDebounced('camera-settings-status');
    }

    function updateCameraFov(val) {
        const v = activeStdViewer();
        if (!v) return;
        const num = parseFloat(val);
        current3DSettings.camera.fov = num;
        document.getElementById('val-cam-fov').textContent = num.toFixed(1);
        const actualFov = 20 + (num / 10) * 70;
        v.camera.fov = actualFov;
        v.camera.updateProjectionMatrix();
        save3DSettingsDebounced('camera-settings-status');
    }

    function reset3DCamera() {
        const v = activeStdViewer();
        if (!v) return;
        const defaultPos = v.getDefaultCamPos();
        const size = v.getModelSize();
        v.camera.position.copy(defaultPos);
        v.camera.fov = 45;
        v.camera.updateProjectionMatrix();
        if (typeof v.getDefaultCamTarget === 'function') v.controls.target.copy(v.getDefaultCamTarget());
        else v.controls.target.set(0, (size.y || 5) * 0.1, 0);
        v.controls.update();
        if (!isFullViewMode) {
            current3DSettings.camera.dist = 5.0;
            current3DSettings.camera.fov = 5.0;
            current3DSettings.camera.preset = 'iso';
            const valDist = document.getElementById('val-cam-dist');
            const valFov = document.getElementById('val-cam-fov');
            if (valDist) valDist.textContent = '5.0';
            if (valFov) valFov.textContent = '5.0';
            const distInput = document.getElementById('input-cam-dist');
            const fovInput = document.getElementById('input-cam-fov');
            if (distInput) distInput.value = '5.0';
            if (fovInput) fovInput.value = '5.0';
            save3DSettingsDebounced('camera-settings-status');
        }
    }

    // Sync lighting configured by admin to this client in real-time
    function applyAdminLighting(lighting) {
        if (!lighting || typeof lighting !== 'object') return;
        const isEditingLight = document.activeElement && (
            document.activeElement.id === 'input-light-ambient' ||
            document.activeElement.id === 'input-light-sun' ||
            document.activeElement.id === 'input-light-exp' ||
            document.activeElement.id === 'input-light-fill'
        );
        if (isEditingLight) return;

        if (!current3DSettings.lighting) current3DSettings.lighting = {};
        
        const ambient = parseFloat(lighting.ambient);
        const sun = parseFloat(lighting.sun);
        const exposure = parseFloat(lighting.exposure);
        const fill = parseFloat(lighting.fill);
        const shadow = lighting.shadow !== false;

        let changed = false;
        if (!isNaN(ambient) && Math.abs((current3DSettings.lighting.ambient ?? 1.4) - ambient) > 0.001) {
            current3DSettings.lighting.ambient = ambient;
            changed = true;
            const el = document.getElementById('val-light-ambient');
            if (el) el.textContent = ambient.toFixed(1);
            const inp = document.getElementById('input-light-ambient');
            if (inp) inp.value = ambient;
        }
        if (!isNaN(sun) && Math.abs((current3DSettings.lighting.sun ?? 1.8) - sun) > 0.001) {
            current3DSettings.lighting.sun = sun;
            changed = true;
            const el = document.getElementById('val-light-sun');
            if (el) el.textContent = sun.toFixed(1);
            const inp = document.getElementById('input-light-sun');
            if (inp) inp.value = sun;
        }
        if (!isNaN(exposure) && Math.abs((current3DSettings.lighting.exposure ?? 1.0) - exposure) > 0.001) {
            current3DSettings.lighting.exposure = exposure;
            changed = true;
            const el = document.getElementById('val-light-exp');
            if (el) el.textContent = exposure.toFixed(2);
            const inp = document.getElementById('input-light-exp');
            if (inp) inp.value = exposure;
        }
        if (!isNaN(fill) && Math.abs((current3DSettings.lighting.fill ?? 0.8) - fill) > 0.001) {
            current3DSettings.lighting.fill = fill;
            changed = true;
            const el = document.getElementById('val-light-fill');
            if (el) el.textContent = fill.toFixed(1);
            const inp = document.getElementById('input-light-fill');
            if (inp) inp.value = fill;
        }
        if (current3DSettings.lighting.shadow !== shadow) {
            current3DSettings.lighting.shadow = shadow;
            changed = true;
            const btn = document.getElementById('btn-toggle-3d-shadow');
            if (btn) {
                if (shadow) {
                    btn.className = "px-3 py-1.5 rounded-lg text-xs font-black transition bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm active:scale-95";
                    btn.textContent = "ON";
                } else {
                    btn.className = "px-3 py-1.5 rounded-lg text-xs font-black transition bg-slate-700 hover:bg-slate-600 text-gray-400 shadow-sm active:scale-95";
                    btn.textContent = "OFF";
                }
            }
        }

        if (changed) {
            allViewers().forEach(vw => {
                if (!vw) return;
                if (vw.lights) {
                    if (!isNaN(ambient) && vw.lights.ambient) vw.lights.ambient.intensity = ambient;
                    if (!isNaN(sun) && vw.lights.sun) {
                        vw.lights.sun.intensity = sun;
                        vw.lights.sun.castShadow = shadow;
                    }
                    if (!isNaN(fill) && vw.lights.fill) vw.lights.fill.intensity = fill;
                }
                if (vw.renderer) {
                    if (!isNaN(exposure)) vw.renderer.toneMappingExposure = exposure;
                    vw.renderer.shadowMap.enabled = shadow;
                }
                if (vw.scene) {
                    vw.scene.traverse(node => {
                        if (node.isMesh && node.material) {
                            node.material.needsUpdate = true;
                        }
                    });
                }
            });
        }
    }

    // Light, Shader & Shadow Adjustments — langsung sync ke semua viewer dan simpan ke settings
    function update3DLight(type, val) {
        const num = parseFloat(val);
        if (isNaN(num)) return;
        if (!current3DSettings.lighting) current3DSettings.lighting = {};

        if (type === 'ambient') {
            current3DSettings.lighting.ambient = num;
            const el = document.getElementById('val-light-ambient');
            if (el) el.textContent = num.toFixed(1);
        } else if (type === 'sun') {
            current3DSettings.lighting.sun = num;
            const el = document.getElementById('val-light-sun');
            if (el) el.textContent = num.toFixed(1);
        } else if (type === 'exposure') {
            current3DSettings.lighting.exposure = num;
            const el = document.getElementById('val-light-exp');
            if (el) el.textContent = num.toFixed(2);
        } else if (type === 'fill') {
            current3DSettings.lighting.fill = num;
            const el = document.getElementById('val-light-fill');
            if (el) el.textContent = num.toFixed(1);
        }
        // sync semua viewer (std + full, lantai 1 + 2)
        allViewers().forEach(vw => {
            if (!vw) return;
            if (vw.lights) {
                if (type === 'ambient' && vw.lights.ambient) vw.lights.ambient.intensity = num;
                if (type === 'sun' && vw.lights.sun) vw.lights.sun.intensity = num;
                if (type === 'fill' && vw.lights.fill) vw.lights.fill.intensity = num;
            }
            if (type === 'exposure' && vw.renderer) {
                vw.renderer.toneMappingExposure = num;
            }
        });
        save3DSettingsDebounced('light-settings-status');
    }

    function toggle3DShadow(forceState) {
        if (!current3DSettings.lighting) current3DSettings.lighting = {};
        const isCurrentOn = current3DSettings.lighting.shadow !== false;
        const newState = (forceState !== undefined) ? !!forceState : !isCurrentOn;
        current3DSettings.lighting.shadow = newState;

        allViewers().forEach(vw => {
            if (!vw || !vw.renderer) return;
            vw.renderer.shadowMap.enabled = newState;
            if (vw.lights && vw.lights.sun) {
                vw.lights.sun.castShadow = newState;
            }
            if (vw.scene) {
                vw.scene.traverse(node => {
                    if (node.isMesh && node.material) {
                        node.material.needsUpdate = true;
                    }
                });
            }
        });

        const btn = document.getElementById('btn-toggle-3d-shadow');
        if (btn) {
            if (newState) {
                btn.className = "px-3 py-1.5 rounded-lg text-xs font-black transition bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm active:scale-95";
                btn.textContent = "ON";
            } else {
                btn.className = "px-3 py-1.5 rounded-lg text-xs font-black transition bg-slate-700 hover:bg-slate-600 text-gray-400 shadow-sm active:scale-95";
                btn.textContent = "OFF";
            }
        }
        save3DSettingsDebounced('light-settings-status');
    }

    function setLightingPreset(presetName) {
        const presets = {
            'siang': { ambient: 1.8, sun: 2.2, exposure: 1.10, fill: 0.9, shadow: true },
            'sore': { ambient: 1.2, sun: 1.8, exposure: 0.95, fill: 0.5, shadow: true },
            'malam': { ambient: 0.6, sun: 0.8, exposure: 0.80, fill: 0.3, shadow: true },
            'studio': { ambient: 1.4, sun: 2.5, exposure: 1.20, fill: 0.8, shadow: true }
        };
        const p = presets[presetName];
        if (!p) return;

        update3DLight('ambient', p.ambient);
        update3DLight('sun', p.sun);
        update3DLight('exposure', p.exposure);
        update3DLight('fill', p.fill);
        toggle3DShadow(p.shadow);

        const ambInp = document.getElementById('input-light-ambient');
        const sunInp = document.getElementById('input-light-sun');
        const expInp = document.getElementById('input-light-exp');
        const fillInp = document.getElementById('input-light-fill');
        if (ambInp) ambInp.value = p.ambient;
        if (sunInp) sunInp.value = p.sun;
        if (expInp) expInp.value = p.exposure;
        if (fillInp) fillInp.value = p.fill;
    }

    function reset3DLighting() {
        setLightingPreset('studio');
    }

    function getRobotColor(robotId) {
        const colors = {
            1: '#0284c7', // Sky Blue (Alpha)
            2: '#8b5cf6', // Violet / Purple (Beta)
            3: '#f59e0b', // Amber / Golden Orange (Gamma)
            4: '#10b981', // Emerald
            5: '#ec4899'  // Pink
        };
        return colors[robotId] || '#3b82f6';
    }

    function switchDashboardFloor(floorNum) {
        floorNum = Number(floorNum) === 2 ? 2 : 1;
        if (Number(currentDashboardFloor) === floorNum && ((floorNum === 1 && threeStdF1) || (floorNum === 2 && threeStd))) {
            const v = floorNum === 1 ? threeStdF1 : threeStd;
            if (v && typeof v.resize === 'function') v.resize();
            return;
        }
        currentDashboardFloor = floorNum;
        currentFullViewFloor = floorNum;
        
        const tabF1 = document.getElementById('std-tab-f1');
        const tabF2 = document.getElementById('std-tab-f2');
        const fvTabF1 = document.getElementById('fullview-tab-f1');
        const fvTabF2 = document.getElementById('fullview-tab-f2');
        const container = document.getElementById('std-map-container');
        const title = document.getElementById('std-floor-title');
        const badge = document.getElementById('std-floor-badge');

        const canvasContainer3D = document.getElementById('std-3d-canvas-container');
        const canvasContainer3DF1 = document.getElementById('std-3d-canvas-f1');
        const hint3D = document.getElementById('std-3d-hint');
        const toolbar3D = document.getElementById('std-3d-toolbar');
        const camPanel = document.getElementById('panel-3d-camera');
        const lightPanel = document.getElementById('panel-3d-light');

        if (camPanel) camPanel.classList.add('hidden');
        if (lightPanel) lightPanel.classList.add('hidden');

        // Kedua lantai tampil 3D — samakan ukuran/light/kamera via current3DSettings yg dishare
        container.style.backgroundImage = 'none';
        container.style.backgroundColor = '#0f172a';
        if (hint3D) hint3D.classList.remove('hidden');
        if (toolbar3D) toolbar3D.classList.remove('hidden');

        if (floorNum === 1) {
            if (tabF1) tabF1.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            if (tabF2) tabF2.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            if (fvTabF1) fvTabF1.className = "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow";
            if (fvTabF2) fvTabF2.className = "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:text-white hover:bg-white/10";
            if (title) title.innerHTML = '<i class="fa-solid fa-cube text-emerald-400"></i> Lantai 1<span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30 ml-1">3D</span>';
            if (badge) badge.textContent = 'Menampilkan Lantai 1 (3D)';
            if (canvasContainer3D) canvasContainer3D.classList.add('hidden');
            if (canvasContainer3DF1) {
                canvasContainer3DF1.classList.remove('hidden');
                try {
                    const _ld = document.getElementById('std-3d-loader');
                    const _ldT = document.getElementById('std-3d-loader-title');
                    const _ldS = document.getElementById('std-3d-loader-status');
                    const _ldB = document.getElementById('std-3d-loader-bar');
                    const _ldP = document.getElementById('std-3d-loader-pct');
                    if (_ld) {
                        if (modelLoadedByFloor[1]) {
                            _ld.classList.add('hidden');
                        } else {
                            _ld.classList.remove('hidden');
                            if (_ldT) _ldT.textContent = 'Memuat Model 3D Lantai 1...';
                            if (_ldS) _ldS.textContent = 'Memeriksa penyimpanan lokal...';
                            if (_ldB) _ldB.style.width = '5%';
                            if (_ldP) _ldP.textContent = '5%';
                        }
                    }
                } catch(e){}
                if (!threeStdF1) {
                    threeStdF1 = initThreeViewer('std-3d-canvas-f1', 1);
                } else {
                    threeStdF1.resize();
                }
            }
        } else {
            if (tabF2) tabF2.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            if (tabF1) tabF1.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            if (fvTabF2) fvTabF2.className = "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow";
            if (fvTabF1) fvTabF1.className = "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:text-white hover:bg-white/10";
            if (title) title.innerHTML = '<i class="fa-solid fa-cube text-sky-400"></i> Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms) <span class="text-[10px] bg-sky-500/20 text-sky-400 px-2 py-0.5 rounded-full border border-sky-500/30 ml-1">3D</span>';
            if (badge) badge.textContent = 'Menampilkan Lantai 2 (3D)';
            if (canvasContainer3DF1) canvasContainer3DF1.classList.add('hidden');
            if (canvasContainer3D) {
                canvasContainer3D.classList.remove('hidden');
                try {
                    const _ld2 = document.getElementById('std-3d-loader');
                    const _ldT2 = document.getElementById('std-3d-loader-title');
                    const _ldS2 = document.getElementById('std-3d-loader-status');
                    const _ldB2 = document.getElementById('std-3d-loader-bar');
                    const _ldP2 = document.getElementById('std-3d-loader-pct');
                    if (_ld2) {
                        if (modelLoadedByFloor[2]) {
                            _ld2.classList.add('hidden');
                        } else {
                            _ld2.classList.remove('hidden');
                            if (_ldT2) _ldT2.textContent = 'Memuat Model 3D Lantai 2...';
                            if (_ldS2) _ldS2.textContent = 'Memeriksa penyimpanan lokal...';
                            if (_ldB2) _ldB2.style.width = '5%';
                            if (_ldP2) _ldP2.textContent = '5%';
                        }
                    }
                } catch(e){}
                if (!threeStd) {
                    threeStd = initThreeViewer('std-3d-canvas-container', 2);
                } else {
                    threeStd.resize();
                }
            }
            if (hint3D) hint3D.classList.remove('hidden');
            if (toolbar3D) toolbar3D.classList.remove('hidden');
        }

        runSimulationStep();
    }

    function switchFullViewFloor(floorNum) {
        currentFullViewFloor = Number(floorNum) === 2 ? 2 : 1;
        switchDashboardFloor(floorNum);
    }

    function toggleFullView(showFull) {
        if (showFull === undefined) isFullViewMode = !isFullViewMode;
        else isFullViewMode = !!showFull;

        const mapCard = document.getElementById('std-map-card');
        const mapContainer = document.getElementById('std-map-container');
        const headerBar = document.getElementById('std-header-bar');
        const floorTitleBar = document.getElementById('std-floor-title-bar');
        const toolbar3D = document.getElementById('std-3d-toolbar');
        const fullviewTopBar = document.getElementById('fullview-top-bar');
        const asideEl = document.getElementById('main-sidebar') || document.querySelector('body > aside') || document.querySelector('aside');
        const mainEl = document.getElementById('main-content') || document.querySelector('body > main') || document.querySelector('main');

        if (asideEl) {
            if (isFullViewMode) {
                asideEl.style.setProperty('display', 'none', 'important');
                asideEl.classList.add('hidden', 'fullview-hidden');
            } else {
                asideEl.style.removeProperty('display');
                asideEl.classList.remove('hidden', 'fullview-hidden');
            }
        }
        if (mainEl) {
            if (isFullViewMode) {
                mainEl.style.setProperty('z-index', '99999', 'important');
            } else {
                mainEl.style.removeProperty('z-index');
            }
        }
        document.body.classList.toggle('body-in-fullview', isFullViewMode);

        if (mapCard) {
            mapCard.classList.toggle('dashboard-fullview-card', isFullViewMode);
        }
        if (mapContainer) {
            mapContainer.classList.toggle('dashboard-fullview-canvas', isFullViewMode);
        }
        if (headerBar) {
            headerBar.classList.toggle('hidden', isFullViewMode);
        }
        if (floorTitleBar) {
            floorTitleBar.classList.toggle('hidden', isFullViewMode);
        }
        if (toolbar3D) {
            toolbar3D.classList.toggle('hidden', isFullViewMode);
        }
        if (fullviewTopBar) {
            fullviewTopBar.classList.toggle('hidden', !isFullViewMode);
        }

        // Close dropdown panels if open
        const camPanel = document.getElementById('panel-3d-camera');
        const lightPanel = document.getElementById('panel-3d-light');
        const labelPanel = document.getElementById('panel-3d-label-size');
        const robotPanel = document.getElementById('panel-3d-robot-control');
        if (camPanel) camPanel.classList.add('hidden');
        if (lightPanel) lightPanel.classList.add('hidden');
        if (labelPanel) labelPanel.classList.add('hidden');
        if (robotPanel) robotPanel.classList.add('hidden');

        if (!isFullViewMode) {
            if (typeof toggleFullViewDispatchPanel === 'function') {
                toggleFullViewDispatchPanel(false);
            }
        }

        if (isFullViewMode) {
            // Sync indicators in full view top bar
            if (typeof updateFollowButton === 'function') updateFollowButton();
            if (typeof updateAutopilotUI === 'function') updateAutopilotUI();
            if (typeof updateFullViewActiveDeliveriesList === 'function') updateFullViewActiveDeliveriesList();
            const f_icon = document.getElementById('fullview-icon-labels');
            const f_text = document.getElementById('fullview-text-labels');
            const f_btn = document.getElementById('fullview-btn-labels');
            if (f_icon) f_icon.className = show3DRoomLabels ? 'fa-solid fa-tag text-emerald-400' : 'fa-solid fa-tag text-gray-500';
            if (f_text) f_text.textContent = show3DRoomLabels ? 'Label: AKTIF' : 'Label: NONAKTIF';
            if (f_btn) {
                f_btn.className = show3DRoomLabels
                    ? 'bg-slate-900/90 hover:bg-slate-800 border border-emerald-500/30 text-emerald-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap'
                    : 'bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap';
            }
        }

        setTimeout(() => {
            const activeV = activeStdViewer();
            if (activeV && typeof activeV.resize === 'function') {
                activeV.resize();
            }
        }, 50);

        setTimeout(runSimulationStep, 80);
    }

    function getNode(nameOrId, preferredFloor = null) {
        if (!nameOrId) return null;
        if (locations[nameOrId]) return nameOrId;
        
        let matches = [];
        for (let id in locations) {
            if (locations[id].name === nameOrId) {
                matches.push(id);
            }
        }
        if (matches.length === 1) return matches[0];
        if (matches.length > 1) {
            if (preferredFloor) {
                const match = matches.find(id => Number(locations[id].floor) === Number(preferredFloor));
                if (match) return match;
            }
            return matches[0];
        }
        
        for (let id in locations) {
            if (locations[id].name && locations[id].name.toLowerCase() === String(nameOrId).toLowerCase()) {
                return id;
            }
        }
        return null;
    }

    function findShortestPath(start, end) {
        if (!start || !end || !locations[start] || !locations[end]) return [];
        if (start === end) return [start];
        let queue = [[start]];
        let visited = new Set([start]);

        while (queue.length > 0) {
            let path = queue.shift();
            let current = path[path.length - 1];

            let neighbors = adj[current] || [];
            for (let neighbor of neighbors) {
                if (!visited.has(neighbor)) {
                    visited.add(neighbor);
                    let newPath = [...path, neighbor];
                    if (neighbor === end) return newPath;
                    queue.push(newPath);
                }
            }
        }
        return [];
    }

    // A* pathfinding di atas graph.json (biaya uniform per edge, heuristik euclidean UV).
    // Hasil optimal = BFS untuk graph unweighted; mengikuti connected edges, bukan garis lurus.
    // Fallback ke BFS bila A* gagal. Node objectName-linked ikut karena _u/_v sudah ter-resolve.
    function findPathAStar(start, end) {
        if (!start || !end || !locations[start] || !locations[end]) return [];
        if (start === end) return [start];
        const h = (id) => {
            const a = locations[id], b = locations[end];
            if (!a || !b) return 0;
            const au = (a._u ?? a.x / 100), av = (a._v ?? a.y / 100);
            const bu = (b._u ?? b.x / 100), bv = (b._v ?? b.y / 100);
            return Math.hypot(au - bu, av - bv);
        };
        const open = new Map([[start, h(start)]]);
        const gScore = new Map([[start, 0]]);
        const came = new Map();
        const closed = new Set();
        while (open.size > 0) {
            let cur = null, best = Infinity;
            open.forEach((f, id) => { if (f < best) { best = f; cur = id; } });
            if (cur === end) {
                const path = [cur];
                while (came.has(path[0])) path.unshift(came.get(path[0]));
                return path;
            }
            open.delete(cur);
            closed.add(cur);
            for (const nb of (adj[cur] || [])) {
                if (closed.has(nb) || !locations[nb]) continue;
                const g = (gScore.get(cur) ?? Infinity) + 1;
                if (g < (gScore.get(nb) ?? Infinity)) {
                    came.set(nb, cur);
                    gScore.set(nb, g);
                    open.set(nb, g + h(nb));
                }
            }
        }
        return findShortestPath(start, end);
    }

    // Rotasi halus avatar menuju heading (rad shortest-path lerp, dipanggil tiap simulation step 50ms)
    function smoothFaceTowards(holder, targetDeg) {
        const target = -((targetDeg || 0) * Math.PI / 180);
        let d = target - holder.rotation.y;
        while (d > Math.PI) d -= Math.PI * 2;
        while (d < -Math.PI) d += Math.PI * 2;
        holder.rotation.y += d * 0.18;
    }

    function resolveLocationNodeId(x, y, floor = null) {
        let closestId = null;
        let minDst = Infinity;
        for (let id in locations) {
            const loc = locations[id];
            if (floor && Number(loc.floor) !== Number(floor)) continue;
            const dst = Math.hypot(loc.x - x, loc.y - y);
            if (dst < minDst) {
                minDst = dst;
                closestId = id;
            }
        }
        return closestId || (Number(floor) === 2 ? (locations['2_Tangga'] ? '2_Tangga' : '2_Stairs') : getBaseLocationId());
    }

    function resolveLocationName(x, y, floor = null) {
        const id = resolveLocationNodeId(x, y, floor);
        if (id && locations[id]) {
            return locations[id].name || id;
        }
        return Number(floor) === 2 ? 'Lantai 2' : 'Lantai 1';
    }

    function parseServerDate(dateStr) {
        if (!dateStr) return new Date();
        let s = String(dateStr).trim().replace(' ', 'T');
        if (!s.includes('Z') && !s.includes('+') && !s.slice(10).includes('-')) s += 'Z';
        return new Date(s);
    }

    function interpolate(p1, p2, ratio) {
        return {
            x: p1.x + (p2.x - p1.x) * ratio,
            y: p1.y + (p2.y - p1.y) * ratio
        };
    }

    function getStairsNodeId(floor) {
        const f = Number(floor || 1);
        if (f === 1) {
            if (locations['1_Tangga']) return '1_Tangga';
            if (locations['1_Stairs']) return '1_Stairs';
        } else {
            if (locations['2_Tangga']) return '2_Tangga';
            if (locations['2_Stairs']) return '2_Stairs';
        }
        for (const [id, loc] of Object.entries(locations)) {
            if (Number(loc.floor) === f && (loc.name?.toLowerCase().includes('tangga') || loc.name?.toLowerCase().includes('stairs'))) {
                return id;
            }
        }
        return f === 1 ? '1_Tangga' : '2_Tangga';
    }

    function calculatePathDistance(path) {
        if (!path || path.length < 2) return 0;
        let dist = 0;
        for (let i = 0; i < path.length - 1; i++) {
            const p1 = locations[path[i]];
            const p2 = locations[path[i + 1]];
            dist += (p1 && p2) ? Math.hypot(p2.x - p1.x, p2.y - p1.y) : 3.0;
        }
        return Math.max(1.0, dist);
    }

    function interpolateAlongPath(path, ratio) {
        if (!path || path.length === 0) return null;
        if (path.length === 1) {
            const p = locations[path[0]] || { x: 0, y: 0 };
            return { coords: { x: p.x, y: p.y }, angle: 0, segIdx: 0 };
        }

        const clampedRatio = Math.max(0, Math.min(1.0, ratio));
        const segDistances = [];
        let totalDistance = 0;

        for (let i = 0; i < path.length - 1; i++) {
            const p1 = locations[path[i]];
            const p2 = locations[path[i + 1]];
            const dist = (p1 && p2) ? Math.hypot(p2.x - p1.x, p2.y - p1.y) : 0.001;
            segDistances.push(dist);
            totalDistance += dist;
        }

        if (totalDistance <= 0.0001) {
            const p = locations[path[0]] || { x: 0, y: 0 };
            return { coords: { x: p.x, y: p.y }, angle: 0, segIdx: 0 };
        }

        const targetDist = clampedRatio * totalDistance;
        let accumulated = 0;
        let currentSegIdx = path.length - 2;
        let ratioInSeg = 1.0;

        for (let i = 0; i < segDistances.length; i++) {
            const nextAcc = accumulated + segDistances[i];
            if (targetDist <= nextAcc || i === segDistances.length - 1) {
                currentSegIdx = i;
                const segLen = segDistances[i];
                ratioInSeg = segLen > 0 ? (targetDist - accumulated) / segLen : 0;
                ratioInSeg = Math.max(0, Math.min(1.0, ratioInSeg));
                break;
            }
            accumulated = nextAcc;
        }

        const p1 = locations[path[currentSegIdx]] || { x: 0, y: 0 };
        const p2 = locations[path[currentSegIdx + 1]] || p1;
        const coords = interpolate(p1, p2, ratioInSeg);

        let angle = 0;
        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        if (dx !== 0 || dy !== 0) {
            angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
        }

        return { coords, angle, segIdx: currentSegIdx };
    }

    function planRouteBetween(fromId, toId) {
        if (!locations[fromId] || !locations[toId]) return [];
        const f1 = Number(locations[fromId].floor || 1);
        const f2 = Number(locations[toId].floor || 1);
        
        if (f1 === f2) {
            const p = findPathAStar(fromId, toId);
            return [{ type: 'travel', floor: f1, path: p }];
        } else {
            const stairsFrom = getStairsNodeId(f1);
            const stairsTo = getStairsNodeId(f2);
            const p1 = findPathAStar(fromId, stairsFrom);
            const p2 = findPathAStar(stairsTo, toId);
            return [
                { type: 'travel', floor: f1, path: p1 },
                { type: 'stairs', fromFloor: f1, toFloor: f2, fromNode: stairsFrom, toNode: stairsTo, durationMs: 5500 },
                { type: 'travel', floor: f2, path: p2 }
            ];
        }
    }

    function buildReturnMission(robot, now) {
        const baseLoc = getBaseLocation();
        const baseId = getBaseLocationId();
        const robotFloor = Number(robot.floor || 1);
        if (robotFloor === 1 && Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) < 1.5) {
            robot.floor = 1;
            return null;
        }

        const currentLocId = resolveLocationNodeId(robot.current_x, robot.current_y, robotFloor);
        const targetId = baseId;
        if (!currentLocId || (robotFloor === 1 && currentLocId === targetId)) return null;

        const rawStages = planRouteBetween(currentLocId, targetId);
        if (!rawStages || rawStages.length === 0) return null;

        const consolidatedStages = [];
        for (let st of rawStages) {
            if (consolidatedStages.length > 0) {
                const prev = consolidatedStages[consolidatedStages.length - 1];
                if (prev.type === 'travel' && st.type === 'travel' && prev.floor === st.floor) {
                    if (st.path && st.path.length > 0) {
                        prev.path = [...prev.path, ...st.path.slice(1)];
                    }
                    continue;
                }
            }
            consolidatedStages.push(st);
        }

        let accumulatedMs = 0;
        consolidatedStages.forEach(st => {
            st.startMs = accumulatedMs;
            if (st.type === 'stairs') {
                st.durationMs = 5000;
            } else {
                const dist = calculatePathDistance(st.path);
                st.durationMs = Math.max(2500, Math.round(dist * 700));
            }
            accumulatedMs += st.durationMs;
        });

        return {
            originId: currentLocId,
            destId: targetId,
            stages: consolidatedStages,
            totalDurationMs: accumulatedMs,
            startedAt: now.getTime() + 200
        };
    }

    function syncRobotBaseLocation(robotId, bx, by, floor = 1, status = 'Idle', batteryLevel = null) {
        const robot = robots.find(r => Number(r.id) === Number(robotId));
        const effectiveBattery = (batteryLevel !== null && batteryLevel !== undefined) 
            ? batteryLevel 
            : (robot ? robot.battery_level : null);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const payload = {
            status: status,
            current_x: bx,
            current_y: by,
            floor: floor
        };
        if (effectiveBattery !== null && effectiveBattery !== undefined) {
            payload.battery_level = Math.round(effectiveBattery);
        }

        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).catch(err => console.error('Error syncing base station location:', err));
    }

    function syncRobotPosition(robotId, x, y, floor, status, batteryLevel) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const payload = {
            current_x: x,
            current_y: y,
            floor: floor || 1
        };
        if (batteryLevel !== undefined && batteryLevel !== null) {
            payload.battery_level = Math.round(batteryLevel);
        }
        if (status === 'Maintenance' || status === 'Charging' || status === 'Returning') {
            payload.status = status;
        }
        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).catch(err => console.error('Error syncing robot telemetry:', err));
    }

    function triggerAutonomousCrash(robot, delivery, coords, floorNum, elapsedMs) {
        robot.hasIssue = true;
        robot.pausedElapsedMs = elapsedMs;
        robot.current_x = coords.x;
        robot.current_y = coords.y;
        robot.floor = floorNum;
        if (delivery) delivery.status = 'Pending';

        const locationName = resolveLocationName(coords.x, coords.y, floorNum);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robot.id}/simulate-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                issue_type: 'Collision',
                description: `Robot ${robot.name} mengalami tabrakan dengan rintangan di area ${locationName} (Lantai ${floorNum}). Pengantaran terhenti sementara.`,
                current_x: coords.x,
                current_y: coords.y,
                floor: floorNum,
                paused_elapsed_ms: elapsedMs
            })
        })
        .then(res => res.json())
        .then(data => {
            fetchData();
        })
        .catch(err => console.error('Error triggering autonomous crash:', err));
    }

    function triggerLowBatteryReturn(robot, delivery, coords, floorNum, elapsedMs) {
        robot.current_x = coords.x;
        robot.current_y = coords.y;
        robot.floor = floorNum;
        robot.isLowBatteryReturning = true;
        robot.status = 'Returning';
        robot.isReturning = true;
        robot.pausedElapsedMs = elapsedMs;

        if (delivery) {
            const mission = delivery._cachedMission || getDeliveryMission(delivery, robot);
            const isPickedUp = mission && mission.pickupStartMs && (elapsedMs >= mission.pickupStartMs + 3000);
            delivery._itemPickedUp = !!isPickedUp;
            delivery.status = 'Pending';
        }

        const now = new Date(new Date().getTime() + serverClientOffset);
        robot.returnMission = buildReturnMission(robot, now);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robot.id}/pause-for-charge`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                current_x: coords.x,
                current_y: coords.y,
                floor: floorNum,
                battery_level: robot.battery_level
            })
        })
        .then(res => res.json())
        .catch(err => console.error('Error triggering low battery return:', err));
    }

    function resumeFromBaseAfterCharge(robot) {
        const pendingDeliv = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'Pending' || d.status === 'In Progress'));
        const now = new Date(new Date().getTime() + serverClientOffset);
        const baseLoc = getBaseLocation();
        const baseId = getBaseLocationId();

        robot.isLowBatteryReturning = false;
        robot.isReturning = false;
        robot.returnMission = null;
        robot.current_x = baseLoc.x;
        robot.current_y = baseLoc.y;
        robot.floor = 1;
        robot.battery_level = 100;
        robot._justCharged = true;

        if (pendingDeliv) {
            const itemPickedUp = !!pendingDeliv._itemPickedUp;

            delete pendingDeliv._cachedMission;
            pendingDeliv.origin_location = baseId;
            if (itemPickedUp) {
                pendingDeliv.start_location = baseId;
            }
            pendingDeliv.status = 'In Progress';
            pendingDeliv._clientStartedAt = now.getTime();
            pendingDeliv.started_at = now.toISOString();

            robot.status = 'Delivering';

            getDeliveryMission(pendingDeliv, robot);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fetch(`/api/robots/${robot.id}/resume-from-base`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ item_picked_up: itemPickedUp })
            })
            .then(res => res.json())
            .then(data => {
                if (data.delivery) {
                    delete data.delivery._cachedMission;
                    pendingDeliv.started_at = data.delivery.started_at;
                    pendingDeliv._clientStartedAt = parseServerDate(data.delivery.started_at).getTime();
                }
            })
            .catch(err => console.error('Error resuming from base after charge:', err));
        } else {
            robot.status = 'Idle';
            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', 100);
        }
    }

    function getDeliveryMission(delivery, robot) {
        if (delivery._cachedMission) {
            return delivery._cachedMission;
        }

        const startNodeId = getNode(delivery.start_location);
        const destNodeId = getNode(delivery.destination_location);
        
        const robotFloor = Number(robot?.floor || 1);
        const baseLoc = getBaseLocation();
        const baseId = getBaseLocationId();

        let originNodeId = getNode(delivery.origin_location);
        if (robot && robot.current_x && robot.current_y) {
            const isAtBase = robotFloor === 1 && Math.hypot(robot.current_x - baseLoc.x, robot.current_y - baseLoc.y) < 2.0;
            if (isAtBase) {
                originNodeId = baseId;
            } else {
                originNodeId = resolveLocationNodeId(robot.current_x, robot.current_y, robotFloor) || baseId;
            }
        }
        if (!originNodeId || !locations[originNodeId]) {
            originNodeId = baseId;
        }

        const validStart = (startNodeId && locations[startNodeId]) ? startNodeId : Object.keys(locations)[0];
        const validDest = (destNodeId && locations[destNodeId]) ? destNodeId : Object.keys(locations)[1];

        const pickupStage = {
            type: 'pickup',
            nodeId: validStart,
            floor: locations[validStart]?.floor || 1,
            durationMs: 3000
        };

        const dropoffStage = {
            type: 'dropoff',
            nodeId: validDest,
            floor: locations[validDest]?.floor || 1,
            durationMs: 3000
        };

        let rawStages = [];
        if (originNodeId !== validStart) {
            rawStages = [
                ...planRouteBetween(originNodeId, validStart),
                pickupStage,
                ...planRouteBetween(validStart, validDest),
                dropoffStage
            ];
        } else {
            rawStages = [
                pickupStage,
                ...planRouteBetween(validStart, validDest),
                dropoffStage
            ];
        }

        const consolidatedStages = [];
        for (let st of rawStages) {
            if (consolidatedStages.length > 0) {
                const prev = consolidatedStages[consolidatedStages.length - 1];
                if (prev.type === 'travel' && st.type === 'travel' && prev.floor === st.floor) {
                    if (st.path && st.path.length > 0) {
                        prev.path = [...prev.path, ...st.path.slice(1)];
                    }
                    continue;
                }
            }
            consolidatedStages.push(st);
        }

        let accumulatedMs = 0;
        consolidatedStages.forEach(st => {
            st.startMs = accumulatedMs;
            if (st.type === 'stairs') {
                st.durationMs = 5000;
            } else if (st.type === 'pickup' || st.type === 'dropoff') {
                st.durationMs = 3000;
            } else {
                const dist = calculatePathDistance(st.path);
                st.durationMs = Math.max(3000, Math.round(dist * 700));
            }
            accumulatedMs += st.durationMs;
        });

        const mission = {
            originId: originNodeId,
            startId: validStart,
            destId: validDest,
            pickupStartMs: pickupStage.startMs,
            stages: consolidatedStages,
            totalDurationMs: accumulatedMs
        };

        delivery._cachedMission = mission;
        return mission;
    }

    // Gambar garis path delivery di scene 3D (garis putus-putus khas 2D di kaki robot, depthTest: false agar tidak tenggelam)
    function drawPath3D(viewers, remainingPts, robotColor, opacity, dashSize, gapSize, yOff){
        viewers.forEach(v => {
            if (!v || !v.activePathGroup) return;
            const sz = v.getModelSize ? v.getModelSize() : null;
            if (!sz || sz.x < 0.1) return;
            // Tepat di kaki robot / permukaan lantai (tidak melayang di atas robot)
            const extraY = (yOff !== undefined && yOff !== null) ? yOff : 0.012;
            const pts3 = remainingPts.map(pt => {
                const locObj = typeof pt === 'string' ? locations[pt] : pt;
                const vv = worldPosForLoc(locObj, sz);
                vv.y = (vv.y || 0) + extraY;
                return vv;
            });
            if (pts3.length < 2) return;

            // Garis putus-putus khas 2D (presisi, bersih, di kaki robot)
            const geo = new THREE.BufferGeometry().setFromPoints(pts3);
            const dSize = (dashSize !== undefined && dashSize > 0) ? dashSize : 0.18;
            const gSize = (gapSize !== undefined && gapSize > 0) ? gapSize : 0.12;
            const mat = new THREE.LineDashedMaterial({
                color: new THREE.Color(robotColor),
                transparent: true,
                opacity: opacity !== undefined ? opacity : 0.95,
                dashSize: dSize,
                gapSize: gSize,
                depthTest: false,
                depthWrite: false
            });
            const line = new THREE.Line(geo, mat);
            line.computeLineDistances();
            line.renderOrder = 9999;
            v.activePathGroup.add(line);
        });
    }

    function drawRobotPaths() {
        // Clear 3D active paths semua viewer (Lantai 1 + 2, std + fullview)
        allViewers().forEach(viewer => { if(viewer && viewer.activePathGroup) viewer.activePathGroup.clear(); });
        
        const now = new Date(new Date().getTime() + serverClientOffset);

        // 1. Draw paths for active deliveries (Unified continuous dashed path per floor)
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || (delivery.status !== 'In Progress' && delivery.status !== 'Pending')) return;
            
            const mission = getDeliveryMission(delivery, robot);
            if (!mission || !mission.stages) return;

            const robotColor = getRobotColor(robot.id);
            const startedTime = parseServerDate(delivery.started_at);
            const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
            const isPending = delivery.status === 'Pending';
            const robotFloor = Number(robot.floor || 1);

            [1, 2].forEach(floorNum => {
                const floorPts = [];

                mission.stages.forEach(st => {
                    if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                    const stageFloor = Number(st.floor || 1);
                    if (stageFloor !== floorNum) return;

                    const stageEndMs = st.startMs + st.durationMs;
                    if (elapsedMs >= stageEndMs && !isPending) return;

                    const isCurrentActive = !isPending && (elapsedMs >= st.startMs && elapsedMs < stageEndMs);
                    const isFutureStage = isPending || (elapsedMs < st.startMs);

                    if (isCurrentActive && robotFloor === floorNum) {
                        const curSeg = robot.currentSegIdx || 0;
                        const currElev = locations[st.path[curSeg]]?.y_elev ?? locations[st.path[0]]?.y_elev;
                        floorPts.push({ x: robot.current_x, y: robot.current_y, floor: floorNum, y_elev: currElev });

                        for (let i = curSeg + 1; i < st.path.length; i++) {
                            if (locations[st.path[i]]) floorPts.push(locations[st.path[i]]);
                        }
                        if (floorPts.length === 1 && st.path.length > 0) {
                            const lastN = st.path[st.path.length - 1];
                            if (locations[lastN]) floorPts.push(locations[lastN]);
                        }
                    } else if (isFutureStage || (isCurrentActive && robotFloor !== floorNum)) {
                        st.path.forEach((nodeId) => {
                            const loc = locations[nodeId];
                            if (!loc) return;
                            if (floorPts.length > 0) {
                                const prev = floorPts[floorPts.length - 1];
                                const sameX = Math.abs((prev.x || 0) - loc.x) < 0.001;
                                const sameY = Math.abs((prev.y || 0) - loc.y) < 0.001;
                                if (sameX && sameY) return;
                            }
                            floorPts.push(loc);
                        });
                    }
                });

                if (floorPts.length >= 2) {
                    const dashS = isPending ? 0.15 : 0.18;
                    const gapS = isPending ? 0.15 : 0.12;
                    const op = isPending ? 0.65 : 0.95;
                    if (floorNum === 2) {
                        drawPath3D([threeStd], floorPts, robotColor, op, dashS, gapS, 0.012);
                    } else {
                        drawPath3D([threeStdF1], floorPts, robotColor, op, dashS, gapS, 0.012);
                    }
                }
            });
        });

        // 2. Draw return paths for returning idle robots (Unified continuous dashed return path per floor)
        robots.forEach(robot => {
            if ((robot.status === 'Idle' || robot.status === 'Returning' || robot.isReturning) && robot.returnMission && robot.returnMission.stages) {
                const robotColor = getRobotColor(robot.id);
                const elapsedMs = now.getTime() - robot.returnMission.startedAt;
                const robotFloor = Number(robot.floor || 1);

                [1, 2].forEach(floorNum => {
                    const floorPts = [];

                    robot.returnMission.stages.forEach(st => {
                        if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                        const stageFloor = Number(st.floor || 1);
                        if (stageFloor !== floorNum) return;

                        const stageEndMs = st.startMs + st.durationMs;
                        if (elapsedMs >= stageEndMs) return;

                        const isCurrentActive = (elapsedMs >= st.startMs && elapsedMs < stageEndMs);
                        const isFutureStage = (elapsedMs < st.startMs);

                        if (isCurrentActive && robotFloor === floorNum) {
                            const curSeg = robot.returnSegIdx || 0;
                            const currElev = locations[st.path[curSeg]]?.y_elev ?? locations[st.path[0]]?.y_elev;
                            floorPts.push({ x: robot.current_x, y: robot.current_y, floor: floorNum, y_elev: currElev });

                            for (let i = curSeg + 1; i < st.path.length; i++) {
                                if (locations[st.path[i]]) floorPts.push(locations[st.path[i]]);
                            }
                            if (floorPts.length === 1 && st.path.length > 0) {
                                const lastN = st.path[st.path.length - 1];
                                if (locations[lastN]) floorPts.push(locations[lastN]);
                            }
                        } else if (isFutureStage || (isCurrentActive && robotFloor !== floorNum)) {
                            st.path.forEach((nodeId) => {
                                const loc = locations[nodeId];
                                if (!loc) return;
                                if (floorPts.length > 0) {
                                    const prev = floorPts[floorPts.length - 1];
                                    const sameX = Math.abs((prev.x || 0) - loc.x) < 0.001;
                                    const sameY = Math.abs((prev.y || 0) - loc.y) < 0.001;
                                    if (sameX && sameY) return;
                                }
                                floorPts.push(loc);
                            });
                        }
                    });

                    if (floorPts.length >= 2) {
                        if (floorNum === 2) {
                            drawPath3D([threeStd], floorPts, robotColor, 0.75, 0.16, 0.14, 0.012);
                        } else {
                            drawPath3D([threeStdF1], floorPts, robotColor, 0.75, 0.16, 0.14, 0.012);
                        }
                    }
                });
            }
        });
        // ensure network lines visible state updated after path redraw
        allViewers().forEach(viewer => { if(viewer && viewer.networkGroup) viewer.networkGroup.visible = showNetworkLines; });
    }

    function runSimulationStep() {
        if (document.hidden) return;
        const now = new Date(new Date().getTime() + serverClientOffset);

        // (2D overlay/SVG dihapus — kedua lantai murni 3D; avatar & path digambar di scene)
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            robot._activeDelivery = delivery || null;
            
            // Check if robot has active issue / alert
            const robotAlert = activeAlerts.find(a => Number(a.robot_id) === Number(robot.id) && a.status === 'Active');
            const isMaintenance = robot.status === 'Maintenance';
            const hasIssue = isMaintenance || (robot.status === 'Charging' && robot.battery_level <= 10) || (delivery && delivery.status === 'Pending');
            robot.hasIssue = hasIssue;
            robot.activeAlert = robotAlert;

            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            const baseLoc = getBaseLocation();
            let taskText = `Siaga di ${baseLoc.name || 'Markas Robot'}`;
            let currentLocName = resolveLocationName(coords.x, coords.y, floorNum);

            if (hasIssue) {
                const issueName = robotAlert ? robotAlert.issue_type : (robot.battery_level <= 10 ? 'Baterai Habis' : 'Perbaikan');
                if (delivery) {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> KENDALA: ${issueName} - Pengantaran Terhenti!</span>`;
                    currentLocName = `Terhenti di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                } else {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> KENDALA: ${issueName} (Perlu Penanganan)</span>`;
                    currentLocName = `Tertahan di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                }
            } else if (robotAlert && robot.status === 'Idle') {
                taskText = `<span class="text-amber-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1 animate-bounce"></i> Ada Laporan: ${robotAlert.issue_type} (Tinjau di Kontrol Bot)</span>`;
            } else if (robot.status === 'Charging') {
                coords = { x: baseLoc.x, y: baseLoc.y };
                floorNum = 1;
                robot.current_x = baseLoc.x;
                robot.current_y = baseLoc.y;
                robot.floor = 1;

                taskText = `<i class="fa-solid fa-bolt text-orange-500 mr-1 animate-pulse"></i> Pengisian Daya di ${baseLoc.name || 'Markas Robot'} (${robot.battery_level}%)...`;
                currentLocName = baseLoc.name || 'Markas Robot';
                
                // Active charging at base station
                const nowTime = now.getTime();
                if (!robot.lastChargeTick) robot.lastChargeTick = nowTime;
                if (nowTime - robot.lastChargeTick >= 1000) {
                    robot.lastChargeTick = nowTime;
                    const nextBat = Math.min(100, (Number(robot.battery_level) || 0) + 15);
                    robot.battery_level = nextBat;
                    
                    if (nextBat >= 100) {
                        robot.battery_level = 100;
                        robot._justCharged = true;
                        robot.isLowBatteryReturning = false;
                        robot.isReturning = false;
                        robot.returnMission = null;
                        resumeFromBaseAfterCharge(robot);
                    } else {
                        syncRobotPosition(robot.id, baseLoc.x, baseLoc.y, 1, 'Charging', nextBat);
                    }
                }
            } else if (robot.status === 'Maintenance') {
                taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Maintenance required</span>';
            }
            
            if (delivery && delivery.status === 'In Progress' && !hasIssue && robot.status !== 'Charging' && !robot.isLowBatteryReturning) {
                robot.status = 'Delivering';
                robot.returnMission = null;
                robot.isReturning = false;
                robot.needsReturnToBase = false;
                const mission = getDeliveryMission(delivery, robot);
                
                if (mission.stages && mission.stages.length > 0) {
                    const startedTime = parseServerDate(delivery.started_at);
                    const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
                    let angle = 0;
                    
                    if (elapsedMs >= mission.totalDurationMs) {
                        const lastStage = mission.stages[mission.stages.length - 1];
                        const lastNodeId = (lastStage.type === 'travel' && lastStage.path) ? lastStage.path[lastStage.path.length - 1] : mission.destId;
                        const destLoc = locations[lastNodeId] || locations[mission.destId];
                        if (destLoc) {
                            coords = destLoc;
                            floorNum = destLoc.floor || 1;
                        }
                        taskText = `Delivered ${delivery.item_name} to ${locations[mission.destId]?.name || delivery.destination_location}`;
                        currentLocName = locations[mission.destId]?.name || delivery.destination_location;
                        completeDeliveryAPI(delivery.id, coords.x, coords.y, floorNum);
                    } else {
                        let activeStage = null;
                        for (let st of mission.stages) {
                            if (elapsedMs >= st.startMs && elapsedMs < st.startMs + st.durationMs) {
                                activeStage = st;
                                break;
                            }
                        }
                        if (!activeStage) {
                            activeStage = mission.stages[mission.stages.length - 1];
                        }

                        const stageElapsed = Math.max(0, elapsedMs - activeStage.startMs);
                        const stageRatio = Math.max(0, Math.min(stageElapsed / activeStage.durationMs, 1.0));

                        if (activeStage.type === 'stairs') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const isSecondHalf = stageRatio >= 0.5;
                            floorNum = isSecondHalf ? activeStage.toFloor : activeStage.fromFloor;
                            const currentNodeId = isSecondHalf ? activeStage.toNode : activeStage.fromNode;
                            coords = locations[currentNodeId] || coords;
                            angle = 0;
                            taskText = `<span class="text-amber-600 font-bold"><i class="fa-solid fa-stairs animate-bounce mr-1"></i> Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec}s)...</span>`;
                            currentLocName = `Tangga (Transit Lantai ${activeStage.toFloor})`;
                            robot.currentSegIdx = 0;
                        } else if (activeStage.type === 'pickup') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const locNode = locations[activeStage.nodeId] || locations[mission.startId];
                            if (locNode) {
                                coords = locNode;
                                floorNum = locNode.floor || 1;
                            }
                            angle = 0;
                            taskText = `<span class="text-blue-600 font-bold"><i class="fa-solid fa-box-open animate-bounce mr-1"></i> Mengambil ${delivery.item_name} di ${locations[mission.startId]?.name || delivery.start_location} (${remainingSec}s)...</span>`;
                            currentLocName = locations[mission.startId]?.name || delivery.start_location;
                            robot.currentSegIdx = 0;
                        } else if (activeStage.type === 'dropoff') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const locNode = locations[activeStage.nodeId] || locations[mission.destId];
                            if (locNode) {
                                coords = locNode;
                                floorNum = locNode.floor || 1;
                            }
                            angle = 0;
                            taskText = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-dolly animate-bounce mr-1"></i> Menyerahkan ${delivery.item_name} di ${locations[mission.destId]?.name || delivery.destination_location} (${remainingSec}s)...</span>`;
                            currentLocName = locations[mission.destId]?.name || delivery.destination_location;
                            robot.currentSegIdx = 0;
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            const along = interpolateAlongPath(path, stageRatio);
                            if (along) {
                                coords = along.coords;
                                angle = along.angle;
                                robot.currentSegIdx = along.segIdx;
                            }
                            const isHeadingToPickup = mission.pickupStartMs && activeStage.startMs < mission.pickupStartMs;
                            if (isHeadingToPickup) {
                                taskText = `Menuju titik ambil: ${locations[mission.startId]?.name || delivery.start_location}`;
                            } else {
                                taskText = `Mengantar ${delivery.item_name} ke ${locations[mission.destId]?.name || delivery.destination_location}`;
                            }
                            currentLocName = resolveLocationName(coords.x, coords.y, floorNum);

                            // --- BATTERY DRAIN WHILE MOVING ---
                            const nowTime = now.getTime();
                            if (!robot.lastBatteryTick) robot.lastBatteryTick = nowTime;
                            if (nowTime - robot.lastBatteryTick >= 3500) {
                                robot.lastBatteryTick = nowTime;
                                robot.battery_level = Math.max(0, (Number(robot.battery_level) || 100) - 1);
                                syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                                
                                // Low Battery Threshold (<= 20%) -> Auto pause & return to base for charging
                                if (robot.battery_level <= 20 && !robot.isLowBatteryReturning && robot.status !== 'Charging') {
                                    triggerLowBatteryReturn(robot, delivery, coords, floorNum, elapsedMs);
                                    return;
                                }
                            }

                            // --- AUTONOMOUS COLLISION / CRASH SIMULATION ---
                            if (!robot.lastCrashCheck) robot.lastCrashCheck = nowTime;
                            if (!robot.lastCrashTime) robot.lastCrashTime = 0;
                            if (nowTime - robot.lastCrashCheck >= 12000) {
                                robot.lastCrashCheck = nowTime;
                                if (nowTime - robot.lastCrashTime >= 45000 && elapsedMs > 5000 && elapsedMs < (mission.totalDurationMs - 5000)) {
                                    if (Math.random() < 0.07) {
                                        robot.lastCrashTime = nowTime;
                                        triggerAutonomousCrash(robot, delivery, coords, floorNum, elapsedMs);
                                        return;
                                    }
                                }
                            }
                        }
                    }

                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.floor = floorNum;
                    robot.rotation = angle;
                }
            } else if ((robot.status === 'Idle' || robot.status === 'Returning') && !hasIssue) {
                const isEditingThis = isEditingRobot3D && Number(robot.id) === Number(focusedRobotId);
                const rIdx = robots.findIndex(r => Number(r.id) === Number(robot.id));
                const parkOff = getBaseParkingOffset(rIdx >= 0 ? rIdx : 0);
                const distToBase = (Number(robot.floor || 1) === 1) 
                    ? Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) 
                    : 999;
                const isNearBase = Number(robot.floor || 1) === 1 && distToBase < 2.2;

                if (isEditingThis) {
                    robot.returnMission = null;
                    robot.isReturning = false;
                    robot.needsReturnToBase = false;
                    robot.customPosition = true;
                    coords = { x: (robot.current_x !== undefined ? robot.current_x : baseLoc.x), y: (robot.current_y !== undefined ? robot.current_y : baseLoc.y) };
                    floorNum = robot.floor || currentDashboardFloor;
                    taskText = 'Mode Edit Posisi Robot (Geser 3D / D-Pad)';
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else if (isNearBase && (robot.status === 'Returning' || robot.isReturning || robot.returnMission)) {
                    coords = { x: baseLoc.x + parkOff.dx, y: baseLoc.y + parkOff.dy };
                    floorNum = 1;
                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.floor = 1;
                    robot.returnMission = null;
                    robot.isReturning = false;

                    if (robot.isLowBatteryReturning || robot.battery_level <= 20) {
                        robot.isLowBatteryReturning = false;
                        robot.status = 'Charging';
                        taskText = `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Mengisi daya di ${baseLoc.name || 'Markas Robot'}...</span>`;
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Charging', robot.battery_level);
                    } else {
                        robot.status = 'Idle';
                        taskText = `Siaga di ${baseLoc.name || 'Markas Robot'}`;
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                    }
                    currentLocName = baseLoc.name || 'Markas Robot';
                } else if (!isNearBase && distToBase > 2.0 && !robot.customPosition) {
                    const hasActiveOrPendingDelivery = activeDeliveries.some(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
                    if (!hasActiveOrPendingDelivery && !robot.isDispatching && !robot._activeDelivery) {
                        if (!robot.returnMission) {
                            robot.returnMission = buildReturnMission(robot, now);
                            if (!robot.returnMission) {
                                // Safe fallback if path not found
                                floorNum = 1;
                                coords = { x: baseLoc.x + parkOff.dx, y: baseLoc.y + parkOff.dy };
                                robot.current_x = coords.x;
                                robot.current_y = coords.y;
                                robot.floor = 1;
                                robot.status = 'Idle';
                                robot.isReturning = false;
                                syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                            }
                        }
                    }
                }

                if (!isEditingThis && robot.returnMission) {
                    robot.isReturning = true;
                    robot.status = 'Returning';
                    const mission = robot.returnMission;
                    const elapsedMs = now.getTime() - mission.startedAt;
                    let angle = 0;

                    if (elapsedMs < 0) {
                        taskText = `<span class="text-indigo-600 font-bold"><i class="fa-solid fa-box-open mr-1"></i> Selesai antar, persiapan balik ke ${baseLoc.name || 'Base'}...</span>`;
                        coords = { x: robot.current_x, y: robot.current_y };
                        floorNum = robot.floor || 1;
                    } else if (elapsedMs >= mission.totalDurationMs) {
                        coords = { x: baseLoc.x + parkOff.dx, y: baseLoc.y + parkOff.dy };
                        floorNum = 1;
                        robot.current_x = coords.x;
                        robot.current_y = coords.y;
                        robot.floor = 1;
                        robot.returnMission = null;
                        robot.isReturning = false;

                        if (robot.isLowBatteryReturning || robot.battery_level <= 20) {
                            robot.isLowBatteryReturning = false;
                            robot.status = 'Charging';
                            taskText = `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Mengisi daya di ${baseLoc.name || 'Markas'}...</span>`;
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Charging', robot.battery_level);
                        } else {
                            robot.status = 'Idle';
                            taskText = `Siaga di ${baseLoc.name || 'Markas Pangkalan'}`;
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                        }
                        currentLocName = baseLoc.name || 'Markas Pangkalan';
                    } else {
                        let activeStage = null;
                        for (let st of mission.stages) {
                            if (elapsedMs >= st.startMs && elapsedMs < st.startMs + st.durationMs) {
                                activeStage = st;
                                break;
                            }
                        }
                        if (!activeStage) activeStage = mission.stages[mission.stages.length - 1];

                        const stageElapsed = Math.max(0, elapsedMs - activeStage.startMs);
                        const stageRatio = Math.max(0, Math.min(stageElapsed / activeStage.durationMs, 1.0));

                        if (activeStage.type === 'stairs') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const isSecondHalf = stageRatio >= 0.5;
                            floorNum = isSecondHalf ? activeStage.toFloor : activeStage.fromFloor;
                            const currentNodeId = isSecondHalf ? activeStage.toNode : activeStage.fromNode;
                            coords = locations[currentNodeId] || coords;
                            angle = 0;
                            taskText = `<span class="text-amber-600 font-bold"><i class="fa-solid fa-stairs animate-bounce mr-1"></i> Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec} dtk)...</span>`;
                            robot.returnSegIdx = 0;

                            if (robot.floor !== floorNum) {
                                robot.floor = floorNum;
                                syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                            }
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            const along = interpolateAlongPath(path, stageRatio);
                            if (along) {
                                coords = along.coords;
                                angle = along.angle;
                                robot.returnSegIdx = along.segIdx;
                            }
                            if (robot.isLowBatteryReturning) {
                                taskText = `<span class="text-orange-600 font-bold animate-pulse"><i class="fa-solid fa-battery-quarter text-orange-500 mr-1"></i> Baterai Rendah (${robot.battery_level}%), Kembali ke Markas...</span>`;
                            } else {
                                taskText = `<span class="text-indigo-600 font-bold"><i class="fa-solid fa-arrow-rotate-left mr-1"></i> Kembali ke ${baseLoc.name || 'Markas'}...</span>`;
                            }
                        }

                        // Battery drain while returning
                        const nowTime = now.getTime();
                        if (!robot.lastBatteryTick) robot.lastBatteryTick = nowTime;
                        if (nowTime - robot.lastBatteryTick >= 2500) {
                            robot.lastBatteryTick = nowTime;
                            robot.battery_level = Math.max(0, (Number(robot.battery_level) || 100) - 1);
                        }

                        // Throttle sync telemetry position during return
                        if (!robot.lastPosSync || (nowTime - robot.lastPosSync >= 1500)) {
                            robot.lastPosSync = nowTime;
                            syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                        }

                        robot.current_x = coords.x;
                        robot.current_y = coords.y;
                        robot.floor = floorNum;
                        robot.rotation = angle;

                        // Immediate snap upon reaching base station during movement
                        if (floorNum === 1 && Math.hypot(coords.x - baseLoc.x, coords.y - baseLoc.y) < 2.0) {
                            coords = { x: baseLoc.x + parkOff.dx, y: baseLoc.y + parkOff.dy };
                            robot.current_x = coords.x;
                            robot.current_y = coords.y;
                            robot.floor = 1;
                            robot.returnMission = null;
                            robot.isReturning = false;
                            const isChargingNeeded = robot.isLowBatteryReturning || (Number(robot.battery_level) || 100) <= 20;
                            robot.isLowBatteryReturning = false;
                            robot.status = isChargingNeeded ? 'Charging' : 'Idle';
                            taskText = (robot.status === 'Charging') 
                                ? `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Mengisi daya di ${baseLoc.name || 'Markas'}...</span>`
                                : `Siaga di ${baseLoc.name || 'Markas Pangkalan'}`;
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, robot.status, robot.battery_level);
                        }
                    }
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else if (!isEditingThis) {
                    const isAtBase = (Number(robot.floor || 1) === 1 && Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) < 2.0);
                    if (isAtBase) {
                        coords = { x: baseLoc.x + parkOff.dx, y: baseLoc.y + parkOff.dy };
                        floorNum = 1;
                    } else {
                        coords = { x: (robot.current_x !== undefined ? robot.current_x : baseLoc.x), y: (robot.current_y !== undefined ? robot.current_y : baseLoc.y) };
                        floorNum = robot.floor || 1;
                    }
                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.floor = floorNum;
                    taskText = isAtBase ? `Siaga di ${baseLoc.name || 'Markas Pangkalan'}` : `Siaga di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                }
            }

            // Avatar 3D per lantai (std + fullview); posisi & status badge sinkron dari data gerak yang sama.
            // Sembunyikan avatar di lantai lain agar tidak ada ghost mesh
            const destNodeId = (delivery && delivery.status === 'In Progress' && robot.status === 'Delivering') ? delivery.destination_location : null;
            const destName = destNodeId ? (locations[destNodeId]?.name || null) : null;
            if (Number(floorNum) === 2) {
                updateRobot3DAvatar(threeStd, robot, coords, destName);
                hideRobot3DAvatar(threeStdF1, robot);
            } else {
                updateRobot3DAvatar(threeStdF1, robot, coords, destName);
                hideRobot3DAvatar(threeStd, robot);
            }

            // Sinkronisasi otomatis lantai tampilan jika sedang mem-follow robot ini dan robot berpindah lantai
            if (isFollowMode && Number(focusedRobotId) === Number(robot.id)) {
                const targetFloor = Number(floorNum) === 2 ? 2 : 1;
                if (isFullViewMode) {
                    if (Number(currentFullViewFloor) !== targetFloor) {
                        switchFullViewFloor(targetFloor);
                    }
                } else {
                    if (Number(currentDashboardFloor) !== targetFloor) {
                        switchDashboardFloor(targetFloor);
                    }
                }
            }

            // Update Robot Cards in standard view
            const badge = document.getElementById(`robot-status-badge-${robot.id}`);
            const batBar = document.getElementById(`robot-battery-bar-${robot.id}`);
            const batText = document.getElementById(`robot-battery-text-${robot.id}`);
            const locText = document.getElementById(`robot-location-text-${robot.id}`);
            const taskTextDiv = document.getElementById(`robot-task-text-${robot.id}`);

            if (badge) {
                if (hasIssue) {
                    const issueLabel = robot.activeAlert ? robot.activeAlert.issue_type.toUpperCase() : 'PERBAIKAN';
                    badge.textContent = issueLabel;
                    badge.className = 'text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-300 animate-pulse';
                } else if (robot.activeAlert && robot.status === 'Idle') {
                    badge.textContent = 'Siaga (Laporan)';
                    badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300 animate-pulse';
                } else if (robot.isReturning) {
                    badge.textContent = 'Kembali';
                    badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-indigo-100 text-indigo-700 border border-indigo-200';
                } else {
                    const statusIndo = robot.status === 'Idle' ? 'Siaga' :
                        (robot.status === 'Delivering' ? 'Mengantar' :
                        (robot.status === 'Charging' ? 'Mengisi Daya' :
                        (robot.status === 'Returning' ? 'Kembali' : 'Perbaikan')));
                    badge.textContent = statusIndo;
                    badge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                        robot.status === 'Delivering' ? 'bg-blue-100 text-blue-700 border border-blue-200' :
                        (robot.status === 'Charging' ? 'bg-orange-100 text-orange-700 border border-orange-200' :
                        (robot.status === 'Maintenance' ? 'bg-rose-100 text-rose-700 border border-rose-200' :
                        'bg-emerald-100 text-emerald-700 border border-emerald-200'))
                    }`;
                }
            }
            if (batBar) {
                batBar.style.width = `${robot.battery_level}%`;
                batBar.className = `h-1.5 rounded-full ${robot.battery_level <= 20 ? 'bg-rose-500' : 'bg-[#3b4cb8]'}`;
            }
            if (batText) batText.textContent = `${robot.battery_level}%`;
            if (locText) locText.textContent = `${currentLocName} (Lantai ${floorNum})`;
            if (taskTextDiv) taskTextDiv.innerHTML = taskText;
        });

        // Update Emergency Alert Banner
        updateEmergencyBanner();

        // Draw path lines dynamically after positions and segments have updated
        drawRobotPaths();

        // Check autopilot conditions
        runAutopilotManager();
    }

    function updateEmergencyBanner() {
        const banner = document.getElementById('emergency-alert-banner');
        const bannerText = document.getElementById('emergency-banner-text');
        const issueRobots = robots.filter(r => r.hasIssue);
        const alertRobots = robots.filter(r => r.activeAlert && !r.hasIssue);

        if (!banner || !bannerText) return;

        if (issueRobots.length > 0) {
            banner.classList.remove('hidden');
            const descriptions = issueRobots.map(r => {
                const alertType = r.activeAlert ? r.activeAlert.issue_type : (r.battery_level <= 10 ? 'Baterai Habis' : 'Perbaikan');
                return `${r.name}: ${alertType} (${r.activeAlert?.description || 'Pengantaran mandek'})`;
            }).join(' | ');
            bannerText.innerHTML = `⚠️ ${descriptions}. <strong>Cepat perbaiki agar robot dapat kembali bekerja!</strong>`;
        } else if (alertRobots.length > 0) {
            banner.classList.remove('hidden');
            const descriptions = alertRobots.map(r => `${r.name}: ${r.activeAlert.issue_type} (${r.activeAlert.description || 'Laporan baru'})`).join(' | ');
            bannerText.innerHTML = `⚠️ <strong>Pemberitahuan Admin:</strong> Terdapat laporan kendala untuk ${descriptions}. <a href="/bot-control" class="underline font-bold text-amber-200 hover:text-white ml-1">Buka Kontrol Bot untuk kelola status &rarr;</a>`;
        } else {
            banner.classList.add('hidden');
        }
    }

    // Toggle Dropdown for Issue Simulation
    function toggleSimulateMenu() {
        const menu = document.getElementById('simulate-menu');
        if (menu) menu.classList.toggle('hidden');
    }

    // Close dropdown on outside click
    window.addEventListener('click', function(e) {
        const container = document.getElementById('simulate-dropdown-container');
        const menu = document.getElementById('simulate-menu');
        if (container && menu && !container.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    // Simulate robot issue
    function simulateIssueAction(robotId, issueType) {
        const menu = document.getElementById('simulate-menu');
        if (menu) menu.classList.add('hidden');

        if (!window.isAdmin) {
            alert('Akses Terbatas: Hanya Admin yang dapat mensimulasikan masalah robot.');
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robotId}/simulate-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ issue_type: issueType })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchData();
            }
        })
        .catch(err => console.error('Error simulating issue:', err));
    }

    // Fix a specific robot
    function fixRobotAction(robotId) {
        if (!window.isAdmin) {
            alert('Akses Terbatas: Hanya Admin yang dapat memperbaiki robot.');
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robotId}/fix`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchData();
            }
        })
        .catch(err => console.error('Error fixing robot:', err));
    }

    // Fix all robots with active issues
    function fixActiveIssueRobot() {
        const issueRobots = robots.filter(r => r.hasIssue);
        if (issueRobots.length === 0) return;
        issueRobots.forEach(r => fixRobotAction(r.id));
    }

    // Delivery Completion API
    function completeDeliveryAPI(deliveryId, finalX, finalY, finalFloor) {
        const delivery = activeDeliveries.find(d => d.id === deliveryId);
        if (!delivery || delivery.isCompleting) return;
        delivery.isCompleting = true;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/deliveries/${deliveryId}/complete`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                current_x: finalX, 
                current_y: finalY,
                floor: finalFloor || 1
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
                if (robot && data.robot) {
                    robot.status = data.robot.status;
                    robot.current_x = data.robot.current_x;
                    robot.current_y = data.robot.current_y;
                    robot.floor = data.robot.floor;
                }
                fetchData();
            }
        })
        .catch(err => {
            console.error('Error completing delivery:', err);
            delivery.isCompleting = false;
        });
    }

    // Autopilot Management (System-Wide via Backend & Client Sync)
    function toggleAutopilot() {
        if (!window.isAdmin) {
            alert('Akses Terbatas: Hanya Admin / Bot Control yang dapat mengontrol Autopilot.');
            return;
        }

        const nextState = !isAutopilotEnabled;
        isAutopilotEnabled = nextState;
        if (nextState) {
            robots.forEach(r => {
                r.returnMission = null;
                r.isReturning = false;
                r.needsReturnToBase = false;
                r.isDispatching = false;
                if (r.status === 'Returning') r.status = 'Idle';
            });
            // Langsung dispatch robot serentak tanpa jeda agar robot tidak ragu/kembali ke base
            lastAutopilotCheck = 0;
            setTimeout(dispatchAllRobotsSerentak, 120);
        }
        updateAutopilotUI();

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('/api/system/autopilot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ enabled: nextState })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                isAutopilotEnabled = !!data.autopilot_enabled;
                updateAutopilotUI();
                fetchData();
            }
        })
        .catch(err => console.error('Error toggling autopilot:', err));
    }

    function updateAutopilotUI() {
        // Standard view button
        const btn = document.getElementById('autopilot-btn');
        const text = document.getElementById('autopilot-text');
        const icon = document.getElementById('autopilot-icon');

        if (btn && text) {
            if (isAutopilotEnabled) {
                btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30";
                text.innerHTML = '<span class="relative flex h-2 w-2 mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span></span> Autopilot: AKTIF (Serentak)';
                if (icon) icon.className = "fa-solid fa-robot animate-bounce";
            } else {
                if (window.isAdmin) {
                    btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300";
                } else {
                    btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 bg-gray-100 text-gray-500 border border-gray-200 cursor-not-allowed";
                }
                text.textContent = 'Autopilot: NONAKTIF (Manual)';
                if (icon) icon.className = "fa-solid fa-wand-magic-sparkles";
            }
        }

        // Full View top bar button
        const fvBtn = document.getElementById('fullview-autopilot-btn');
        const fvText = document.getElementById('fullview-autopilot-text');
        const fvIcon = document.getElementById('fullview-autopilot-icon');
        if (fvBtn && fvText) {
            if (isAutopilotEnabled) {
                fvBtn.className = "bg-emerald-600 hover:bg-emerald-500 border border-emerald-400/40 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap shadow-lg shadow-emerald-600/30";
                fvText.innerHTML = '<span class="relative flex h-2 w-2 mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span></span> Autopilot: AKTIF';
                if (fvIcon) fvIcon.className = "fa-solid fa-robot animate-bounce text-white";
            } else {
                const isAdmin = window.isAdmin ?? false;
                fvBtn.className = "bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-200 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap" + (isAdmin ? "" : " cursor-not-allowed opacity-60");
                fvText.textContent = 'Autopilot: NONAKTIF';
                if (fvIcon) fvIcon.className = "fa-solid fa-wand-magic-sparkles text-amber-400";
            }
        }

        // Full View dispatch inspector status
        const fvTitle = document.getElementById('fv-dispatch-autopilot-title');
        const fvInspIcon = document.getElementById('fv-dispatch-autopilot-icon');
        if (fvTitle) {
            if (isAutopilotEnabled) {
                fvTitle.textContent = "Mode Autopilot: AKTIF (Serentak)";
                fvTitle.className = "font-bold text-emerald-400 text-[11px]";
                if (fvInspIcon) fvInspIcon.className = "fa-solid fa-robot text-emerald-400 text-sm animate-pulse";
            } else {
                fvTitle.textContent = "Mode Autopilot: NONAKTIF (Manual)";
                fvTitle.className = "font-bold text-slate-200 text-[11px]";
                if (fvInspIcon) fvInspIcon.className = "fa-solid fa-hand text-sky-400 text-sm";
            }
        }
    }

    // Full View Manual Dispatch Inspector Logic
    function toggleFullViewDispatchPanel(forceState) {
        const panel = document.getElementById('fullview-dispatch-panel');
        const btn = document.getElementById('fullview-btn-dispatch');
        if (!panel) return;

        const shouldShow = (forceState !== undefined) ? !!forceState : panel.classList.contains('hidden');
        if (shouldShow) {
            panel.classList.remove('hidden');
            if (btn) {
                btn.classList.add('ring-2', 'ring-indigo-400', 'bg-indigo-500');
            }
            populateFullViewDispatchDropdowns();
            updateFullViewActiveDeliveriesList();
        } else {
            panel.classList.add('hidden');
            if (btn) {
                btn.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-500');
            }
        }
    }

    function populateFullViewDispatchDropdowns() {
        const robotSelect = document.getElementById('fv-dispatch-robot');
        const startSelect = document.getElementById('fv-dispatch-start');
        const destSelect = document.getElementById('fv-dispatch-dest');
        if (!robotSelect || !startSelect || !destSelect) return;

        // 1. Populate Robots
        const currentRobotVal = robotSelect.value;
        robotSelect.innerHTML = '<option value="" disabled selected>Pilih robot pengantar...</option>';
        let firstAvailableRobotId = null;

        robots.forEach(robot => {
            const isBusy = (robot.status !== 'Idle' && robot.status !== 'Returning') || robot.battery_level <= 20 || robot.isReturning || robot.isDispatching || robot.hasIssue;
            const opt = document.createElement('option');
            opt.value = robot.id;
            
            const statusIndoMap = {
                'Idle': 'Siaga',
                'Delivering': 'Mengantar',
                'Charging': 'Mengisi Daya',
                'Maintenance': 'Perbaikan',
                'Returning': 'Kembali'
            };
            const rStatusText = statusIndoMap[robot.status] || robot.status;
            let label = `${robot.name} (${rStatusText} - Bat: ${robot.battery_level}%)`;
            if (robot.status !== 'Idle') {
                label += ` [${rStatusText}]`;
            } else if (robot.battery_level <= 20) {
                label += ' [Baterai Rendah]';
            }
            opt.textContent = label;
            if (isBusy) {
                opt.disabled = true;
            } else if (!firstAvailableRobotId) {
                firstAvailableRobotId = robot.id;
            }
            if (currentRobotVal && String(robot.id) === String(currentRobotVal)) {
                opt.selected = true;
            }
            robotSelect.appendChild(opt);
        });

        if (!robotSelect.value && firstAvailableRobotId) {
            robotSelect.value = firstAvailableRobotId;
        }

        // 2. Populate Locations if not populated yet
        if (destSelect.options.length <= 1) {
            let f1Opts = '';
            let f2Opts = '';
            Object.values(locations).forEach(loc => {
                if (loc.is_destination || !loc.hidden) {
                    const floor = Number(loc.floor || 1);
                    const optHtml = `<option value="${loc.id}">${loc.name} (Lantai ${floor})</option>`;
                    if (floor === 2) f2Opts += optHtml;
                    else f1Opts += optHtml;
                }
            });

            startSelect.innerHTML = `
                <option value="" disabled selected>Pilih titik jemput barang...</option>
                <optgroup label="Lantai 1 (Ground Floor)">${f1Opts}</optgroup>
                <optgroup label="Lantai 2 (Second Floor)">${f2Opts}</optgroup>
            `;

            destSelect.innerHTML = `
                <option value="" disabled selected>Pilih tujuan pengantaran...</option>
                <optgroup label="Lantai 1 (Ground Floor)">${f1Opts}</optgroup>
                <optgroup label="Lantai 2 (Second Floor)">${f2Opts}</optgroup>
            `;
        }

        onFvDispatchRobotChange();
    }

    function onFvDispatchRobotChange() {
        const robotSelect = document.getElementById('fv-dispatch-robot');
        const posDesc = document.getElementById('fv-robot-pos-desc');
        const batDesc = document.getElementById('fv-robot-bat-desc');
        if (!robotSelect) return;

        const robotId = robotSelect.value;
        const robot = robots.find(r => String(r.id) === String(robotId));

        if (robot) {
            const floor = Number(robot.floor || 1);
            const resolvedNode = resolveLocationNodeId(robot.current_x, robot.current_y, floor) || getBaseLocationId();
            const resolvedName = (resolvedNode && locations[resolvedNode]) ? locations[resolvedNode].name : `Lantai ${floor}`;
            if (posDesc) posDesc.textContent = `Lokasi Robot: ${resolvedName} (Lt ${floor})`;
            if (batDesc) {
                batDesc.textContent = `Bat: ${robot.battery_level}%`;
                batDesc.className = robot.battery_level > 50 ? 'font-bold text-emerald-400' : (robot.battery_level > 20 ? 'font-bold text-amber-400' : 'font-bold text-rose-400');
            }
        } else {
            if (posDesc) posDesc.textContent = 'Lokasi: -';
            if (batDesc) {
                batDesc.textContent = 'Bat: -%';
                batDesc.className = 'font-bold text-slate-400';
            }
        }
    }

    function handleFullViewManualDispatch(e) {
        if (e) e.preventDefault();

        const robotSelect = document.getElementById('fv-dispatch-robot');
        const itemSelect = document.getElementById('fv-dispatch-item');
        const startSelect = document.getElementById('fv-dispatch-start');
        const destSelect = document.getElementById('fv-dispatch-dest');
        const errBox = document.getElementById('fv-dispatch-error');
        const errText = document.getElementById('fv-dispatch-error-text');
        const succBox = document.getElementById('fv-dispatch-success');
        const succText = document.getElementById('fv-dispatch-success-text');
        const submitBtn = document.getElementById('fv-dispatch-submit-btn');
        const btnText = document.getElementById('fv-dispatch-btn-text');

        if (errBox) errBox.classList.add('hidden');
        if (succBox) succBox.classList.add('hidden');

        const robotId = robotSelect?.value;
        const item = itemSelect?.value;
        const start = startSelect?.value;
        const dest = destSelect?.value;

        if (!robotId) {
            if (errText) errText.textContent = 'Silakan pilih robot terlebih dahulu!';
            if (errBox) errBox.classList.remove('hidden');
            return;
        }
        if (!item) {
            if (errText) errText.textContent = 'Silakan pilih barang yang akan diantar!';
            if (errBox) errBox.classList.remove('hidden');
            return;
        }
        if (!start || !dest) {
            if (errText) errText.textContent = 'Titik jemput dan titik tujuan wajib dipilih!';
            if (errBox) errBox.classList.remove('hidden');
            return;
        }
        if (start === dest) {
            if (errText) errText.textContent = 'Titik tujuan tidak boleh sama dengan titik jemput!';
            if (errBox) errBox.classList.remove('hidden');
            return;
        }

        const robot = robots.find(r => String(r.id) === String(robotId));
        const rFloor = Number(robot?.floor || 1);
        const origin = (robot && robot.current_x != null && robot.current_y != null)
            ? (resolveLocationNodeId(robot.current_x, robot.current_y, rFloor) || getBaseLocationId())
            : getBaseLocationId();

        if (submitBtn) submitBtn.disabled = true;
        if (btnText) btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menugaskan...';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('/api/deliveries', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                robot_id: robotId,
                item_name: item,
                origin_location: origin,
                start_location: start,
                destination_location: dest
            })
        })
        .then(res => res.json())
        .then(data => {
            if (submitBtn) submitBtn.disabled = false;
            if (btnText) btnText.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Tugaskan Robot Sekarang';

            if (data.success) {
                if (robot) {
                    robot.status = 'Delivering';
                    robot.isDispatching = false;
                }
                if (succText) succText.textContent = `${robot ? robot.name : 'Robot'} berhasil ditugaskan mengantar ${item}!`;
                if (succBox) succBox.classList.remove('hidden');

                if (itemSelect) itemSelect.value = '';
                
                fetchData();
                populateFullViewDispatchDropdowns();
                updateFullViewActiveDeliveriesList();

                setTimeout(() => {
                    if (succBox) succBox.classList.add('hidden');
                }, 5000);
            } else {
                if (errText) errText.textContent = data.message || 'Gagal menugaskan robot.';
                if (errBox) errBox.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error dispatching from full view:', err);
            if (submitBtn) submitBtn.disabled = false;
            if (btnText) btnText.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Tugaskan Robot Sekarang';
            if (errText) errText.textContent = 'Terjadi kesalahan jaringan. Coba lagi.';
            if (errBox) errBox.classList.remove('hidden');
        });
    }

    function updateFullViewActiveDeliveriesList() {
        const container = document.getElementById('fv-active-deliv-container');
        const badge = document.getElementById('fv-active-count-badge');
        const topBadge = document.getElementById('fullview-active-deliv-badge');

        const activeList = (activeDeliveries || []).filter(d => d.status === 'In Progress' || d.status === 'Pending');
        const count = activeList.length;

        if (badge) badge.textContent = `${count} Aktif`;
        if (topBadge) {
            topBadge.textContent = count;
            if (count > 0) {
                topBadge.classList.remove('hidden');
            } else {
                topBadge.classList.add('hidden');
            }
        }

        if (!container) return;

        if (count === 0) {
            container.innerHTML = `
                <div class="p-3 rounded-xl bg-slate-800/40 border border-slate-700/40 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-box-open text-slate-500 text-lg mb-1 block"></i>
                    Tidak ada pengantaran aktif saat ini
                </div>
            `;
            return;
        }

        container.innerHTML = activeList.map(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            const robotName = robot ? robot.name : `Robot #${delivery.robot_id}`;
            const startName = (locations[delivery.start_location] && locations[delivery.start_location].name) || delivery.start_location || '-';
            const destName = (locations[delivery.destination_location] && locations[delivery.destination_location].name) || delivery.destination_location || '-';
            const itemName = delivery.item_name || 'Barang';

            return `
                <div class="p-2.5 rounded-xl bg-slate-800/80 border border-slate-700/80 hover:border-slate-600 transition shadow-sm text-xs">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="font-bold text-slate-200 flex items-center gap-1.5 truncate">
                            <i class="fa-solid fa-robot text-sky-400"></i> ${robotName}
                        </span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-md font-semibold bg-sky-500/20 text-sky-300 border border-sky-500/30 flex items-center gap-1 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span> ${delivery.status === 'In Progress' ? 'Berlangsung' : (delivery.status === 'Pending' ? 'Tertunda' : (delivery.status === 'Completed' ? 'Selesai' : (delivery.status === 'Failed' ? 'Gagal' : delivery.status)))}
                        </span>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] text-slate-300 font-medium mb-1">
                        <i class="fa-solid fa-box text-amber-400 text-[10px]"></i>
                        <span class="truncate">${itemName}</span>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] text-slate-400">
                        <span class="truncate text-slate-300">${startName}</span>
                        <i class="fa-solid fa-arrow-right text-[9px] text-slate-500 shrink-0"></i>
                        <span class="truncate text-indigo-300 font-medium">${destName}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function dispatchAllRobotsSerentak() {
        if (!isAutopilotEnabled) return;

        const baseId = getBaseLocationId();
        let destinationNodeIds = Object.keys(locations).filter(id => locations[id].is_destination && id !== baseId && !id.includes('Tangga') && !id.includes('_Stairs'));
        if (destinationNodeIds.length < 2) {
            destinationNodeIds = Object.keys(locations).filter(id => !id.includes('_N') && !id.includes('Tangga') && !id.includes('_Stairs') && id !== baseId);
        }
        if (destinationNodeIds.length < 2) return;

        const items = ['Handuk', 'Makanan', 'Dokumen', 'Kopi', 'Paket', 'Botol Air', 'Sparepart'];

        // Find all idle healthy robots ready for dispatch
        const eligibleRobots = robots.filter(r => 
            (r.status === 'Idle' || r.status === 'Returning') && 
            r.battery_level > 20 && 
            !r.isDispatching && 
            !r.hasIssue
        );

        if (eligibleRobots.length === 0) return;

        eligibleRobots.forEach((robot, idx) => {
            robot.isDispatching = true;
            robot.returnMission = null;
            robot.isReturning = false;
            robot.needsReturnToBase = false;

            const item = items[(idx + Math.floor(Math.random() * items.length)) % items.length];
            let currentLoc = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1) || baseId;

            // Pick a realistic pickup point (Titik Jemput) that is NOT base station
            const availablePickups = destinationNodeIds.filter(id => id !== currentLoc);
            const pickupLoc = availablePickups[(idx * 2) % availablePickups.length] || availablePickups[0];

            // Pick a destination (Titik Antar) that is DIFFERENT from pickup and DIFFERENT from current location
            const availableDests = destinationNodeIds.filter(id => id !== pickupLoc && id !== currentLoc);
            const dest = availableDests[(idx * 2 + 1) % availableDests.length] || availableDests[0];

            setTimeout(() => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('/api/deliveries', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        robot_id: robot.id,
                        item_name: item,
                        origin_location: currentLoc,
                        start_location: pickupLoc,
                        destination_location: dest
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetchData();
                    }
                    robot.isDispatching = false;
                })
                .catch(err => {
                    console.error('Error dispatching robot:', err);
                    robot.isDispatching = false;
                });
            }, idx * 350);
        });
    }

    let lastAutopilotCheck = 0;
    function runAutopilotManager() {
        if (!isAutopilotEnabled) return;

        const now = Date.now();
        if (now - lastAutopilotCheck < 3000) return;
        lastAutopilotCheck = now;

        // Check if any robot is Idle and ready to be dispatched
        const readyRobots = robots.filter(r => 
            (r.status === 'Idle' || r.status === 'Returning') && 
            !r.isDispatching && 
            !r.hasIssue && 
            r.battery_level > 20
        );

        if (readyRobots.length > 0) {
            dispatchAllRobotsSerentak();
        }
    }

    function fetchData() {
        fetch('/api/telemetry')
        .then(res => res.json())
        .then(data => {
            if (data.server_time) {
                const serverTime = new Date(data.server_time);
                const clientTime = new Date();
                serverClientOffset = serverTime.getTime() - clientTime.getTime();
            }

            if (typeof data.autopilot_enabled !== 'undefined') {
                if (isAutopilotEnabled !== data.autopilot_enabled) {
                    isAutopilotEnabled = !!data.autopilot_enabled;
                    updateAutopilotUI();
                }
            }

            if (data.settings_3d && data.settings_3d.lighting) {
                applyAdminLighting(data.settings_3d.lighting);
            }
            
            if (activeDeliveries && Array.isArray(activeDeliveries)) {
                data.active_deliveries.forEach(newDeliv => {
                    const existing = activeDeliveries.find(d => d.id === newDeliv.id);
                    if (existing) {
                        if (existing._cachedMission) newDeliv._cachedMission = existing._cachedMission;
                        if (existing._cachedPath) newDeliv._cachedPath = existing._cachedPath;
                        if (existing.isCompleting) newDeliv.isCompleting = existing.isCompleting;
                    }
                });
            }
            activeDeliveries = data.active_deliveries;
            activeAlerts = data.active_alerts || [];

            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                if (existing) {
                    const isEditingThis = isEditingRobot3D && Number(existing.id) === Number(focusedRobotId);
                    if (isEditingThis) {
                        existing.battery_level = newRobot.battery_level;
                        return;
                    }

                    const bLoc = getBaseLocation();
                    const isClientAtBase = Number(existing.floor || 1) === 1 && Math.hypot((existing.current_x || bLoc.x) - bLoc.x, (existing.current_y || bLoc.y) - bLoc.y) < 2.0;
                    const hasDeliveryInProgress = activeDeliveries.some(d => Number(d.robot_id) === Number(existing.id) && d.status === 'In Progress');

                    if (hasDeliveryInProgress) {
                        existing.status = 'Delivering';
                        existing.returnMission = null;
                        existing.isReturning = false;
                        existing.needsReturnToBase = false;
                    } else if (existing.status === 'Delivering') {
                        if (newRobot.status === 'Maintenance') {
                            existing.status = 'Maintenance';
                            existing.hasIssue = true;
                        } else {
                            existing.status = newRobot.status;
                            if (newRobot.status === 'Idle' && !isClientAtBase) {
                                existing.returnMission = buildReturnMission(existing, new Date(new Date().getTime() + serverClientOffset));
                                existing.isReturning = true;
                            }
                        }
                    } else if (existing.status === 'Returning' || existing.isReturning || !!existing.returnMission) {
                        if (newRobot.status === 'Maintenance') {
                            existing.status = 'Maintenance';
                            existing.hasIssue = true;
                        }
                    } else if (existing.status === 'Idle') {
                        if (newRobot.status === 'Charging') {
                            existing.status = 'Charging';
                        } else if (newRobot.status === 'Returning' && !isClientAtBase) {
                            existing.status = 'Returning';
                        }
                    } else {
                        existing.status = newRobot.status;
                    }

                    // Coordinates & Floor Merge (Firmly lock Base/Charging and Client Navigation)
                    if (existing.status === 'Charging' || (existing.status === 'Idle' && isClientAtBase)) {
                        existing.floor = 1;
                        existing.current_x = bLoc.x;
                        existing.current_y = bLoc.y;
                    } else if (existing.status === 'Delivering' || existing.status === 'Returning' || existing.isReturning || !!existing.returnMission) {
                        // Keep live client-side coordinates along path - NEVER overwrite from server!
                    } else if (newRobot.current_x != null && newRobot.current_y != null && !existing.customPosition) {
                        existing.floor = newRobot.floor || existing.floor || 1;
                        existing.current_x = newRobot.current_x;
                        existing.current_y = newRobot.current_y;
                    }

                    // Battery Level Merge
                    const isLocalCharging = (existing.status === 'Charging');
                    const justCharged = existing._justCharged;

                    if (justCharged) {
                        existing.battery_level = Math.max(100, Number(existing.battery_level) || 100);
                        if (Number(newRobot.battery_level) >= 95) {
                            existing._justCharged = false;
                        }
                    } else if (isLocalCharging) {
                        existing.battery_level = Math.max(Number(existing.battery_level) || 0, Number(newRobot.battery_level) || 0);
                    } else {
                        existing.battery_level = Number(newRobot.battery_level);
                    }
                } else {
                    robots.push(newRobot);
                }
            });

            if (typeof updateFullViewActiveDeliveriesList === 'function') {
                updateFullViewActiveDeliveriesList();
            }
            if (typeof populateFullViewDispatchDropdowns === 'function') {
                populateFullViewDispatchDropdowns();
            }

            // Real-time Update KPI Cards (Unit Aktif, Misi Berjalan, Selesai Hari Ini, Peringatan Sistem)
            if (data.stats) {
                const statActiveRobots = document.getElementById('stat-active-robots');
                const statActiveDeliveries = document.getElementById('stat-active-deliveries');
                const statDeliveriesToday = document.getElementById('stat-deliveries-today');
                const statSuccessRate = document.getElementById('stat-success-rate');
                const statActiveAlerts = document.getElementById('stat-active-alerts');
                const statActiveAlertsBadge = document.getElementById('stat-active-alerts-badge');

                if (statActiveRobots && typeof data.stats.active_robots_count !== 'undefined') {
                    statActiveRobots.textContent = `${data.stats.active_robots_count}/${data.stats.total_robots_count}`;
                }
                if (statActiveDeliveries && typeof data.stats.active_deliveries_count !== 'undefined') {
                    statActiveDeliveries.textContent = data.stats.active_deliveries_count;
                }
                if (statDeliveriesToday && typeof data.stats.deliveries_today_count !== 'undefined') {
                    statDeliveriesToday.textContent = data.stats.deliveries_today_count;
                }
                if (statSuccessRate && typeof data.stats.success_rate !== 'undefined') {
                    statSuccessRate.textContent = `${data.stats.success_rate}%`;
                }
                if (statActiveAlerts && typeof data.stats.active_alerts_count !== 'undefined') {
                    statActiveAlerts.textContent = data.stats.active_alerts_count;
                    statActiveAlerts.className = `text-lg font-black ${data.stats.active_alerts_count > 0 ? 'text-rose-600' : 'text-gray-800'}`;
                }
                if (statActiveAlertsBadge && typeof data.stats.active_alerts_count !== 'undefined') {
                    statActiveAlertsBadge.textContent = data.stats.active_alerts_count > 0 ? 'Perhatian' : 'Optimal';
                    statActiveAlertsBadge.className = `text-[10px] font-bold ${data.stats.active_alerts_count > 0 ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-gray-500 bg-gray-100 border-gray-200'} px-1.5 py-0.5 rounded-full border`;
                }
            }
        })
        .catch(err => console.error('Error fetching dashboard telemetry:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateAutopilotUI();
        // Ensure Full View starts closed
        toggleFullView(false);
        // Sync shadow button state on load
        if (current3DSettings.lighting.shadow === false) {
            toggle3DShadow(false);
        }
        // Default tampil Lantai 1 (3D) — switch sekaligus init viewer F1 + tampilkan kanvas
        switchDashboardFloor(1);
        setInterval(runSimulationStep, 50);
        setInterval(fetchData, 3000);
        // Preload model lantai lain di background → masuk CacheStorage,
        // jadi switch lantai berikutnya instan (tanpa unduh 14-18MB lagi)
        const preloadOther = () => {
            try {
                const otherUrl = Number(currentDashboardFloor) === 1 ? floor2ModelUrl : floor1ModelUrl;
                fetchGLBBufferWithCache(otherUrl, null).catch(() => {});
            } catch (e) { /* abaikan — preload opsional */ }
        };
        if ('requestIdleCallback' in window) requestIdleCallback(preloadOther, { timeout: 8000 });
        else setTimeout(preloadOther, 4000);
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            const v = activeStdViewer();
            if (v && typeof v.resize === 'function') {
                v.resize();
            }
            runSimulationStep();
        }
    });

    window.addEventListener('keydown', (e) => {
        if ((e.key === 'v' || e.key === 'V') && isFollowMode) {
            const tag = (e.target && e.target.tagName) || '';
            if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') {
                cycleFollowCameraMode();
                return;
            }
        }
        if (e.key === 'Escape') {
            const dispatchPanel = document.getElementById('fullview-dispatch-panel');
            if (dispatchPanel && !dispatchPanel.classList.contains('hidden')) {
                toggleFullViewDispatchPanel(false);
                return;
            }
            if (isFullViewMode) {
                toggleFullView(false);
            }
        }
    });
</script>
@endsection
