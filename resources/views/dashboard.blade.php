@extends('layouts.layout')

@section('title', 'ROBOPATH - Live Fleet Tracking')
@section('page_title', 'System Overview')
@section('page_subtitle', 'Real-time Multi-Floor Robot Tracking & System Metrics')

@section('styles')
<style>
    .floor-map-card {
        position: relative;
        width: 100%;
        aspect-ratio: 1800 / 1375;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(59, 76, 184, 0.08), inset 0 0 0 1px rgba(0,0,0,0.06);
    }
    
    /* Full View 1-Screen Fit (Zero Scroll) */
    .fullview-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        transition: all 0.3s ease;
    }
    .fullview-floor-box {
        position: relative;
        height: calc(100vh - 170px);
        max-height: calc(100vh - 170px);
        aspect-ratio: 1800 / 1375;
        max-width: 100%;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        border-radius: 1rem;
        box-shadow: 0 4px 25px rgba(0,0,0,0.12), inset 0 0 0 1px rgba(0,0,0,0.06);
        transition: all 0.2s ease;
    }

    .robot-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        transition: left 0.05s linear, top 0.05s linear;
        z-index: 30;
    }
    .path-svg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 10;
    }
</style>
@endsection

@section('content')
<!-- Container 1: Standard Dashboard View (Stat Cards + Active Floor Map + Robot Roster) -->
<div id="standard-view" class="space-y-6">

    <!-- Emergency Alert Banner (Shown when any robot has an incident / paused task) -->
    <div id="emergency-alert-banner" class="hidden p-4 bg-gradient-to-r from-rose-600 to-red-700 rounded-2xl shadow-xl text-white flex flex-wrap items-center justify-between gap-4 border border-rose-400 animate-pulse">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shrink-0 shadow">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-black uppercase tracking-wider bg-white text-rose-700 px-2 py-0.5 rounded-full shadow-sm">Peringatan Darurat</span>
                    <span class="text-xs font-bold text-rose-100">Pengantaran Mandek / Terhenti</span>
                </div>
                <p class="text-sm font-bold mt-1 text-white" id="emergency-banner-text">Robot terhenti akibat kendala di jalur. Cepat benerin!</p>
            </div>
        </div>
        <div id="emergency-banner-actions" class="flex items-center gap-2">
            @if(auth()->check() && auth()->user()->isAdmin())
            <button id="emergency-fix-btn" onclick="fixActiveIssueRobot()" 
                    class="px-4 py-2.5 bg-white hover:bg-rose-50 text-rose-700 font-extrabold text-xs rounded-xl shadow-lg transition duration-200 flex items-center gap-2">
                <i class="fa-solid fa-wrench"></i>
                <span>Benerin Sekarang (Fix &amp; Resume)</span>
            </button>
            @else
            <span class="text-xs bg-black/25 text-white px-3 py-1.5 rounded-xl font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-lock text-rose-200"></i> Menunggu Supervisor/Admin Memperbaiki
            </span>
            @endif
        </div>
    </div>

    <!-- Top Stat Cards (4 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

        <!-- Card 1: Active Units -->
        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Active Units</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-gray-800">{{ $activeRobotsCount }}/{{ $totalRobotsCount }}</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Online</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-[#3b4cb8] text-xl shadow-sm">
                <i class="fa-solid fa-robot"></i>
            </div>
        </div>

        <!-- Card 2: Active Missions -->
        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Active Missions</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-gray-800">{{ $activeDeliveriesCount }}</span>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">In Progress</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-xl shadow-sm">
                <i class="fa-solid fa-route"></i>
            </div>
        </div>

        <!-- Card 3: Completed Today -->
        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Completed Today</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black text-gray-800">{{ $deliveriesTodayCount }}</span>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">{{ $successRate }}% Success</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 text-xl shadow-sm">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Card 4: System Alerts -->
        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">System Alerts</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black {{ $activeAlertsCount > 0 ? 'text-rose-600' : 'text-gray-800' }}">{{ $activeAlertsCount }}</span>
                    <span class="text-xs font-bold {{ $activeAlertsCount > 0 ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-gray-500 bg-gray-100 border-gray-200' }} px-2 py-0.5 rounded-full border">
                        {{ $activeAlertsCount > 0 ? 'Needs Attention' : 'Optimal' }}
                    </span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl {{ $activeAlertsCount > 0 ? 'bg-rose-50 border border-rose-100 text-rose-600' : 'bg-gray-100 border border-gray-200 text-gray-400' }} flex items-center justify-center text-xl shadow-sm">
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
                            <button onclick="switchDashboardFloor('all')" id="std-tab-all" class="px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition">
                                Semua Lantai
                            </button>
                            <button onclick="switchDashboardFloor(1)" id="std-tab-f1" class="px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
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
                            <span id="autopilot-text">Autopilot: OFF</span>
                        </button>
                        @endif

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

                <!-- Active Floor Title Badge & Label Toggle -->
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-[#3b4cb8] flex items-center gap-1.5" id="std-floor-title">
                        <i class="fa-solid fa-layer-group"></i> Semua Lantai (Merged View: Lantai 2 Atas &amp; Lantai 1 Bawah)
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="toggleDestinationLabels()" id="btn-toggle-dest-labels" title="Tampilkan/Sembunyikan Label Nama Ruangan" class="px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-blue-50 text-[#3b4cb8] border border-blue-200 hover:bg-blue-100 shadow-sm">
                            <i class="fa-solid fa-tags"></i> <span id="label-toggle-text">Label Ruangan: ON</span>
                        </button>
                        <span class="text-[10px] bg-blue-100 text-blue-700 font-bold px-2.5 py-0.5 rounded-full border border-blue-200" id="std-floor-badge">
                            Showing All Floors (Merged)
                        </span>
                    </div>
                </div>

                <!-- Map Canvas Container (Proporsional 1800/1375) -->
                <div class="floor-map-card overflow-hidden shadow-inner border border-gray-200 relative" id="std-map-container" style="background-image: url('{{ asset('images/LantaiMerge.jpeg') }}');">
                    <svg class="path-svg" id="std-path-svg"></svg>
                    <div id="std-destinations-overlay" class="absolute inset-0"></div>
                    <div id="std-robots-overlay"></div>
                </div>

                <!-- Status & Location Indicator Legends -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 mt-3 border-t border-gray-200 text-xs text-gray-600 font-semibold">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-gray-400 uppercase text-[10px] font-bold">Robot:</span>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm"></span><span>Idle</span></div>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-500 shadow-sm"></span><span>Delivering</span></div>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-orange-500 shadow-sm"></span><span>Charging</span></div>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-sm"></span><span>Maintenance</span></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-gray-400 uppercase text-[10px] font-bold">Tujuan:</span>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-600 ring-2 ring-blue-200"></span><span>Ruangan L1</span></div>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-600 ring-2 ring-purple-200"></span><span>Ruangan L2</span></div>
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 ring-2 ring-amber-200"></span><span>Markas (N7)</span></div>
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
                <div class="bg-slate-50 border border-gray-200 p-4 rounded-xl flex flex-col justify-between hover:border-[#3b4cb8] transition duration-200" id="robot-card-{{ $robot->id }}">
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
    <!-- Header Bar with Back Button, Layout Switcher & Legend -->
    <div class="bg-white border border-gray-200 px-5 py-2.5 rounded-2xl shadow-md flex flex-wrap items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3">
            <button onclick="toggleFullView(false)" class="bg-[#3b4cb8] hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-2 shadow transition duration-200">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </button>
            <div class="hidden sm:block">
                <h3 class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group text-[#3b4cb8]"></i> 2-Floor Full View (Overview)
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

    <!-- Scaled Merged Floor Canvas (Both floors fit in 1 view) -->
    <div class="fullview-wrapper" id="fullview-wrapper">
        <div class="fullview-floor-box" id="fullview-container-merged" style="background-image: url('{{ asset('images/LantaiMerge.jpeg') }}');">
            <div class="absolute top-2.5 left-2.5 z-20 bg-black/75 backdrop-blur-sm text-white font-bold text-[10px] px-3 py-1.5 rounded-lg border border-white/10 shadow flex items-center gap-2 pointer-events-none">
                <i class="fa-solid fa-layer-group text-sky-400"></i> OVERVIEW (Lantai 2 di Atas &bull; Lantai 1 di Bawah)
            </div>
            <svg class="path-svg" id="fullview-path-svg"></svg>
            <div id="fullview-destinations-overlay" class="absolute inset-0 pointer-events-none"></div>
            <div id="fullview-robots-overlay" class="absolute inset-0 pointer-events-none"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const mergedMapImg = "{{ asset('images/LantaiMerge.jpeg') }}";
    const floor1Img = "{{ asset('images/floor1.jpeg') }}";
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
            is_destination: {{ ($loc['is_destination'] ?? false) ? 'true' : 'false' }}
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
    let serverClientOffset = 0;
    let currentDashboardFloor = 'all';
    let isFullViewMode = false;

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
        
        const tabAll = document.getElementById('std-tab-all');
        const tabF1 = document.getElementById('std-tab-f1');
        const tabF2 = document.getElementById('std-tab-f2');
        const container = document.getElementById('std-map-container');
        const title = document.getElementById('std-floor-title');
        const badge = document.getElementById('std-floor-badge');

        const activeCls = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
        const inactiveCls = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";

        if (tabAll) tabAll.className = floorNum === 'all' ? activeCls : inactiveCls;
        if (tabF1) tabF1.className = floorNum === 1 ? activeCls : inactiveCls;
        if (tabF2) tabF2.className = floorNum === 2 ? activeCls : inactiveCls;

        container.style.backgroundImage = `url('${mergedMapImg}')`;

        if (floorNum === 'all') {
            title.innerHTML = '<i class="fa-solid fa-layer-group"></i> Semua Lantai (Merged View: Lantai 2 Atas &amp; Lantai 1 Bawah)';
            badge.textContent = 'Showing All Floors (Merged)';
        } else if (floorNum === 1) {
            title.innerHTML = '<i class="fa-solid fa-building-user"></i> Lantai 1 (Ground Floor - Lobby, Office &amp; Receptionist)';
            badge.textContent = 'Showing Floor 1';
        } else {
            title.innerHTML = '<i class="fa-solid fa-building-user"></i> Lantai 2 (Upper Floor - Direksi, Lounge &amp; Meeting Rooms)';
            badge.textContent = 'Showing Floor 2';
        }

        drawDestinations();
        runSimulationStep();
    }

    let showDestinationLabels = true;

    function toggleDestinationLabels() {
        showDestinationLabels = !showDestinationLabels;
        const btn = document.getElementById('btn-toggle-dest-labels');
        const text = document.getElementById('label-toggle-text');
        if (btn) {
            btn.className = showDestinationLabels 
                ? 'px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-blue-50 text-[#3b4cb8] border border-blue-200 hover:bg-blue-100 shadow-sm'
                : 'px-2.5 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200';
        }
        if (text) {
            text.textContent = showDestinationLabels ? 'Label Ruangan: ON' : 'Label Ruangan: OFF';
        }
        drawDestinations();
    }

    function drawDestinations() {
        const stdOverlay = document.getElementById('std-destinations-overlay');
        const fullOverlay = document.getElementById('fullview-destinations-overlay');
        if (stdOverlay) stdOverlay.innerHTML = '';
        if (fullOverlay) fullOverlay.innerHTML = '';

        for (let id in locations) {
            const loc = locations[id];
            const isBase = (id === '1_N7');
            const isStairs = id.includes('Stairs');
            const isDest = (loc.is_destination === true || isBase || isStairs);
            if (!isDest) continue;

            const floorMatch = (currentDashboardFloor === 'all' || Number(loc.floor) === Number(currentDashboardFloor));

            function createPin(isFullview = false) {
                const pin = document.createElement('div');
                pin.className = 'absolute -translate-x-1/2 -translate-y-1/2 z-15 pointer-events-auto flex flex-col items-center group cursor-pointer';
                pin.style.left = `${loc.x}%`;
                pin.style.top = `${loc.y}%`;

                let dotColor = 'bg-blue-600 ring-2 ring-blue-300';
                let iconClass = 'fa-location-dot';
                let labelPrefix = '';

                if (isBase) {
                    dotColor = 'bg-amber-500 ring-2 ring-amber-300';
                    iconClass = 'fa-charging-station';
                    labelPrefix = '⚡ ';
                } else if (isStairs) {
                    dotColor = 'bg-orange-500 ring-2 ring-orange-300';
                    iconClass = 'fa-stairs';
                    labelPrefix = '🪜 ';
                } else if (Number(loc.floor) === 2) {
                    dotColor = 'bg-purple-600 ring-2 ring-purple-300';
                }

                const dotSize = isFullview ? 'w-3 h-3 text-[6px]' : 'w-3.5 h-3.5 text-[7px]';
                const labelTextSize = isFullview ? 'text-[7px] px-1 py-0.2' : 'text-[8px] px-1.5 py-0.5';

                pin.innerHTML = `
                    <div class="${dotSize} rounded-full ${dotColor} text-white flex items-center justify-center shadow-md transition transform group-hover:scale-125">
                        <i class="fa-solid ${iconClass}"></i>
                    </div>
                    ${showDestinationLabels ? `
                        <div class="mt-0.5 ${labelTextSize} rounded font-bold tracking-tight bg-white/95 backdrop-blur-sm border border-gray-200/90 shadow-sm text-gray-800 whitespace-nowrap pointer-events-none select-none transition group-hover:bg-gray-900 group-hover:text-white group-hover:border-gray-900 group-hover:z-30">
                            ${labelPrefix}${loc.name}
                        </div>
                    ` : `
                        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 ${labelTextSize} rounded font-bold tracking-tight bg-gray-900/90 text-white shadow-lg whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition z-30">
                            ${labelPrefix}${loc.name}
                        </div>
                    `}
                `;
                return pin;
            }

            if (stdOverlay && floorMatch) {
                stdOverlay.appendChild(createPin(false));
            }
            if (fullOverlay) {
                fullOverlay.appendChild(createPin(true));
            }
        }
    }

    let currentFullViewLayout = localStorage.getItem('fullview_layout') || 'vertical';

    function setFullViewLayout(layout) {
        currentFullViewLayout = layout;
        try {
            localStorage.setItem('fullview_layout', layout);
        } catch (e) {}

        const wrapper = document.getElementById('fullview-wrapper');
        const btnVertical = document.getElementById('btn-layout-vertical');
        const btnHorizontal = document.getElementById('btn-layout-horizontal');

        if (wrapper) {
            if (layout === 'horizontal') {
                wrapper.classList.add('fullview-horizontal');
            } else {
                wrapper.classList.remove('fullview-horizontal');
            }
        }

        if (btnVertical && btnHorizontal) {
            if (layout === 'horizontal') {
                btnHorizontal.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold transition duration-200 bg-white text-[#3b4cb8] shadow-sm';
                btnVertical.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold transition duration-200 text-gray-600 hover:text-gray-900';
            } else {
                btnVertical.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold transition duration-200 bg-white text-[#3b4cb8] shadow-sm';
                btnHorizontal.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold transition duration-200 text-gray-600 hover:text-gray-900';
            }
        }

        setTimeout(runSimulationStep, 60);
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
            setFullViewLayout(currentFullViewLayout);
        } else {
            fullView.classList.add('hidden');
            stdView.classList.remove('hidden');
            if (mainScroll) mainScroll.scrollTop = 0;
            window.scrollTo(0, 0);
        }

        setTimeout(() => {
            drawDestinations();
            runSimulationStep();
        }, 50);
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

    function planRouteBetween(fromId, toId) {
        if (!locations[fromId] || !locations[toId]) return [];
        const f1 = Number(locations[fromId].floor || 1);
        const f2 = Number(locations[toId].floor || 1);
        
        if (f1 === f2) {
            const p = findShortestPath(fromId, toId);
            return [{ type: 'travel', floor: f1, path: p }];
        } else {
            const stairsFrom = f1 === 1 ? '1_Stairs' : '2_Stairs';
            const stairsTo = f2 === 1 ? '1_Stairs' : '2_Stairs';
            const p1 = findShortestPath(fromId, stairsFrom);
            const p2 = findShortestPath(stairsTo, toId);
            return [
                { type: 'travel', floor: f1, path: p1 },
                { type: 'stairs', fromFloor: f1, toFloor: f2, fromNode: stairsFrom, toNode: stairsTo, durationMs: 5500 },
                { type: 'travel', floor: f2, path: p2 }
            ];
        }
    }

    function buildReturnMission(robot, now) {
        const baseLoc = locations['1_N7'] || { x: 76.23, y: 64.42, floor: 1 };
        const robotFloor = Number(robot.floor || 1);
        if (robotFloor === 1 && Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) < 2.0) {
            robot.floor = 1;
            return null;
        }

        const currentLocId = resolveLocationNodeId(robot.current_x, robot.current_y, robotFloor);
        const targetId = '1_N7';
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
        if (status === 'Maintenance' || status === 'Charging') {
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
                description: `Robot ${robot.name} menabrak hambatan/dinding koridor di ${resolveLocationName(coords.x, coords.y, floorNum)}! Pengantaran mandek (pending).`,
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
        robot.isLowBatteryReturning = true;
        robot.pausedElapsedMs = elapsedMs;
        if (delivery) delivery.status = 'Pending';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robot.id}/simulate-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                issue_type: 'Low Battery',
                description: `Baterai Robot ${robot.name} kritis (<20%) di tengah jalan! Pengantaran mandek, unit kembali ke base station N7 untuk charging.`,
                current_x: coords.x,
                current_y: coords.y,
                floor: floorNum,
                paused_elapsed_ms: elapsedMs
            })
        })
        .then(res => res.json())
        .then(data => {
            fetchData();
            robot.returnMission = buildReturnMission(robot, new Date(new Date().getTime() + serverClientOffset));
            robot.isReturning = true;
        })
        .catch(err => console.error('Error triggering low battery return:', err));
    }

    function resumeFromBaseAfterCharge(robot) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robot.id}/resume-from-base`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(res => res.json())
        .then(data => {
            activeDeliveries.forEach(d => {
                if (Number(d.robot_id) === Number(robot.id)) {
                    delete d._cachedMission;
                }
            });
            if (data.delivery) {
                delete data.delivery._cachedMission;
            }
            fetchData();
        })
        .catch(err => console.error('Error resuming from base after charge:', err));
    }

    function getDeliveryMission(delivery, robot) {
        if (delivery._cachedMission) {
            return delivery._cachedMission;
        }

        const startNodeId = getNode(delivery.start_location);
        const destNodeId = getNode(delivery.destination_location);
        
        const robotFloor = Number(robot?.floor || 1);
        let originNodeId = getNode(delivery.origin_location);
        if (!originNodeId && robot && robot.current_x && robot.current_y) {
            originNodeId = resolveLocationNodeId(robot.current_x, robot.current_y, robotFloor);
        }
        if (!originNodeId || !locations[originNodeId]) {
            originNodeId = '1_N7';
        }

        let validStart = (startNodeId && locations[startNodeId]) ? startNodeId : '1_Waiting Room';
        const validDest = (destNodeId && locations[destNodeId]) ? destNodeId : '2_Ruang Direktur';

        const pickupStage = {
            type: 'pickup',
            nodeId: validStart,
            floor: locations[validStart]?.floor || robotFloor,
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
            } else if (st.type === 'pickup') {
                st.durationMs = 3000;
            } else if (st.type === 'dropoff') {
                st.durationMs = 3000;
            } else {
                const dist = calculatePathDistance(st.path);
                st.durationMs = Math.max(2500, Math.round(dist * 700));
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

    function drawRobotPaths() {
        const stdSvg = document.getElementById('std-path-svg');
        if (stdSvg) stdSvg.innerHTML = '';

        const fullSvg = document.getElementById('fullview-path-svg');
        if (fullSvg) fullSvg.innerHTML = '';
        
        const now = new Date(new Date().getTime() + serverClientOffset);

        // 1. Draw paths for active deliveries (with past segment trimming)
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || (robot.status !== 'Delivering' && delivery.status !== 'Pending')) return;
            
            const mission = getDeliveryMission(delivery, robot);
            if (!mission || !mission.stages) return;

            const robotColor = getRobotColor(robot.id);
            const startedMs = delivery._clientStartedAt || parseServerDate(delivery.started_at).getTime();
            const elapsedMs = Math.max(0, now.getTime() - startedMs);

            mission.stages.forEach(st => {
                if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                
                const stageEndMs = st.startMs + st.durationMs;
                // If robot has already passed this stage entirely, do not draw it
                if (elapsedMs >= stageEndMs) {
                    return;
                }

                const isCurrentActive = (elapsedMs >= st.startMs && elapsedMs < stageEndMs);
                const isFutureStage = (elapsedMs < st.startMs);

                // Build trimmed points ahead of the robot
                const remainingPts = [];
                if (isCurrentActive) {
                    remainingPts.push({ x: robot.current_x, y: robot.current_y });
                    const segIdx = robot.currentSegIdx || 0;
                    for (let i = segIdx + 1; i < st.path.length; i++) {
                        if (locations[st.path[i]]) {
                            remainingPts.push(locations[st.path[i]]);
                        }
                    }
                } else if (isFutureStage) {
                    st.path.forEach(nodeId => {
                        if (locations[nodeId]) remainingPts.push(locations[nodeId]);
                    });
                } else {
                    return;
                }

                if (remainingPts.length < 2) return;

                if (isFullViewMode) {
                    const container = document.getElementById('fullview-container-merged');
                    if (!fullSvg || !container) return;

                    let pts = '';
                    remainingPts.forEach(pt => {
                        const px = (pt.x / 100) * container.clientWidth;
                        const py = (pt.y / 100) * container.clientHeight;
                        pts += `${px},${py} `;
                    });

                    if (pts.trim()) {
                        const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                        poly.setAttribute('points', pts.trim());
                        poly.setAttribute('stroke', robotColor);
                        poly.setAttribute('stroke-width', '2.5');
                        poly.setAttribute('stroke-dasharray', delivery.status === 'Pending' ? '3,3' : '5,5');
                        poly.setAttribute('fill', 'none');
                        poly.setAttribute('opacity', delivery.status === 'Pending' ? '0.5' : '0.85');
                        fullSvg.appendChild(poly);
                    }
                } else {
                    if (currentDashboardFloor !== 'all' && Number(st.floor) !== Number(currentDashboardFloor)) return;
                    const stdContainer = document.getElementById('std-map-container');
                    if (!stdContainer || !stdSvg) return;

                    let pts = '';
                    remainingPts.forEach(pt => {
                        const px = (pt.x / 100) * stdContainer.clientWidth;
                        const py = (pt.y / 100) * stdContainer.clientHeight;
                        pts += `${px},${py} `;
                    });

                    if (pts.trim()) {
                        const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                        poly.setAttribute('points', pts.trim());
                        poly.setAttribute('stroke', robotColor);
                        poly.setAttribute('stroke-width', '3');
                        poly.setAttribute('stroke-dasharray', delivery.status === 'Pending' ? '3,3' : '6,6');
                        poly.setAttribute('fill', 'none');
                        poly.setAttribute('opacity', delivery.status === 'Pending' ? '0.5' : '0.9');
                        stdSvg.appendChild(poly);
                    }
                }
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

                    if (isFullViewMode) {
                        const container = document.getElementById('fullview-container-merged');
                        if (!fullSvg || !container) return;

                        let pts = '';
                        remainingPts.forEach(pt => {
                            const px = (pt.x / 100) * container.clientWidth;
                            const py = (pt.y / 100) * container.clientHeight;
                            pts += `${px},${py} `;
                        });

                        if (pts.trim()) {
                            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                            poly.setAttribute('points', pts.trim());
                            poly.setAttribute('stroke', robotColor);
                            poly.setAttribute('stroke-width', '2');
                            poly.setAttribute('stroke-dasharray', '4,4');
                            poly.setAttribute('fill', 'none');
                            poly.setAttribute('opacity', '0.75');
                            fullSvg.appendChild(poly);
                        }
                    } else {
                        if (currentDashboardFloor !== 'all' && Number(st.floor) !== Number(currentDashboardFloor)) return;
                        const stdContainer = document.getElementById('std-map-container');
                        if (!stdContainer || !stdSvg) return;

                        let pts = '';
                        remainingPts.forEach(pt => {
                            const px = (pt.x / 100) * stdContainer.clientWidth;
                            const py = (pt.y / 100) * stdContainer.clientHeight;
                            pts += `${px},${py} `;
                        });

                        if (pts.trim()) {
                            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                            poly.setAttribute('points', pts.trim());
                            poly.setAttribute('stroke', robotColor);
                            poly.setAttribute('stroke-width', '2.5');
                            poly.setAttribute('stroke-dasharray', '4,4');
                            poly.setAttribute('fill', 'none');
                            poly.setAttribute('opacity', '0.85');
                            stdSvg.appendChild(poly);
                        }
                    }
                });
            }
        });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        
        // Standard view overlay
        const stdOverlay = document.getElementById('std-robots-overlay');
        if (stdOverlay) stdOverlay.innerHTML = '';

        // Fullview overlay
        const fullOverlay = document.getElementById('fullview-robots-overlay');
        if (fullOverlay) fullOverlay.innerHTML = '';
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            
            // Check if robot has active issue / alert
            const robotAlert = activeAlerts.find(a => Number(a.robot_id) === Number(robot.id) && a.status === 'Active');
            const hasIssue = !!robotAlert || robot.status === 'Maintenance' || (delivery && delivery.status === 'Pending');
            robot.hasIssue = hasIssue;
            robot.activeAlert = robotAlert;

            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            let statusColor = 'bg-emerald-500';
            let taskText = 'Standby at base station (N7)';
            let currentLocName = resolveLocationName(coords.x, coords.y, floorNum);

            if (hasIssue) {
                statusColor = 'bg-rose-600';
                const issueName = robotAlert ? robotAlert.issue_type : (robot.battery_level <= 10 ? 'Baterai Habis' : 'Maintenance');
                if (delivery) {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> MASALAH: ${issueName} - Pengantaran Mandek!</span>`;
                    currentLocName = `Mandek di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                } else {
                    taskText = `<span class="text-rose-600 font-black animate-pulse"><i class="fa-solid fa-triangle-exclamation mr-1"></i> MASALAH: ${issueName} (Cepat Benerin!)</span>`;
                    currentLocName = `Tertahan di ${resolveLocationName(coords.x, coords.y, floorNum)}`;
                }
            } else if (robot.status === 'Charging') {
                statusColor = 'bg-orange-500';
                taskText = `<i class="fa-solid fa-bolt text-orange-500 mr-1 animate-pulse"></i> Pengisian Daya di Base N7 (${robot.battery_level}%)...`;
                
                // Active charging at base station
                const nowTime = now.getTime();
                if (!robot.lastChargeTick) robot.lastChargeTick = nowTime;
                if (nowTime - robot.lastChargeTick >= 1000) {
                    robot.lastChargeTick = nowTime;
                    robot.battery_level = Math.min(100, (Number(robot.battery_level) || 0) + 15);
                    syncRobotPosition(robot.id, coords.x, coords.y, floorNum, 'Charging', robot.battery_level);
                    
                    if (robot.battery_level >= 100) {
                        robot.battery_level = 100;
                        resumeFromBaseAfterCharge(robot);
                    }
                }
            } else if (robot.status === 'Maintenance') {
                statusColor = 'bg-rose-500';
                taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Maintenance required</span>';
            }
            
            if (delivery && (delivery.status === 'In Progress' || delivery.status === 'Pending') && !hasIssue) {
                robot.status = 'Delivering';
                statusColor = 'bg-sky-500';
                robot.returnMission = null;
                robot.isReturning = false;
                const mission = getDeliveryMission(delivery, robot);
                
                if (mission.stages && mission.stages.length > 0) {
                    if (!delivery._clientStartedAt) {
                        delivery._clientStartedAt = now.getTime();
                    }
                    const elapsedMs = Math.max(0, now.getTime() - delivery._clientStartedAt);
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
                            statusColor = 'bg-amber-500';
                            robot.currentSegIdx = 0;

                            if (robot.floor !== floorNum) {
                                robot.floor = floorNum;
                                syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                            }
                        } else if (activeStage.type === 'pickup') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const locNode = locations[activeStage.nodeId] || locations[mission.startId];
                            if (locNode) {
                                coords = locNode;
                                floorNum = locNode.floor || 1;
                            }
                            taskText = `<span class="text-blue-600 font-bold"><i class="fa-solid fa-box-open animate-bounce mr-1"></i> Mengambil ${delivery.item_name} di ${locations[mission.startId]?.name || delivery.start_location} (${remainingSec}s)...</span>`;
                            currentLocName = locations[mission.startId]?.name || delivery.start_location;
                            statusColor = 'bg-blue-500';
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
                            statusColor = 'bg-emerald-500';
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

                            // --- BATTERY DRAIN WHILE MOVING ---
                            const nowTime = now.getTime();
                            if (!robot.lastBatteryTick) robot.lastBatteryTick = nowTime;
                            if (nowTime - robot.lastBatteryTick >= 3500) {
                                robot.lastBatteryTick = nowTime;
                                robot.battery_level = Math.max(0, (Number(robot.battery_level) || 100) - 1);
                                syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                                
                                // Low Battery Threshold (< 20%) -> Auto pause & return to base for charging
                                if (robot.battery_level < 20 && !robot.isLowBatteryReturning) {
                                    triggerLowBatteryReturn(robot, delivery, coords, floorNum, elapsedMs);
                                    return;
                                }
                            }

                            // --- AUTONOMOUS COLLISION / CRASH SIMULATION ---
                            if (!robot.lastCrashCheck) robot.lastCrashCheck = nowTime;
                            if (!robot.lastCrashTime) robot.lastCrashTime = 0;
                            if (nowTime - robot.lastCrashCheck >= 12000) {
                                robot.lastCrashCheck = nowTime;
                                // 7% chance to hit a wall mid-corridor transit
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
                const baseLoc = locations['1_N7'] || { x: 76.23, y: 64.42, floor: 1 };
                const distToBase = (Number(robot.floor || 1) === 1) 
                    ? Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) 
                    : 999;
                const isNearBase = Number(robot.floor || 1) === 1 && distToBase < 2.0;

                // Instant Arrival: When robot arrives at Base (within 2m on Floor 1)
                if (isNearBase && (robot.status === 'Returning' || robot.isReturning || robot.returnMission)) {
                    coords = { x: baseLoc.x, y: baseLoc.y };
                    floorNum = 1;
                    robot.current_x = baseLoc.x;
                    robot.current_y = baseLoc.y;
                    robot.floor = 1;
                    robot.returnMission = null;
                    robot.isReturning = false;

                    if (robot.isLowBatteryReturning || robot.battery_level < 20) {
                        robot.isLowBatteryReturning = false;
                        robot.status = 'Charging';
                        taskText = `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Charging di Base (1_N7)...</span>`;
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Charging', robot.battery_level);
                    } else {
                        robot.status = 'Idle';
                        taskText = 'Standby at base station (N7)';
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                    }
                    currentLocName = 'Base Station (N7)';
                    statusColor = (robot.status === 'Charging') ? 'bg-orange-500' : 'bg-emerald-500';
                } else if (!isNearBase && distToBase > 0.8) {
                    if (!robot.returnMission) {
                        robot.returnMission = buildReturnMission(robot, now);
                    }
                }

                if (robot.returnMission) {
                    robot.isReturning = true;
                    statusColor = 'bg-indigo-500';
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

                        if (robot.isLowBatteryReturning || robot.battery_level < 20) {
                            robot.isLowBatteryReturning = false;
                            robot.status = 'Charging';
                            taskText = `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Charging di Base (1_N7)...</span>`;
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Charging', robot.battery_level);
                        } else {
                            robot.status = 'Idle';
                            taskText = 'Standby at base station (N7)';
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                        }
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
                            statusColor = 'bg-amber-500';
                            robot.returnSegIdx = 0;

                            if (robot.floor !== floorNum) {
                                robot.floor = floorNum;
                                syncRobotPosition(robot.id, coords.x, coords.y, floorNum, robot.status, robot.battery_level);
                            }
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
                        if (floorNum === 1 && Math.hypot(coords.x - baseLoc.x, coords.y - baseLoc.y) < 1.2) {
                            coords = { x: baseLoc.x, y: baseLoc.y };
                            robot.current_x = baseLoc.x;
                            robot.current_y = baseLoc.y;
                            robot.floor = 1;
                            robot.returnMission = null;
                            robot.isReturning = false;
                            robot.status = (robot.isLowBatteryReturning || robot.battery_level < 20) ? 'Charging' : 'Idle';
                            taskText = (robot.status === 'Charging') 
                                ? `<span class="text-orange-500 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Baterai Rendah! Charging di Base (1_N7)...</span>`
                                : 'Standby at base station (N7)';
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, robot.status, robot.battery_level);
                        }
                    }
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else {
                    if (isNearBase) {
                        coords = { x: baseLoc.x, y: baseLoc.y };
                        floorNum = 1;
                        robot.current_x = baseLoc.x;
                        robot.current_y = baseLoc.y;
                        robot.floor = 1;
                        if (robot.status === 'Returning' || robot.isReturning) {
                            robot.status = 'Idle';
                            robot.isReturning = false;
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
                        }
                        taskText = 'Standby at base station (N7)';
                        currentLocName = 'Base Station (N7)';
                    } else {
                        coords = { x: robot.current_x || baseLoc.x, y: robot.current_y || baseLoc.y };
                        floorNum = robot.floor || 1;
                        taskText = 'Standby (Persiapan kembali ke base)';
                        currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                    }
                }
            }

            // Create Robot marker element
            function createRobotMarker(compact = false) {
                const marker = document.createElement('div');
                marker.className = 'robot-marker z-30';
                
                let displayX = coords.x;
                let displayY = coords.y;
                if (robot.status === 'Idle' && !robot.isReturning && !hasIssue && Number(floorNum) === 1) {
                    const baseLoc = locations['1_N7'] || { x: 76.23, y: 64.42, floor: 1 };
                    if (Math.hypot(coords.x - baseLoc.x, coords.y - baseLoc.y) < 1.5) {
                        displayX = baseLoc.x + (Number(robot.id) - 2) * 2.2;
                    }
                }

                marker.style.left = `${displayX}%`;
                marker.style.top = `${displayY}%`;
                
                const sizeClass = compact ? 'w-6 h-6' : 'w-8 h-8';
                const pingClass = compact ? 'h-8 w-8' : 'h-10 w-10';
                const iconSize = compact ? 'text-[10px]' : 'text-xs';
                const isTransit = taskText.includes('Transit Tangga');
                const isPickingUp = taskText.includes('Mengambil');
                const isDroppingOff = taskText.includes('Menyerahkan');
                const isReturning = taskText.includes('Kembali ke Markas');
                const issueIcon = robot.activeAlert?.issue_type === 'Collision' 
                    ? 'fa-car-burst' 
                    : (robot.activeAlert?.issue_type === 'Low Battery' ? 'fa-battery-empty' : 'fa-triangle-exclamation');

                let ringClass = 'border-gray-300';
                let actionIcon = 'fa-robot';
                let iconColor = 'text-emerald-600';

                if (hasIssue) {
                    ringClass = 'border-rose-500 ring-4 ring-rose-400 animate-pulse';
                    actionIcon = `${issueIcon} text-rose-600 animate-bounce`;
                } else if (isTransit) {
                    ringClass = 'border-amber-400 ring-2 ring-amber-300';
                    actionIcon = 'fa-stairs text-amber-500 animate-bounce';
                } else if (isPickingUp) {
                    ringClass = 'border-blue-400 ring-4 ring-blue-300 animate-pulse';
                    actionIcon = 'fa-box-open text-blue-600 animate-bounce';
                } else if (isDroppingOff) {
                    ringClass = 'border-emerald-400 ring-4 ring-emerald-300 animate-pulse';
                    actionIcon = 'fa-dolly text-emerald-600 animate-bounce';
                } else if (isReturning) {
                    ringClass = 'border-indigo-400 ring-2 ring-indigo-300';
                    actionIcon = 'fa-arrow-rotate-left text-indigo-600';
                } else if (robot.status === 'Delivering') {
                    ringClass = 'border-blue-400 ring-2 ring-blue-200';
                    actionIcon = 'fa-robot text-[#3b4cb8]';
                } else if (robot.status === 'Charging') {
                    ringClass = 'border-orange-400 ring-2 ring-orange-200';
                    actionIcon = 'fa-bolt text-orange-500 animate-pulse';
                } else if (robot.status === 'Maintenance') {
                    ringClass = 'border-rose-500 ring-2 ring-rose-300';
                    actionIcon = 'fa-wrench text-rose-600';
                } else {
                    ringClass = 'border-gray-300';
                    actionIcon = 'fa-robot text-emerald-600';
                }

                marker.innerHTML = `
                    <div class="relative flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex ${pingClass} rounded-full ${hasIssue ? 'bg-rose-600' : statusColor} opacity-50"></span>
                        <div class="relative ${sizeClass} rounded-lg bg-white border ${ringClass} flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                            <i class="fa-solid ${actionIcon} ${iconSize}"></i>
                        </div>
                        <div class="absolute -top-5 ${hasIssue ? 'bg-rose-600 text-white' : 'bg-white/95 text-gray-800'} border ${hasIssue ? 'border-rose-700' : 'border-gray-200'} text-[8px] font-bold px-1.5 py-0.2 rounded shadow-sm whitespace-nowrap pointer-events-none">
                            ${robot.name.split(' ')[1]} (${robot.battery_level}%) ${hasIssue ? '⚠️' : ''}
                        </div>
                    </div>
                `;
                return marker;
            }

            // Render on active overlays
            if (isFullViewMode) {
                if (fullOverlay) fullOverlay.appendChild(createRobotMarker(true));
            } else {
                if (currentDashboardFloor === 'all' || Number(floorNum) === Number(currentDashboardFloor)) {
                    if (stdOverlay) stdOverlay.appendChild(createRobotMarker(false));
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

        const robot = robots.find(r => Number(r.id) === Number(robotId));
        const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robotId) && (d.status === 'In Progress' || d.status === 'Pending'));

        let elapsedMs = null;
        if (delivery && delivery.started_at) {
            const startedTime = parseServerDate(delivery.started_at);
            const nowTime = new Date().getTime() + serverClientOffset;
            elapsedMs = Math.max(0, nowTime - startedTime.getTime());
            delivery.status = 'Pending';
        }

        if (robot) {
            robot.hasIssue = true;
            robot.status = 'Maintenance';
            robot.pausedElapsedMs = elapsedMs;
            if (issueType === 'Low Battery') {
                robot.battery_level = 10;
            }
            if (robot.isReturning) {
                robot.isReturning = false;
                robot.returnMission = null;
            }
            // Add optimistic alert immediately so emergency banner & issue badge display without lag
            const optimisticAlert = {
                id: 'opt_' + Date.now(),
                robot_id: robotId,
                issue_type: issueType,
                description: `Simulasi Masalah: ${issueType} pada ${robot.name}`,
                status: 'Active'
            };
            activeAlerts = activeAlerts.filter(a => Number(a.robot_id) !== Number(robotId));
            activeAlerts.push(optimisticAlert);
            robot.activeAlert = optimisticAlert;
            updateEmergencyBanner();
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robotId}/simulate-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                issue_type: issueType,
                current_x: robot ? robot.current_x : null,
                current_y: robot ? robot.current_y : null,
                floor: robot ? robot.floor : 1,
                paused_elapsed_ms: elapsedMs
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchData();
            }
        })
        .catch(err => console.error('Error simulating issue:', err));
    }

    // Fix a specific robot with Instant Optimistic UI
    function fixRobotAction(robotId) {
        if (!window.isAdmin) {
            alert('Akses Terbatas: Hanya Admin yang dapat memperbaiki robot.');
            return;
        }

        const robot = robots.find(r => Number(r.id) === Number(robotId));
        let pausedElapsed = robot ? robot.pausedElapsedMs : null;

        const deliv = activeDeliveries.find(d => Number(d.robot_id) === Number(robotId) && (d.status === 'Pending' || d.status === 'In Progress'));
        const nowTime = new Date().getTime() + serverClientOffset;

        if (pausedElapsed === null && deliv && deliv.started_at) {
            const started = parseServerDate(deliv.started_at).getTime();
            const mission = deliv._cachedMission || getDeliveryMission(deliv, robot);
            if (mission && mission.totalDurationMs) {
                const calcElapsed = Math.max(2000, nowTime - started);
                pausedElapsed = Math.min(calcElapsed, Math.max(2000, mission.totalDurationMs - 3000));
            }
        }
        if (pausedElapsed === null || !isFinite(pausedElapsed)) {
            pausedElapsed = 4000;
        }

        // --- INSTANT OPTIMISTIC UI UPDATE (Zero Lag) ---
        if (robot) {
            robot.hasIssue = false;
            robot.activeAlert = null;
            robot.pausedElapsedMs = null;
            robot.lastCrashTime = nowTime; // Prevent re-crash immediately
            robot.status = deliv ? 'Delivering' : 'Idle';
        }

        // Remove active alerts for this robot immediately
        activeAlerts = activeAlerts.filter(a => Number(a.robot_id) !== Number(robotId));

        // Immediately resume delivery locally so the robot marker starts moving without waiting for network
        if (deliv) {
            deliv.status = 'In Progress';
            deliv._clientStartedAt = nowTime - pausedElapsed;
            deliv.started_at = new Date(nowTime - pausedElapsed).toISOString();
        }

        // Update emergency banner immediately
        updateEmergencyBanner();

        // Visual feedback on the fix button if it exists
        const fixBtn = document.getElementById('emergency-fix-btn');
        if (fixBtn) {
            fixBtn.innerHTML = '<i class="fa-solid fa-check"></i><span>Berhasil Diperbaiki!</span>';
            setTimeout(() => {
                if (fixBtn) {
                    fixBtn.innerHTML = '<i class="fa-solid fa-wrench"></i><span>Benerin Sekarang (Fix &amp; Resume)</span>';
                }
            }, 1200);
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/api/robots/${robotId}/fix`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'resume',
                paused_elapsed_ms: pausedElapsed
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (robot) {
                    robot.status = data.robot ? data.robot.status : (data.delivery ? 'Delivering' : 'Idle');
                }
                if (data.delivery && deliv) {
                    deliv.started_at = data.delivery.started_at;
                    deliv._clientStartedAt = parseServerDate(data.delivery.started_at).getTime();
                }
                fetchData();
            }
        })
        .catch(err => console.error('Error fixing robot:', err));
    }

    // Fix all robots with active issues instantaneously
    function fixActiveIssueRobot() {
        const issueRobots = robots.filter(r => r.hasIssue);
        if (issueRobots.length === 0) return;

        // Optimistically clear all issues
        issueRobots.forEach(r => {
            r.hasIssue = false;
            r.activeAlert = null;
            r.pausedElapsedMs = null;
            r.lastCrashTime = new Date().getTime();
        });
        activeAlerts = [];
        updateEmergencyBanner();

        // Call fix action for each robot
        issueRobots.forEach(r => fixRobotAction(r.id));
    }

    // Delivery Completion API
    function completeDeliveryAPI(deliveryId, finalX, finalY, finalFloor) {
        const delivery = activeDeliveries.find(d => Number(d.id) === Number(deliveryId));
        if (!delivery || delivery.isCompleting) return;
        delivery.isCompleting = true;

        const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
        if (robot) {
            robot.lastCompletedAt = Date.now();
        }

        // Immediately remove completed delivery from local memory so it stops interpolating
        activeDeliveries = activeDeliveries.filter(d => Number(d.id) !== Number(deliveryId));

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
                if (robot && data.robot) {
                    robot.status = data.robot.status;
                    robot.current_x = data.robot.current_x;
                    robot.current_y = data.robot.current_y;
                    robot.floor = data.robot.floor;
                    // Immediately trigger return towards base station
                    robot.returnMission = buildReturnMission(robot, new Date(new Date().getTime() + serverClientOffset));
                    robot.isReturning = true;
                }
                fetchData();
            }
        })
        .catch(err => {
            console.error('Error completing delivery:', err);
            delivery.isCompleting = false;
        });
    }

    // Autopilot Management (Centralized Backend Autopilot)
    let isTogglingAutopilot = false;

    function toggleAutopilot() {
        if (!window.isAdmin) {
            alert('Akses Terbatas: Hanya Admin / Bot Control yang dapat mengontrol Autopilot.');
            return;
        }

        if (isTogglingAutopilot) return;
        isTogglingAutopilot = true;

        const nextState = !isAutopilotEnabled;
        isAutopilotEnabled = nextState;
        updateAutopilotUI();

        const btn = document.getElementById('autopilot-btn');
        if (btn) btn.style.pointerEvents = 'none';

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
        .catch(err => {
            console.error('Error toggling autopilot:', err);
            isAutopilotEnabled = !nextState;
            updateAutopilotUI();
        })
        .finally(() => {
            if (btn) btn.style.pointerEvents = 'auto';
            setTimeout(() => {
                isTogglingAutopilot = false;
            }, 600);
        });
    }

    function updateAutopilotUI() {
        const btn = document.getElementById('autopilot-btn');
        const text = document.getElementById('autopilot-text');
        const icon = document.getElementById('autopilot-icon');
        if (!btn || !text) return;

        if (isAutopilotEnabled) {
            btn.className = "px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition duration-200 bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-500 animate-pulse";
            text.textContent = 'Autopilot: ON (Active)';
            if (icon) icon.className = "fa-solid fa-wand-magic-sparkles text-amber-300";
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

    function fetchData() {
        fetch('/api/telemetry')
        .then(res => res.json())
        .then(data => {
            if (data.server_time) {
                const serverTime = new Date(data.server_time);
                const clientTime = new Date();
                serverClientOffset = serverTime.getTime() - clientTime.getTime();
            }

            if (!isTogglingAutopilot && typeof data.autopilot_enabled !== 'undefined') {
                if (isAutopilotEnabled !== data.autopilot_enabled) {
                    isAutopilotEnabled = !!data.autopilot_enabled;
                    updateAutopilotUI();
                }
            }
            
            if (activeDeliveries && Array.isArray(activeDeliveries)) {
                data.active_deliveries.forEach(newDeliv => {
                    const existing = activeDeliveries.find(d => Number(d.id) === Number(newDeliv.id));
                    if (existing) {
                        if (existing._cachedMission) newDeliv._cachedMission = existing._cachedMission;
                        if (existing._clientStartedAt) newDeliv._clientStartedAt = existing._clientStartedAt;
                    }
                });
            }
            activeDeliveries = data.active_deliveries;
            activeAlerts = data.active_alerts || [];

            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                const bLoc = locations['1_N7'] || { x: 76.23, y: 64.42, floor: 1 };
                if (Number(newRobot.floor || 1) === 1 && Math.hypot((newRobot.current_x || bLoc.x) - bLoc.x, (newRobot.current_y || bLoc.y) - bLoc.y) < 2.0) {
                    newRobot.floor = 1;
                }

                const hasActiveReport = activeAlerts.some(a => Number(a.robot_id) === Number(newRobot.id) && a.status === 'Active');
                const hasDeliveryInProgress = activeDeliveries.some(d => Number(d.robot_id) === Number(newRobot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
                if (hasActiveReport) {
                    newRobot.status = 'Maintenance';
                } else if (hasDeliveryInProgress && newRobot.status !== 'Maintenance' && newRobot.status !== 'Charging') {
                    newRobot.status = 'Delivering';
                }

                if (existing) {
                    if (hasActiveReport) {
                        existing.status = 'Maintenance';
                        existing.hasIssue = true;
                    } else if (existing.status !== newRobot.status) {
                        existing.status = newRobot.status;
                    }

                    // DO NOT overwrite coordinates or floor if robot is actively moving (Delivering, Returning, or isReturning)
                    const isActivelyMoving = (existing.status === 'Delivering' || existing.status === 'Returning' || existing.isReturning);
                    if (!isActivelyMoving) {
                        existing.floor = newRobot.floor || existing.floor || 1;
                        if (newRobot.current_x != null && newRobot.current_y != null) {
                            existing.current_x = newRobot.current_x;
                            existing.current_y = newRobot.current_y;
                        }
                    } else if (existing.current_x == null || existing.current_y == null) {
                        existing.current_x = newRobot.current_x;
                        existing.current_y = newRobot.current_y;
                    }

                    // Baterai HANYA boleh bertambah jika status robot adalah 'Charging'
                    if (newRobot.status === 'Charging') {
                        existing.battery_level = newRobot.battery_level;
                    } else if (typeof existing.battery_level === 'number' && typeof newRobot.battery_level === 'number') {
                        existing.battery_level = Math.min(existing.battery_level, newRobot.battery_level);
                    } else {
                        existing.battery_level = newRobot.battery_level;
                    }
                } else {
                    robots.push(newRobot);
                }
            });
        })
        .catch(err => console.error('Error fetching dashboard telemetry:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        try { localStorage.removeItem('autopilot_enabled'); } catch(e) {}
        setFullViewLayout(currentFullViewLayout);
        updateAutopilotUI();
        drawDestinations();
        runSimulationStep();
        setInterval(runSimulationStep, 50);
        setInterval(fetchData, 3000);
    });

    window.addEventListener('resize', () => {
        drawDestinations();
        runSimulationStep();
    });
</script>
@endsection
