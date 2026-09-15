@extends('layouts.layout')

@section('title', 'ROBOPATH - Delivery Dispatch & Live Tracking')
@section('page_title', 'Deliveries Management')
@section('page_subtitle', 'Dispatch tasks, monitor active deliveries, and trace active units')

@section('styles')
<style>
    .map-container {
        position: relative;
        background-color: #0f172a;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        aspect-ratio: 16/9;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    #deliv-3d-canvas-container, #deliv-3d-canvas-f1 {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }
    #deliv-3d-canvas-container canvas, #deliv-3d-canvas-f1 canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
    }
    .location-pin {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
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
                        <i class="fa-solid fa-layer-group text-[#3b4cb8] mr-1"></i> Live Active Tracking - Lantai 1
                    </h3>
                    <p class="text-xs text-gray-500" id="live-map-subtitle">Lantai 1 (Ground Floor - Lobby, Office & Receptionist)</p>
                </div>
                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                    <button onclick="switchLiveFloor(1)" id="btn-deliv-f1" class="px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition">
                        Lantai 1
                    </button>
                    <button onclick="switchLiveFloor(2)" id="btn-deliv-f2" class="px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                        Lantai 2
                    </button>
                </div>
            </div>

            <!-- The Map — kedua lantai murni 3D -->
            <div class="map-container relative overflow-hidden" id="map-container" style="background-color:#0f172a;">
                <!-- 3D Canvas Layer for Floor 1 -->
                <div id="deliv-3d-canvas-f1" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>
                <!-- 3D Canvas Layer for Floor 2 -->
                <div id="deliv-3d-canvas-container" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>

                <!-- 3D Loading Overlay (dipakai bergantian per lantai aktif) -->
                <div id="deliv-3d-loader" class="absolute inset-0 z-30 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center text-white">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#3b4cb8] to-sky-400 p-0.5 shadow-2xl mb-4 animate-bounce">
                        <div class="w-full h-full bg-slate-900 rounded-2xl flex items-center justify-center">
                            <i class="fa-solid fa-cube text-2xl text-sky-400 animate-spin"></i>
                        </div>
                    </div>
                    <h4 class="font-bold text-sm tracking-wide text-gray-100 mb-1" id="deliv-3d-loader-title">Memuat Model 3D Lantai 1...</h4>
                    <p class="text-xs text-gray-400 mb-4" id="deliv-3d-loader-status">Mengunduh aset GLB (8 MB)...</p>
                    <div class="w-56 bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                        <div id="deliv-3d-loader-bar" class="bg-gradient-to-r from-[#3b4cb8] to-sky-400 h-2 rounded-full transition-all duration-200" style="width:5%"></div>
                    </div>
                    <span id="deliv-3d-loader-pct" class="text-[11px] font-mono text-sky-400 font-bold mt-2">5%</span>
                </div>

                <!-- 3D Hint Badge -->
                <div id="deliv-3d-hint" class="hidden absolute bottom-2 right-2 z-30 bg-slate-900/80 backdrop-blur-md text-white px-2.5 py-1 rounded-lg text-[10px] font-semibold border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                    <i class="fa-solid fa-cube text-sky-400"></i> Model 3D Aktif &bull; Putar (Drag) &bull; Zoom (Scroll)
                </div>
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
    const floor1ModelUrl = "{{ asset('models/Denah_Lantai_1-opt.glb') }}";
    const floor2ModelUrl = "{{ asset('models/Lantai_2-final.glb') }}";
    const robotModelUrl = "{{ asset('models/robot.glb') }}";
    const MODEL_CACHE_NAME = 'robopath-glb-cache-v1';
    let threeDeliv = null;
    let threeDelivF1 = null;
    let modelLoadedByFloor = { 1: false, 2: false };
    let labelScaleMultiplier = {{ $labelScale ?? 1.0 }};
    let settings3D = @json($settings3D ?? []);
    let current3DSettings = {
        camera: { dist: parseFloat(settings3D?.camera?.dist ?? 5.0), fov: parseFloat(settings3D?.camera?.fov ?? 5.0), preset: settings3D?.camera?.preset ?? 'iso' },
        lighting: { ambient: parseFloat(settings3D?.lighting?.ambient ?? 1.4), sun: parseFloat(settings3D?.lighting?.sun ?? 1.8), exposure: parseFloat(settings3D?.lighting?.exposure ?? 1.0), fill: parseFloat(settings3D?.lighting?.fill ?? 0.8) },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0),
        robot_scale: parseFloat(settings3D?.robot_scale ?? 0.6)
    };
    // robot template shared (same as dashboard)
    let robotTemplate = null, robotTemplateReady = false, robotTemplateLoading = false, robotTemplateFailed = false;
    let robotTemplateCallbacks = [], robotTemplateTries = 0;
    function activeDelivViewer(){ return Number(liveCurrentFloor)===1 ? threeDelivF1 : threeDeliv; }
    function allDelivViewers(){ return [threeDeliv, threeDelivF1].filter(Boolean); }
    function parkCoordsForFloor(f){ return f===1 ? {x:72.1,y:85.71} : {x:72.3,y:66.3}; }
    function viewerOfHolder(holder){
        if(!holder) return null;
        for(const v of allDelivViewers()){ if(v && v.robotMeshes && v.robotMeshes.has(Number(holder.userData?.robotId))) return v; }
        return activeDelivViewer();
    }

    // Helper: Create room label sprite (compact & sleek)
    function createRoomLabelSprite(text, isDest = true, isStairs = false) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = 384;
        canvas.height = 96;

        const bgFill = isStairs ? 'rgba(217, 119, 6, 0.92)' : (isDest ? 'rgba(15, 23, 42, 0.90)' : 'rgba(30, 41, 59, 0.85)');
        const borderColor = isStairs ? '#fbbf24' : (isDest ? '#38bdf8' : '#94a3b8');

        const radius = 18;
        ctx.fillStyle = bgFill;
        ctx.strokeStyle = borderColor;
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.roundRect(8, 8, canvas.width - 16, canvas.height - 16, radius);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = borderColor;
        ctx.beginPath();
        ctx.arc(32, canvas.height / 2, 7, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 26px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';

        let cleanText = String(text).replace(/^[12]_/, '');
        if (cleanText.length > 20) cleanText = cleanText.substring(0, 18) + '...';
        ctx.fillText(cleanText, 52, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        const spriteMaterial = new THREE.SpriteMaterial({ map: texture, transparent: true, depthTest: false, depthWrite: false });
        const sprite = new THREE.Sprite(spriteMaterial);
        sprite.scale.set(3.6 * labelScaleMultiplier, 0.9 * labelScaleMultiplier, 1);
        sprite.renderOrder = 999;
        return sprite;
    }
    function worldPosForLoc(loc, size){
        const u=(loc._u ?? loc.x/100), v=(loc._v ?? loc.y/100);
        return new THREE.Vector3((u-0.5)*(size.x*0.95),0,(v-0.5)*(size.z*0.95));
    }

    // Helper: Cached GLB buffer loader (with progress cb)
    async function fetchGLBBufferWithCache(url, onProgress) {
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cachedResponse = await cache.match(url);
                if (cachedResponse) {
                    if (onProgress) onProgress(1,1,true);
                    return await cachedResponse.arrayBuffer();
                }
            } catch (e) { console.warn('[Robopath Cache] read bypass', e); }
        }
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const contentLength = response.headers.get('content-length');
        const totalBytes = contentLength ? parseInt(contentLength,10) : 8000000;
        let loadedBytes=0; const reader=response.body.getReader(); const chunks=[];
        while(true){
            const {done,value}=await reader.read();
            if(done) break;
            chunks.push(value); loadedBytes+=value.length;
            if(onProgress) onProgress(loadedBytes,totalBytes,false);
        }
        const all=new Uint8Array(loadedBytes); let pos=0; for(const c of chunks){ all.set(c,pos); pos+=c.length; }
        const buffer=all.buffer;
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const headers=new Headers(); headers.append('Content-Type','model/gltf-binary'); headers.append('Content-Length', String(buffer.byteLength));
                const cacheResponse = new Response(buffer.slice(0), { headers });
                await cache.put(url, cacheResponse);
            } catch (e) {}
        }
        return buffer;
    }
    function ensureRobotTemplate(cb){
        if(robotTemplateReady){ cb(robotTemplate); return; }
        if(robotTemplateFailed){ cb(null); return; }
        robotTemplateCallbacks.push(cb);
        if(robotTemplateLoading) return;
        robotTemplateLoading=true; robotTemplateTries++;
        fetchGLBBufferWithCache(robotModelUrl).then(buf=>{
            const loader=new THREE.GLTFLoader();
            if(typeof THREE.DRACOLoader!=='undefined'){ const d=new THREE.DRACOLoader(); d.setDecoderPath("{{ asset('draco') }}/"); loader.setDRACOLoader(d); }
            loader.parse(buf,'',(gltf)=>{
                const root=gltf.scene;
                const box=new THREE.Box3().setFromObject(root); const sz=box.getSize(new THREE.Vector3()); const ctr=box.getCenter(new THREE.Vector3());
                root.position.x-=ctr.x; root.position.z-=ctr.z; root.position.y-=box.min.y;
                const targetH=0.55; const s=sz.y>0.01?(targetH/sz.y):0.35; root.scale.set(s,s,s);
                root.traverse(c=>{ if(c.isMesh){ c.castShadow=true; c.receiveShadow=true; }});
                robotTemplate=root; robotTemplateReady=true; robotTemplateLoading=false;
                robotTemplateCallbacks.forEach(fn=>{ try{fn(robotTemplate);}catch(e){} }); robotTemplateCallbacks=[];
            },()=>{ robotTemplateLoading=false; if(robotTemplateTries<3) setTimeout(()=>ensureRobotTemplate(()=>{}),1500); else { robotTemplateFailed=true; robotTemplateCallbacks.forEach(fn=>{try{fn(null);}catch(e){}}); robotTemplateCallbacks=[]; }});
        }).catch(e=>{ robotTemplateLoading=false; if(robotTemplateTries<3) setTimeout(()=>ensureRobotTemplate(()=>{}),1500); else { robotTemplateFailed=true; robotTemplateCallbacks.forEach(fn=>{try{fn(null);}catch(e){}}); robotTemplateCallbacks=[]; }});
    }
    // ObjectName anchor: posisi runtime dari Box3 center geometri GLB (GLB = source of truth).
    // Hasil di field runtime _u/_v/_fy — tidak pernah persist ke graph.json. Fallback x/y bila Not found.
    function locUV(loc) {
        return { u: (loc._u ?? loc.x / 100), v: (loc._v ?? loc.y / 100) };
    }
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
        const f = floorNum!=null ? Number(floorNum) : 2;
        let ok = 0; const miss = [];
        for (const id in store) {
            const loc = store[id];
            if (Number(loc.floor) !== f || !loc.objectName) continue;
            if (resolveObjectAnchor(loc, model, size)) ok++;
            else miss.push(id + ' (' + loc.objectName + ')');
        }
        console.log('[Robopath] object anchors resolved:', ok, miss.length ? ('NOT FOUND: ' + miss.join(', ')) : '');
    }

    function initThreeViewer(containerId, floorNum) {
        floorNum = Number(floorNum)===1 ? 1 : 2;
        const modelUrl = floorNum===1 ? floor1ModelUrl : floor2ModelUrl;
        const container = document.getElementById(containerId);
        if (!container) return null;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0f172a);
        const width = container.clientWidth || 800;
        const height = container.clientHeight || 450;

        const initFovVal = parseFloat(current3DSettings.camera.fov ?? 5.0);
        const initFov = 20 + (initFovVal / 10) * 70;
        const camera = new THREE.PerspectiveCamera(initFov, width / height, 0.1, 1000);
        camera.position.set(0, 38, 48);

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
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
        controls.dampingFactor = 0.05;
        controls.maxPolarAngle = Math.PI / 2.05;
        controls.minDistance = 2;
        controls.maxDistance = 200;

        const ambientLight = new THREE.AmbientLight(0xffffff, parseFloat(current3DSettings.lighting.ambient ?? 1.4));
        scene.add(ambientLight);
        const dirLight = new THREE.DirectionalLight(0xffffff, parseFloat(current3DSettings.lighting.sun ?? 1.8));
        dirLight.position.set(30, 50, 30);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 1024;
        dirLight.shadow.mapSize.height = 1024;
        scene.add(dirLight);
        const fillLight = new THREE.DirectionalLight(0x93c5fd, parseFloat(current3DSettings.lighting.fill ?? 0.8));
        fillLight.position.set(-30, 20, -30);
        scene.add(fillLight);
        scene.add(fillLight);

        const grid = new THREE.GridHelper(80, 40, 0x3b4cb8, 0x334155);
        grid.position.y = -0.05;
        scene.add(grid);

        const labelsGroup = new THREE.Group();
        scene.add(labelsGroup);
        const robotsGroup = new THREE.Group();
        scene.add(robotsGroup);
        const robotMeshes = new Map();
        const activePathGroup = new THREE.Group();
        scene.add(activePathGroup);

        let defaultCamTarget = new THREE.Vector3(0,0,0);
        let defaultCamPos = new THREE.Vector3(0,38,48);
        let loadedModel = null;
        let modelSize = new THREE.Vector3();

        function snapRobot3D(holder, worldPct, sz){
            const wp = worldPosForLoc(worldPct, sz);
            holder.position.set(wp.x, 0.02, wp.z);
            if(!holder.userData.targetWp) holder.userData.targetWp=new THREE.Vector3();
            holder.userData.targetWp.copy(holder.position);
            return wp;
        }
        function smoothFaceTowards(holder, deg){
            const rad = -(deg||0)*Math.PI/180;
            holder.rotation.y += (rad - holder.rotation.y)*0.2;
        }
        function updateRobotStatusSprite(holder, robot, delivery, hasIssue, destName){
            if(!holder.userData.statusSprite) return;
            const spr=holder.userData.statusSprite; const tex=spr.material.map; if(!tex || !spr.userData.canvas) return;
            const c=spr.userData.canvas, ctx=c.getContext('2d'); ctx.clearRect(0,0,c.width,c.height);
            let label='', bg='';
            if(hasIssue){ label='⚠ '+(robot._activeIssue||'ISSUE').toString().toUpperCase(); bg='rgba(225,29,72,0.94)'; }
            else if(robot.status==='Delivering' && delivery){ label='▶ MENGANTAR → '+(destName||delivery.destination_location||''); bg='rgba(59,130,246,0.94)'; }
            else if(robot.status==='Charging'){ label='⚡ CHARGING'; bg='rgba(234,88,12,0.94)'; }
            else if(robot.status==='Maintenance'){ label='🔧 MAINTENANCE'; bg='rgba(225,29,72,0.94)'; }
            else { label='● IDLE'; bg='rgba(16,185,129,0.94)'; }
            ctx.font='bold 30px Segoe UI, sans-serif'; const pad=26, h=50, tw=Math.min(512-16, ctx.measureText(label).width+52), radius=h/2;
            const x0=(512-tw)/2, y0=(72-h)/2;
            ctx.fillStyle=bg; ctx.beginPath(); ctx.roundRect(x0,y0,tw,h,radius); ctx.fill();
            ctx.strokeStyle='rgba(255,255,255,0.95)'; ctx.lineWidth=3; ctx.stroke();
            ctx.fillStyle='#fff'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(label,256,36);
            tex.needsUpdate=true; spr.visible=true;
        }
        function getOrCreateRobotMesh(robot){
            const rid=Number(robot.id);
            if(robotMeshes.has(rid)) return robotMeshes.get(rid);
            const holder=new THREE.Group(); holder.userData.robotId=rid;
            const rSc = parseFloat(current3DSettings.robot_scale ?? 0.6);
            holder.scale.set(rSc, rSc, rSc);
            const boxGeo=new THREE.BoxGeometry(0.5,0.5,0.5);
            const boxMat=new THREE.MeshStandardMaterial({color:getRobotColor(rid)});
            const box=new THREE.Mesh(boxGeo, boxMat); box.position.y=0.25; holder.add(box);
            const c2=document.createElement('canvas'); c2.width=512; c2.height=96;
            const sMat=new THREE.SpriteMaterial({map:new THREE.CanvasTexture(c2), transparent:true, depthTest:false, depthWrite:false});
            const nameSprite=new THREE.Sprite(sMat); nameSprite.scale.set(1.4,0.26,1); nameSprite.position.set(0,1.35,0); nameSprite.renderOrder=999;
            nameSprite.userData={canvas:c2, texture:sMat.map}; holder.add(nameSprite); holder.userData.nameSprite=nameSprite;
            const c3=document.createElement('canvas'); c3.width=512; c3.height=72;
            const stMat=new THREE.SpriteMaterial({map:new THREE.CanvasTexture(c3), transparent:true, depthTest:false, depthWrite:false});
            const stSpr=new THREE.Sprite(stMat); stSpr.scale.set(1.35,0.2,1); stSpr.position.set(0,1.78,0); stSpr.renderOrder=1000; stSpr.visible=false;
            stSpr.userData={canvas:c3, texture:stMat.map}; holder.add(stSpr); holder.userData.statusSprite=stSpr;
            holder.userData.boxMesh=box; holder.visible=false;
            const swap=(tpl)=>{
                if(!tpl || !holder.userData.boxMesh) return;
                try{ const clone=tpl.clone(true); clone.traverse(c=>{ if(c.isMesh){ c.castShadow=true; c.receiveShadow=true; }}); holder.remove(holder.userData.boxMesh); holder.add(clone); holder.userData.glbClone=clone; }catch(e){}
            };
            if(robotTemplateReady) swap(robotTemplate); else try{ ensureRobotTemplate(swap);}catch(e){}
            // update name sprite text
            try{ const ctx=c2.getContext('2d'); ctx.clearRect(0,0,512,96); ctx.fillStyle='rgba(15,23,42,0.9)'; ctx.beginPath(); ctx.roundRect(8,8,496,80,18); ctx.fill(); ctx.strokeStyle='#38bdf8'; ctx.lineWidth=3; ctx.stroke(); ctx.fillStyle='#fff'; ctx.font='bold 28px Segoe UI'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(String(robot.name||('Robot '+rid)),256,48); sMat.map.needsUpdate=true; }catch(e){}
            robotsGroup.add(holder); robotMeshes.set(rid, holder); return holder;
        }
        function updateRobot3DAvatar(viewer, robot, coords, destName){
            if(!viewer || !viewer.getModelSize || !viewer.robotMeshes) return false;
            const sz=viewer.getModelSize(); if(!sz||sz.x<=0.1) return false;
            const holder=viewer.getOrCreateRobotMesh(robot); snapRobot3D(holder, coords, sz); holder.visible=true; try{holder.rotation.y=-((robot.rotation||0)*Math.PI/180);}catch(e){}
            const d=(robot.status==='Delivering') ? (robot._activeDelivery||null) : null;
            try{ updateRobotStatusSprite(holder, robot, d, !!robot.hasIssue, destName);}catch(e){}
            return true;
        }

        const loaderEl = document.getElementById('deliv-3d-loader');
        const loaderBar = document.getElementById('deliv-3d-loader-bar');
        const loaderPct = document.getElementById('deliv-3d-loader-pct');
        const loaderStatus = document.getElementById('deliv-3d-loader-status');
        const loaderTitle = document.getElementById('deliv-3d-loader-title');
        if(loaderEl && !modelLoadedByFloor[floorNum]){
            loaderEl.classList.remove('hidden');
            if(loaderTitle) loaderTitle.textContent=`Memuat Model 3D Lantai ${floorNum}...`;
            if(loaderStatus) loaderStatus.textContent=`Mengunduh aset GLB (${floorNum===1?'8':'14'} MB)...`;
            if(loaderBar) loaderBar.style.width='5%';
            if(loaderPct) loaderPct.textContent='5%';
        }

        const gltfLoader = new THREE.GLTFLoader();
        if (typeof THREE.DRACOLoader !== 'undefined') {
            const dracoLoader = new THREE.DRACOLoader();
            dracoLoader.setDecoderPath("{{ asset('draco') }}/");
            gltfLoader.setDRACOLoader(dracoLoader);
        }

        let _delivModel = null;
        let _delivSize = new THREE.Vector3();
        fetchGLBBufferWithCache(modelUrl, (loadedBytes,totalBytes,fromCache)=>{
            if(!loaderEl || Number(currentDashboardFloor||liveCurrentFloor)===floorNum && modelLoadedByFloor[floorNum]) return;
            if(fromCache){ if(loaderBar) loaderBar.style.width='90%'; if(loaderPct) loaderPct.textContent='90%'; if(loaderStatus) loaderStatus.textContent='Memuat dari Cache Lokal (Instan)...'; }
            else { const pct=Math.min(Math.round((loadedBytes/totalBytes)*100),99); if(loaderBar) loaderBar.style.width=pct+'%'; if(loaderPct) loaderPct.textContent=pct+'%'; if(loaderStatus) loaderStatus.textContent=`Mengunduh: ${(loadedBytes/1048576).toFixed(1)} MB / ${(totalBytes/1048576).toFixed(1)} MB`; }
        }).then(buffer => {
            gltfLoader.parse(buffer, '', (gltf) => {
                const model = gltf.scene;
                _delivModel = model; loadedModel=model;
                const box = new THREE.Box3().setFromObject(model);
                const center = box.getCenter(new THREE.Vector3());
                const size = box.getSize(new THREE.Vector3());
                _delivSize.copy(size); modelSize.copy(size);

                model.position.x -= center.x;
                model.position.y -= box.min.y;
                model.position.z -= center.z;
                const mScale = parseFloat(current3DSettings.model_scale ?? 1.0);
                model.scale.set(mScale,mScale,mScale);
                const scaledBox = new THREE.Box3().setFromObject(model);
                const scaledSize = scaledBox.getSize(new THREE.Vector3());
                if(scaledSize.x>0.1) { size.copy(scaledSize); _delivSize.copy(scaledSize); modelSize.copy(scaledSize); }

                model.traverse((child) => {
                    if (child.isMesh) { child.castShadow = true; child.receiveShadow = true; }
                });
                scene.add(model);
                modelLoadedByFloor[floorNum]=true;
                try { resolveAllObjectAnchors(locations, model, _delivSize, floorNum); } catch (e) { console.warn('[Robopath] resolve anchors fail', e); }

                labelsGroup.clear();
                let labelIdx=0;
                for (let id in locations) {
                    const loc = locations[id];
                    if (Number(loc.floor) !== floorNum) continue;
                    const isStairs = id.includes('Stairs');
                    if (!loc.is_destination && !isStairs) continue;
                    const sprite = createRoomLabelSprite(loc.name || id, loc.is_destination, isStairs);
                    const wp = worldPosForLoc(loc, _delivSize);
                    sprite.position.set(wp.x, (_delivSize.y||0.22)+0.32+(labelIdx%5)*0.22, wp.z);
                    labelIdx++; labelsGroup.add(sprite);
                }

                try{
                    const park=parkCoordsForFloor(floorNum); const parkX=park.x, parkY=park.y;
                    robots.forEach((r,idx)=>{
                        const holder=getOrCreateRobotMesh(r);
                        const offX=(idx-(robots.length-1)/2)*2.0;
                        const wp=worldPosForLoc({x:parkX+offX,y:parkY}, _delivSize);
                        holder.position.set(wp.x,0.02,wp.z);
                        if(!holder.userData.targetWp) holder.userData.targetWp=new THREE.Vector3();
                        holder.userData.targetWp.copy(holder.position);
                        holder.rotation.y=-((r.rotation||0)*Math.PI/180);
                        holder.visible=true;
                    });
                }catch(e){}

                const maxDim = Math.max(_delivSize.x, _delivSize.z);
                try{
                    const bbox=new THREE.Box3().setFromObject(model);
                    if(!bbox.isEmpty()){ const c=bbox.getCenter(new THREE.Vector3()); defaultCamTarget.set(c.x,c.y*0.5,c.z); } else defaultCamTarget.set(0,_delivSize.y*0.15,0);
                }catch(e){ defaultCamTarget.set(0,_delivSize.y*0.15,0); }
                defaultCamPos.set(defaultCamTarget.x, defaultCamTarget.y+maxDim*0.45, defaultCamTarget.z+maxDim*0.55);
                const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                const savedDist = 5 + (savedDistVal / 10) * 115;
                const dir0 = defaultCamPos.clone().sub(defaultCamTarget).normalize();
                camera.position.copy(defaultCamTarget).add(dir0.multiplyScalar(savedDist));
                controls.target.copy(defaultCamTarget);
                controls.update();
                if(loaderEl && Number(liveCurrentFloor)===floorNum){
                    if(loaderBar) loaderBar.style.width='100%'; if(loaderPct) loaderPct.textContent='100%'; if(loaderStatus) loaderStatus.textContent='Model siap!';
                    setTimeout(()=>{ if(Number(liveCurrentFloor)===floorNum) loaderEl.classList.add('hidden'); },200);
                }
            }, undefined, (err) => { console.error('Error parsing GLB model Lantai '+floorNum+':', err); if(loaderStatus) loaderStatus.textContent='Gagal memproses model 3D!'; });
        }).catch(err => { console.error('Error fetching GLB:', err); if(loaderStatus) loaderStatus.textContent='Gagal mengunduh aset 3D!'; });

        const raycaster=new THREE.Raycaster(); const mouse=new THREE.Vector2();
        renderer.domElement.addEventListener('click',(e)=>{
            const rect=renderer.domElement.getBoundingClientRect();
            mouse.x=((e.clientX-rect.left)/rect.width)*2-1; mouse.y=-((e.clientY-rect.top)/rect.height)*2+1;
            raycaster.setFromCamera(mouse,camera);
            const targets=[]; robotMeshes.forEach(h=>{ if(h.visible) targets.push(h); });
            const hits=raycaster.intersectObjects(targets,true);
            if(hits.length){
                let obj=hits[0].object; while(obj && obj.parent && !obj.userData.robotId) obj=obj.parent;
                const rid=obj?.userData?.robotId ?? hits[0].object?.parent?.userData?.robotId;
                let holder=obj; while(holder && !robotMeshes.has(Number(holder.userData?.robotId))) holder=holder.parent;
                if(holder && holder.userData.robotId) { if(typeof focusDelivRobot==='function') focusDelivRobot(Number(holder.userData.robotId)); }
                else if(rid && typeof focusDelivRobot==='function') focusDelivRobot(Number(rid));
            }
        });

        let animationFrameId = null;
        function animate() {
            animationFrameId = requestAnimationFrame(animate);
            robotMeshes.forEach(holder=>{ const tgt=holder.userData.targetWp; if(tgt) holder.position.lerp(tgt, 0.25); });
            controls.update();
            renderer.render(scene, camera);
        }
        animate();

        function onResize() {
            if (!container || container.clientWidth === 0) return;
            const w = container.clientWidth;
            const h = container.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }
        window.addEventListener('resize', onResize);

        return { scene,camera,renderer,controls,labelsGroup,robotsGroup,activePathGroup,robotMeshes,getOrCreateRobotMesh,updateRobot3DAvatar,snapRobot3D,resize: onResize, getModelSize:()=>modelSize.clone(), getDefaultCamTarget:()=>defaultCamTarget.clone(), destroy: () => { if (animationFrameId) cancelAnimationFrame(animationFrameId); window.removeEventListener('resize', onResize); renderer.dispose(); } };
    }

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
    let activeAlerts = [];
    let serverClientOffset = 0;
    let liveCurrentFloor = 1;
    
    let simulationInterval = null;
    let syncInterval = null;
    let autopilotEnabled = {{ Illuminate\Support\Facades\Cache::get('autopilot_enabled', false) ? 'true' : 'false' }};

    function switchLiveFloor(floorNum) {
        liveCurrentFloor = floorNum;
        const btnF1 = document.getElementById('btn-deliv-f1');
        const btnF2 = document.getElementById('btn-deliv-f2');
        const map = document.getElementById('map-container');
        const title = document.getElementById('live-map-title');
        const subtitle = document.getElementById('live-map-subtitle');
        const canvas3D = document.getElementById('deliv-3d-canvas-container');
        const canvas3DF1 = document.getElementById('deliv-3d-canvas-f1');
        const hint3D = document.getElementById('deliv-3d-hint');
        const loaderEl = document.getElementById('deliv-3d-loader');
        map.style.backgroundImage = 'none';
        map.style.backgroundColor = '#0f172a';
        if (hint3D) hint3D.classList.remove('hidden');
        if (floorNum === 1) {
            btnF1.className = "px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition";
            btnF2.className = "px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            if (title) title.innerHTML = '<i class="fa-solid fa-cube text-emerald-400 mr-1"></i> Live Active Tracking - Lantai 1 <span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded-full border border-emerald-500/30 ml-1">3D</span>';
            if (subtitle) subtitle.textContent = 'Lantai 1 (Ground Floor - Lobby, Office & Receptionist) [3D Mode]';
            if (canvas3D) canvas3D.classList.add('hidden');
            if (canvas3DF1) {
                canvas3DF1.classList.remove('hidden');
                if (loaderEl && !modelLoadedByFloor[1]) { loaderEl.classList.remove('hidden'); const t=document.getElementById('deliv-3d-loader-title'); if(t) t.textContent='Memuat Model 3D Lantai 1...'; const s=document.getElementById('deliv-3d-loader-status'); if(s) s.textContent='Mengunduh aset GLB (8 MB)...'; }
                setTimeout(() => {
                    if (!threeDelivF1) {
                        threeDelivF1 = initThreeViewer('deliv-3d-canvas-f1', 1);
                    } else {
                        try{ threeDelivF1.resize(); }catch(e){}
                        if(!modelLoadedByFloor[1]){
                            try{ const c=document.getElementById('deliv-3d-canvas-f1'); if(c) c.innerHTML=''; }catch(e){}
                            threeDelivF1 = initThreeViewer('deliv-3d-canvas-f1', 1);
                        }
                    }
                }, 50);
            }
        } else {
            btnF2.className = "px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition";
            btnF1.className = "px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition";
            if (canvas3DF1) canvas3DF1.classList.add('hidden');
            if (canvas3D) {
                canvas3D.classList.remove('hidden');
                if (loaderEl && !modelLoadedByFloor[2]) { loaderEl.classList.remove('hidden'); const t=document.getElementById('deliv-3d-loader-title'); if(t) t.textContent='Memuat Model 3D Lantai 2...'; const s=document.getElementById('deliv-3d-loader-status'); if(s) s.textContent='Mengunduh aset GLB (14 MB)...'; }
                setTimeout(() => {
                    if (!threeDeliv) {
                        threeDeliv = initThreeViewer('deliv-3d-canvas-container', 2);
                    } else {
                        try{ threeDeliv.resize(); }catch(e){}
                        if(!modelLoadedByFloor[2]){
                            try{ const c=document.getElementById('deliv-3d-canvas-container'); if(c) c.innerHTML=''; }catch(e){}
                            threeDeliv = initThreeViewer('deliv-3d-canvas-container', 2);
                        }
                    }
                }, 50);
            }
            if (title) title.innerHTML = '<i class="fa-solid fa-cube text-sky-400 mr-1"></i> Live Active Tracking - Lantai 2 <span class="text-[10px] bg-sky-500/20 text-sky-400 px-1.5 py-0.5 rounded-full border border-sky-500/30 ml-1">3D</span>';
            if (subtitle) subtitle.textContent = 'Lantai 2 (Upper Floor - Direksi, Lounge & Meeting Rooms)';
        }
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
        const selectedOpt = select.options[select.selectedIndex];
        if (!selectedOpt) return;
        const rx = parseFloat(selectedOpt.getAttribute('data-x'));
        const ry = parseFloat(selectedOpt.getAttribute('data-y'));
        const startSelect = document.getElementById('dispatch-start');
        if (!startSelect) return;
        const closestNodeId = resolveLocationNodeId(rx, ry);
        if (closestNodeId && startSelect.querySelector(`option[value="${closestNodeId}"]`)) {
            startSelect.value = closestNodeId;
        } else if (startSelect.querySelector('option[value="1_N7"]')) {
            startSelect.value = '1_N7';
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
        if (delivery._cachedMission) return delivery._cachedMission;

        const startNodeId = getNode(delivery.start_location);
        const destNodeId = getNode(delivery.destination_location);
        
        let originNodeId = getNode(delivery.origin_location);
        if (!originNodeId && robot && robot.current_x && robot.current_y) {
            originNodeId = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1);
        }
        if (!originNodeId || !locations[originNodeId]) originNodeId = '1_N7';

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
                    if (st.path && st.path.length > 0) prev.path = [...prev.path, ...st.path.slice(1)];
                    continue;
                }
            }
            consolidatedStages.push(st);
        }

        let totalTravelSegments = 0;
        consolidatedStages.forEach(st => { if (st.type === 'travel') totalTravelSegments += Math.max(1, (st.path?.length || 1) - 1); });

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

    function getRobotColor(robotId) {
        const id = Number(robotId);
        if (id === 1) return '#0284c7'; // Sky blue for Alpha
        if (id === 2) return '#8b5cf6'; // Purple for Beta
        if (id === 3) return '#f59e0b'; // Amber for Gamma
        return '#10b981';
    }

    function drawRobotPaths() {
        const viewers = allDelivViewers().filter(v=>v&&v.activePathGroup);
        viewers.forEach(v=>v.activePathGroup.clear());
        function drawPath3D(pts, color, dashed){
            if(pts.length<2) return;
            viewers.forEach(v=>{
                const sz=v.getModelSize(); if(!sz||sz.x<=0.1) return;
                const floorNum=v.floor||2;
                const hasPt = pts.some(p=> Number((locations[p]||p).floor||floorNum)===floorNum);
                if(!hasPt && pts[0] && typeof pts[0]==='object' && pts[0].x!=null){
                    // pts includes robot current_x/y which may be on other floor - skip if floor mismatch
                }
                const vecs=pts.map(p=>{
                    const loc = typeof p==='string' ? locations[p] : p;
                    if(!loc) return null;
                    // filter by viewer floor: if loc is node id, check floor
                    if(loc.floor!=null && Number(loc.floor)!==floorNum) return null;
                    const wp=worldPosForLoc(loc, sz);
                    return new THREE.Vector3(wp.x, 0.06, wp.z);
                }).filter(Boolean);
                if(vecs.length<2) return;
                const geo=new THREE.BufferGeometry().setFromPoints(vecs);
                const mat=new THREE.LineDashedMaterial({color:color, linewidth:1, scale:1, dashSize: dashed?0.6:0, gapSize: dashed?0.4:0, transparent:true, opacity:0.9});
                const line=new THREE.Line(geo, mat);
                line.computeLineDistances();
                v.activePathGroup.add(line);
            });
        }
        const now = new Date(new Date().getTime() + serverClientOffset);
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
                let remainingPts=[];
                if (isCurrentActive) {
                    remainingPts.push({ x: robot.current_x, y: robot.current_y, floor: robot.floor });
                    const segIdx = robot.currentSegIdx || 0;
                    for (let i = segIdx + 1; i < st.path.length; i++) if (locations[st.path[i]]) remainingPts.push(st.path[i]);
                } else if (isFutureStage) {
                    st.path.forEach(nodeId => { if (locations[nodeId]) remainingPts.push(nodeId); });
                } else return;
                if (remainingPts.length < 2) return;
                drawPath3D(remainingPts, robotColor, delivery.status==='Pending');
            });
        });
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
                    let remainingPts=[];
                    if (isCurrentActive) {
                        remainingPts.push({ x: robot.current_x, y: robot.current_y, floor: robot.floor });
                        const segIdx = robot.returnSegIdx || 0;
                        for (let i = segIdx + 1; i < st.path.length; i++) if (locations[st.path[i]]) remainingPts.push(st.path[i]);
                    } else if (isFutureStage) {
                        st.path.forEach(nodeId => { if (locations[nodeId]) remainingPts.push(nodeId); });
                    } else return;
                    if (remainingPts.length < 2) return;
                    drawPath3D(remainingPts, robotColor, true);
                });
            }
        });
    }

    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            let coords = { x: robot.current_x, y: robot.current_y };
            let floorNum = robot.floor || 1;
            let statusColor = 'bg-emerald-500';
            let taskText = 'Standby at base station (N7)';
            
            const hasIssue = (robot.status === 'Maintenance' || (robot.status === 'Charging' && robot.battery_level <= 10) || delivery?.status === 'Pending');

            if (robot.status === 'Charging') {
                statusColor = 'bg-orange-500';
                taskText = 'Battery charging';
            } else if (robot.status === 'Maintenance') {
                statusColor = 'bg-rose-500';
                taskText = 'Maintenance required';
            }
            
            if (hasIssue) {
                // Freezes in place while issue is unresolved
                statusColor = 'bg-rose-600';
                if (robot.status === 'Maintenance') {
                    taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Terjadi Masalah / Perlu Diperbaiki</span>';
                } else if (robot.status === 'Charging' && robot.battery_level <= 10) {
                    taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-battery-empty mr-1"></i> Baterai Habis! Pengiriman Tertunda</span>';
                } else {
                    taskText = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-pause mr-1"></i> Tertunda: Masalah Operasional</span>';
                }
            } else if (robot.status === 'Delivering' && delivery && delivery.status === 'In Progress') {
                robot.isReturning = false;
                robot.returnMission = null;
                statusColor = 'bg-sky-500';
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
            } else if (robot.status === 'Idle') {
                const baseLoc = locations['1_N7'] || { x: 80.6, y: 68.48, floor: 1 };
                const distToBase = (Number(robot.floor || 1) === 1) 
                    ? Math.hypot((robot.current_x || baseLoc.x) - baseLoc.x, (robot.current_y || baseLoc.y) - baseLoc.y) 
                    : 999;

                const isAutopilot = autopilotEnabled || localStorage.getItem('autopilot_enabled') === 'true';
                if (!isAutopilot && distToBase > 0.8) {
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
                            taskText = `Transit Tangga ke Lantai ${activeStage.toFloor} (${remainingSec}s)...`;
                            statusColor = 'bg-amber-500';
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
                    }
                } else {
                    robot.isReturning = false;
                    coords = { x: robot.current_x || baseLoc.x, y: robot.current_y || baseLoc.y };
                    floorNum = robot.floor || 1;
                }
            }
            
            // 3D avatar update per floor
            robot._activeDelivery = delivery || null;
            const _activeDeliv = delivery||null;
            const destIdForBadge = (_activeDeliv && _activeDeliv.destination_location) ? _activeDeliv.destination_location : (delivery && delivery.destination_location);
            const destNameForBadge = destIdForBadge ? (locations[destIdForBadge]?.name || destIdForBadge) : null;
            const _knownIssue = hasIssue;
            const _floorNum = Number(floorNum)||1;
            function delivSyncAvatar(viewer, robot, coords, destName){
                if(!viewer || !viewer.getOrCreateRobotMesh) return;
                const sz=viewer.getModelSize ? viewer.getModelSize() : null;
                if(!sz || sz.x<=0.1) return;
                const holder=viewer.getOrCreateRobotMesh(robot);
                const wp=worldPosForLoc(coords, sz);
                holder.position.set(wp.x, 0.02, wp.z);
                if(!holder.userData.targetWp) holder.userData.targetWp=new THREE.Vector3();
                holder.userData.targetWp.copy(holder.position);
                holder.rotation.y=-((robot.rotation||0)*Math.PI/180);
                holder.visible=true;
                try{
                    if(holder.userData.statusSprite && holder.userData.statusSprite.userData.canvas){
                        const spr=holder.userData.statusSprite, c=spr.userData.canvas, ctx=c.getContext('2d'); ctx.clearRect(0,0,c.width,c.height);
                        let label='', bg='';
                        if(_knownIssue){ label='⚠ '+(robot._activeIssue||'ISSUE').toString().toUpperCase(); bg='rgba(225,29,72,0.94)'; }
                        else if(robot.status==='Delivering' && _activeDeliv){ label='▶ MENGANTAR → '+(destName||_activeDeliv.destination_location||''); bg='rgba(59,130,246,0.94)'; }
                        else if(robot.status==='Charging'){ label='⚡ CHARGING'; bg='rgba(234,88,12,0.94)'; }
                        else if(robot.status==='Maintenance'){ label='🔧 MAINTENANCE'; bg='rgba(225,29,72,0.94)'; }
                        else { label='● IDLE'; bg='rgba(16,185,129,0.94)'; }
                        ctx.font='bold 30px Segoe UI, sans-serif'; const pad=26,h=50,tw=Math.min(512-16, ctx.measureText(label).width+52),r=h/2, x0=(512-tw)/2, y0=(72-h)/2;
                        ctx.fillStyle=bg; ctx.beginPath(); if(ctx.roundRect) ctx.roundRect(x0,y0,tw,h,r); else { ctx.moveTo(x0+r,y0); ctx.arcTo(x0+tw,y0,x0+tw,y0+h,r); ctx.arcTo(x0+tw,y0+h,x0,y0+h,r); ctx.arcTo(x0,y0+h,x0,y0,r); ctx.arcTo(x0,y0,x0+tw,y0,r); } ctx.fill();
                        ctx.strokeStyle='rgba(255,255,255,0.95)'; ctx.lineWidth=3; ctx.stroke();
                        ctx.fillStyle='#fff'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(label,256,36);
                        spr.material.map.needsUpdate=true; spr.visible=true;
                    }
                }catch(e){}
            }
            if(_floorNum===2){
                if(threeDeliv) delivSyncAvatar(threeDeliv, robot, coords, destNameForBadge);
                if(threeDelivF1 && threeDelivF1.robotMeshes && threeDelivF1.robotMeshes.has(Number(robot.id))){
                    try{ const h=threeDelivF1.robotMeshes.get(Number(robot.id)); h.visible=false; }catch(e){}
                }
            } else {
                if(threeDelivF1) delivSyncAvatar(threeDelivF1, robot, coords, destNameForBadge);
                if(threeDeliv && threeDeliv.robotMeshes && threeDeliv.robotMeshes.has(Number(robot.id))){
                    try{ const h=threeDeliv.robotMeshes.get(Number(robot.id)); h.visible=false; }catch(e){}
                }
            }
        });
        drawRobotPaths();
        runAutopilotManager();
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

    function runAutopilotManager() {
        const isEnabled = autopilotEnabled || localStorage.getItem('autopilot_enabled') === 'true';
        if (!isEnabled) return;
        
        const idleRobots = robots.filter(r => r.status === 'Idle' && r.battery_level > 20 && !r.isReturning);
        idleRobots.forEach(robot => {
            if (robot.isDispatching || robot.isReturning) return;
            robot.isDispatching = true;
            
            setTimeout(() => {
                if (robot.status !== 'Idle' || robot.isReturning) { robot.isDispatching = false; return; }
                const items = ['Handuk', 'Makanan', 'Dokumen', 'Kopi', 'Paket', 'Botol Air', 'Sparepart'];
                const destinationNodeIds = Object.keys(locations).filter(id => locations[id].is_destination);
                
                if (destinationNodeIds.length < 2) { robot.isDispatching = false; return; }
                const item = items[Math.floor(Math.random() * items.length)];
                let currentLoc = resolveLocationNodeId(robot.current_x, robot.current_y, robot.floor || 1) || '1_N7';
                
                let dest = destinationNodeIds[Math.floor(Math.random() * destinationNodeIds.length)];
                let attempts = 0;
                while (dest === currentLoc && attempts < 10) {
                    dest = destinationNodeIds[Math.floor(Math.random() * destinationNodeIds.length)];
                    attempts++;
                }
                
                fetch('/api/deliveries', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
                    console.error('Error starting autopilot delivery:', err);
                    robot.isDispatching = false;
                });
            }, Math.random() * 2500 + 1500);
        });
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
                    resolveAlertForRobot(robot.id, 'Low Battery');
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
            if (window.activeDeliveries && Array.isArray(window.activeDeliveries)) {
                data.active_deliveries.forEach(newDeliv => {
                    const existing = window.activeDeliveries.find(d => d.id === newDeliv.id);
                    if (existing && existing._cachedPath) {
                        newDeliv._cachedPath = existing._cachedPath;
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
                    existing.floor = newRobot.floor || existing.floor || 1;
                    existing.battery_level = newRobot.battery_level;
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




