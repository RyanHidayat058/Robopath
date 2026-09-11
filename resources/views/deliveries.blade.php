@extends('layouts.layout')

@section('title', 'ROBOPATH - Delivery Dispatch & Live Tracking')
@section('page_title', 'Deliveries Management')
@section('page_subtitle', 'Dispatch tasks, monitor active deliveries, and trace active units')

@section('styles')
<style>
    .map-container {
        position: relative;
        background-image: url('{{ asset("images/LantaiMerge.jpeg") }}');
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        aspect-ratio: 1800 / 1375;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
    }
    .location-pin {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
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
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Dispatch Panel & Recent Activity (1/3 width) -->
    <div class="space-y-8">
        <!-- Dispatch Form -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-paper-plane text-brand-blue"></i>
                Assign New Delivery
            </h3>
            
            <div id="dispatch-error" class="hidden bg-red-100 border border-red-200 text-red-500 text-xs p-3 rounded-xl mb-4">
                Error message here
            </div>
            
            <form id="dispatch-form" onsubmit="dispatchDelivery(event)" class="space-y-4">
                <!-- Select Robot -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Select Available Robot</label>
                    <select id="dispatch-robot" onchange="updateStartLocation()" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-sky-500 transition" required>
                        <option value="" disabled selected>Choose a robot...</option>
                        @foreach($robots as $robot)
                        <option value="{{ $robot->id }}" 
                                data-status="{{ $robot->status }}" 
                                data-battery="{{ $robot->battery_level }}" 
                                data-x="{{ $robot->current_x }}" 
                                data-y="{{ $robot->current_y }}"
                                @if($robot->status !== 'Idle' || $robot->battery_level <= 20) disabled @endif>
                            {{ $robot->name }} ({{ $robot->status }} - Bat: {{ $robot->battery_level }}%) 
                            @if($robot->status !== 'Idle') [Busy] @elseif($robot->battery_level <= 20) [Low Battery] @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Select Item -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Item to Deliver</label>
                    <select id="dispatch-item" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-sky-500 transition" required>
                        <option value="" disabled selected>Choose an item...</option>
                        <option value="Handuk">Handuk (Towels)</option>
                        <option value="Makanan">Makanan (Food / Meals)</option>
                        <option value="Dokumen">Dokumen (Documents)</option>
                        <option value="Kopi">Kopi (Coffee / Beverage)</option>
                        <option value="Paket">Paket (Postal Package)</option>
                        <option value="Botol Air">Botol Air (Water Bottle)</option>
                        <option value="Sparepart">Sparepart (Replacement Parts)</option>
                    </select>
                </div>

                <!-- Starting Location -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Starting Location</label>
                    <select id="dispatch-start" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-sky-500 transition" required>
                        <option value="" disabled>Choose starting location...</option>
                        <optgroup label="Lantai 1 (Ground Floor)">
                            <option value="1_N7" selected>Base Station (N7 - Lantai 1)</option>
                            @foreach($locations as $id => $coords)
                            @if(($coords['floor'] ?? 1) == 1 && $id !== '1_N7' && (($coords['is_destination'] ?? false) || !($coords['hidden'] ?? false)))
                            <option value="{{ $id }}">{{ $coords['name'] }} (Lantai 1)</option>
                            @endif
                            @endforeach
                        </optgroup>
                        <optgroup label="Lantai 2 (Second Floor)">
                            @foreach($locations as $id => $coords)
                            @if(($coords['floor'] ?? 1) == 2 && (($coords['is_destination'] ?? false) || !($coords['hidden'] ?? false)))
                            <option value="{{ $id }}">{{ $coords['name'] }} (Lantai 2)</option>
                            @endif
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <!-- Destination Location -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Destination Room</label>
                    <select id="dispatch-dest" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-sky-500 transition" required>
                        <option value="" disabled selected>Choose destination...</option>
                        <optgroup label="Lantai 1 (Ground Floor)">
                            @foreach($locations as $id => $coords)
                            @if(($coords['floor'] ?? 1) == 1 && (($coords['is_destination'] ?? false) || !($coords['hidden'] ?? false)))
                            <option value="{{ $id }}">{{ $coords['name'] }} (Lantai 1)</option>
                            @endif
                            @endforeach
                        </optgroup>
                        <optgroup label="Lantai 2 (Second Floor)">
                            @foreach($locations as $id => $coords)
                            @if(($coords['floor'] ?? 1) == 2 && (($coords['is_destination'] ?? false) || !($coords['hidden'] ?? false)))
                            <option value="{{ $id }}">{{ $coords['name'] }} (Lantai 2)</option>
                            @endif
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <button type="submit" class="w-full bg-sky-500 hover:bg-brand-blue text-slate-900/50  font-bold py-3 rounded-xl  hover:shadow-sky-500/50 transition duration-200 text-sm">
                    <i class="fa-solid fa-truck-flatbed mr-1.5"></i> Dispatch Robot
                </button>
            </form>
        </div>

        <!-- Recent Activity Timeline -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2 pb-3 border-b border-gray-200">
                <i class="fa-solid fa-list-check text-brand-blue"></i>
                Recent Activity Timeline
            </h3>
            <div class="space-y-4 overflow-y-auto max-h-[300px] pr-2" id="timeline-container">
                @foreach($recentActivity->take(6) as $act)
                <div class="relative pl-6 border-l border-gray-200">
                    <!-- Glowing indicator dot -->
                    <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full {{ $act->status === 'Completed' ? 'bg-green-500 ' : ($act->status === 'Failed' ? 'bg-rose-400 ' : 'bg-brand-blue  animate-pulse') }}"></span>
                    
                    <span class="text-[10px] text-gray-400 font-semibold block">{{ $act->updated_at->diffForHumans() }}</span>
                    <p class="text-xs font-bold text-gray-800 mt-0.5">
                        {{ $act->robot->name }}
                    </p>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        @if($act->status === 'Completed')
                        Delivered <strong class="text-gray-700">{{ $act->item_name }}</strong> to <strong class="text-gray-700">{{ $act->destination_location }}</strong>
                        @elseif($act->status === 'In Progress')
                        Dispatched carrying <strong class="text-gray-700">{{ $act->item_name }}</strong> to <strong class="text-gray-700">{{ $act->destination_location }}</strong>
                        @else
                        Failed to deliver <strong class="text-gray-700">{{ $act->item_name }}</strong>
                        @endif
                    </p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Right Column: Live Tracker & Current Deliveries (2/3 width) -->
    <div class="lg:col-span-2 space-y-8 lg:sticky lg:top-6 self-start">
        <!-- Live Tracker Map -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-800" id="live-map-title">
                        <i class="fa-solid fa-layer-group text-[#3b4cb8] mr-1"></i> Live Active Tracking - Semua Lantai
                    </h3>
                    <p class="text-xs text-gray-500" id="live-map-subtitle">Semua Lantai (Merged View: Lantai 2 di atas, Lantai 1 di bawah)</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleDeliveriesLabels()" id="btn-toggle-deliv-labels" title="Tampilkan/Sembunyikan Label Nama Ruangan" class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-blue-50 text-[#3b4cb8] border border-blue-200 hover:bg-blue-100 shadow-sm">
                        <i class="fa-solid fa-tags"></i> <span id="deliv-label-text">Label: ON</span>
                    </button>
                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                        <button onclick="switchLiveFloor('all')" id="btn-deliv-fall" class="px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition">
                            Semua Lantai
                        </button>
                        <button onclick="switchLiveFloor(1)" id="btn-deliv-f1" class="px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                            Lantai 1
                        </button>
                        <button onclick="switchLiveFloor(2)" id="btn-deliv-f2" class="px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                            Lantai 2
                        </button>
                    </div>
                </div>
            </div>

            <!-- The Map -->
            <div class="map-container relative overflow-hidden" id="map-container">
                <svg class="path-svg" id="path-svg"></svg>
                
                <!-- Locations pins -->
                <div id="locations-overlay"></div>
                
                <!-- Robot markers -->
                <div id="robots-overlay"></div>
            </div>
        </div>

        <!-- Current Deliveries List -->
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
            <h3 class="text-base font-bold text-gray-800 mb-4 border-b border-gray-200 pb-3">Active Missions</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead>
                        <tr class="text-gray-400 text-xs font-bold uppercase border-b border-gray-200">
                            <th class="py-2.5">Robot</th>
                            <th>Cargo</th>
                            <th>Start Point</th>
                            <th>Destination</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody id="active-deliveries-table-body">
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400 text-xs">No active missions running at the moment.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
    let activeAlerts = [];
    let serverClientOffset = 0;
    let liveCurrentFloor = 'all';
    
    let simulationInterval = null;
    let syncInterval = null;
    let autopilotEnabled = {{ Illuminate\Support\Facades\Cache::get('autopilot_enabled', false) ? 'true' : 'false' }};

    function switchLiveFloor(floorNum) {
        liveCurrentFloor = floorNum;
        const btnAll = document.getElementById('btn-deliv-fall');
        const btnF1 = document.getElementById('btn-deliv-f1');
        const btnF2 = document.getElementById('btn-deliv-f2');
        const map = document.getElementById('map-container');
        const title = document.getElementById('live-map-title');
        const subtitle = document.getElementById('live-map-subtitle');
        
        if (btnAll) btnAll.className = (floorNum === 'all') ? "px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition" : "px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
        if (btnF1) btnF1.className = (floorNum === 1 || floorNum === '1') ? "px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition" : "px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
        if (btnF2) btnF2.className = (floorNum === 2 || floorNum === '2') ? "px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition" : "px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";

        map.style.backgroundImage = `url('${mergedMapImg}')`;

        if (floorNum === 'all') {
            if (title) title.innerHTML = '<i class="fa-solid fa-layer-group text-[#3b4cb8] mr-1"></i> Live Active Tracking - Semua Lantai';
            if (subtitle) subtitle.textContent = 'Semua Lantai (Merged View: Lantai 2 di atas, Lantai 1 di bawah)';
        } else if (Number(floorNum) === 1) {
            if (title) title.innerHTML = '<i class="fa-solid fa-layer-group text-[#3b4cb8] mr-1"></i> Live Active Tracking - Lantai 1';
            if (subtitle) subtitle.textContent = 'Lantai 1 (Ground Floor - Lobby, Office & Receptionist)';
        } else {
            if (title) title.innerHTML = '<i class="fa-solid fa-layer-group text-[#3b4cb8] mr-1"></i> Live Active Tracking - Lantai 2';
            if (subtitle) subtitle.textContent = 'Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms)';
        }
        
        drawLocationPins();
        drawRobotPaths();
        runSimulationStep();
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

    function updateStartLocation() {
        const select = document.getElementById('dispatch-robot');
        if (!select || select.selectedIndex < 0) return;
        const startSelect = document.getElementById('dispatch-start');
        if (!startSelect) return;
        if (!startSelect.value) {
            startSelect.value = '1_Kasir';
        }
    }

    function dispatchDelivery(e) {
        e.preventDefault();
        
        const robotId = document.getElementById('dispatch-robot').value;
        const item = document.getElementById('dispatch-item').value;
        const start = document.getElementById('dispatch-start').value;
        const dest = document.getElementById('dispatch-dest').value;
        const errDiv = document.getElementById('dispatch-error');
        
        errDiv.classList.add('hidden');
        
        if (start === dest) {
            errDiv.textContent = 'Destination must be different from the starting location!';
            errDiv.classList.remove('hidden');
            return;
        }

        const select = document.getElementById('dispatch-robot');
        const selectedOpt = select.options[select.selectedIndex];
        const rx = parseFloat(selectedOpt.getAttribute('data-x'));
        const ry = parseFloat(selectedOpt.getAttribute('data-y'));
        const origin = resolveLocationNodeId(rx, ry);

        fetch('/api/deliveries', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
            if (data.success) {
                const bot = robots.find(r => Number(r.id) === Number(robotId));
                if (bot) bot.status = 'Delivering';
                
                document.getElementById('dispatch-form').reset();
                fetchData();
                reloadPageDropdowns();
            } else {
                errDiv.textContent = data.message || 'Failed to dispatch robot.';
                errDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error dispatching:', err);
            errDiv.textContent = 'A network error occurred. Please try again.';
            errDiv.classList.remove('hidden');
        });
    }

    let showDeliveriesLabels = true;

    function toggleDeliveriesLabels() {
        showDeliveriesLabels = !showDeliveriesLabels;
        const btn = document.getElementById('btn-toggle-deliv-labels');
        const text = document.getElementById('deliv-label-text');
        if (btn) {
            btn.className = showDeliveriesLabels 
                ? 'px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-blue-50 text-[#3b4cb8] border border-blue-200 hover:bg-blue-100 shadow-sm'
                : 'px-2.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200';
        }
        if (text) {
            text.textContent = showDeliveriesLabels ? 'Label: ON' : 'Label: OFF';
        }
        drawLocationPins();
    }

    function drawLocationPins() {
        const overlay = document.getElementById('locations-overlay');
        if (!overlay) return;
        overlay.innerHTML = '';
        
        for (let id in locations) {
            const loc = locations[id];
            if (liveCurrentFloor !== 'all' && Number(loc.floor) !== Number(liveCurrentFloor)) continue;
            
            const isBase = (id === '1_N7');
            const isStairs = id.includes('Stairs');
            const isDest = (loc.is_destination === true || isBase || isStairs);
            if (!isDest && loc.hidden) continue;
            
            const pin = document.createElement('div');
            pin.className = 'location-pin group z-20 cursor-pointer flex flex-col items-center';
            pin.style.left = `${loc.x}%`;
            pin.style.top = `${loc.y}%`;
            
            let pinColor = 'bg-blue-600 ring-2 ring-blue-300';
            let iconMarkup = '<i class="fa-solid fa-location-dot text-[7px] text-white"></i>';
            let labelPrefix = '';

            if (isBase) {
                pinColor = 'bg-amber-500 ring-2 ring-amber-300';
                iconMarkup = '<i class="fa-solid fa-charging-station text-[7px] text-white"></i>';
                labelPrefix = '⚡ ';
            } else if (isStairs) {
                pinColor = 'bg-orange-500 ring-2 ring-orange-300';
                iconMarkup = '<i class="fa-solid fa-stairs text-[7px] text-white"></i>';
                labelPrefix = '🪜 ';
            } else if (Number(loc.floor) === 2) {
                pinColor = 'bg-purple-600 ring-2 ring-purple-300';
                iconMarkup = '<i class="fa-solid fa-location-dot text-[7px] text-white"></i>';
            }
            
            pin.innerHTML = `
                <div class="w-3.5 h-3.5 rounded-full ${pinColor} border border-white shadow transition group-hover:scale-125 flex items-center justify-center">
                    ${iconMarkup}
                </div>
                ${showDeliveriesLabels && isDest ? `
                    <div class="mt-0.5 px-1.5 py-0.5 rounded text-[8px] font-bold tracking-tight bg-white/95 backdrop-blur-sm border border-gray-200 shadow-sm text-gray-800 whitespace-nowrap pointer-events-none select-none transition group-hover:bg-gray-900 group-hover:text-white group-hover:border-gray-900 group-hover:z-30">
                        ${labelPrefix}${loc.name}
                    </div>
                ` : `
                    <div class="absolute bottom-5 left-1/2 -translate-x-1/2 bg-gray-900/90 text-white text-[9px] font-bold px-2 py-0.5 rounded shadow-lg opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none z-30">
                        ${labelPrefix}${loc.name} ${isStairs ? '(Pindah Lantai)' : ''}
                    </div>
                `}
            `;
            
            pin.addEventListener('click', () => {
                const destSelect = document.getElementById('dispatch-dest');
                if (destSelect && destSelect.querySelector(`option[value="${id}"]`)) {
                    destSelect.value = id;
                    destSelect.classList.add('ring-2', 'ring-[#3b4cb8]');
                    setTimeout(() => destSelect.classList.remove('ring-2', 'ring-[#3b4cb8]'), 1200);
                }
            });
            overlay.appendChild(pin);
        }
    }

    function parseServerDate(dateStr) {
        if (!dateStr) return new Date();
        let s = String(dateStr).trim().replace(' ', 'T');
        if (!s.includes('Z') && !s.includes('+') && !s.slice(10).includes('-')) {
            s += 'Z';
        }
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
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const payload = {
            status: status,
            current_x: bx,
            current_y: by,
            floor: floor
        };
        if (batteryLevel !== null && batteryLevel !== undefined) {
            payload.battery_level = Math.round(batteryLevel);
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

    function getDeliveryMission(delivery, robot) {
        if (delivery._cachedMission) return delivery._cachedMission;

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
                    if (st.path && st.path.length > 0) prev.path = [...prev.path, ...st.path.slice(1)];
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

    function getRobotColor(robotId) {
        const id = Number(robotId);
        if (id === 1) return '#0284c7'; // Sky blue for Alpha
        if (id === 2) return '#8b5cf6'; // Purple for Beta
        if (id === 3) return '#f59e0b'; // Amber for Gamma
        return '#10b981';
    }

    function drawRobotPaths() {
        const svg = document.getElementById('path-svg');
        if (!svg) return;
        svg.innerHTML = '';
        
        const now = new Date(new Date().getTime() + serverClientOffset);
        
        // 1. Draw delivery paths (with trimming)
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
                if (liveCurrentFloor !== 'all' && Number(st.floor) !== Number(liveCurrentFloor)) return;
                
                const stageEndMs = st.startMs + st.durationMs;
                if (elapsedMs >= stageEndMs && delivery.status !== 'Pending') return;

                const isCurrentActive = (elapsedMs >= st.startMs && elapsedMs < stageEndMs) || delivery.status === 'Pending';
                const isFutureStage = (elapsedMs < st.startMs);

                const remainingPts = [];
                if (isCurrentActive) {
                    remainingPts.push({ x: robot.current_x, y: robot.current_y });
                    const segIdx = robot.currentSegIdx || 0;
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

                const container = document.getElementById('map-container');
                if (!container) return;
                
                let pointsStr = '';
                remainingPts.forEach(pt => {
                    const px = (pt.x / 100) * container.clientWidth;
                    const py = (pt.y / 100) * container.clientHeight;
                    pointsStr += `${px},${py} `;
                });
                
                if (pointsStr.trim()) {
                    const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                    polyline.setAttribute('points', pointsStr.trim());
                    polyline.setAttribute('stroke', robotColor);
                    polyline.setAttribute('stroke-width', '2.5');
                    polyline.setAttribute('stroke-dasharray', delivery.status === 'Pending' ? '3,3' : '6,6');
                    polyline.setAttribute('fill', 'none');
                    polyline.setAttribute('opacity', delivery.status === 'Pending' ? '0.5' : '0.85');
                    svg.appendChild(polyline);
                }
            });
        });

        // 2. Draw return paths for returning idle robots (with trimming)
        robots.forEach(robot => {
            if (robot.status === 'Idle' && robot.returnMission && robot.returnMission.stages) {
                const robotColor = getRobotColor(robot.id);
                const elapsedMs = now.getTime() - robot.returnMission.startedAt;

                robot.returnMission.stages.forEach(st => {
                    if (st.type !== 'travel' || !st.path || st.path.length < 2) return;
                    if (liveCurrentFloor !== 'all' && Number(st.floor) !== Number(liveCurrentFloor)) return;
                    
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
                    
                    const container = document.getElementById('map-container');
                    if (!container) return;
                    
                    let pointsStr = '';
                    remainingPts.forEach(pt => {
                        const px = (pt.x / 100) * container.clientWidth;
                        const py = (pt.y / 100) * container.clientHeight;
                        pointsStr += `${px},${py} `;
                    });
                    
                    if (pointsStr.trim()) {
                        const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                        polyline.setAttribute('points', pointsStr.trim());
                        polyline.setAttribute('stroke', robotColor);
                        polyline.setAttribute('stroke-width', '2');
                        polyline.setAttribute('stroke-dasharray', '4,4');
                        polyline.setAttribute('fill', 'none');
                        polyline.setAttribute('opacity', '0.85');
                        svg.appendChild(polyline);
                    }
                });
            }
        });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        drawRobotPaths();
        
        const overlay = document.getElementById('robots-overlay');
        if (overlay) overlay.innerHTML = '';
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            let statusColor = 'bg-emerald-500';
            let taskText = 'Standby at base station (N7)';
            
            const robotAlert = activeAlerts.find(a => Number(a.robot_id) === Number(robot.id) && a.status === 'Active');
            const hasIssue = !!robotAlert || robot.status === 'Maintenance' || (delivery?.status === 'Pending');

            if (robot.status === 'Charging' && !hasIssue) {
                statusColor = 'bg-orange-500';
                taskText = `Pengisian Daya di Base N7 (${robot.battery_level}%)...`;
            } else if (robot.status === 'Maintenance' || hasIssue) {
                statusColor = 'bg-rose-500';
                taskText = 'Maintenance required';
            }
            
            if (hasIssue) {
                // Freezes in place while issue is unresolved
                statusColor = 'bg-rose-600';
                const issueName = robotAlert ? robotAlert.issue_type : 'Masalah Operasional';
                taskText = `<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Terjadi Masalah (${issueName}) - Tertahan!</span>`;
            } else if (delivery && (delivery.status === 'In Progress' || delivery.status === 'Pending')) {
                robot.status = 'Delivering';
                robot.isReturning = false;
                robot.returnMission = null;
                statusColor = 'bg-sky-500';
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
                        completeDeliveryAPI(delivery.id, coords.x, coords.y, floorNum);
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
                            taskText = `Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec}s)...`;
                            statusColor = 'bg-amber-500';

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
                            taskText = `Mengambil ${delivery.item_name} di ${locations[mission.startId]?.name || delivery.start_location} (${remainingSec}s)...`;
                            statusColor = 'bg-blue-500';
                            robot.currentSegIdx = 0;
                        } else if (activeStage.type === 'dropoff') {
                            const remainingSec = Math.max(1, Math.ceil((activeStage.durationMs - stageElapsed) / 1000));
                            const locNode = locations[activeStage.nodeId] || locations[mission.destId];
                            if (locNode) {
                                coords = locNode;
                                floorNum = locNode.floor || 1;
                            }
                            taskText = `Menyerahkan ${delivery.item_name} di ${locations[mission.destId]?.name || delivery.destination_location} (${remainingSec}s)...`;
                            statusColor = 'bg-emerald-500';
                            robot.currentSegIdx = 0;
                        } else {
                            floorNum = activeStage.floor || 1;
                            const path = activeStage.path || [];
                            if (path.length >= 2) {
                                const floatIdx = stageRatio * (path.length - 1);
                                const currentSegIdx = Math.max(0, Math.min(Math.floor(floatIdx), path.length - 2));
                                robot.currentSegIdx = currentSegIdx;
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
                                if (p1 && p2) {
                                    coords = interpolate(p1, p2, ratioInSegment);
                                    const dx = p2.x - p1.x;
                                    const dy = p2.y - p1.y;
                                    if (dx !== 0 || dy !== 0) angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                }
                            } else if (path.length === 1 && locations[path[0]]) {
                                coords = locations[path[0]];
                            }
                            const isHeadingToPickup = mission.pickupStartMs && activeStage.startMs < mission.pickupStartMs;
                            if (isHeadingToPickup) {
                                taskText = `Menuju titik ambil: ${locations[mission.startId]?.name || delivery.start_location}`;
                            } else {
                                taskText = `Mengantar ${delivery.item_name} ke ${locations[mission.destId]?.name || delivery.destination_location}`;
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
                    robot.status = (robot.battery_level < 20) ? 'Charging' : 'Idle';
                    taskText = (robot.status === 'Charging') ? `Pengisian Daya di Base N7 (${robot.battery_level}%)...` : 'Standby at base station (N7)';
                    syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, robot.status, robot.battery_level);
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
                        robot.status = 'Idle';
                        taskText = 'Standby at base station (N7)';
                        syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, 'Idle', robot.battery_level);
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
                            taskText = `Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec}s)...`;
                            statusColor = 'bg-amber-500';

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
                                robot.returnSegIdx = currentSegIdx;
                                const ratioInSegment = floatIdx - currentSegIdx;
                                const p1 = locations[path[currentSegIdx]];
                                const p2 = locations[path[currentSegIdx + 1]];
                                if (p1 && p2) {
                                    coords = interpolate(p1, p2, ratioInSegment);
                                    const dx = p2.x - p1.x;
                                    const dy = p2.y - p1.y;
                                    if (dx !== 0 || dy !== 0) angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                }
                            } else if (path.length === 1 && locations[path[0]]) {
                                coords = locations[path[0]];
                            }
                            taskText = `Kembali ke Markas (N7)...`;
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
                            robot.status = (robot.battery_level < 20) ? 'Charging' : 'Idle';
                            taskText = (robot.status === 'Charging') ? `Pengisian Daya di Base N7 (${robot.battery_level}%)...` : 'Standby at base station (N7)';
                            syncRobotBaseLocation(robot.id, baseLoc.x, baseLoc.y, 1, robot.status, robot.battery_level);
                        }
                    }
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
                    } else {
                        robot.isReturning = false;
                        coords = { x: robot.current_x || baseLoc.x, y: robot.current_y || baseLoc.y };
                        floorNum = robot.floor || 1;
                    }
                }
            }
            
            if (overlay && (liveCurrentFloor === 'all' || Number(floorNum) === Number(liveCurrentFloor))) {
                const isTransit = taskText.includes('Transit Tangga');
                const isPickingUp = taskText.includes('Mengambil');
                const isDroppingOff = taskText.includes('Menyerahkan');
                const isReturning = taskText.includes('Kembali ke Markas') || taskText.includes('Selesai antar');

                let ringClass = 'border-gray-300';
                let actionIcon = 'fa-robot';
                let iconColor = 'text-green-600';

                if (hasIssue) {
                    ringClass = 'border-red-500 ring-4 ring-red-400 animate-bounce';
                    actionIcon = 'fa-triangle-exclamation text-red-600';
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
                    actionIcon = 'fa-bolt text-orange-400 animate-pulse';
                } else if (robot.status === 'Maintenance') {
                    ringClass = 'border-red-500 ring-2 ring-red-300';
                    actionIcon = 'fa-wrench text-red-500';
                } else {
                    ringClass = 'border-gray-300';
                    actionIcon = 'fa-robot text-green-600';
                }

                const marker = document.createElement('div');
                marker.className = 'robot-marker z-30';
                marker.style.left = `${coords.x}%`;
                marker.style.top = `${coords.y}%`;
                
                marker.innerHTML = `
                    <div class="relative flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-10 w-10 rounded-full ${hasIssue ? 'bg-red-500 ring-4 ring-red-400' : statusColor} opacity-40"></span>
                        <div class="relative w-8 h-8 rounded-xl bg-white border ${ringClass} flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                            <i class="fa-solid ${actionIcon} text-xs"></i>
                        </div>
                        <div class="absolute -top-6 ${hasIssue ? 'bg-red-600 text-white border-red-700' : 'bg-white/95 text-gray-700 border-gray-200'} border text-[8px] font-bold px-1.5 py-0.5 rounded shadow whitespace-nowrap pointer-events-none">
                            ${robot.name.split(' ')[1]} (${robot.battery_level}%)${hasIssue ? ' [PROBLEM]' : ''}
                        </div>
                    </div>
                `;
                overlay.appendChild(marker);
            }
        });
    }

    function completeDeliveryAPI(deliveryId, finalX, finalY, finalFloor) {
        const delivery = activeDeliveries.find(d => d.id === deliveryId);
        if (!delivery || delivery.isCompleting) return;
        delivery.isCompleting = true;
        
        fetch(`/api/deliveries/${deliveryId}/complete`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
                const robot = robots.find(r => r.id === delivery.robot_id);
                if (robot && data.robot) {
                    robot.status = data.robot.status;
                    robot.current_x = data.robot.current_x;
                    robot.current_y = data.robot.current_y;
                    robot.floor = data.robot.floor;
                }
                fetchData();
                reloadPageDropdowns();
            }
        })
        .catch(err => { console.error('Error completing delivery:', err); delivery.isCompleting = false; });
    }

    function reloadPageDropdowns() {
        const select = document.getElementById('dispatch-robot');
        if (!select) return;
        const currentValue = select.value;
        select.innerHTML = '<option value="" disabled>Choose a robot...</option>';
        robots.forEach(robot => {
            const isBusy = robot.status !== 'Idle' || robot.battery_level <= 20 || robot.isReturning;
            const option = document.createElement('option');
            option.value = robot.id;
            option.textContent = `${robot.name} (${robot.isReturning ? 'Returning' : robot.status} - Bat: ${robot.battery_level}%) ${isBusy ? (robot.isReturning ? '[Returning to N7]' : (robot.status !== 'Idle' ? '[Busy]' : '[Low Battery]')) : ''}`;
            if (isBusy) option.disabled = true;
            if (robot.id.toString() === currentValue) option.selected = true;
            select.appendChild(option);
        });
        updateStartLocation();
    }

    function syncTelemetry() {
        robots.forEach(robot => {
            if (robot.status === 'Delivering' || (robot.status === 'Idle' && !robot.returnPath)) {
                return; // Skip telemetry sync during active deliveries or stationary idle
            }
            
            let nextBattery = robot.battery_level;
            let nextStatus = robot.status;
            
            if (robot.status === 'Charging') {
                nextBattery = Math.min(100, robot.battery_level + 5);
                if (nextBattery === 100) {
                    nextStatus = 'Idle';
                }
            }
            
            fetch(`/api/robots/${robot.id}/telemetry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    status: nextStatus,
                    battery_level: nextBattery
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.robot) {
                    robot.battery_level = data.robot.battery_level;
                    robot.status = data.robot.status;
                }
            })
            .catch(err => console.error('Error syncing telemetry:', err));
        });
    }

    function triggerIncident(robotId, type, desc) {
        fetch('/api/reports', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                robot_id: robotId,
                issue_type: type,
                description: desc
            })
        })
        .then(() => {
            fetchData();
            reloadPageDropdowns();
        });
    }

    function resolveAlertForRobot(robotId, type) {
        fetch('/api/telemetry')
        .then(res => res.json())
        .then(data => {
            const alert = data.active_alerts.find(a => Number(a.robot_id) === Number(robotId) && a.issue_type === type);
            if (alert) {
                fetch(`/api/reports/${alert.id}/resolve`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(() => fetchData());
            }
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
            activeAlerts = data.active_alerts;
            if (typeof data.autopilot_enabled !== 'undefined') {
                autopilotEnabled = !!data.autopilot_enabled;
            }
            
            // Merge robots data keeping local animation properties
            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                const bLoc = locations['1_N7'] || { x: 76.23, y: 64.42, floor: 1 };
                if (Math.hypot((newRobot.current_x || bLoc.x) - bLoc.x, (newRobot.current_y || bLoc.y) - bLoc.y) < 2.0) {
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
                    if (existing.status !== newRobot.status) {
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
            
            updateActiveMissionsTable();
            updateTimeline(data.recent_deliveries);
        })
        .catch(err => console.error('Error fetching:', err));
    }

    function updateTimeline(recentDeliveries) {
        const container = document.getElementById('timeline-container');
        if (!container) return;
        if (!recentDeliveries || recentDeliveries.length === 0) {
            container.innerHTML = `<div class="text-xs text-gray-400 font-medium text-center py-6">No recent activity logged yet.</div>`;
            return;
        }
        
        container.innerHTML = '';
        recentDeliveries.forEach(act => {
            const timeStr = new Date(act.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const isCompleted = act.status === 'Completed';
            const dotColor = isCompleted ? 'bg-green-500 ' : (act.status === 'Failed' ? 'bg-rose-400 ' : 'bg-brand-blue  animate-pulse');
            
            const div = document.createElement('div');
            div.className = 'relative pl-6 border-l border-gray-200';
            div.innerHTML = `
                <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full ${dotColor}"></span>
                <span class="text-[10px] text-gray-400 font-semibold block">${timeStr}</span>
                <p class="text-xs font-bold text-gray-800 mt-0.5">${act.robot.name}</p>
                <p class="text-[11px] text-gray-500 mt-0.5">
                    ${isCompleted 
                        ? `Delivered <strong class="text-gray-700">${act.item_name}</strong> to <strong class="text-gray-700">${act.destination_location}</strong>`
                        : `Dispatched carrying <strong class="text-gray-700">${act.item_name}</strong> to <strong class="text-gray-700">${act.destination_location}</strong>`
                    }
                </p>
            `;
            container.appendChild(div);
        });
    }

    function updateActiveMissionsTable() {
        const tbody = document.getElementById('active-deliveries-table-body');
        if (activeDeliveries.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="py-8 text-center text-gray-400 text-xs">No active missions running at the moment.</td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = '';
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot) return;
            
            const mission = getDeliveryMission(delivery, robot);
            const totalDurationMs = mission?.totalDurationMs || 30000;
            const startedTime = parseServerDate(delivery.started_at);
            const now = new Date(new Date().getTime() + serverClientOffset);
            const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
            const ratio = Math.min(elapsedMs / totalDurationMs, 1.0);
            const pct = Math.round(ratio * 100);
            const startName = locations[delivery.start_location]?.name || delivery.start_location;
            const destName = locations[delivery.destination_location]?.name || delivery.destination_location;

            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-200/50 hover:bg-gray-50/50 text-xs';
            tr.innerHTML = `
                <td class="py-3.5 font-bold text-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue"></span>
                        ${robot.name}
                    </div>
                </td>
                <td class="text-gray-500 font-semibold">${delivery.item_name}</td>
                <td class="text-gray-500 font-semibold">${startName}</td>
                <td class="text-gray-700 font-semibold">${destName}</td>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-20 bg-gray-100 rounded-full h-1.5">
                            <div class="bg-brand-blue h-1.5 rounded-full" style="width: ${pct}%"></div>
                        </div>
                        <span class="font-bold text-brand-blue font-mono">${pct}%</span>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    window.addEventListener('resize', () => {
        drawRobotPaths();
    });

    document.addEventListener('DOMContentLoaded', () => {
        try { localStorage.removeItem('autopilot_enabled'); } catch(e) {}
        drawLocationPins();
        fetchData();
        reloadPageDropdowns();
        
        simulationInterval = setInterval(runSimulationStep, 50);
        
        syncInterval = setInterval(() => {
            syncTelemetry();
            fetchData();
        }, 2000);
    });
</script>
@endsection




