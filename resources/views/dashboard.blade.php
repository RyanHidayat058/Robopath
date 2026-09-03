@extends('layouts.layout')

@section('title', 'ROBOPATH - 3D Live Fleet Tracking')
@section('page_title', 'System Overview')
@section('page_subtitle', 'Real-time 3D Multi-Floor Building & Robot Fleet Tracking')

@section('styles')
<style>
    model-viewer {
        width: 100%;
        height: 580px;
        background-color: #0f172a;
        --poster-color: transparent;
    }
</style>
@endsection

@section('content')
<!-- Top Stat Cards (4 Columns) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

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

<!-- Main Section: 3D Live Building View & Robot Roster -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left / Main Column: 3D Model Live Building View (2/3 width) -->
    <div class="lg:col-span-2 space-y-6 lg:sticky lg:top-6 self-start">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-cube text-[#3b4cb8]"></i> 3D Building Floor Model (OFFICE V2)
                    </h3>
                    <p class="text-xs text-gray-500">Interactive 3D building visualization, floor inspection, and robot telemetry</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-bold text-[#3b4cb8] bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-200 shadow-sm">
                    <i class="fa-solid fa-layer-group"></i> 2 Floors (Lantai 1 &amp; 2)
                </div>
            </div>

            <!-- 3D Model Viewer with WebAssembly Draco Mesh Decompression -->
            <div class="relative w-full h-[580px] rounded-2xl overflow-hidden shadow-inner border border-slate-700 bg-slate-900">
                <model-viewer
                    id="office-model-viewer"
                    src="{{ asset('models/office_v2.glb') }}"
                    alt="3D Building Floor Model OFFICE V2"
                    camera-controls
                    touch-action="pan-y"
                    auto-rotate
                    rotation-per-second="15deg"
                    auto-rotate-delay="4000"
                    shadow-intensity="1.5"
                    shadow-softness="0.8"
                    exposure="1.3"
                    camera-orbit="45deg 55deg auto"
                    min-camera-orbit="auto auto 5%"
                    max-camera-orbit="auto auto 200%"
                    interaction-prompt="auto"
                >
                    <!-- Loading Progress bar inside model-viewer -->
                    <div slot="progress-bar" class="absolute inset-0 bg-slate-900 flex flex-col items-center justify-center p-6 text-white text-center z-50">
                        <div class="w-14 h-14 rounded-2xl bg-[#3b4cb8] flex items-center justify-center text-2xl mb-4 shadow-lg animate-bounce">
                            <i class="fa-solid fa-cube text-white"></i>
                        </div>
                        <h4 class="font-bold text-base text-white mb-2">Loading 3D Office Model</h4>
                        <p class="text-xs text-blue-200 mb-4 font-mono">Decompressing 3D Draco Geometry &amp; Shaders...</p>
                        <div class="w-64 bg-slate-800 rounded-full h-2.5 overflow-hidden border border-slate-700">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-2.5 rounded-full animate-pulse" style="width: 85%"></div>
                        </div>
                    </div>
                </model-viewer>

                <!-- 3D Controls Help Tag -->
                <div class="absolute bottom-3 left-3 bg-black/70 backdrop-blur-md text-white text-[11px] font-semibold px-3 py-1.5 rounded-lg flex items-center gap-2 z-20 border border-white/10 shadow-lg pointer-events-none">
                    <i class="fa-solid fa-mouse text-sky-400"></i> Klik Kiri &amp; Geser untuk Memutar • Scroll untuk Zoom • Klik Kanan untuk Pan
                </div>
            </div>

            <!-- Status Indicator Legends -->
            <div class="flex flex-wrap gap-4 mt-4 pt-4 border-t border-gray-200 text-xs text-gray-600 font-semibold">
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

    <!-- Right Column: Active Robots Roster (1/3 width) -->
    <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2 pb-3 border-b border-gray-200">
            <i class="fa-solid fa-robot text-[#3b4cb8]"></i> Active Robot Roster
        </h3>

        <div class="space-y-4 flex-1" id="robot-cards-container">
            @foreach($robots as $robot)
            <div class="bg-blue-50/40 border border-gray-200 p-4 rounded-xl flex flex-col justify-between hover:border-[#3b4cb8] transition duration-200" id="robot-card-{{ $robot->id }}">
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
                            Blank Room 2
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
@endsection

@section('scripts')
<script>
    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);

    function fetchData() {
        fetch('/api/telemetry')
        .then(res => res.json())
        .then(data => {
            activeDeliveries = data.active_deliveries;
            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                if (existing) {
                    existing.status = newRobot.status;
                    existing.battery_level = newRobot.battery_level;
                    existing.current_x = newRobot.current_x;
                    existing.current_y = newRobot.current_y;
                } else {
                    robots.push(newRobot);
                }

                // Update cards
                const badge = document.getElementById(`robot-status-badge-${newRobot.id}`);
                const batBar = document.getElementById(`robot-battery-bar-${newRobot.id}`);
                const batText = document.getElementById(`robot-battery-text-${newRobot.id}`);
                const taskTextDiv = document.getElementById(`robot-task-text-${newRobot.id}`);

                if (badge) {
                    badge.textContent = newRobot.status;
                    badge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                        newRobot.status === 'Delivering' ? 'bg-blue-100 text-blue-700 border border-blue-200' :
                        (newRobot.status === 'Charging' ? 'bg-orange-100 text-orange-700 border border-orange-200' :
                        (newRobot.status === 'Maintenance' ? 'bg-rose-100 text-rose-700 border border-rose-200' :
                        'bg-emerald-100 text-emerald-700 border border-emerald-200'))
                    }`;
                }
                if (batBar) {
                    batBar.style.width = `${newRobot.battery_level}%`;
                    batBar.className = `h-1.5 rounded-full ${newRobot.battery_level <= 20 ? 'bg-rose-500' : 'bg-[#3b4cb8]'}`;
                }
                if (batText) batText.textContent = `${newRobot.battery_level}%`;
                if (taskTextDiv) {
                    taskTextDiv.textContent = newRobot.status === 'Delivering' ? 'In delivery mission' : (newRobot.status === 'Charging' ? 'Charging battery' : 'Standby at home base');
                }
            });
        })
        .catch(err => console.error('Error fetching dashboard telemetry:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        setInterval(fetchData, 3000);
    });
</script>
@endsection
