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
        @foreach($locations as $id => $loc)
        '{{ $id }}': { 
            id: '{{ $id }}',
            name: '{{ addslashes($loc['name'] ?? $id) }}',
            x: {{ $loc['x'] }}, 
            y: {{ $loc['y'] }}, 
            floor: {{ $loc['floor'] ?? 1 }} 
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

        let rawStages = [];
        if (originNodeId !== validStart) {
            rawStages = [...planRouteBetween(originNodeId, validStart), ...planRouteBetween(validStart, validDest)];
        } else {
            rawStages = planRouteBetween(validStart, validDest);
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
            stages: consolidatedStages,
            totalDurationMs: accumulatedMs
        };

        delivery._cachedMission = mission;
        return mission;
    }

    function drawRobotPaths() {
        const stdSvg = document.getElementById('std-path-svg');
        if (stdSvg) stdSvg.innerHTML = '';

        const fullSvgF1 = document.getElementById('fullview-path-svg-f1');
        const fullSvgF2 = document.getElementById('fullview-path-svg-f2');
        if (fullSvgF1) fullSvgF1.innerHTML = '';
        if (fullSvgF2) fullSvgF2.innerHTML = '';
        
        // 1. Draw paths for active deliveries
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || robot.status !== 'Delivering') return;
            
            const mission = getDeliveryMission(delivery, robot);
            if (!mission || !mission.stages) return;

            mission.stages.forEach(st => {
                if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                
                if (isFullViewMode) {
                    const targetSvg = st.floor === 2 ? fullSvgF2 : fullSvgF1;
                    const container = document.getElementById(st.floor === 2 ? 'fullview-container-f2' : 'fullview-container-f1');
                    if (!targetSvg || !container) return;

                    let pts = '';
                    st.path.forEach(nodeId => {
                        const node = locations[nodeId];
                        if (!node) return;
                        const px = (node.x / 100) * container.clientWidth;
                        const py = (node.y / 100) * container.clientHeight;
                        pts += `${px},${py} `;
                    });

                    if (pts.trim()) {
                        const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                        poly.setAttribute('points', pts.trim());
                        poly.setAttribute('stroke', '#38bdf8');
                        poly.setAttribute('stroke-width', '2');
                        poly.setAttribute('stroke-dasharray', '5,5');
                        poly.setAttribute('fill', 'none');
                        targetSvg.appendChild(poly);
                    }
                } else {
                    if (Number(st.floor) !== Number(currentDashboardFloor)) return;
                    const stdContainer = document.getElementById('std-map-container');
                    if (!stdContainer || !stdSvg) return;

                    let pts = '';
                    st.path.forEach(nodeId => {
                        const node = locations[nodeId];
                        if (!node) return;
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
        });

        // 2. Draw return paths for returning idle robots
        robots.forEach(robot => {
            if (robot.status === 'Idle' && robot.returnMission && robot.returnMission.stages) {
                robot.returnMission.stages.forEach(st => {
                    if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                    
                    if (isFullViewMode) {
                        const targetSvg = st.floor === 2 ? fullSvgF2 : fullSvgF1;
                        const container = document.getElementById(st.floor === 2 ? 'fullview-container-f2' : 'fullview-container-f1');
                        if (!targetSvg || !container) return;

                        let pts = '';
                        st.path.forEach(nodeId => {
                            const node = locations[nodeId];
                            if (!node) return;
                            const px = (node.x / 100) * container.clientWidth;
                            const py = (node.y / 100) * container.clientHeight;
                            pts += `${px},${py} `;
                        });

                        if (pts.trim()) {
                            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                            poly.setAttribute('points', pts.trim());
                            poly.setAttribute('stroke', '#6366f1');
                            poly.setAttribute('stroke-width', '2');
                            poly.setAttribute('stroke-dasharray', '4,4');
                            poly.setAttribute('fill', 'none');
                            targetSvg.appendChild(poly);
                        }
                    } else {
                        if (Number(st.floor) !== Number(currentDashboardFloor)) return;
                        const stdContainer = document.getElementById('std-map-container');
                        if (!stdContainer || !stdSvg) return;

                        let pts = '';
                        st.path.forEach(nodeId => {
                            const node = locations[nodeId];
                            if (!node) return;
                            const px = (node.x / 100) * stdContainer.clientWidth;
                            const py = (node.y / 100) * stdContainer.clientHeight;
                            pts += `${px},${py} `;
                        });

                        if (pts.trim()) {
                            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                            poly.setAttribute('points', pts.trim());
                            poly.setAttribute('stroke', '#6366f1');
                            poly.setAttribute('stroke-width', '2');
                            poly.setAttribute('stroke-dasharray', '4,4');
                            poly.setAttribute('fill', 'none');
                            stdSvg.appendChild(poly);
                        }
                    }
                });
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
            let taskText = 'Standby at base station (N7)';
            let currentLocName = resolveLocationName(coords.x, coords.y, floorNum);

            if (robot.status === 'Charging') {
                statusColor = 'bg-orange-500';
                taskText = '<i class="fa-solid fa-bolt text-orange-500 mr-1"></i> Battery charging';
            } else if (robot.status === 'Maintenance') {
                statusColor = 'bg-rose-500';
                taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Maintenance required</span>';
            }
            
            if (robot.status === 'Delivering' && delivery) {
                statusColor = 'bg-sky-500';
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
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            if (path.length >= 2) {
                                const floatIdx = stageRatio * (path.length - 1);
                                const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
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
                            }
                            taskText = `Delivering ${delivery.item_name} to ${locations[mission.destId]?.name || delivery.destination_location}`;
                            currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                        }
                    }

                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.floor = floorNum;
                    robot.rotation = angle;
                }
            } else if (robot.status === 'Idle') {
                const baseLoc = locations['1_N7'] || { x: 80.6, y: 68.48, floor: 1 };
                const distToBase = (Number(robot.floor || 1) === 1) 
                    ? Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) 
                    : 999;

                if (distToBase > 0.8) {
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
                            statusColor = 'bg-amber-500';
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            if (path.length >= 2) {
                                const floatIdx = stageRatio * (path.length - 1);
                                const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
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
                            }
                            taskText = `<span class="text-indigo-600 font-bold"><i class="fa-solid fa-arrow-rotate-left mr-1"></i> Kembali ke Markas (N7)...</span>`;
                        }

                        robot.current_x = coords.x;
                        robot.current_y = coords.y;
                        robot.floor = floorNum;
                        robot.rotation = angle;
                    }
                    currentLocName = resolveLocationName(coords.x, coords.y, floorNum);
                } else {
                    coords = { x: baseLoc.x, y: baseLoc.y };
                    floorNum = 1;
                    robot.current_x = baseLoc.x;
                    robot.current_y = baseLoc.y;
                    robot.floor = 1;
                    taskText = 'Standby at base station (N7)';
                    currentLocName = 'Base Station (N7)';
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
                const isTransit = taskText.includes('Transit Tangga');
                const isReturning = taskText.includes('Kembali ke Markas');

                marker.innerHTML = `
                    <div class="relative flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex ${pingClass} rounded-full ${statusColor} opacity-40"></span>
                        <div class="relative ${sizeClass} rounded-lg bg-white border ${isTransit ? 'border-amber-400 ring-2 ring-amber-300' : (isReturning ? 'border-indigo-400 ring-2 ring-indigo-300' : 'border-gray-300')} flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                            <i class="fa-solid ${isTransit ? 'fa-stairs text-amber-500 animate-bounce' : (isReturning ? 'fa-arrow-rotate-left text-indigo-600' : 'fa-robot')} ${iconSize} ${robot.status === 'Delivering' && !isTransit ? 'text-[#3b4cb8]' : (robot.status === 'Charging' ? 'text-orange-500' : (robot.status === 'Maintenance' ? 'text-rose-600' : (isTransit ? 'text-amber-500' : (isReturning ? 'text-indigo-600' : 'text-emerald-600'))))}"></i>
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
                const targetOverlay = Number(floorNum) === 2 ? fullOverlayF2 : fullOverlayF1;
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
                if (robot.isReturning) {
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
                    if (existing && existing._cachedMission) {
                        newDeliv._cachedMission = existing._cachedMission;
                    }
                });
            }
            activeDeliveries = data.active_deliveries;

            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                if (existing) {
                    if (existing.status !== newRobot.status) {
                        existing.status = newRobot.status;
                        if (!existing.isReturning) {
                            existing.current_x = newRobot.current_x;
                            existing.current_y = newRobot.current_y;
                        }
                    } else if (!existing.isReturning && existing.status !== 'Delivering') {
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
