@extends('layouts.layout')

@section('title', 'ROBOPATH - Live Fleet Tracking')
@section('page_title', 'System Overview')
@section('page_subtitle', 'Real-time Multi-Floor Robot Tracking & System Metrics')

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
    
    /* Full View 1-Screen Fit (Zero Scroll) */
    .fullview-wrapper {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
    }
    .fullview-floor-box {
        position: relative;
        height: calc((100vh - 210px) / 2);
        max-height: 40vh;
        aspect-ratio: 16/9;
        max-width: 100%;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        border-radius: 0.75rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08), inset 0 0 0 1px rgba(0,0,0,0.05);
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
                <i class="fa-solid fa-lock text-rose-200"></i> Menunggu Supervisor/Admin
            </span>
            @endif
        </div>
    </div>

    <!-- Top Stat Strip (4 Compact Chips) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        <!-- Chip 1: Active Units -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Active Units</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800">{{ $activeRobotsCount }}/{{ $totalRobotsCount }}</span>
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-200">Online</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-[#3b4cb8] text-base shadow-sm shrink-0">
                <i class="fa-solid fa-robot"></i>
            </div>
        </div>

        <!-- Chip 2: Active Missions -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Active Missions</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800">{{ $activeDeliveriesCount }}</span>
                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full border border-blue-200">In Progress</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-base shadow-sm shrink-0">
                <i class="fa-solid fa-route"></i>
            </div>
        </div>

        <!-- Chip 3: Completed Today -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">Completed Today</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black text-gray-800">{{ $deliveriesTodayCount }}</span>
                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-200">{{ $successRate }}%</span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-base shadow-sm shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Chip 4: System Alerts -->
        <div class="bg-white border border-gray-200 px-4 py-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-0.5">System Alerts</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-lg font-black {{ $activeAlertsCount > 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ $activeAlertsCount }}</span>
                    <span class="text-[10px] font-bold {{ $activeAlertsCount > 0 ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-gray-500 bg-gray-100 border-gray-200' }} px-1.5 py-0.5 rounded-full border">
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
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col justify-between">
                <!-- Header with Floor Switch Tabs & Full View Button -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-[#3b4cb8]"></i> Live Floor Tracking
                        </h3>
                        <p class="text-xs text-gray-500">Real-time robot telemetry &amp; delivery route visualization</p>
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

                        <!-- Autopilot Button (Role-Gated) -->
                        <button id="autopilot-btn" onclick="toggleAutopilot()" 
                                class="px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 {{ (auth()->check() && auth()->user()->isAdmin()) ? 'bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300' : 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed opacity-70' }}"
                                {{ (auth()->check() && auth()->user()->isAdmin()) ? '' : 'disabled title="Akses Terbatas: Hanya Admin/Bot Control yang dapat mengontrol Autopilot"' }}>
                            <i class="fa-solid fa-wand-magic-sparkles" id="autopilot-icon"></i>
                            <span id="autopilot-text">Autopilot: OFF</span>
                            @if(!auth()->check() || !auth()->user()->isAdmin())
                            <i class="fa-solid fa-lock text-[10px] text-gray-400"></i>
                            @endif
                        </button>

                        @if(auth()->check() && auth()->user()->isAdmin())
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
                                    <i class="fa-solid fa-car-burst text-rose-500"></i> Tabrakan - Robot Alpha
                                </button>
                                <button onclick="simulateIssueAction(2, 'Low Battery')" class="w-full text-left px-3 py-2 text-xs hover:bg-rose-50 text-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-battery-empty text-amber-500"></i> Baterai Habis - Robot Beta
                                </button>
                                <button onclick="simulateIssueAction(3, 'Sensor Error')" class="w-full text-left px-3 py-2 text-xs hover:bg-rose-50 text-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-orange-500"></i> Sensor Rusak - Robot Gamma
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- Full View 2 Lantai Button -->
                        <button onclick="toggleFullView(true)" class="bg-[#3b4cb8] hover:bg-blue-700 text-white font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-md hover:shadow-lg transition duration-200">
                            <i class="fa-solid fa-expand"></i> Full View
                        </button>
                    </div>
                </div>

                <!-- Active Floor Title Badge -->
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-[#3b4cb8] flex items-center gap-1.5" id="std-floor-title">
                        <i class="fa-solid fa-building-user"></i> Lantai 1 (Ground Floor - Lobby, Office &amp; Receptionist)
                    </span>
                    <span class="text-[10px] bg-blue-100 text-blue-700 font-bold px-2.5 py-0.5 rounded-full border border-blue-200" id="std-floor-badge">
                        Showing Floor 1
                    </span>
                </div>

                <!-- Map Canvas Container (Proporsional 16:9) — kedua lantai murni 3D -->
                <div class="floor-map-card overflow-hidden shadow-inner border border-gray-200" id="std-map-container" style="background-color: #0f172a;">
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
                            <i class="fa-solid fa-share-nodes text-violet-400"></i> <span id="text-network">Jaringan: OFF</span>
                        </button>
                        <button id="btn-toggle-follow" onclick="toggleFollowMode()" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition" title="Kamera ikut robot yang difokuskan">
                            <i class="fa-solid fa-eye text-sky-400" id="icon-follow"></i> <span id="text-follow">Follow: OFF</span>
                        </button>
                        <!-- Room Labels Toggle -->
                        <button id="btn-toggle-3d-labels" onclick="toggle3DRoomLabels()" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-tag text-emerald-400" id="icon-3d-labels"></i> <span id="text-3d-labels">Label: ON</span>
                        </button>
                        <!-- Room Label Size Panel Button -->
                        <button onclick="toggle3DControlPanel('label-size')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-text-height text-indigo-400"></i> Ukuran Label
                        </button>
                        <!-- Camera Preset / Edit Button -->
                        <button onclick="toggle3DControlPanel('camera')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-video text-sky-400"></i> Kamera
                        </button>
                        <!-- Lighting Control Button -->
                        <button onclick="toggle3DControlPanel('light')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-sun text-amber-400"></i> Pencahayaan
                        </button>
                        <!-- Robot Position Control Button -->
                        <button onclick="toggle3DControlPanel('robot')" class="bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition" title="Gerak & rotasi robot 3D (pilih robot dulu)">
                            <i class="fa-solid fa-robot text-emerald-400"></i> Posisi Robot
                        </button>
                    </div>

                    <!-- Camera, Light & Label Size Adjustment Modal Panels -->
                    <!-- 0. Label Size & Model Scale Panel -->
                    <div id="panel-3d-label-size" class="hidden fixed top-16 right-4 z-50 w-72 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <span class="font-bold flex items-center gap-1.5 text-indigo-400">
                                <i class="fa-solid fa-text-height"></i> Ukuran Label & Model
                            </span>
                            <button onclick="toggle3DControlPanel('label-size')" class="text-gray-400 hover:text-white">
                                <i class="fa-solid fa-xmark"></i>
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
                            <button onclick="setLabelScaleQuick(0.6)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Kecil</button>
                            <button onclick="setLabelScaleQuick(1.0)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Normal</button>
                            <button onclick="setLabelScaleQuick(1.6)" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Besar</button>
                        </div>
                        <p id="label-scale-status" class="text-[10px] text-emerald-400 font-mono text-center pt-1"></p>
                    </div>
                    <!-- 1. Camera Panel -->
                    <div id="panel-3d-camera" class="hidden fixed top-16 right-4 z-50 w-72 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <span class="font-bold flex items-center gap-1.5 text-sky-400">
                                <i class="fa-solid fa-video"></i> Pengaturan Kamera
                            </span>
                            <button onclick="toggle3DControlPanel('camera')" class="text-gray-400 hover:text-white">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block mb-1">Preset Sudut Pandang</label>
                            <div class="grid grid-cols-3 gap-1.5">
                                <button onclick="setCameraPreset('iso')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Iso</button>
                                <button onclick="setCameraPreset('top')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Top (Atas)</button>
                                <button onclick="setCameraPreset('front')" class="bg-slate-800 hover:bg-slate-700 py-1.5 px-2 rounded-lg text-[11px] font-semibold text-center border border-white/5">Front</button>
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
                        <button onclick="reset3DCamera()" class="w-full bg-slate-800 hover:bg-slate-700 py-1.5 rounded-lg text-[11px] font-bold border border-white/10 text-gray-300">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reset Kamera Bawaan
                        </button>
                        <p id="camera-settings-status" class="text-[10px] text-sky-400 font-mono text-center pt-0.5"></p>
                    </div>

                    <!-- 2. Light Panel -->
                    <div id="panel-3d-light" class="hidden fixed top-16 right-4 z-50 w-72 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <span class="font-bold flex items-center gap-1.5 text-amber-400">
                                <i class="fa-solid fa-sun"></i> Pengaturan Cahaya
                            </span>
                            <button onclick="toggle3DControlPanel('light')" class="text-gray-400 hover:text-white">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-300">Ambient Light (Kecerahan Ruang)</span>
                                <span id="val-light-ambient" class="font-mono text-amber-400">{{ number_format($settings3D['lighting']['ambient'] ?? 1.4, 1) }}</span>
                            </div>
                            <input id="input-light-ambient" type="range" min="0.2" max="3.0" step="0.1" value="{{ $settings3D['lighting']['ambient'] ?? 1.4 }}" oninput="update3DLight('ambient', this.value)" class="w-full accent-amber-400">
                        </div>
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-300">Sun Light (Cahaya Utama / Shadow)</span>
                                <span id="val-light-sun" class="font-mono text-amber-400">{{ number_format($settings3D['lighting']['sun'] ?? 1.8, 1) }}</span>
                            </div>
                            <input id="input-light-sun" type="range" min="0.2" max="4.0" step="0.1" value="{{ $settings3D['lighting']['sun'] ?? 1.8 }}" oninput="update3DLight('sun', this.value)" class="w-full accent-amber-400">
                        </div>
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-300">Exposure (Paparan Lensa)</span>
                                <span id="val-light-exp" class="font-mono text-amber-400">{{ number_format($settings3D['lighting']['exposure'] ?? 1.0, 2) }}</span>
                            </div>
                            <input id="input-light-exp" type="range" min="0.3" max="2.5" step="0.05" value="{{ $settings3D['lighting']['exposure'] ?? 1.0 }}" oninput="update3DLight('exposure', this.value)" class="w-full accent-amber-400">
                        </div>
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-gray-300">Fill Sky Light (Aksen Biru)</span>
                                <span id="val-light-fill" class="font-mono text-amber-400">{{ number_format($settings3D['lighting']['fill'] ?? 0.8, 1) }}</span>
                            </div>
                            <input id="input-light-fill" type="range" min="0" max="2.0" step="0.1" value="{{ $settings3D['lighting']['fill'] ?? 0.8 }}" oninput="update3DLight('fill', this.value)" class="w-full accent-amber-400">
                        </div>
                        <p id="light-settings-status" class="text-[10px] text-amber-400 font-mono text-center pt-0.5"></p>
                    </div>

                    <!-- Robot Position Control Panel (D-pad + Rotasi + World XZ) -->
                    <div id="panel-3d-robot-control" class="hidden fixed top-16 right-4 z-50 w-72 max-h-[calc(100vh-100px)] overflow-y-auto bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-white/15 shadow-2xl space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <span class="font-bold flex items-center gap-1.5 text-emerald-400">
                                <i class="fa-solid fa-robot"></i> Kontrol Posisi Robot
                            </span>
                            <button onclick="toggle3DControlPanel('robot')" class="text-gray-400 hover:text-white">
                                <i class="fa-solid fa-xmark"></i>
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
                                <button onclick="move3DRobot(0, -0.3)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Depan (-Z)">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <div></div>
                                <button onclick="move3DRobot(-0.3, 0)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kiri (-X)">
                                    <i class="fa-solid fa-arrow-left"></i>
                                </button>
                                <button onclick="move3DRobot(0, 0.3)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Belakang (+Z)">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                                <button onclick="move3DRobot(0.3, 0)" class="bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kanan (+X)">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        <!-- D-Pad Vertikal (Y World) -->
                        <div>
                            <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1.5 text-[10px]">Gerak Vertikal (Y World)</label>
                            <div class="flex items-center justify-center gap-2 max-w-[180px] mx-auto">
                                <button onclick="move3DRobotY(0.3)" class="bg-sky-900/60 hover:bg-sky-800 border border-sky-400/30 text-sky-300 font-bold py-2 px-4 rounded-lg transition flex items-center justify-center" title="Naik (+Y)">
                                    <i class="fa-solid fa-arrow-up mr-1"></i> <span class="text-[10px]">Naik</span>
                                </button>
                                <button onclick="move3DRobotY(-0.3)" class="bg-sky-900/60 hover:bg-sky-800 border border-sky-400/30 text-sky-300 font-bold py-2 px-4 rounded-lg transition flex items-center justify-center" title="Turun (-Y)">
                                    <i class="fa-solid fa-arrow-down mr-1"></i> <span class="text-[10px]">Turun</span>
                                </button>
                            </div>
                        </div>
                        <!-- Rotasi Y -->
                        <div>
                            <label class="block font-bold text-gray-400 uppercase tracking-wider mb-1.5 text-[10px]">Rotasi Y</label>
                            <div class="flex items-center gap-2 mb-2">
                                <button onclick="rotate3DRobot(-15)" class="bg-indigo-900/60 hover:bg-indigo-800 border border-indigo-400/30 text-indigo-300 font-bold py-2 px-3 rounded-lg transition" title="Putar CCW -15°">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                                <button onclick="rotate3DRobot(15)" class="bg-indigo-900/60 hover:bg-indigo-800 border border-indigo-400/30 text-indigo-300 font-bold py-2 px-3 rounded-lg transition" title="Putar CW +15°">
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
                            <button onclick="reset3DRobotPosition()" class="flex-1 bg-slate-700 hover:bg-slate-600 border border-white/10 text-white font-bold py-2 rounded-xl transition text-[11px]">
                                <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset
                            </button>
                            <button onclick="save3DRobotToGraph()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl transition text-[11px]">
                                <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan
                            </button>
                        </div>
                        <p id="robot-3d-status" class="text-[10px] text-emerald-400 font-mono text-center pt-0.5"></p>
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
                        <span>Idle / Standby</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-sky-500 shadow-sm"></span>
                        <span>Delivering</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-500 shadow-sm"></span>
                        <span>Charging</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500 shadow-sm"></span>
                        <span>Maintenance</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Active Robots Roster (1/3 width, Matches Left Height) -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2 pb-3 border-b border-gray-200">
                <i class="fa-solid fa-robot text-[#3b4cb8]"></i> Active Robot Roster
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
                            {{ $robot->status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-2">
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Battery</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full" id="robot-battery-bar-{{ $robot->id }}" style="width: {{ $robot->battery_level }}%"></div>
                                </div>
                                <span class="font-mono font-bold text-gray-700 text-[11px]" id="robot-battery-text-{{ $robot->id }}">{{ $robot->battery_level }}%</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Location</span>
                            <span class="font-semibold text-gray-700 text-[11px]" id="robot-location-text-{{ $robot->id }}">
                                Blank Room 2 (Floor 1)
                            </span>
                        </div>
                    </div>

                    <div class="text-[11px] text-gray-500 pt-2 border-t border-gray-200/60" id="robot-task-text-{{ $robot->id }}">
                        Standby at home base
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Container 2: Full View 2 Lantai (Single Screen Overview) -->
<div id="fullview-mode" class="hidden space-y-3">
    <!-- Header Bar with Back Button & Legend -->
    <div class="bg-white border border-gray-200 px-5 py-3 rounded-2xl shadow-md flex items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3">
            <button onclick="toggleFullView(false)" class="bg-[#3b4cb8] hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2 shadow transition duration-200">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </button>
            <div class="hidden sm:block">
                <h3 class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group text-[#3b4cb8]"></i> 2-Floor Full View (Single Screen Overview)
                </h3>
            </div>
        </div>

        <!-- Robot Status Legend in Fullview -->
        <div class="flex items-center gap-3 text-[11px] font-semibold text-gray-600 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span>Idle</span></div>
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-500"></span><span>Delivering</span></div>
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-500"></span><span>Charging</span></div>
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span><span>Maintenance</span></div>
        </div>
    </div>

    <!-- Scaled Dual Floor Canvas (Both floors fit in 1 view) -->
    <div class="fullview-wrapper">
        <!-- Floor 2 (Atas) -->
        <div class="fullview-floor-box" id="fullview-container-f2" style="background-color: #0f172a;">
            <div class="absolute top-2 left-2 z-20 bg-black/75 backdrop-blur-sm text-white font-bold text-[10px] px-2.5 py-1 rounded-lg border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                <i class="fa-solid fa-building-user text-sky-400"></i> LANTAI 2 (Upper Floor - Direksi &amp; Meeting Rooms) [3D Mode]
            </div>
            <div id="fullview-3d-canvas-f2" class="absolute inset-0 z-0 pointer-events-auto"></div>
        </div>

        <!-- Floor 1 (Bawah) -->
        <div class="fullview-floor-box" id="fullview-container-f1" style="background-color: #0f172a;">
            <div class="absolute top-2 left-2 z-20 bg-black/75 backdrop-blur-sm text-white font-bold text-[10px] px-2.5 py-1 rounded-lg border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                <i class="fa-solid fa-building-user text-emerald-400"></i> LANTAI 1 (Ground Floor - Lobby &amp; Office) [3D Mode]
            </div>
            <div id="fullview-3d-canvas-f1" class="absolute inset-0 z-0 pointer-events-auto"></div>
        </div>
    </div>
</div>
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

    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);
    let activeAlerts = @json($activeAlerts ?? []);
    let isAutopilotEnabled = {{ Illuminate\Support\Facades\Cache::get('autopilot_enabled', false) ? 'true' : 'false' }};
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
            fill: parseFloat(settings3D?.lighting?.fill ?? 0.8)
        },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0),
        robot_scale: parseFloat(settings3D?.robot_scale ?? 0.6),
        node_scale: parseFloat(settings3D?.node_scale ?? 0.6),
        node_color: settings3D?.node_color ?? '#ff0000'
    };
    let settings3DSaveTimeout = null;
    let serverClientOffset = 0;
    let currentDashboardFloor = 1;
    let isFullViewMode = false;

    // 3D Three.js State, Cache & Loader
    const floor2ModelUrl = "{{ asset('models/Lantai_2-final.glb') }}";
    const floor1ModelUrl = "{{ asset('models/Denah_Lantai_1-opt.glb') }}";
    const robotModelUrl = "{{ asset('models/robot.glb') }}";
    const MODEL_CACHE_NAME = 'robopath-glb-cache-v1';
    let threeStd = null;
    let threeFull = null;
    let threeStdF1 = null;
    let threeFullF1 = null;
    let modelLoadedByFloor = { 1: false, 2: false };
    // Viewer 3D std yang sedang tampil sesuai lantai aktif
    function activeStdViewer(){ return Number(currentDashboardFloor) === 1 ? threeStdF1 : threeStd; }
    function allViewers(){ return [threeStd, threeStdF1, threeFull, threeFullF1].filter(v => !!v); }
    // Koordinat parkir avatar (% denah) per lantai — dekat Stairs masing-masing
    function parkCoordsForFloor(f){ return Number(f) === 1 ? { x: 72.1, y: 85.71 } : { x: 72.3, y: 66.3 }; }
    // Cari viewer pemilik holder (utk kontrol manual D-pad)
    function viewerOfHolder(holder){
        for (const v of [threeStd, threeStdF1, threeFull, threeFullF1]) {
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
    let showNetworkLines = false;
    let isEditingRobot3D = false;
    let robotTemplate = null;
    let robotTemplateReady = false;
    let robotTemplateLoading = false;
    let robotTemplateCallbacks = [];
    let robotTemplateTries = 0;
    let robotTemplateFailed = false;

    // Helper: Create high-DPI room label sprite (compact & sleek)
    function createRoomLabelSprite(text, isDest = true, isStairs = false) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = 384;
        canvas.height = 96;

        const bgFill = isStairs ? 'rgba(217, 119, 6, 0.92)' : (isDest ? 'rgba(15, 23, 42, 0.90)' : 'rgba(30, 41, 59, 0.85)');
        const borderColor = isStairs ? '#fbbf24' : (isDest ? '#ff0000' : '#94a3b8');
        const textColor = '#ffffff';

        // Rounded Rect pill
        const radius = 18;
        ctx.fillStyle = bgFill;
        ctx.strokeStyle = borderColor;
        ctx.lineWidth = 4;

        ctx.beginPath();
        ctx.roundRect(8, 8, canvas.width - 16, canvas.height - 16, radius);
        ctx.fill();
        ctx.stroke();

        // Icon indicator dot
        ctx.fillStyle = borderColor;
        ctx.beginPath();
        ctx.arc(32, canvas.height / 2, 7, 0, Math.PI * 2);
        ctx.fill();

        // Label Text
        ctx.fillStyle = textColor;
        ctx.font = 'bold 26px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';

        let cleanText = String(text).replace(/^[12]_/, '');
        if (cleanText.length > 20) {
            cleanText = cleanText.substring(0, 18) + '...';
        }
        ctx.fillText(cleanText, 52, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.minFilter = THREE.LinearFilter;
        texture.wrapS = THREE.ClampToEdgeWrapping;
        texture.wrapT = THREE.ClampToEdgeWrapping;

        const spriteMaterial = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthTest: false,
            depthWrite: false
        });

        const sprite = new THREE.Sprite(spriteMaterial);
        const baseW = 3.6 * labelScaleMultiplier;
        const baseH = 0.9 * labelScaleMultiplier;
        sprite.scale.set(baseW, baseH, 1);
        sprite.renderOrder = 999;
        return sprite;
    }

    // Helper: Load GLB with browser CacheStorage API (Instant reload)
    async function fetchGLBBufferWithCache(url, onProgress) {
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
        const camera = new THREE.PerspectiveCamera(initFov, width / height, 0.1, 1000);
        camera.position.set(0, 38, 48);

        const renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.outputEncoding = THREE.sRGBEncoding;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = parseFloat(current3DSettings.lighting.exposure ?? 1.0);
        renderer.shadowMap.enabled = true;
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
        sunLight.castShadow = true;
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
            const rSc = parseFloat(current3DSettings.robot_scale ?? 0.6);
            holder.scale.set(rSc, rSc, rSc);
            // placeholder box until glb ready
            const boxMesh=new THREE.Mesh(new THREE.BoxGeometry(0.35,0.5,0.35), new THREE.MeshStandardMaterial({color:getRobotColor(id)}));
            boxMesh.position.y=0.25; boxMesh.castShadow=true; boxMesh.receiveShadow=true;
            holder.add(boxMesh); holder.userData.boxMesh=boxMesh;
            // Panah kecil ke bawah (cone 3D) — indicator arah robot
            const robotCol=getRobotColor(id);
            const coneGeo=new THREE.ConeGeometry(0.14, 0.32, 8);
            const coneMat=new THREE.MeshStandardMaterial({color:robotCol, emissive:robotCol, emissiveIntensity:0.35, metalness:0.3, roughness:0.5});
            const cone=new THREE.Mesh(coneGeo, coneMat);
            cone.position.set(0, 1.0, 0); cone.rotation.x=Math.PI; // cone default up, flip to point down
            cone.castShadow=true;
            holder.add(cone); holder.userData.arrowCone=cone;
            // Badge nomor kecil di atas cone (sprite mini)
            const c=document.createElement('canvas'); c.width=128; c.height=48;
            const cx=c.getContext('2d');
            cx.fillStyle=robotCol; cx.strokeStyle='#ffffff'; cx.lineWidth=2;
            cx.beginPath(); cx.roundRect(4,4,120,40,10); cx.fill(); cx.stroke();
            cx.fillStyle='#ffffff'; cx.font='bold 20px sans-serif'; cx.textAlign='center'; cx.textBaseline='middle';
            const shortName=(robot.name||('Robot '+id)).replace('Robot ','R').split(' ')[0];
            cx.fillText('#'+id+' '+shortName,64,24);
            const tex=new THREE.CanvasTexture(c); tex.minFilter=THREE.LinearFilter;
            const spr=new THREE.Sprite(new THREE.SpriteMaterial({map:tex, transparent:true, depthTest:false, depthWrite:false}));
            spr.scale.set(0.6,0.22,1); spr.position.set(0,1.25,0); spr.renderOrder=999;
            holder.add(spr); holder.userData.nameSprite=spr;
            // Sprite status dinamis (badge mengambang) — update per tick di updateRobotStatusSprite()
            const stC=document.createElement('canvas'); stC.width=512; stC.height=72;
            const stTex=new THREE.CanvasTexture(stC); stTex.minFilter=THREE.LinearFilter;
            const stSpr=new THREE.Sprite(new THREE.SpriteMaterial({map:stTex, transparent:true, depthTest:false, depthWrite:false}));
            stSpr.scale.set(1.35,0.2,1); stSpr.position.set(0,1.78,0); stSpr.renderOrder=1000; stSpr.visible=false;
            stSpr.userData={canvas:stC, texture:stTex};
            holder.add(stSpr); holder.userData.statusSprite=stSpr;
            robotsGroup.add(holder); robotMeshes.set(id, holder);
            // ganti box placeholder dengan clone GLB (atau segera bila template sudah siap)
            const swapBoxForGlb=(tpl)=>{
                if(!tpl || !holder.parent) return;
                if(holder.userData.glbClone) return; // sudah swap
                const clone=tpl.clone(true);
                // tint: traverse and keep but add emissive hint
                const col=new THREE.Color(getRobotColor(id));
                clone.traverse(n=>{
                    if(n.isMesh && n.material){
                        n.material=n.material.clone();
                        // blend toward robot color lightly
                        if(n.material.color) n.material.color.lerp(col,0.25);
                        n.castShadow=true; n.receiveShadow=true;
                    }
                });
                clone.position.set(0,0,0);
                holder.remove(boxMesh); boxMesh.geometry.dispose();
                holder.add(clone); holder.userData.glbClone=clone;
            };
            // bila template sudah siap (holder dibuat belakangan) swap langsung; bila tidak, antre + retry otomatis
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
            if (loaderTitle) loaderTitle.textContent = `Memuat Model 3D Lantai ${floorNum}...`;
            if (loaderStatus) loaderStatus.textContent = `Mengunduh aset GLB (${floorNum === 1 ? '8' : '14'} MB)...`;
        }

        // Setup GLTF Loader with DRACO
        const gltfLoader = new THREE.GLTFLoader();
        if (typeof THREE.DRACOLoader !== 'undefined') {
            const dracoLoader = new THREE.DRACOLoader();
            dracoLoader.setDecoderPath("{{ asset('draco') }}/");
            gltfLoader.setDRACOLoader(dracoLoader);
        }

        // Load GLB using cached ArrayBuffer
        fetchGLBBufferWithCache(modelUrl, (loadedBytes, totalBytes, fromCache) => {
            if (loaderEl && Number(currentDashboardFloor) === floorNum) {
                if (fromCache) {
                    if (loaderBar) loaderBar.style.width = '90%';
                    if (loaderPct) loaderPct.textContent = '90%';
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
                // Stagger ketinggian per label agar tidak saling tumpuk di denah padat
                labelsGroup.clear();
                let labelIdx = 0;
                for (let id in locations) {
                    const loc = locations[id];
                    if (Number(loc.floor) !== floorNum) continue;
                    const isStairs = id.includes('Stairs');
                    if (!loc.is_destination && !isStairs) continue;
                    const sprite = createRoomLabelSprite(loc.name || id, loc.is_destination, isStairs);
                    sprite.position.copy(worldPosForLoc(loc, modelSize));
                    sprite.position.y = (modelSize.y || 0.22) + 0.32 + (labelIdx % 5) * 0.22;
                    labelIdx++;
                    labelsGroup.add(sprite);
                }
                // Build network lines (Lantai 2 adj) — garis ke semua ruangan
                try{ buildNetworkLines(); }catch(e){}

                // Eager-create semua robot mesh saat model lantai ready
                // Fix bug m0428: getOrCreateRobotMesh hanya dipanggil saat floorNum cocok di runSimulationStep,
                // tapi robot default Idle di lantai lain → mesh tidak pernah dibuat → robot 3D tidak muncul.
                // Solusi: buat semua mesh di sini, paksa visible=true & posisi di area Stairs lantai ini (parkir)
                // supaya monitoring mode langsung menampilkan avatar. Saat delivery update, runSimulationStep akan
                // override posisi sesuai koordinat aktual robot.
                try {
                    // area parkir dekat Stairs lantai ini — tersebar agar tidak tumpang tindih
                    const park = parkCoordsForFloor(floorNum);
                    const parkX = park.x, parkY = park.y;
                    robots.forEach((r, idx) => {
                        const holder = getOrCreateRobotMesh(r);
                        const offX = (idx - (robots.length - 1) / 2) * 2.0; // tersebar horizontal
                        const wp = worldPosForLoc({ x: parkX + offX, y: parkY }, modelSize);
                        holder.position.set(wp.x, 0.02, wp.z);
                        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
                        holder.userData.targetWp.copy(holder.position);
                        holder.rotation.y = -((r.rotation || 0) * Math.PI / 180);
                        holder.visible = true; // paksa tampil di lantai ini saat init
                    });
                    console.log('[Robopath] robotMeshes eager-created:', robotMeshes.size, `(parked near Stairs Lantai ${floorNum})`);
                } catch (e) { console.warn('[Robopath] eager-create robotMeshes fail', e); }

                const maxDim = Math.max(modelSize.x, modelSize.z);
                // FIX: pusatkan kamera ke tengah bangunan aktual (Box3 center),
                // bukan origin (0,0,0) — model GLB tidak selalu centered di origin.
                try {
                    const bbox = new THREE.Box3().setFromObject(loadedModel);
                    if (!bbox.isEmpty()) {
                        const c = bbox.getCenter(new THREE.Vector3());
                        defaultCamTarget.set(c.x, c.y * 0.5, c.z);
                    } else {
                        defaultCamTarget.set(0, (modelSize.y || 0.22) * 0.15, 0);
                    }
                } catch (e) { defaultCamTarget.set(0, (modelSize.y || 0.22) * 0.15, 0); }
                defaultCamPos.set(
                    defaultCamTarget.x,
                    defaultCamTarget.y + maxDim * 0.45,
                    defaultCamTarget.z + maxDim * 0.55
                );
                // apply saved camera dist if exists
                const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                const savedDist = 5 + (savedDistVal / 10) * 115;
                const dir0 = defaultCamPos.clone().sub(defaultCamTarget).normalize();
                camera.position.copy(defaultCamTarget).add(dir0.multiplyScalar(savedDist));
                controls.target.copy(defaultCamTarget);
                controls.update();

                // Hide loader with smooth fade — hanya jika lantai aktif masih lantai ini
                if (loaderEl) {
                    if (loaderBar) loaderBar.style.width = '100%';
                    if (loaderPct) loaderPct.textContent = '100%';
                    if (loaderStatus) loaderStatus.textContent = 'Model siap!';
                    const doHide = () => { if (Number(currentDashboardFloor) === floorNum) loaderEl.classList.add('hidden'); };
                    setTimeout(doHide, 200);
                    // jika sekarang tidak aktif, simpan hide untuk saat lantai diaktifkan
                    if (Number(currentDashboardFloor) !== floorNum) {
                        const tag = `hideLoader${floorNum}`;
                        loaderEl.dataset[tag] = '1';
                    }
                }

                if (typeof onLoadedCallback === 'function') onLoadedCallback();
            }, (err) => {
                console.error('[Robopath 3D] Error parsing GLB buffer:', err);
                if (loaderStatus && Number(currentDashboardFloor) === floorNum) loaderStatus.textContent = 'Gagal memproses model 3D! Coba pindah lantai dan kembali.';
            });
        })
        .catch(err => {
            console.error('[Robopath 3D] Error fetching model:', err);
            if (loaderStatus && Number(currentDashboardFloor) === floorNum) loaderStatus.textContent = 'Gagal mengunduh aset 3D! Coba refresh.';
        });

        // Raycast klik & geser (drag) avatar 3D
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        const dragPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), -0.02);
        const dragOffset = new THREE.Vector3();
        let dragTargetHolder = null;
        let isPointerDragging = false;
        let pointerStartClient = { x: 0, y: 0 };

        renderer.domElement.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
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
                if (holder) {
                    dragTargetHolder = holder;
                    isPointerDragging = false;
                    pointerStartClient = { x: e.clientX, y: e.clientY };
                    const hitPoint = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                    if (hitPoint) {
                        dragOffset.copy(holder.position).sub(hitPoint);
                        dragOffset.y = 0;
                    }
                }
            }
        });

        renderer.domElement.addEventListener('pointermove', (e) => {
            if (!dragTargetHolder) return;
            const distSq = Math.hypot(e.clientX - pointerStartClient.x, e.clientY - pointerStartClient.y);
            if (distSq > 4) {
                isPointerDragging = true;
                controls.enabled = false;
                isEditingRobot3D = true;
                const rect = renderer.domElement.getBoundingClientRect();
                mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
                mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
                raycaster.setFromCamera(mouse, camera);
                const hitPoint = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                if (hitPoint) {
                    const newPos = hitPoint.add(dragOffset);
                    dragTargetHolder.position.x = newPos.x;
                    dragTargetHolder.position.z = newPos.z;
                    if (!dragTargetHolder.userData.targetWp) dragTargetHolder.userData.targetWp = new THREE.Vector3();
                    dragTargetHolder.userData.targetWp.copy(dragTargetHolder.position);

                    const rid = Number(dragTargetHolder.userData.robotId);
                    focusedRobotId = rid;
                    const r = robots.find(x => Number(x.id) === rid);
                    if (r && modelSize && modelSize.x > 0.1) {
                        r.customPosition = true;
                        r.returnMission = null;
                        r.isReturning = false;
                        r.needsReturnToBase = false;
                        const pct = locFromWorld(newPos.x, newPos.z, modelSize);
                        r.current_x = parseFloat(pct.x.toFixed(2));
                        r.current_y = parseFloat(pct.y.toFixed(2));
                    }
                    updateSelectedRobotUI();
                    const st = document.getElementById('robot-3d-status');
                    if (st) st.textContent = `Posisi: X=${dragTargetHolder.position.x.toFixed(2)} Z=${dragTargetHolder.position.z.toFixed(2)}`;
                }
            }
        });

        const endDragOrClick = (e) => {
            if (dragTargetHolder) {
                controls.enabled = true;
                const rid = Number(dragTargetHolder.userData.robotId);
                if (!isPointerDragging) {
                    focusRobotOnMap(rid);
                } else {
                    focusedRobotId = rid;
                    updateFocusBadge();
                    updateSelectedRobotUI();
                }
                dragTargetHolder = null;
                isPointerDragging = false;
            }
        };
        renderer.domElement.addEventListener('pointerup', endDragOrClick);
        renderer.domElement.addEventListener('pointerleave', endDragOrClick);

        let animationFrameId = null;
        function animate() {
            animationFrameId = requestAnimationFrame(animate);
            // Fase 2.1: lerp per-frame posisi robot 3D menuju target sim step -> gerak halus, bukan teleport
            robotMeshes.forEach(holder => {
                const tgt = holder.userData.targetWp;
                if (tgt) holder.position.lerp(tgt, Math.min(1, 0.25));
            });
            // Follow mode: kamera ngikut robot yang difokuskan (Lantai 2 only)
            if(isFollowMode && focusedRobotId!=null && robotMeshes.has(Number(focusedRobotId))){
                const holder = robotMeshes.get(Number(focusedRobotId));
                const tgt = holder.position.clone(); tgt.y += 0.3;
                controls.target.lerp(tgt, 0.08);
                // keep distance roughly savedDist behind current dir
                const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                const savedDist = 5 + (savedDistVal/10)*115;
                const dir = camera.position.clone().sub(controls.target).normalize();
                if(dir.length()<0.01) dir.set(0.35,0.55,0.75).normalize();
                const desired = controls.target.clone().add(dir.multiplyScalar(savedDist));
                camera.position.lerp(desired, 0.08);
            }
            controls.update();
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
    function snapRobot3D(holder, worldPct, sz) {
        const wp = worldPosForLoc({ x: worldPct.x, y: worldPct.y }, sz);
        holder.position.set(wp.x, 0.02, wp.z);
        if(!holder.userData.targetWp) holder.userData.targetWp = new THREE.Vector3();
        holder.userData.targetWp.copy(holder.position);
        return wp;
    }
    // Perbarui badge status 3D di atas robot (idle / mengantar → tujuan / charging / maintenance / masalah)
    function updateRobotStatusSprite(holder, robot, delivery, hasIssue, destName) {
        const spr = holder.userData.statusSprite;
        if (!spr) return;
        const c = spr.userData.canvas, tex = spr.userData.texture;
        const ctx = c.getContext('2d');
        ctx.clearRect(0, 0, c.width, c.height);
        let label = '● IDLE', bg = 'rgba(16,185,129,0.94)';
        if (hasIssue) {
            const issue = robot.activeAlert ? String(robot.activeAlert.issue_type).toUpperCase() : (robot.battery_level <= 10 ? 'LOW BATTERY' : 'MAINTENANCE');
            label = '⚠ ' + issue; bg = 'rgba(225,29,72,0.94)';
        } else if (robot.status === 'Delivering' && delivery) {
            label = '▶ MENGANTAR → ' + (destName || '?'); bg = 'rgba(59,130,246,0.94)';
        } else if (robot.status === 'Charging') {
            label = '⚡ CHARGING'; bg = 'rgba(249,115,22,0.94)';
        } else if (robot.status === 'Maintenance') {
            label = '🔧 MAINTENANCE'; bg = 'rgba(225,29,72,0.94)';
        }
        ctx.font = 'bold 30px "Segoe UI", system-ui, sans-serif';
        const wRaw = ctx.measureText(label).width;
        const pad = 26, h = 50, tw = Math.min(c.width - 16, wRaw + pad * 2);
        const x = (c.width - tw) / 2, y = (c.height - h) / 2, r = h / 2;
        ctx.beginPath();
        ctx.moveTo(x + r, y); ctx.arcTo(x + tw, y, x + tw, y + h, r); ctx.arcTo(x + tw, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + tw, y, r);
        ctx.closePath(); ctx.fillStyle = bg; ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.85)'; ctx.lineWidth = 3; ctx.stroke();
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(label, c.width / 2, c.height / 2 + 2);
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
                snapRobot3D(holder, coords, sz);
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

        allViewers().forEach(viewer => {
            if (viewer && viewer.labelsGroup) viewer.labelsGroup.visible = show3DRoomLabels;
        });

        if (show3DRoomLabels) {
            if (icon) icon.className = 'fa-solid fa-tag text-emerald-400';
            if (text) text.textContent = 'Label: ON';
            if (btn) btn.className = 'bg-slate-900/80 hover:bg-slate-900 backdrop-blur-md text-white px-3 py-1.5 rounded-xl text-xs font-bold border border-white/10 shadow-lg flex items-center gap-1.5 transition';
        } else {
            if (icon) icon.className = 'fa-solid fa-tag text-gray-500';
            if (text) text.textContent = 'Label: OFF';
            if (btn) btn.className = 'bg-slate-900/40 hover:bg-slate-900/80 backdrop-blur-md text-gray-400 px-3 py-1.5 rounded-xl text-xs font-bold border border-white/5 shadow-lg flex items-center gap-1.5 transition';
        }
    }

    // Toggle camera / light / label-size / robot interactive toolbars
    function toggle3DControlPanel(panelName) {
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
        if(txt) txt.textContent='Fokus: '+(r? r.name : ('Robot '+focusedRobotId))+(isFollowMode?' • Follow':'');
    }
    function updateFollowButton(){
        const t=document.getElementById('text-follow');
        const ic=document.getElementById('icon-follow');
        const btn=document.getElementById('btn-toggle-follow');
        if(t) t.textContent = isFollowMode ? 'Follow: ON' : 'Follow: OFF';
        if(ic) ic.className = isFollowMode ? 'fa-solid fa-eye text-emerald-400 animate-pulse' : 'fa-solid fa-eye text-sky-400';
        if(btn) btn.classList.toggle('ring-2', isFollowMode);
        if(btn) btn.classList.toggle('ring-emerald-400', isFollowMode);
        updateFocusBadge();
    }
    function updateNetworkButton(){
        const t=document.getElementById('text-network');
        const btn=document.getElementById('btn-toggle-network');
        if(t) t.textContent = showNetworkLines ? 'Jaringan: ON' : 'Jaringan: OFF';
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
        const order=[activeStdViewer(), Number(currentDashboardFloor)===1?threeStd:threeStdF1, threeFull, threeFullF1];
        for(const v of order){
            if(v && v.robotMeshes && v.robotMeshes.has(Number(focusedRobotId))) return v.robotMeshes.get(Number(focusedRobotId));
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
        updateFollowButton();
        if(isFollowMode && focusedRobotId!=null) focusRobotOnMap(focusedRobotId, true);
    }
    function focusRobotOnMap(robotId, isFollowClick=false){
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
        if(Number(currentDashboardFloor)!==robotFloor){
            switchDashboardFloor(robotFloor);
            // wait for viewer then focus
            setTimeout(()=>focusRobotOnMap(rid, isFollowClick), 250);
            return;
        }
        const stdViewer = activeStdViewer();
        if(!stdViewer || !stdViewer.robotMeshes){
            // viewer belum ready, retry
            setTimeout(()=>focusRobotOnMap(rid, isFollowClick), 300);
            return;
        }
        // ensure mesh exists (create if needed) then snap/follow
        let holder=null;
        try{ holder=stdViewer.getOrCreateRobotMesh(robot); }catch(e){}
        if(!holder) return;
        // paksa visible & ambil posisi aktual (parkir atau delivery)
        holder.visible=true;
        // if follow, animate loop will lerp; if click once, lerp target instantly + keep offset
        const tgt = holder.position.clone(); tgt.y += 0.3;
        const cam = stdViewer.camera; const ctrl = stdViewer.controls;
        const savedDistVal=parseFloat(current3DSettings.camera.dist ?? 5.0);
        // Clamp jarak fokus relatif ukuran model — setting tersimpan bisa terlalu jauh/dekat utk lantai ini
        const fSize = stdViewer.getModelSize();
        const fMaxDim = Math.max(fSize.x || 30, fSize.z || 30);
        let savedDist=5+(savedDistVal/10)*115;
        savedDist = Math.min(Math.max(savedDist, fMaxDim * 0.5), fMaxDim * 2.2);
        const dir = cam.position.clone().sub(ctrl.target).normalize();
        if(dir.length()<0.01) dir.set(0.35,0.55,0.75).normalize();
        const newTarget = tgt.clone();
        const newPos = newTarget.clone().add(dir.multiplyScalar(savedDist));
        // smooth snap 450ms
        const startPos=cam.position.clone(); const startTarget=ctrl.target.clone();
        const t0=performance.now(); const dur=450;
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
        [threeStd, threeFull, threeStdF1, threeFullF1].forEach(viewer => {
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
        [threeStd, threeFull, threeStdF1, threeFullF1].forEach(viewer=>{
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
        current3DSettings.camera.dist = 5.0;
        current3DSettings.camera.fov = 5.0;
        current3DSettings.camera.preset = 'iso';
        document.getElementById('val-cam-dist').textContent = '5.0';
        document.getElementById('val-cam-fov').textContent = '5.0';
        const distInput = document.getElementById('input-cam-dist');
        const fovInput = document.getElementById('input-cam-fov');
        if (distInput) distInput.value = '5.0';
        if (fovInput) fovInput.value = '5.0';
        save3DSettingsDebounced('camera-settings-status');
    }

    // Light Adjustments — langsung simpan ke graph.json (berlaku ke semua viewer, setting dishare)
    function update3DLight(type, val) {
        const v = activeStdViewer();
        if (!v) return;
        const num = parseFloat(val);
        if (type === 'ambient') {
            current3DSettings.lighting.ambient = num;
            document.getElementById('val-light-ambient').textContent = num.toFixed(1);
        } else if (type === 'sun') {
            current3DSettings.lighting.sun = num;
            document.getElementById('val-light-sun').textContent = num.toFixed(1);
        } else if (type === 'exposure') {
            current3DSettings.lighting.exposure = num;
            document.getElementById('val-light-exp').textContent = num.toFixed(2);
        } else if (type === 'fill') {
            current3DSettings.lighting.fill = num;
            document.getElementById('val-light-fill').textContent = num.toFixed(1);
        }
        // sync semua viewer (std + full, lantai 1 + 2)
        allViewers().forEach(vw => {
            if (!vw || !vw.lights) return;
            if (type === 'ambient') vw.lights.ambient.intensity = num;
            if (type === 'sun') vw.lights.sun.intensity = num;
            if (type === 'fill') vw.lights.fill.intensity = num;
            if (type === 'exposure') vw.renderer.toneMappingExposure = num;
        });
        save3DSettingsDebounced('light-settings-status');
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
        currentDashboardFloor = floorNum;
        
        const tabF1 = document.getElementById('std-tab-f1');
        const tabF2 = document.getElementById('std-tab-f2');
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
            tabF1.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            tabF2.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            title.innerHTML = '<i class="fa-solid fa-cube text-emerald-400"></i> Lantai 1 (Ground Floor - Lobby, Office & Receptionist) <span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30 ml-1">3D</span>';
            badge.textContent = 'Showing Floor 1 (3D)';
            if (canvasContainer3D) canvasContainer3D.classList.add('hidden');
            if (canvasContainer3DF1) {
                canvasContainer3DF1.classList.remove('hidden');
                // FIX loader blank + stuck retry: tampilkan loader sinkron + handle hide tertunda
                try {
                    const _ld = document.getElementById('std-3d-loader');
                    const _ldT = document.getElementById('std-3d-loader-title');
                    const _ldS = document.getElementById('std-3d-loader-status');
                    const _ldB = document.getElementById('std-3d-loader-bar');
                    const _ldP = document.getElementById('std-3d-loader-pct');
                    if (_ld) {
                        if (modelLoadedByFloor[1]) {
                            if (_ld.dataset.hideLoader1) delete _ld.dataset.hideLoader1;
                            _ld.classList.add('hidden');
                        } else {
                            _ld.classList.remove('hidden');
                            if (_ldT) _ldT.textContent = 'Memuat Model 3D Lantai 1...';
                            if (_ldS) _ldS.textContent = 'Mengunduh aset GLB (8 MB)...';
                            if (_ldB) _ldB.style.width = '5%';
                            if (_ldP) _ldP.textContent = '5%';
                        }
                    }
                } catch(e){}
                setTimeout(() => {
                    if (!threeStdF1 || !modelLoadedByFloor[1]) {
                        if (threeStdF1 && !modelLoadedByFloor[1]) {
                            try { canvasContainer3DF1.innerHTML=''; } catch(e){}
                            try { if(threeStdF1.renderer) threeStdF1.renderer.dispose(); } catch(e){}
                            threeStdF1 = null;
                        }
                        threeStdF1 = initThreeViewer('std-3d-canvas-f1', 1);
                    } else {
                        threeStdF1.resize();
                    }
                }, 50);
            }
        } else {
            tabF2.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            tabF1.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            container.style.backgroundImage = 'none';
            container.style.backgroundColor = '#0f172a';
            title.innerHTML = '<i class="fa-solid fa-cube text-sky-400"></i> Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms) <span class="text-[10px] bg-sky-500/20 text-sky-400 px-2 py-0.5 rounded-full border border-sky-500/30 ml-1">3D</span>';
            badge.textContent = 'Showing Floor 2 (3D)';
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
                            if (_ld2.dataset.hideLoader2) delete _ld2.dataset.hideLoader2;
                            _ld2.classList.add('hidden');
                        } else {
                            _ld2.classList.remove('hidden');
                            if (_ldT2) _ldT2.textContent = 'Memuat Model 3D Lantai 2...';
                            if (_ldS2) _ldS2.textContent = 'Mengunduh aset GLB (14 MB)...';
                            if (_ldB2) _ldB2.style.width = '5%';
                            if (_ldP2) _ldP2.textContent = '5%';
                        }
                    }
                } catch(e){}
                setTimeout(() => {
                    if (!threeStd || !modelLoadedByFloor[2]) {
                        if (threeStd && !modelLoadedByFloor[2]) {
                            try { canvasContainer3D.innerHTML=''; } catch(e){}
                            try { if(threeStd.renderer) threeStd.renderer.dispose(); } catch(e){}
                            threeStd = null;
                        }
                        threeStd = initThreeViewer('std-3d-canvas-container', 2);
                    } else {
                        threeStd.resize();
                    }
                }, 50);
            }
            if (hint3D) hint3D.classList.remove('hidden');
            if (toolbar3D) toolbar3D.classList.remove('hidden');
        }

        runSimulationStep();
    }

    function toggleFullView(showFull) {
        isFullViewMode = showFull;
        const stdView = document.getElementById('standard-view');
        const fullView = document.getElementById('fullview-mode');
        const mainScroll = document.querySelector('main > div');

        if (showFull) {
            stdView.classList.add('hidden');
            fullView.classList.remove('hidden');
            if (mainScroll) mainScroll.scrollTop = 0;
            window.scrollTo(0, 0);
            setTimeout(() => {
                if (!threeFull) {
                    threeFull = initThreeViewer('fullview-3d-canvas-f2', 2);
                } else {
                    threeFull.resize();
                }
                if (!threeFullF1) {
                    threeFullF1 = initThreeViewer('fullview-3d-canvas-f1', 1);
                } else {
                    threeFullF1.resize();
                }
            }, 100);
        } else {
            fullView.classList.add('hidden');
            stdView.classList.remove('hidden');
            if (mainScroll) mainScroll.scrollTop = 0;
            window.scrollTo(0, 0);
        }

        setTimeout(runSimulationStep, 50);
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
        return closestId || (Number(floor) === 2 ? '2_Stairs' : '1_N7');
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

    function planRouteBetween(fromId, toId) {
        if (!locations[fromId] || !locations[toId]) return [];
        const f1 = Number(locations[fromId].floor || 1);
        const f2 = Number(locations[toId].floor || 1);
        
        if (f1 === f2) {
            const p = findPathAStar(fromId, toId);
            return [{ type: 'travel', floor: f1, path: p }];
        } else {
            const stairsFrom = f1 === 1 ? '1_Stairs' : '2_Stairs';
            const stairsTo = f2 === 1 ? '1_Stairs' : '2_Stairs';
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
        const currentLocId = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1);
        const targetId = '1_N7';
        if (!currentLocId || currentLocId === targetId) return null;

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

        let totalTravelSegments = 0;
        consolidatedStages.forEach(st => {
            if (st.type === 'travel') totalTravelSegments += Math.max(1, (st.path?.length || 1) - 1);
        });

        const baseTravelTimeMs = 24000;
        let accumulatedMs = 0;
        consolidatedStages.forEach(st => {
            st.startMs = accumulatedMs;
            if (st.type === 'stairs') {
                st.durationMs = 5500;
            } else {
                const segCount = Math.max(1, (st.path?.length || 1) - 1);
                st.durationMs = Math.max(5000, Math.round(baseTravelTimeMs * (segCount / Math.max(1, totalTravelSegments))));
            }
            accumulatedMs += st.durationMs;
        });

        return {
            originId: currentLocId,
            destId: targetId,
            stages: consolidatedStages,
            totalDurationMs: accumulatedMs,
            startedAt: now.getTime() + 1500
        };
    }

    function syncRobotBaseLocation(robotId, bx, by) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                current_x: bx,
                current_y: by
            })
        }).catch(err => console.error('Error syncing base station location:', err));
    }

    function getDeliveryMission(delivery, robot) {
        if (delivery._cachedMission) {
            return delivery._cachedMission;
        }

        const startNodeId = getNode(delivery.start_location);
        const destNodeId = getNode(delivery.destination_location);
        
        let originNodeId = getNode(delivery.origin_location);
        if (!originNodeId && robot && robot.current_x && robot.current_y) {
            originNodeId = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1);
        }
        if (!originNodeId || !locations[originNodeId]) {
            originNodeId = '1_N7';
        }

        const validStart = (startNodeId && locations[startNodeId]) ? startNodeId : '1_Waiting Room';
        const validDest = (destNodeId && locations[destNodeId]) ? destNodeId : '2_Ruang Direktur';

        const pickupStage = {
            type: 'pickup',
            nodeId: validStart,
            floor: locations[validStart]?.floor || 1,
            durationMs: 2500
        };

        const dropoffStage = {
            type: 'dropoff',
            nodeId: validDest,
            floor: locations[validDest]?.floor || 1,
            durationMs: 2500
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

        let totalTravelSegments = 0;
        consolidatedStages.forEach(st => {
            if (st.type === 'travel') totalTravelSegments += Math.max(1, (st.path?.length || 1) - 1);
        });

        const baseTravelTimeMs = 26000;
        let accumulatedMs = 0;
        consolidatedStages.forEach(st => {
            st.startMs = accumulatedMs;
            if (st.type === 'stairs') {
                st.durationMs = 5500;
            } else if (st.type === 'pickup' || st.type === 'dropoff') {
                st.durationMs = 2500;
            } else {
                const segCount = Math.max(1, (st.path?.length || 1) - 1);
                st.durationMs = Math.max(6000, Math.round(baseTravelTimeMs * (segCount / Math.max(1, totalTravelSegments))));
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

    // Gambar garis path delivery di scene 3D (semua viewer yg ada — std + full, L1 + L2)
    function drawPath3D(viewers, remainingPts, robotColor, opacity, dashSize, gapSize, yOff){
        viewers.forEach(v => {
            if (!v || !v.activePathGroup) return;
            const sz = v.getModelSize ? v.getModelSize() : null;
            if (!sz || sz.x < 0.1) return;
            const y = 0.035; // Menempel langsung di atas lantai 3D
            const pts3 = remainingPts.map(pt => { const vv = worldPosForLoc(pt, sz); vv.y = y; return vv; });
            if (pts3.length < 2) return;
            const geo = new THREE.BufferGeometry().setFromPoints(pts3);
            const mat = new THREE.LineDashedMaterial({ color: new THREE.Color(robotColor), transparent: true, opacity: opacity, dashSize: dashSize, gapSize: gapSize });
            const line = new THREE.Line(geo, mat); line.computeLineDistances();
            v.activePathGroup.add(line);
        });
    }

    function drawRobotPaths() {
        // Clear 3D active paths semua viewer (Lantai 1 + 2, std + fullview)
        allViewers().forEach(viewer => { if(viewer && viewer.activePathGroup) viewer.activePathGroup.clear(); });
        
        const now = new Date(new Date().getTime() + serverClientOffset);

        // 1. Draw paths for active deliveries (with past segment trimming)
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || (robot.status !== 'Delivering' && delivery.status !== 'Pending')) return;
            
            const mission = getDeliveryMission(delivery, robot);
            if (!mission || !mission.stages) return;

            const robotColor = getRobotColor(robot.id);
            const startedTime = parseServerDate(delivery.started_at);
            const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());

            mission.stages.forEach(st => {
                if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                
                const stageEndMs = st.startMs + st.durationMs;
                if (elapsedMs >= stageEndMs && delivery.status !== 'Pending') return;

                const isCurrentActive = (elapsedMs >= st.startMs && elapsedMs < stageEndMs) || delivery.status === 'Pending';
                const isFutureStage = (elapsedMs < st.startMs);

                const remainingPts = [];
                if (isCurrentActive) {
                    remainingPts.push({ x: robot.current_x, y: robot.current_y });
                    const segIdx = robot.currentSegIdx || 0;
                    for (let i = segIdx + 1; i < st.path.length; i++) if (locations[st.path[i]]) remainingPts.push(locations[st.path[i]]);
                } else if (isFutureStage) {
                    st.path.forEach(nodeId => { if (locations[nodeId]) remainingPts.push(locations[nodeId]); });
                } else return;

                if (remainingPts.length < 2) return;

                // Garis aktif 3D per lantai (std + fullview)
                const isPending = delivery.status === 'Pending';
                if (Number(st.floor) === 2) {
                    drawPath3D([threeStd, threeFull], remainingPts, robotColor, isPending ? 0.52 : 0.92, 0.38, 0.22, 0.08);
                    return;
                }
                drawPath3D([threeStdF1, threeFullF1], remainingPts, robotColor, isPending ? 0.52 : 0.92, 0.38, 0.22, 0.08);
            });
        });

        // 2. Draw return paths for returning idle robots (with past segment trimming)
        robots.forEach(robot => {
            if (robot.status === 'Idle' && robot.returnMission && robot.returnMission.stages) {
                const robotColor = getRobotColor(robot.id);
                const elapsedMs = now.getTime() - robot.returnMission.startedAt;

                robot.returnMission.stages.forEach(st => {
                    if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                    const stageEndMs = st.startMs + st.durationMs;

                    if (elapsedMs >= stageEndMs) return;

                    const isCurrentActive = (elapsedMs >= st.startMs && elapsedMs < stageEndMs);
                    const isFutureStage = (elapsedMs < st.startMs);

                    const remainingPts = [];
                    if (isCurrentActive) {
                        remainingPts.push({ x: robot.current_x, y: robot.current_y });
                        const segIdx = robot.returnSegIdx || 0;
                        for (let i = segIdx + 1; i < st.path.length; i++) {
                            if (locations[st.path[i]]) remainingPts.push(locations[st.path[i]]);
                        }
                    } else if (isFutureStage) {
                        st.path.forEach(nodeId => {
                            if (locations[nodeId]) remainingPts.push(locations[nodeId]);
                        });
                    } else {
                        return;
                    }

                    if (remainingPts.length < 2) return;

                    // Return path 3D per lantai (std + fullview)
                    if (Number(st.floor) === 2) {
                        drawPath3D([threeStd, threeFull], remainingPts, robotColor, 0.78, 0.32, 0.20, 0.07);
                        return;
                    }
                    drawPath3D([threeStdF1, threeFullF1], remainingPts, robotColor, 0.78, 0.32, 0.20, 0.07);
                });
            }
        });
        // ensure network lines visible state updated after path redraw
        allViewers().forEach(viewer => { if(viewer && viewer.networkGroup) viewer.networkGroup.visible = showNetworkLines; });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);

        // (2D overlay/SVG dihapus — kedua lantai murni 3D; avatar & path digambar di scene)
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            robot._activeDelivery = delivery || null; // Fase 2: referensi utk badge status 3D (satu sumber data gerak)
            
            // Check if robot has active issue / alert
            const robotAlert = activeAlerts.find(a => Number(a.robot_id) === Number(robot.id) && a.status === 'Active');
            const hasIssue = !!robotAlert || robot.status === 'Maintenance' || (robot.status === 'Charging' && robot.battery_level <= 10) || (delivery && delivery.status === 'Pending');
            robot.hasIssue = hasIssue;
            robot.activeAlert = robotAlert;

            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            let taskText = 'Standby at base station (N7)';
            let currentLocName = resolveLocationName(coords.x, coords.y, floorNum);

            if (hasIssue) {
                const issueName = robotAlert ? robotAlert.issue_type : (robot.battery_level <= 10 ? 'Baterai Habis' : 'Maintenance');
                if (delivery) {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> MASALAH: ${issueName} - Pengantaran Mandek!</span>`;
                    currentLocName = `Mandek di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                } else {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> MASALAH: ${issueName} (Cepat Benerin!)</span>`;
                    currentLocName = `Tertahan di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                }
            } else if (robot.status === 'Charging') {
                taskText = '<i class="fa-solid fa-bolt text-orange-500 mr-1"></i> Battery charging';
            } else if (robot.status === 'Maintenance') {
                taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Maintenance required</span>';
            }
            
            if (robot.status === 'Delivering' && delivery && !hasIssue) {
                robot.returnMission = null;
                robot.isReturning = false;
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
                            taskText = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-dolly animate-bounce mr-1"></i> Menyerahkan ${delivery.item_name} di ${locations[mission.destId]?.name || delivery.destination_location} (${remainingSec}s)...</span>`;
                            currentLocName = locations[mission.destId]?.name || delivery.destination_location;
                            robot.currentSegIdx = 0;
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            if (path.length >= 2) {
                                const floatIdx = stageRatio * (path.length - 1);
                                const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
                                robot.currentSegIdx = currentSegIdx;
                                if (p1 && p2) {
                                    coords = interpolate(p1, p2, ratioInSegment);
                                    const dx = p2.x - p1.x;
                                    const dy = p2.y - p1.y;
                                    if (dx !== 0 || dy !== 0) {
                                        angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                    }
                                }
                            } else if (path.length === 1 && locations[path[0]]) {
                                coords = locations[path[0]];
                                robot.currentSegIdx = 0;
                            }
                            const isHeadingToPickup = mission.pickupStartMs && activeStage.startMs < mission.pickupStartMs;
                            if (isHeadingToPickup) {
                                taskText = `Menuju titik ambil: ${locations[mission.startId]?.name || delivery.start_location}`;
                            } else {
                                taskText = `Mengantar ${delivery.item_name} ke ${locations[mission.destId]?.name || delivery.destination_location}`;
                            }
                            currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                        }
                    }

                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.floor = floorNum;
                    robot.rotation = angle;
                }
            } else if (robot.status === 'Idle' && !hasIssue) {
                const isEditingThis = isEditingRobot3D && Number(robot.id) === Number(focusedRobotId);
                const baseLoc = locations['1_N7'] || { x: 80.6, y: 68.48, floor: 1 };
                const distToBase = (Number(robot.floor || 1) === 1) 
                    ? Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) 
                    : 999;

                if (isEditingThis) {
                    robot.returnMission = null;
                    robot.isReturning = false;
                    robot.needsReturnToBase = false;
                    robot.customPosition = true;
                    coords = { x: (robot.current_x !== undefined ? robot.current_x : baseLoc.x), y: (robot.current_y !== undefined ? robot.current_y : baseLoc.y) };
                    floorNum = robot.floor || currentDashboardFloor;
                    taskText = 'Mode Edit Posisi Robot (Geser 3D / D-Pad)';
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else if (!isAutopilotEnabled && robot.needsReturnToBase && !robot.customPosition && distToBase > 0.8) {
                    if (!robot.returnMission) {
                        robot.returnMission = buildReturnMission(robot, now);
                    }
                }

                if (!isEditingThis && robot.returnMission) {
                    robot.isReturning = true;
                    const mission = robot.returnMission;
                    const elapsedMs = now.getTime() - mission.startedAt;
                    let angle = 0;

                    if (elapsedMs < 0) {
                        taskText = `<span class="text-indigo-600 font-bold"><i class="fa-solid fa-box-open mr-1"></i> Selesai antar, persiapan balik ke N7...</span>`;
                        coords = { x: robot.current_x, y: robot.current_y };
                        floorNum = robot.floor || 1;
                    } else if (elapsedMs >= mission.totalDurationMs) {
                        coords = { x: baseLoc.x, y: baseLoc.y };
                        floorNum = 1;
                        robot.current_x = baseLoc.x;
                        robot.current_y = baseLoc.y;
                        robot.floor = 1;
                        robot.returnMission = null;
                        robot.isReturning = false;
                        robot.needsReturnToBase = false;
                        taskText = 'Standby at base station (N7)';
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y);
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
                            taskText = `<span class="text-amber-600 font-bold"><i class="fa-solid fa-stairs animate-bounce mr-1"></i> Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec}s)...</span>`;
                            robot.returnSegIdx = 0;
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            if (path.length >= 2) {
                                const floatIdx = stageRatio * (path.length - 1);
                                const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
                                robot.returnSegIdx = currentSegIdx;
                                if (p1 && p2) {
                                    coords = interpolate(p1, p2, ratioInSegment);
                                    const dx = p2.x - p1.x;
                                    const dy = p2.y - p1.y;
                                    if (dx !== 0 || dy !== 0) {
                                        angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                    }
                                }
                            } else if (path.length === 1 && locations[path[0]]) {
                                coords = locations[path[0]];
                                robot.returnSegIdx = 0;
                            }
                            taskText = `<span class="text-indigo-600 font-bold"><i class="fa-solid fa-arrow-rotate-left mr-1"></i> Kembali ke Markas (N7)...</span>`;
                        }

                        robot.current_x = coords.x;
                        robot.current_y = coords.y;
                        robot.floor = floorNum;
                        robot.rotation = angle;
                    }
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else if (!isEditingThis) {
                    coords = { x: (robot.current_x !== undefined ? robot.current_x : baseLoc.x), y: (robot.current_y !== undefined ? robot.current_y : baseLoc.y) };
                    floorNum = robot.floor || 1;
                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    const isAtBase = (Number(floorNum) === 1 && Math.hypot(coords.x - baseLoc.x, coords.y - baseLoc.y) < 1.0);
                    taskText = isAtBase ? 'Standby at base station (N7)' : `Standby di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                }
            }

            // Create Robot marker element
            // (createRobotMarker 2D dihapus — kedua lantai murni 3D; klik avatar 3D utk fokus)

            // Avatar 3D per lantai (std + fullview); posisi & status badge sinkron dari data gerak yang sama
            const destNodeId = (delivery && delivery.status === 'In Progress' && robot.status === 'Delivering') ? delivery.destination_location : null;
            const destName = destNodeId ? (locations[destNodeId]?.name || null) : null;
            if (Number(floorNum) === 2) {
                updateRobot3DAvatar(threeStd, robot, coords, destName);
                updateRobot3DAvatar(threeFull, robot, coords, destName);
            } else {
                updateRobot3DAvatar(threeStdF1, robot, coords, destName);
                updateRobot3DAvatar(threeFullF1, robot, coords, destName);
            }

            // Update Robot Cards in standard view
            const badge = document.getElementById(`robot-status-badge-${robot.id}`);
            const batBar = document.getElementById(`robot-battery-bar-${robot.id}`);
            const batText = document.getElementById(`robot-battery-text-${robot.id}`);
            const locText = document.getElementById(`robot-location-text-${robot.id}`);
            const taskTextDiv = document.getElementById(`robot-task-text-${robot.id}`);

            if (badge) {
                if (hasIssue) {
                    const issueLabel = robot.activeAlert ? robot.activeAlert.issue_type.toUpperCase() : 'MASALAH';
                    badge.textContent = issueLabel;
                    badge.className = 'text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-300 animate-pulse';
                } else if (robot.isReturning) {
                    badge.textContent = 'Returning';
                    badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-indigo-100 text-indigo-700 border border-indigo-200';
                } else {
                    badge.textContent = robot.status;
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
            if (locText) locText.textContent = `${currentLocName} (Floor ${floorNum})`;
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

        if (!banner || !bannerText) return;

        if (issueRobots.length > 0) {
            banner.classList.remove('hidden');
            const descriptions = issueRobots.map(r => {
                const alertType = r.activeAlert ? r.activeAlert.issue_type : (r.battery_level <= 10 ? 'Baterai Habis' : 'Kendala Teknis');
                return `${r.name}: ${alertType} (${r.activeAlert?.description || 'Pengantaran mandek'})`;
            }).join(' | ');
            bannerText.innerHTML = `⚠️ ${descriptions}. <strong>Cepat benerin agar robot dapat kembali bekerja!</strong>`;
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
            });
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
        const btn = document.getElementById('autopilot-btn');
        const text = document.getElementById('autopilot-text');
        const icon = document.getElementById('autopilot-icon');

        if (!btn || !text) return;

        if (isAutopilotEnabled) {
            btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30";
            text.innerHTML = '<span class="relative flex h-2 w-2 mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span></span> Autopilot: ON (SERENTAK)';
            if (icon) icon.className = "fa-solid fa-robot animate-bounce";
        } else {
            if (window.isAdmin) {
                btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300";
            } else {
                btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 bg-gray-100 text-gray-500 border border-gray-200 cursor-not-allowed";
            }
            text.textContent = 'Autopilot: OFF (MANUAL)';
            if (icon) icon.className = "fa-solid fa-wand-magic-sparkles";
        }
    }

    function dispatchAllRobotsSerentak() {
        if (!isAutopilotEnabled) return;

        let destinationNodeIds = Object.keys(locations).filter(id => locations[id].is_destination);
        if (destinationNodeIds.length < 2) {
            destinationNodeIds = Object.keys(locations).filter(id => !id.includes('_N') && !id.includes('_Stairs'));
        }
        if (destinationNodeIds.length < 2) return;

        const items = ['Handuk', 'Makanan', 'Dokumen', 'Kopi', 'Paket', 'Botol Air', 'Sparepart'];

        // Find all idle healthy robots ready for dispatch
        const eligibleRobots = robots.filter(r => 
            r.status === 'Idle' && 
            r.battery_level > 20 && 
            !r.isReturning && 
            !r.isDispatching && 
            !r.hasIssue
        );

        if (eligibleRobots.length === 0) return;

        eligibleRobots.forEach((robot, idx) => {
            robot.isDispatching = true;
            const item = items[(idx + Math.floor(Math.random() * items.length)) % items.length];
            let currentLoc = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1) || '1_N7';

            let dest = destinationNodeIds[Math.floor(Math.random() * destinationNodeIds.length)];
            let attempts = 0;
            while (dest === currentLoc && attempts < 10) {
                dest = destinationNodeIds[Math.floor(Math.random() * destinationNodeIds.length)];
                attempts++;
            }

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
                        start_location: currentLoc,
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
            r.status === 'Idle' && 
            !r.isReturning && 
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
            
            if (window.activeDeliveries && Array.isArray(window.activeDeliveries)) {
                data.active_deliveries.forEach(newDeliv => {
                    const existing = window.activeDeliveries.find(d => d.id === newDeliv.id);
                    if (existing && existing._cachedMission) {
                        newDeliv._cachedMission = existing._cachedMission;
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
                    if (existing.status !== newRobot.status) {
                        existing.status = newRobot.status;
                        if (!existing.isReturning && !existing.customPosition) {
                            existing.current_x = newRobot.current_x;
                            existing.current_y = newRobot.current_y;
                        }
                    } else if (!existing.isReturning && existing.status !== 'Delivering' && !existing.customPosition) {
                        existing.current_x = newRobot.current_x;
                        existing.current_y = newRobot.current_y;
                    }
                    existing.floor = newRobot.floor || existing.floor || 1;
                    existing.battery_level = newRobot.battery_level;
                } else {
                    robots.push(newRobot);
                }
            });
        })
        .catch(err => console.error('Error fetching dashboard telemetry:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateAutopilotUI();
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
</script>
@endsection
