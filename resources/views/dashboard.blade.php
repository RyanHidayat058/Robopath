@extends('layouts.layout')

@section('title', 'ROBOPATH - Dashboard & 3D Live Tracking')
@section('page_title', 'System Overview')
@section('page_subtitle', 'Real-time Robot Tracking & 3D Multi-Floor Metrics')

@section('styles')
<style>
    .stacked-3d-container {
        perspective: 1200px;
        position: relative;
        width: 100%;
        min-height: 520px;
        display: flex;
        flex-direction: column;
        gap: 2rem;
        align-items: center;
        justify-center: center;
        padding: 2rem 0;
    }
    .floor-layer-3d {
        position: relative;
        width: 90%;
        aspect-ratio: 16/9;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        border-radius: 1rem;
        box-shadow: 0 20px 30px rgba(59, 76, 184, 0.15), 0 0 0 1px rgba(0,0,0,0.05);
        transition: transform 0.4s ease, box-shadow 0.4s ease;
        transform: rotateX(50deg) rotateZ(-18deg) translateZ(0);
        transform-style: preserve-3d;
    }
    .floor-layer-3d:hover {
        transform: rotateX(45deg) rotateZ(-12deg) translateZ(20px);
        box-shadow: 0 30px 45px rgba(59, 76, 184, 0.25);
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
    #three-canvas-container {
        width: 100%;
        height: 480px;
        border-radius: 1rem;
        overflow: hidden;
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

<!-- Main Section: Live Map & Robot Roster -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left / Main Column: Live Active Tracking Map (2/3 width) -->
    <div class="lg:col-span-2 space-y-6 lg:sticky lg:top-6 self-start">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-cube text-[#3b4cb8]"></i> 3D Multi-Floor Active Tracking
                    </h3>
                    <p class="text-xs text-gray-500">Floor 1 (Bottom) and Floor 2 (Top) stacked visualization</p>
                </div>

                <!-- Mode Switcher Tabs -->
                <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                    <button onclick="setMapMode('isometric')" id="btn-mode-iso" class="px-4 py-2 rounded-lg bg-[#3b4cb8] text-white shadow transition">
                        <i class="fa-solid fa-[#3b4cb8] fa-layer-group mr-1"></i> Isometric Stacked 2D/3D (60 FPS)
                    </button>
                    <button onclick="setMapMode('three')" id="btn-mode-three" class="px-4 py-2 rounded-lg text-gray-600 hover:text-gray-900 transition">
                        <i class="fa-solid fa-cubes mr-1"></i> Full 3D Model (OFFICE V2.glb)
                    </button>
                </div>
            </div>

            <!-- View 1: Isometric Stacked 2-Floor Container (Default 60 FPS) -->
            <div id="view-isometric" class="space-y-8">
                <!-- Floor 2 (Top Stack Layer) -->
                <div class="bg-blue-50/20 p-4 rounded-2xl border border-blue-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-[#3b4cb8] flex items-center gap-1.5">
                            <i class="fa-solid fa-building text-sm"></i> Lantai 2 (Upper Floor)
                        </span>
                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Floor 2</span>
                    </div>
                    <div class="floor-layer-3d relative overflow-hidden" id="map-container-f2" style="background-image: url('{{ asset('images/floor2.jpeg') }}'); transform: none; aspect-ratio: 16/9; width: 100%;">
                        <svg class="path-svg" id="path-svg-f2"></svg>
                        <div id="robots-overlay-f2"></div>
                    </div>
                </div>

                <!-- Floor 1 (Bottom Stack Layer) -->
                <div class="bg-blue-50/20 p-4 rounded-2xl border border-blue-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-[#3b4cb8] flex items-center gap-1.5">
                            <i class="fa-solid fa-building text-sm"></i> Lantai 1 (Ground Floor)
                        </span>
                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Floor 1</span>
                    </div>
                    <div class="floor-layer-3d relative overflow-hidden" id="map-container-f1" style="background-image: url('{{ asset('images/floor1.jpeg') }}'); transform: none; aspect-ratio: 16/9; width: 100%;">
                        <svg class="path-svg" id="path-svg-f1"></svg>
                        <div id="robots-overlay-f1"></div>
                    </div>
                </div>
            </div>

            <!-- View 2: Full 3D WebGL Model Viewer (OFFICE V2.glb) -->
            <div id="view-three" class="hidden relative">
                <div id="three-canvas-container" class="bg-slate-900 border border-slate-800"></div>
                <div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-md text-white text-[11px] font-semibold px-3 py-1.5 rounded-lg flex items-center gap-2">
                    <i class="fa-solid fa-mouse text-sky-400"></i> Drag to rotate • Scroll to zoom • Right-click to pan
                </div>
            </div>

            <!-- Map Legend -->
            <div class="flex flex-wrap gap-4 mt-6 pt-4 border-t border-gray-200 text-xs text-gray-600 font-semibold">
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
    let currentMapMode = 'isometric';
    let threeInitialized = false;
    let scene, camera, renderer, controls, gltfModel;

    function setMapMode(mode) {
        currentMapMode = mode;
        const vIso = document.getElementById('view-isometric');
        const vThree = document.getElementById('view-three');
        const btnIso = document.getElementById('btn-mode-iso');
        const btnThree = document.getElementById('btn-mode-three');

        if (mode === 'isometric') {
            vIso.classList.remove('hidden');
            vThree.classList.add('hidden');
            btnIso.className = "px-4 py-2 rounded-lg bg-[#3b4cb8] text-white shadow transition font-bold";
            btnThree.className = "px-4 py-2 rounded-lg text-gray-600 hover:text-gray-900 transition font-bold";
        } else {
            vIso.classList.add('hidden');
            vThree.classList.remove('hidden');
            btnIso.className = "px-4 py-2 rounded-lg text-gray-600 hover:text-gray-900 transition font-bold";
            btnThree.className = "px-4 py-2 rounded-lg bg-[#3b4cb8] text-white shadow transition font-bold";

            if (!threeInitialized) {
                initThreeScene();
            }
        }
    }

    function initThreeScene() {
        threeInitialized = true;
        const container = document.getElementById('three-canvas-container');
        if (!container) return;

        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0f172a);

        camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
        camera.position.set(0, 40, 50);

        renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
        renderer.setSize(container.clientWidth, container.clientHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
        container.appendChild(renderer.domElement);

        controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;

        const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
        scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
        dirLight.position.set(20, 40, 20);
        scene.add(dirLight);

        const loader = new THREE.GLTFLoader();
        loader.load("{{ asset('models/office_v2.glb') }}", function(gltf) {
            gltfModel = gltf.scene;
            gltfModel.scale.set(0.1, 0.1, 0.1);
            scene.add(gltfModel);
        }, undefined, function(err) {
            console.error('Error loading OFFICE V2.glb:', err);
        });

        function animateThree() {
            requestAnimationFrame(animateThree);
            controls.update();
            renderer.render(scene, camera);
        }
        animateThree();
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
        const svgF1 = document.getElementById('path-svg-f1');
        const svgF2 = document.getElementById('path-svg-f2');
        if (svgF1) svgF1.innerHTML = '';
        if (svgF2) svgF2.innerHTML = '';
        
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || robot.status !== 'Delivering') return;
            
            const path = getDeliveryPath(delivery, robot);
            if (path.length < 2) return;

            let ptsF1 = '', ptsF2 = '';
            path.forEach((nodeName) => {
                const node = locations[nodeName];
                if (!node) return;
                
                const container = document.getElementById(node.floor === 2 ? 'map-container-f2' : 'map-container-f1');
                if (!container) return;
                const w = container.clientWidth;
                const h = container.clientHeight;
                
                const px = (node.x / 100) * w;
                const py = (node.y / 100) * h;
                
                if (node.floor === 2) ptsF2 += `${px},${py} `;
                else ptsF1 += `${px},${py} `;
            });

            if (ptsF1.trim() && svgF1) {
                const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                poly.setAttribute('points', ptsF1.trim());
                poly.setAttribute('stroke', '#38bdf8');
                poly.setAttribute('stroke-width', '2');
                poly.setAttribute('stroke-dasharray', '5,5');
                poly.setAttribute('fill', 'none');
                svgF1.appendChild(poly);
            }
            if (ptsF2.trim() && svgF2) {
                const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                poly.setAttribute('points', ptsF2.trim());
                poly.setAttribute('stroke', '#38bdf8');
                poly.setAttribute('stroke-width', '2');
                poly.setAttribute('stroke-dasharray', '5,5');
                poly.setAttribute('fill', 'none');
                svgF2.appendChild(poly);
            }
        });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        drawRobotPaths();
        
        const overlayF1 = document.getElementById('robots-overlay-f1');
        const overlayF2 = document.getElementById('robots-overlay-f2');
        if (overlayF1) overlayF1.innerHTML = '';
        if (overlayF2) overlayF2.innerHTML = '';
        
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

            // Render marker on designated floor overlay
            const targetOverlay = floorNum === 2 ? overlayF2 : overlayF1;
            if (targetOverlay) {
                const marker = document.createElement('div');
                marker.className = 'robot-marker z-30';
                marker.style.left = `${coords.x}%`;
                marker.style.top = `${coords.y}%`;
                
                marker.innerHTML = `
                    <div class="relative flex items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-10 w-10 rounded-full ${statusColor} opacity-40"></span>
                        <div class="relative w-8 h-8 rounded-xl bg-white border border-gray-300 flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                            <i class="fa-solid fa-robot text-xs ${robot.status === 'Delivering' ? 'text-[#3b4cb8]' : (robot.status === 'Charging' ? 'text-orange-500' : (robot.status === 'Maintenance' ? 'text-rose-600' : 'text-emerald-600'))}"></i>
                        </div>
                        <div class="absolute -top-6 bg-white/95 text-gray-800 border border-gray-200 text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm whitespace-nowrap pointer-events-none">
                            ${robot.name.split(' ')[1]} (${robot.battery_level}%)
                        </div>
                    </div>
                `;
                targetOverlay.appendChild(marker);
            }

            // Update Robot Card info
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
