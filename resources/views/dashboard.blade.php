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
<div id="standard-view" class="space-y-8">
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

                    <!-- Floor Switcher & Full View Action -->
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                            <button onclick="switchDashboardFloor(1)" id="std-tab-f1" class="px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition">
                                Lantai 1
                            </button>
                            <button onclick="switchDashboardFloor(2)" id="std-tab-f2" class="px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                                Lantai 2
                            </button>
                        </div>

                        <button onclick="toggleFullView(true)" class="bg-[#3b4cb8] hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-md hover:shadow-lg transition duration-200">
                            <i class="fa-solid fa-expand"></i> Full View 2 Lantai
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

                <!-- Map Canvas Container (Proporsional 16:9) -->
                <div class="floor-map-card overflow-hidden shadow-inner border border-gray-200" id="std-map-container" style="background-image: url('{{ asset('images/floor1.jpeg') }}');">
                    <svg class="path-svg" id="std-path-svg"></svg>
                    <div id="std-robots-overlay"></div>
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
        <div class="fullview-floor-box" id="fullview-container-f2" style="background-image: url('{{ asset('images/floor2.jpeg') }}');">
            <div class="absolute top-2 left-2 z-20 bg-black/75 backdrop-blur-sm text-white font-bold text-[10px] px-2.5 py-1 rounded-lg border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                <i class="fa-solid fa-building-user text-sky-400"></i> LANTAI 2 (Upper Floor - Direksi &amp; Meeting Rooms)
            </div>
            <svg class="path-svg" id="fullview-path-svg-f2"></svg>
            <div id="fullview-robots-overlay-f2"></div>
        </div>

        <!-- Floor 1 (Bawah) -->
        <div class="fullview-floor-box" id="fullview-container-f1" style="background-image: url('{{ asset('images/floor1.jpeg') }}');">
            <div class="absolute top-2 left-2 z-20 bg-black/75 backdrop-blur-sm text-white font-bold text-[10px] px-2.5 py-1 rounded-lg border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                <i class="fa-solid fa-building-user text-emerald-400"></i> LANTAI 1 (Ground Floor - Lobby &amp; Office)
            </div>
            <svg class="path-svg" id="fullview-path-svg-f1"></svg>
            <div id="fullview-robots-overlay-f1"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const floor1Img = "{{ asset('images/floor1.jpeg') }}";
    const floor2Img = "{{ asset('images/floor2.jpeg') }}";

    const locations = {
        @foreach($locations as $name => $coords)
        '{{ $name }}': { x: {{ $coords['x'] }}, y: {{ $coords['y'] }}, floor: {{ $coords['floor'] ?? 1 }} },
        @endforeach
    };

    const adj = {
        @foreach($adj as $node => $neighbors)
        '{{ $node }}': [ @foreach($neighbors as $nbr) '{{ $nbr }}', @endforeach ],
        @endforeach
    };

    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);
    let serverClientOffset = 0;
    let currentDashboardFloor = 1;
    let isFullViewMode = false;

    function switchDashboardFloor(floorNum) {
        currentDashboardFloor = floorNum;
        
        const tabF1 = document.getElementById('std-tab-f1');
        const tabF2 = document.getElementById('std-tab-f2');
        const container = document.getElementById('std-map-container');
        const title = document.getElementById('std-floor-title');
        const badge = document.getElementById('std-floor-badge');

        if (floorNum === 1) {
            tabF1.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            tabF2.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            container.style.backgroundImage = `url('${floor1Img}')`;
            title.innerHTML = '<i class="fa-solid fa-building-user"></i> Lantai 1 (Ground Floor - Lobby, Office & Receptionist)';
            badge.textContent = 'Showing Floor 1';
        } else {
            tabF2.className = "px-3.5 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow-sm transition";
            tabF1.className = "px-3.5 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            container.style.backgroundImage = `url('${floor2Img}')`;
            title.innerHTML = '<i class="fa-solid fa-building-user"></i> Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms)';
            badge.textContent = 'Showing Floor 2';
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
        } else {
            fullView.classList.add('hidden');
            stdView.classList.remove('hidden');
            if (mainScroll) mainScroll.scrollTop = 0;
            window.scrollTo(0, 0);
        }

        setTimeout(runSimulationStep, 50);
    }

    function getDeliveryPath(delivery, robot) {
        if (delivery._cachedPath && delivery._cachedPath.length >= 2) {
            return delivery._cachedPath;
        }
        
        const startLoc = delivery.start_location;
        const destLoc = delivery.destination_location;
        let originNode = delivery.origin_location;
        
        if (!originNode || originNode === 'Resepsionis' || !locations[originNode]) {
            if (robot && robot.current_x && robot.current_y) {
                originNode = resolveLocationName(robot.current_x, robot.current_y);
            }
        }
        if (!originNode || !locations[originNode]) {
            originNode = 'Blank Room 2';
        }
        
        const path1 = (originNode !== startLoc) ? findShortestPath(originNode, startLoc) : [startLoc];
        const path2 = findShortestPath(startLoc, destLoc);
        const fullPath = (path1.length > 0 && path2.length > 0) ? [...path1, ...path2.slice(1)] : (path2.length > 0 ? path2 : path1);
        
        delivery._cachedPath = fullPath;
        return fullPath;
    }

    function findShortestPath(start, end) {
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

    function resolveLocationName(x, y) {
        let closestName = 'Blank Room 2';
        let minDst = Infinity;
        for (let name in locations) {
            const loc = locations[name];
            const dst = Math.hypot(loc.x - x, loc.y - y);
            if (dst < minDst) {
                minDst = dst;
                closestName = name;
            }
        }
        return closestName;
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

    function drawRobotPaths() {
        // Standard View SVG
        const stdSvg = document.getElementById('std-path-svg');
        if (stdSvg) stdSvg.innerHTML = '';

        // Fullview Mode SVGs
        const fullSvgF1 = document.getElementById('fullview-path-svg-f1');
        const fullSvgF2 = document.getElementById('fullview-path-svg-f2');
        if (fullSvgF1) fullSvgF1.innerHTML = '';
        if (fullSvgF2) fullSvgF2.innerHTML = '';
        
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || robot.status !== 'Delivering') return;
            
            const path = getDeliveryPath(delivery, robot);
            if (path.length < 2) return;

            if (isFullViewMode) {
                const containerF1 = document.getElementById('fullview-container-f1');
                const containerF2 = document.getElementById('fullview-container-f2');
                let ptsF1 = '', ptsF2 = '';

                path.forEach(nodeName => {
                    const node = locations[nodeName];
                    if (!node) return;
                    const container = node.floor === 2 ? containerF2 : containerF1;
                    if (!container) return;
                    const px = (node.x / 100) * container.clientWidth;
                    const py = (node.y / 100) * container.clientHeight;
                    if (node.floor === 2) ptsF2 += `${px},${py} `;
                    else ptsF1 += `${px},${py} `;
                });

                if (ptsF1.trim() && fullSvgF1) {
                    const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    poly.setAttribute('points', ptsF1.trim());
                    poly.setAttribute('stroke', '#38bdf8');
                    poly.setAttribute('stroke-width', '2');
                    poly.setAttribute('stroke-dasharray', '5,5');
                    poly.setAttribute('fill', 'none');
                    fullSvgF1.appendChild(poly);
                }
                if (ptsF2.trim() && fullSvgF2) {
                    const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    poly.setAttribute('points', ptsF2.trim());
                    poly.setAttribute('stroke', '#38bdf8');
                    poly.setAttribute('stroke-width', '2');
                    poly.setAttribute('stroke-dasharray', '5,5');
                    poly.setAttribute('fill', 'none');
                    fullSvgF2.appendChild(poly);
                }
            } else {
                const stdContainer = document.getElementById('std-map-container');
                if (!stdContainer || !stdSvg) return;
                let pts = '';

                path.forEach(nodeName => {
                    const node = locations[nodeName];
                    if (!node || Number(node.floor) !== Number(currentDashboardFloor)) return;
                    const px = (node.x / 100) * stdContainer.clientWidth;
                    const py = (node.y / 100) * stdContainer.clientHeight;
                    pts += `${px},${py} `;
                });

                if (pts.trim()) {
                    const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    poly.setAttribute('points', pts.trim());
                    poly.setAttribute('stroke', '#38bdf8');
                    poly.setAttribute('stroke-width', '2.5');
                    poly.setAttribute('stroke-dasharray', '6,6');
                    poly.setAttribute('fill', 'none');
                    stdSvg.appendChild(poly);
                }
            }
        });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        drawRobotPaths();
        
        // Standard view overlay
        const stdOverlay = document.getElementById('std-robots-overlay');
        if (stdOverlay) stdOverlay.innerHTML = '';

        // Fullview overlays
        const fullOverlayF1 = document.getElementById('fullview-robots-overlay-f1');
        const fullOverlayF2 = document.getElementById('fullview-robots-overlay-f2');
        if (fullOverlayF1) fullOverlayF1.innerHTML = '';
        if (fullOverlayF2) fullOverlayF2.innerHTML = '';
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && d.status === 'In Progress');
            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            let statusColor = 'bg-emerald-500';
            let taskText = 'Standby at home base';
            let currentLocName = 'Blank Room 2';

            if (robot.status === 'Charging') {
                statusColor = 'bg-orange-500';
                taskText = '<i class="fa-solid fa-bolt text-orange-500 mr-1"></i> Battery charging';
            } else if (robot.status === 'Maintenance') {
                statusColor = 'bg-rose-500';
                taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Maintenance required</span>';
            }
            
            if (robot.status === 'Delivering' && delivery) {
                statusColor = 'bg-sky-500';
                const path = getDeliveryPath(delivery, robot);
                
                if (path.length >= 2) {
                    const totalDurationMs = 30000;
                    const startedTime = parseServerDate(delivery.started_at);
                    const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
                    const ratio = Math.max(0.0, Math.min(elapsedMs / totalDurationMs, 1.0));
                    let angle = 0;
                    
                    if (ratio < 1.0) {
                        const floatIdx = ratio * (path.length - 1);
                        const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                        const ratioInSegment = floatIdx - currentSegIdx;
                        const node1 = path[currentSegIdx];
                        const node2 = path[currentSegIdx + 1];
                        const p1 = locations[node1];
                        const p2 = locations[node2];
                        
                        if (p1 && p2) {
                            coords = interpolate(p1, p2, ratioInSegment);
                            floorNum = p2.floor || p1.floor || 1;
                            const dx = p2.x - p1.x;
                            const dy = p2.y - p1.y;
                            if (dx !== 0 || dy !== 0) {
                                angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                            }
                        } else {
                            coords = p1 || p2 || locations['Blank Room 2'];
                        }
                    } else {
                        const lastNode = locations[path[path.length - 1]];
                        coords = lastNode;
                        floorNum = lastNode.floor || 1;
                    }
                    
                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.rotation = angle;
                    taskText = `Delivering ${delivery.item_name} to ${delivery.destination_location}`;
                    currentLocName = resolveLocationName(coords.x, coords.y);
                }
            }

            // Create Robot marker element
            function createRobotMarker(compact = false) {
                const marker = document.createElement('div');
                marker.className = 'robot-marker z-30';
                marker.style.left = `${coords.x}%`;
                marker.style.top = `${coords.y}%`;
                
                const sizeClass = compact ? 'w-6 h-6' : 'w-8 h-8';
                const pingClass = compact ? 'h-8 w-8' : 'h-10 w-10';
                const iconSize = compact ? 'text-[10px]' : 'text-xs';

                marker.innerHTML = `
                    <div class="relative flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex ${pingClass} rounded-full ${statusColor} opacity-40"></span>
                        <div class="relative ${sizeClass} rounded-lg bg-white border border-gray-300 flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                            <i class="fa-solid fa-robot ${iconSize} ${robot.status === 'Delivering' ? 'text-[#3b4cb8]' : (robot.status === 'Charging' ? 'text-orange-500' : (robot.status === 'Maintenance' ? 'text-rose-600' : 'text-emerald-600'))}"></i>
                        </div>
                        <div class="absolute -top-5 bg-white/95 text-gray-800 border border-gray-200 text-[8px] font-bold px-1.5 py-0.2 rounded shadow-sm whitespace-nowrap pointer-events-none">
                            ${robot.name.split(' ')[1]} (${robot.battery_level}%)
                        </div>
                    </div>
                `;
                return marker;
            }

            // Render on active overlays
            if (isFullViewMode) {
                const targetOverlay = floorNum === 2 ? fullOverlayF2 : fullOverlayF1;
                if (targetOverlay) targetOverlay.appendChild(createRobotMarker(true));
            } else {
                if (Number(floorNum) === Number(currentDashboardFloor)) {
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
                badge.textContent = robot.status;
                badge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                    robot.status === 'Delivering' ? 'bg-blue-100 text-blue-700 border border-blue-200' :
                    (robot.status === 'Charging' ? 'bg-orange-100 text-orange-700 border border-orange-200' :
                    (robot.status === 'Maintenance' ? 'bg-rose-100 text-rose-700 border border-rose-200' :
                    'bg-emerald-100 text-emerald-700 border border-emerald-200'))
                }`;
            }
            if (batBar) {
                batBar.style.width = `${robot.battery_level}%`;
                batBar.className = `h-1.5 rounded-full ${robot.battery_level <= 20 ? 'bg-rose-500' : 'bg-[#3b4cb8]'}`;
            }
            if (batText) batText.textContent = `${robot.battery_level}%`;
            if (locText) locText.textContent = `${currentLocName} (Floor ${floorNum})`;
            if (taskTextDiv) taskTextDiv.innerHTML = taskText;
        });
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
            
            if (window.activeDeliveries && Array.isArray(window.activeDeliveries)) {
                data.active_deliveries.forEach(newDeliv => {
                    const existing = window.activeDeliveries.find(d => d.id === newDeliv.id);
                    if (existing && existing._cachedPath) {
                        newDeliv._cachedPath = existing._cachedPath;
                    }
                });
            }
            activeDeliveries = data.active_deliveries;

            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                if (existing) {
                    if (existing.status !== newRobot.status) {
                        existing.status = newRobot.status;
                        existing.current_x = newRobot.current_x;
                        existing.current_y = newRobot.current_y;
                    }
                    existing.battery_level = newRobot.battery_level;
                } else {
                    robots.push(newRobot);
                }
            });
        })
        .catch(err => console.error('Error fetching dashboard telemetry:', err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        runSimulationStep();
        setInterval(runSimulationStep, 50);
        setInterval(fetchData, 3000);
    });
</script>
@endsection
