@extends('layouts.layout')

@section('title', 'ROBOPATH - Map Editor & Bot Control')
@section('page_title', 'Interactive Map Editor & Fleet Control')
@section('page_subtitle', 'Drag and drop nodes, rename rooms, set hidden transit dots, and manage graph paths')

@section('styles')
<style>
    .editor-map-container {
        position: relative;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        aspect-ratio: 16/9;
        border-radius: 1rem;
        user-select: none;
        box-shadow: 0 4px 20px rgba(59, 76, 184, 0.08), inset 0 0 0 1px rgba(0,0,0,0.06);
    }
    #botctrl-3d-canvas-container {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }
    #botctrl-3d-canvas-container canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
    }
    .editor-node {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: grab;
        z-index: 30;
    }
    .editor-node:active {
        cursor: grabbing;
    }
    .editor-node.selected > div > div:first-child {
        outline: 3px solid #f59e0b !important;
        outline-offset: 3px !important;
        transform: scale(1.3) !important;
    }
    .editor-svg {
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
<div class="space-y-8">

    <!-- Top Control Bar: Floor Selection, Editor Tools, Show/Hide Hidden Dots, & Save Button -->
    <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-wrap items-center justify-between gap-4">
        <!-- Floor Selector Tabs -->
        <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-xl border border-gray-200">
            <button onclick="switchFloor(1)" id="tab-floor-1" class="px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white">
                <i class="fa-solid fa-layer-group mr-1.5"></i> Lantai 1 (Ground Floor)
            </button>
            <button onclick="switchFloor(2)" id="tab-floor-2" class="px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200">
                <i class="fa-solid fa-layer-group mr-1.5"></i> Lantai 2 (Second Floor)
            </button>
        </div>

        <!-- Tool Action Buttons -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                <button onclick="setEditorTool('move')" id="tool-move" class="px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-up-down-left-right"></i> Move Node
                </button>
                <button onclick="setEditorTool('add')" id="tool-add" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-plus-circle"></i> Add Node
                </button>
                <button onclick="setEditorTool('connect')" id="tool-connect" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-diagram-project"></i> Connect Edges
                </button>
                <button onclick="setEditorTool('delete')" id="tool-delete" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>

            <!-- Show/Hide Transit Dots Toggle -->
            <button onclick="toggleShowHiddenDots()" id="btn-toggle-hidden" class="bg-blue-50 border border-blue-300 text-[#3b4cb8] font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition">
                <i class="fa-solid fa-eye text-[#3b4cb8]" id="icon-toggle-hidden"></i> <span id="text-toggle-hidden">Showing All Nodes</span>
            </button>

            <button onclick="saveGraphToServer()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-md hover:shadow-lg transition">
                <i class="fa-solid fa-floppy-disk"></i> Save Graph Map
            </button>
        </div>
    </div>

    <!-- Main Workspace: Interactive Map Canvas (Left) & Inspector (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Interactive Map Canvas (2/3 Width) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-[#3b4cb8]"></i> Visual Map Node Editor
                        </h3>
                        <p class="text-xs text-gray-500" id="editor-hint">Tool: Drag nodes to position them. Click a node to rename or configure pickup/hidden flags.</p>
                    </div>
                    <span class="text-xs font-bold text-[#3b4cb8] bg-blue-50 px-3 py-1 rounded-full border border-blue-200" id="floor-badge">
                        Showing Floor 1
                    </span>
                </div>

                <!-- Editor Canvas Container -->
                <div class="editor-map-container shadow-inner border border-gray-300 overflow-hidden" id="editor-map-container" style="background-image: url('{{ asset('images/floor1.jpeg') }}');" onclick="handleMapClick(event)">
                    <!-- 3D Canvas Layer for Floor 2 -->
                    <div id="botctrl-3d-canvas-container" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>
                    <svg class="editor-svg" id="editor-svg"></svg>
                    <div id="editor-nodes-layer"></div>
                    <!-- 3D Hint -->
                    <div id="botctrl-3d-hint" class="hidden absolute bottom-2 right-2 z-30 bg-slate-900/80 backdrop-blur-md text-white px-2.5 py-1 rounded-lg text-[10px] font-semibold border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                        <i class="fa-solid fa-cube text-sky-400"></i> Model 3D Aktif &bull; Putar (Drag) &bull; Zoom (Scroll)
                    </div>
                </div>
            </div>
        </div>

        <!-- Node Inspector & Configuration Panel (1/3 Width) -->
        <div class="space-y-6">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
                <h3 class="text-base font-bold text-gray-800 mb-4 pb-3 border-b border-gray-200 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-[#3b4cb8]"></i> Node Properties Inspector
                </h3>

                <div class="space-y-4 flex-1 text-xs text-gray-700">
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Node Name / Room Title <span class="text-gray-400 font-normal lowercase">(e.g. Hall, Lobby)</span></label>
                        <input type="text" id="inspect-node-name" onchange="handleRenameNode(this.value)" placeholder="Click a node to edit name..." class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold text-gray-800 focus:bg-white focus:border-[#3b4cb8] focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Floor</label>
                            <select id="inspect-floor" onchange="handleFloorChange(this.value)" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-2 py-2 font-bold text-gray-800 focus:outline-none">
                                <option value="1">Lantai 1</option>
                                <option value="2">Lantai 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">X (%)</label>
                            <input type="number" step="0.1" min="0" max="100" id="inspect-x" oninput="handleCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Y (%)</label>
                            <input type="number" step="0.1" min="0" max="100" id="inspect-y" oninput="handleCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition">
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" id="inspect-is-destination" onchange="handleIsDestinationChange(this.checked)" class="mt-0.5 rounded border-gray-300 text-[#3b4cb8] focus:ring-[#3b4cb8]">
                            <div>
                                <span class="font-bold text-gray-800 block">Use as Pickup / Destination Room</span>
                                <span class="text-[11px] text-gray-500 block">Available in delivery room selection dropdowns</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" id="inspect-hidden" onchange="handleHiddenChange(this.checked)" class="mt-0.5 rounded border-gray-300 text-[#3b4cb8] focus:ring-[#3b4cb8]">
                            <div>
                                <span class="font-bold text-gray-800 block">Hide Marker on Dashboard Map</span>
                                <span class="text-[11px] text-gray-500 block">Functions for routing but hidden on map view</span>
                            </div>
                        </label>
                    </div>

                    <!-- Blender Object Link (Lantai 2 3D) -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-gray-500 uppercase tracking-wider">Blender Object (Lt.2)</label>
                            <span id="inspect-object-status" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">Manual</span>
                        </div>
                        <select id="inspect-object" onchange="handleObjectNameChange(this.value)" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono text-[11px] font-bold text-gray-800 focus:outline-none">
                            <option value="">— Manual (x/y) —</option>
                        </select>
                        <div id="inspect-object-xyz" class="font-mono text-[10px] text-gray-500">—</div>
                        <span class="text-[10px] text-gray-400 block">Posisi runtime dari Box3 center GLB. Drag manual memutus link.</span>
                    </div>

                    <!-- Connected Neighbors (Edges) Manager -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-gray-500 uppercase tracking-wider">Connected Edges</label>
                            <span class="text-[10px] text-gray-400 font-semibold" id="neighbors-count-badge">0 edges</span>
                        </div>
                        <div id="inspect-neighbors" class="bg-gray-50 border border-gray-200 rounded-xl p-3 min-h-[70px] max-h-[160px] overflow-y-auto space-y-1.5">
                            <span class="text-gray-400 italic">No node selected</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fleet Reset Action Card -->
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
                <h3 class="text-base font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-amber-500"></i> Fleet System Controls
                </h3>
                <p class="text-xs text-gray-500 mb-4">Emergency reset all robot units to home base and restore idle status.</p>
                <button onclick="resetSystem()" class="w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 rounded-xl text-xs transition duration-200 shadow-md">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset All Units to Home Base
                </button>
            </div>

            <!-- 3D Object Control Panel (Lantai 2 only) -->
            <div id="panel-3d-controls" class="hidden bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
                <h3 class="text-base font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-cube text-[#3b4cb8]"></i> 3D Object Control
                </h3>
                <p class="text-xs text-gray-500 mb-4">Pilih robot untuk manual drive, atau klik object (node/robot) di canvas 3D untuk editor.</p>

                <div class="space-y-4 text-xs">
                    <!-- ROBOT CONTROL -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-gray-500 uppercase tracking-wider">Robot Control</label>
                            <span id="robot-status-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">-</span>
                        </div>
                        <select id="robot-selector" onchange="setActiveRobot(Number(this.value))" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-bold text-gray-800 focus:outline-none">
                            <option value="">Memuat robot...</option>
                        </select>
                        <div id="robot-active-name" class="font-bold text-gray-800 text-[13px]">-</div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Posisi Runtime (X/Y/Z)</label>
                            <div id="drive-readout" class="bg-slate-900 text-emerald-300 border border-slate-700 rounded-xl px-3 py-2 font-mono text-[11px]">R- (model 3D belum siap)</div>
                        </div>
                        <p class="text-[10px] text-gray-400">W/S=Z world · A/D=X world · Q/E=Y testing (snap saat dilepas) · Shift=4x · tanpa rotasi/collision/route/simpan</p>
                        <button onclick="resetActiveRobotPosition()" class="w-full bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 font-bold py-2 rounded-xl transition text-xs">
                            <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset Posisi Robot Aktif
                        </button>
                    </div>

                    <!-- NODE / OBJECT TERPILIH (editor) -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Node / Object Terpilih (Editor)</label>
                        <div id="selected-3d-info" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 font-mono text-gray-800">Tidak ada object dipilih</div>
                    </div>

                    <!-- D-Pad 4 arah -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1.5">Gerak (X/Z World)</label>
                        <div class="grid grid-cols-3 gap-1.5 max-w-[180px]">
                            <div></div>
                            <button onclick="move3DObject(0, -0.3)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold py-2 rounded-lg transition flex items-center justify-center" title="Atas (-Z)">
                                <i class="fa-solid fa-arrow-up"></i>
                            </button>
                            <div></div>
                            <button onclick="move3DObject(-0.3, 0)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kiri (-X)">
                                <i class="fa-solid fa-arrow-left"></i>
                            </button>
                            <button onclick="move3DObject(0, 0.3)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold py-2 rounded-lg transition flex items-center justify-center" title="Bawah (+Z)">
                                <i class="fa-solid fa-arrow-down"></i>
                            </button>
                            <button onclick="move3DObject(0.3, 0)" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold py-2 rounded-lg transition flex items-center justify-center" title="Kanan (+X)">
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Rotasi -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1.5">Rotasi Y</label>
                        <div class="flex items-center gap-2 mb-2">
                            <button onclick="rotate3DObject(-15)" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-300 text-indigo-700 font-bold py-2 px-3 rounded-lg transition" title="Putar CCW -15°">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button onclick="rotate3DObject(15)" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-300 text-indigo-700 font-bold py-2 px-3 rounded-lg transition" title="Putar CW +15°">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <span id="val-3d-rotation" class="text-[11px] font-mono font-bold text-gray-700 ml-1">0°</span>
                        </div>
                        <input type="range" min="0" max="360" step="1" value="0" id="slider-3d-rotation" oninput="set3DRotation(this.value)" class="w-full accent-[#3b4cb8]">
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-1">
                        <button onclick="reset3DObjectPosition()" class="flex-1 bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 font-bold py-2 rounded-xl transition text-xs">
                            <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset Object Terpilih
                        </button>
                        <button onclick="save3DObjectToGraph()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl transition text-xs">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan ke Graph
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const floor1Img = "{{ asset('images/floor1.jpeg') }}";
    const floor2Img = "{{ asset('images/floor2.jpeg') }}";
    const floor2ModelUrl = "{{ asset('models/Lantai_2-final.glb') }}";
    const MODEL_CACHE_NAME = 'robopath-glb-cache-v1';
    let threeBotCtrl = null;
    let labelScaleMultiplier = {{ $labelScale ?? 1.0 }};
    let settings3D = @json($settings3D ?? []);
    let current3DSettings = {
        camera: { dist: parseFloat(settings3D?.camera?.dist ?? 5.0), fov: parseFloat(settings3D?.camera?.fov ?? 5.0), preset: settings3D?.camera?.preset ?? 'iso' },
        lighting: { ambient: parseFloat(settings3D?.lighting?.ambient ?? 1.4), sun: parseFloat(settings3D?.lighting?.sun ?? 1.8), exposure: parseFloat(settings3D?.lighting?.exposure ?? 1.0), fill: parseFloat(settings3D?.lighting?.fill ?? 0.8) },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0)
    };

    // === 3D Robot Avatar & Node Editor State ===
    const robotModelUrl = "{{ asset('models/robot.glb') }}";
    let robotTemplate = null;
    let robotTemplateReady = false;
    let robotTemplateLoading = false;
    let robotTemplateCallbacks = [];
    let robotMeshes = new Map();
    let nodeMeshes = new Map();
    let robotsGroup = null;
    let nodesGroup = null;
    let edgesGroup = null;
    let selected3DObject = null;
    let dragged3D = null;
    let connectStart3DNode = null;
    const raycaster = new THREE.Raycaster();
    const dragPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
    const dragOffset = new THREE.Vector3();
    let robotsData = @json($robots);

    // Manual drive (WASD/QE, world coords, sesi saja — tidak persist ke mana pun).
    // W/S = world Z, A/D = world X, Q/E = world Y (testing). Shift = 4x.
    const DRIVE_SPEED = 2.0;
    const driveKeys = { w: false, a: false, s: false, d: false, q: false, e: false, shift: false };
    let driveJustReleasedY = false;
    // Active robot: satu-satunya source of truth untuk manual drive, readout, dan reset.
    // Default = Robot #1 bila ada; dropdown/canvas selalu sinkron ke state ini.
    let activeRobotId = null;
    function resolveDefaultRobotId() {
        if (robotMeshes.has(1)) return 1;
        const first = robotMeshes.values().next();
        if (!first.done) return first.value.userData.robotId;
        if (robotsData.length) return Number(robotsData[0].id);
        return null;
    }
    function setActiveRobot(id, opts = {}) {
        id = Number(id);
        if (!id) return;
        activeRobotId = id;
        const sel = document.getElementById('robot-selector');
        if (sel && sel.value !== String(id)) sel.value = String(id);
        if (opts.selectHolder !== false && robotMeshes.has(id)) {
            selected3DObject = robotMeshes.get(id);
            updateSelected3DObjectUI();
        }
        refreshRobotPanel();
        updateDriveReadout();
    }
    function refreshRobotSelector() {
        const sel = document.getElementById('robot-selector');
        if (!sel) return;
        sel.innerHTML = robotsData.map(r =>
            `<option value="${r.id}">Robot #${r.id} ${r.name || ''}</option>`).join('') || '<option value="">(tidak ada robot)</option>';
        if (activeRobotId == null) activeRobotId = resolveDefaultRobotId();
        if (activeRobotId != null) sel.value = String(activeRobotId);
    }
    function refreshRobotPanel() {
        const badge = document.getElementById('robot-status-badge');
        const nameEl = document.getElementById('robot-active-name');
        const r = robotsData.find(x => Number(x.id) === Number(activeRobotId));
        const label = r ? (`Robot #${r.id} ${r.name || ''}`.trim()) : (activeRobotId != null ? ('Robot #' + activeRobotId) : '-');
        if (nameEl) nameEl.textContent = label;
        if (badge) {
            const st = r ? (r.status || 'Unknown') : 'Unknown';
            badge.textContent = st;
            const live = (st === 'Idle' || st === 'Delivering');
            badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full border ' + (live
                ? 'bg-emerald-100 text-emerald-700 border-emerald-300'
                : 'bg-gray-100 text-gray-500 border-gray-200');
        }
    }
    function resetActiveRobotPosition() {
        const t = getDriveTarget();
        if (!t) { alert('Model 3D belum siap.'); return; }
        t.position.set(0, t.position.y, 0);
        t.rotation.y = 0;
        updateDriveReadout();
    }
    function getDriveTarget() {
        if (activeRobotId != null && robotMeshes.has(activeRobotId)) return robotMeshes.get(activeRobotId);
        if (robotMeshes.has(1)) return robotMeshes.get(1);
        const first = robotMeshes.values().next();
        return first.done ? null : first.value;
    }
    function updateDriveReadout() {
        const el = document.getElementById('drive-readout');
        if (!el) return;
        const t = getDriveTarget();
        if (!t) { el.textContent = 'R- (model 3D belum siap)'; return; }
        if (activeRobotId == null) activeRobotId = t.userData.robotId;
        const sel = document.getElementById('robot-selector');
        if (sel && sel.value !== String(activeRobotId)) sel.value = String(activeRobotId);
        refreshRobotPanel();
        const p = t.position;
        const active = Object.keys(driveKeys).filter(k => k !== 'shift' && driveKeys[k]).join('').toUpperCase() || '-';
        el.textContent = `R#${t.userData.robotId} X=${p.x.toFixed(2)} Y=${p.y.toFixed(2)} Z=${p.z.toFixed(2)} [${active}]`;
    }
    window.addEventListener('keydown', (e) => {
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;
        if (e.target && e.target.isContentEditable) return;
        const k = e.key.toLowerCase();
        if (k in driveKeys) { driveKeys[k] = true; updateDriveReadout(); }
    });
    window.addEventListener('keyup', (e) => {
        const k = e.key.toLowerCase();
        if (k in driveKeys) {
            driveKeys[k] = false;
            if (k === 'q' || k === 'e') driveJustReleasedY = true;
            updateDriveReadout();
        }
    });
    // Isi dropdown dari robotsData sejak awal (mesh menyusul saat model 3D siap).
    refreshRobotSelector();
    refreshRobotPanel();

    // Helper: warna robot per ID (mirip dashboard)
    function getRobotColor(robotId) {
        const colors = { 1: '#0284c7', 2: '#8b5cf6', 3: '#f59e0b', 4: '#10b981', 5: '#ec4899' };
        return colors[robotId] || '#3b82f6';
    }

    // Helper: mapping 2D percent -> 3D world (XZ plane, y=0)
    // _u/_v runtime (hasil resolveObjectAnchor dari Box3 GLB) diutamakan; fallback x/y persen.
    function worldPosForLoc(loc, size) {
        const u = (loc._u ?? loc.x / 100), v = (loc._v ?? loc.y / 100);
        return new THREE.Vector3((u - 0.5) * (size.x * 0.95), 0, (v - 0.5) * (size.z * 0.95));
    }

    // Helper: reverse mapping 3D world -> 2D percent
    function locFromWorld(worldX, worldZ, size) {
        const xPct = ((worldX / (size.x * 0.95)) + 0.5) * 100;
        const yPct = ((worldZ / (size.z * 0.95)) + 0.5) * 100;
        return {
            x: Math.max(0, Math.min(100, parseFloat(xPct.toFixed(2)))),
            y: Math.max(0, Math.min(100, parseFloat(yPct.toFixed(2))))
        };
    }

    // ObjectName anchor: posisi runtime dari Box3 center geometri GLB (GLB = source of truth).
    // Hasil di field runtime _u/_v/_fy — tidak pernah persist ke graph.json. Fallback x/y bila Not found.
    const BLENDER_OBJECTS = ['LANTAI2_VIP_ROOM_01','LANTAI2_VICE_PRESIDENT_01','LANTAI2_UPS_01','LANTAI2_TOILET_WANITA_02','LANTAI2_TOILET_WANITA_01','LANTAI2_TOILET_PRIA_02','LANTAI2_TOILET_PRIA_01','LANTAI2_TOILET_DIREKSI_01','LANTAI2_SERVER_01','LANTAI2_PRIVATE_MEETING_01','LANTAI2_PRESDIR_01','LANTAI2_PERPUS_01','LANTAI2_PAYROLL_01','LANTAI2_PANTRY_01','LANTAI2_PANEL_01','LANTAI2_OFFICE_01','LANTAI2_MEETING_07','LANTAI2_MEETING_06','LANTAI2_MEETING_05','LANTAI2_MEETING_04','LANTAI2_MEETING_03','LANTAI2_MEETING_02','LANTAI2_MEETING_01','LANTAI2_LOUNGE_01','LANTAI2_JONATHAN_02','LANTAI2_JONATHAN_01','LANTAI2_GUDANG_SEKERTARIS_01','LANTAI2_GUDANG_JANITOR_01','LANTAI2_GUDANG_DIREKSI_01','LANTAI2_GUDANG_ACCOUNTING_01','LANTAI2_DIREKTUR_02','LANTAI2_DIREKTUR_01','LANTAI2_DIREKSI_01','LANTAI2_DIKRI_02','LANTAI2_DIKRI_01'];
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
    function resolveAllObjectAnchors(store, model, size) {
        let ok = 0; const miss = [];
        for (const id in store) {
            const loc = store[id];
            if (Number(loc.floor) !== 2 || !loc.objectName) continue;
            if (resolveObjectAnchor(loc, model, size)) ok++;
            else miss.push(id + ' (' + loc.objectName + ')');
        }
        console.log('[Robopath] object anchors resolved:', ok, miss.length ? ('NOT FOUND: ' + miss.join(', ')) : '');
    }

    // Helper: load + cache robot.glb template (reuse CacheStorage)
    function ensureRobotTemplate(cb) {
        if (robotTemplateReady) { cb(robotTemplate); return; }
        robotTemplateCallbacks.push(cb);
        if (robotTemplateLoading) return;
        robotTemplateLoading = true;
        fetchGLBBufferWithCache(robotModelUrl).then(buf => {
            const loader = new THREE.GLTFLoader();
            if (typeof THREE.DRACOLoader !== 'undefined') {
                const d = new THREE.DRACOLoader();
                d.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.4.3/');
                loader.setDRACOLoader(d);
            }
            loader.parse(buf, '', (gltf) => {
                const root = gltf.scene;
                const box = new THREE.Box3().setFromObject(root);
                const sz = box.getSize(new THREE.Vector3());
                const ctr = box.getCenter(new THREE.Vector3());
                root.position.x -= ctr.x; root.position.z -= ctr.z; root.position.y -= box.min.y;
                const targetH = 0.55; const s = sz.y > 0.01 ? (targetH / sz.y) : 0.35;
                root.scale.set(s, s, s);
                root.traverse(c => { if (c.isMesh) { c.castShadow = true; c.receiveShadow = true; } });
                robotTemplate = root; robotTemplateReady = true;
                robotTemplateCallbacks.forEach(fn => fn(robotTemplate)); robotTemplateCallbacks = [];
            }, () => { console.warn('[Robopath bot_control] robot.glb parse fail'); robotTemplateLoading = false; });
        }).catch(e => { console.warn('[Robopath bot_control] robot.glb fetch fail', e); robotTemplateLoading = false; });
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

        let cleanText = String(text).replace(/^2_/, '');
        if (cleanText.length > 20) cleanText = cleanText.substring(0, 18) + '...';
        ctx.fillText(cleanText, 52, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        const spriteMaterial = new THREE.SpriteMaterial({ map: texture, transparent: true, depthTest: false, depthWrite: false });
        const sprite = new THREE.Sprite(spriteMaterial);
        sprite.scale.set(3.6 * labelScaleMultiplier, 0.9 * labelScaleMultiplier, 1);
        sprite.renderOrder = 999;
        return sprite;
    }

    // Helper: Cached GLB buffer loader
    async function fetchGLBBufferWithCache(url) {
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cachedResponse = await cache.match(url);
                if (cachedResponse) return await cachedResponse.arrayBuffer();
            } catch (e) {}
        }
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const buffer = await response.arrayBuffer();
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cacheResponse = new Response(buffer.slice(0), {
                    headers: { 'Content-Type': 'model/gltf-binary', 'Content-Length': String(buffer.byteLength) }
                });
                await cache.put(url, cacheResponse);
            } catch (e) {}
        }
        return buffer;
    }

    function initThreeViewer(containerId) {
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

        const grid = new THREE.GridHelper(80, 40, 0x3b4cb8, 0x334155);
        grid.position.y = -0.05;
        scene.add(grid);

        const labelsGroup = new THREE.Group();
        scene.add(labelsGroup);
        robotsGroup = new THREE.Group();
        scene.add(robotsGroup);
        nodesGroup = new THREE.Group();
        scene.add(nodesGroup);
        edgesGroup = new THREE.Group();
        scene.add(edgesGroup);

        // Pre-load robot.glb early
        try { ensureRobotTemplate(() => {}); } catch (e) {}

        // Helper: buat/update mesh node 3D (dot biru) untuk lokasi Lantai 2
        function getOrCreateNodeMesh(nodeId) {
            const id = nodeId;
            if (nodeMeshes.has(id)) return nodeMeshes.get(id);
            const loc = locationsData[id];
            if (!loc) return null;
            const isStairs = id.includes('Stairs');
            const dotColor = loc.hidden ? 0x94a3b8 : (loc.is_destination ? 0x3b4cb8 : (isStairs ? 0xf59e0b : 0x38bdf8));
            const dotSize = (loc.is_destination || !loc.hidden) ? 0.12 : 0.08;
            const geo = new THREE.SphereGeometry(dotSize, 16, 16);
            const mat = new THREE.MeshStandardMaterial({ color: dotColor, emissive: dotColor, emissiveIntensity: 0.3, metalness: 0.2, roughness: 0.6 });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.castShadow = true; mesh.receiveShadow = true;
            mesh.userData.nodeId = id;
            mesh.userData.type = 'node';
            nodesGroup.add(mesh);
            nodeMeshes.set(id, mesh);
            return mesh;
        }

        // Helper: buat/update mesh robot 3D (avatar robot.glb + name badge)
        function getOrCreateRobotMesh(robot) {
            const id = Number(robot.id);
            if (robotMeshes.has(id)) return robotMeshes.get(id);
            const holder = new THREE.Group();
            holder.userData.robotId = id;
            holder.userData.type = 'robot';
            // placeholder box
            const boxMesh = new THREE.Mesh(
                new THREE.BoxGeometry(0.35, 0.5, 0.35),
                new THREE.MeshStandardMaterial({ color: getRobotColor(id) })
            );
            boxMesh.position.y = 0.25; boxMesh.castShadow = true; boxMesh.receiveShadow = true;
            holder.add(boxMesh); holder.userData.boxMesh = boxMesh;
            // name badge sprite
            const c = document.createElement('canvas'); c.width = 256; c.height = 64;
            const cx = c.getContext('2d');
            cx.fillStyle = 'rgba(15,23,42,0.92)'; cx.strokeStyle = getRobotColor(id); cx.lineWidth = 3;
            cx.beginPath(); cx.roundRect(6, 6, 244, 52, 12); cx.fill(); cx.stroke();
            cx.fillStyle = '#fff'; cx.font = 'bold 22px sans-serif'; cx.textAlign = 'center'; cx.textBaseline = 'middle';
            cx.fillText(robot.name || ('Robot ' + id), 128, 32);
            const tex = new THREE.CanvasTexture(c); tex.minFilter = THREE.LinearFilter;
            const spr = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false, depthWrite: false }));
            spr.scale.set(1.2, 0.3, 1); spr.position.set(0, 1.05, 0); spr.renderOrder = 999;
            holder.add(spr);
            robotsGroup.add(holder); robotMeshes.set(id, holder);
            // replace placeholder with glb clone when ready
            ensureRobotTemplate((tpl) => {
                if (!holder.parent) return;
                const clone = tpl.clone(true);
                const col = new THREE.Color(getRobotColor(id));
                clone.traverse(n => {
                    if (n.isMesh && n.material) {
                        n.material = n.material.clone();
                        if (n.material.color) n.material.color.lerp(col, 0.25);
                        n.castShadow = true; n.receiveShadow = true;
                    }
                });
                clone.position.set(0, 0, 0);
                holder.remove(boxMesh); boxMesh.geometry.dispose();
                holder.add(clone); holder.userData.glbClone = clone;
            });
            return holder;
        }

        // Raycaster pick handler (dipanggil dari renderer.domElement)
        function handle3DPick(e) {
            const rect = renderer.domElement.getBoundingClientRect();
            const mouse = new THREE.Vector2(
                ((e.clientX - rect.left) / rect.width) * 2 - 1,
                -((e.clientY - rect.top) / rect.height) * 2 + 1
            );
            raycaster.setFromCamera(mouse, camera);
            // Gabungkan nodes + robots untuk pick
            const pickTargets = [];
            nodesGroup.children.forEach(m => pickTargets.push(m));
            robotsGroup.children.forEach(g => { g.children.forEach(m => { if (m.isMesh) pickTargets.push(m); }); });
            const hits = raycaster.intersectObjects(pickTargets, false);
            if (hits.length > 0) {
                let target = hits[0].object;
                // bila mesh child of robot holder, naik ke holder
                let p = target;
                while (p && !p.userData?.type && p.parent) p = p.parent;
                if (p && p.userData?.type) target = p;
                selected3DObject = target;
                updateSelected3DObjectUI();
                // Klik robot di canvas = shortcut pilih robot: sinkronkan selector + activeRobot.
                if (target && target.userData && target.userData.type === 'robot') {
                    setActiveRobot(target.userData.robotId, { selectHolder: false });
                }
                if (currentTool === 'move') {
                    dragged3D = target;
                    controls.enabled = false;
                    // set dragOffset
                    const hitPoint = hits[0].point;
                    dragOffset.copy(target.position).sub(hitPoint);
                } else if (currentTool === 'connect') {
                    if (target.userData.type === 'node') {
                        const nodeId = target.userData.nodeId;
                        if (!connectStart3DNode) {
                            connectStart3DNode = nodeId;
                        } else if (connectStart3DNode !== nodeId) {
                            if (!adjData[connectStart3DNode]) adjData[connectStart3DNode] = [];
                            if (!adjData[nodeId]) adjData[nodeId] = [];
                            if (!adjData[connectStart3DNode].includes(nodeId)) adjData[connectStart3DNode].push(nodeId);
                            if (!adjData[nodeId].includes(connectStart3DNode)) adjData[nodeId].push(connectStart3DNode);
                            connectStart3DNode = null;
                            build3DEdges();
                            renderEditorMap();
                        }
                    }
                } else if (currentTool === 'delete') {
                    if (target.userData.type === 'node') {
                        const nodeId = target.userData.nodeId;
                        if (confirm('Hapus node "' + nodeId + '"?')) {
                            delete locationsData[nodeId];
                            delete adjData[nodeId];
                            for (let k in adjData) adjData[k] = adjData[k].filter(n => n !== nodeId);
                            nodeMeshes.delete(nodeId);
                            nodesGroup.remove(target);
                            build3DEdges();
                            selected3DObject = null;
                            updateSelected3DObjectUI();
                            renderEditorMap();
                        }
                    }
                }
            } else {
                // klik kosong
                if (currentTool === 'add' && currentFloor === 2) {
                    raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                    const wp = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                    if (wp) {
                        const pct = locFromWorld(wp.x, wp.z, _bcSize);
                        const name = prompt('Nama ruangan baru:', 'Hall_' + Math.floor(Math.random() * 100));
                        if (name && name.trim()) {
                            const clean = name.trim();
                            const key = '2_' + clean;
                            locationsData[key] = { id: key, name: clean, x: pct.x, y: pct.y, floor: 2, hidden: false, is_destination: true };
                            adjData[key] = [];
                            const mesh = getOrCreateNodeMesh(key);
                            if (mesh) { const w = worldPosForLoc(locationsData[key], _bcSize); mesh.position.set(w.x, 0.05, w.z); }
                            selectedNodeId = key;
                            inspectNode(key);
                            build3DEdges();
                            renderEditorMap();
                        }
                    }
                }
            }
        }

        function handle3DDragMove(e) {
            if (!dragged3D) return;
            const rect = renderer.domElement.getBoundingClientRect();
            const mouse = new THREE.Vector2(
                ((e.clientX - rect.left) / rect.width) * 2 - 1,
                -((e.clientY - rect.top) / rect.height) * 2 + 1
            );
            raycaster.setFromCamera(mouse, camera);
            const hitPoint = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
            if (!hitPoint) return;
            const newPos = hitPoint.add(dragOffset);
            dragged3D.position.x = newPos.x;
            dragged3D.position.z = newPos.z;
            // sync ke locationsData bila node (drag manual memutus link objectName Blender)
            if (dragged3D.userData.type === 'node') {
                const nodeId = dragged3D.userData.nodeId;
                const pct = locFromWorld(newPos.x, newPos.z, _bcSize);
                locationsData[nodeId].x = pct.x;
                locationsData[nodeId].y = pct.y;
                if (locationsData[nodeId].objectName) {
                    console.log('[Robopath] link objectName diputus (drag manual):', nodeId);
                    delete locationsData[nodeId].objectName;
                }
                delete locationsData[nodeId]._u;
                delete locationsData[nodeId]._v;
                delete locationsData[nodeId]._fy;
                if (selectedNodeId === nodeId) inspectNode(nodeId);
                build3DEdges();
                renderEditorMap();
            }
        }

        function handle3DDragUp() {
            if (dragged3D) { dragged3D = null; controls.enabled = true; }
        }

        function build3DEdges() {
            if (!edgesGroup) return;
            edgesGroup.clear();
            const y = (_bcSize.y || 0.4) + 0.06;
            const seen = new Set();
            for (const a in adjData) {
                if (!locationsData[a] || Number(locationsData[a].floor) !== 2) continue;
                for (const b of (adjData[a] || [])) {
                    if (!locationsData[b] || Number(locationsData[b].floor) !== 2) continue;
                    const key = [a, b].sort().join('|');
                    if (seen.has(key)) continue; seen.add(key);
                    const pA = worldPosForLoc(locationsData[a], _bcSize); pA.y = y;
                    const pB = worldPosForLoc(locationsData[b], _bcSize); pB.y = y;
                    const geo = new THREE.BufferGeometry().setFromPoints([pA, pB]);
                    const mat = new THREE.LineBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.55 });
                    edgesGroup.add(new THREE.Line(geo, mat));
                }
            }
        }

        // Hook raycaster ke renderer canvas
        renderer.domElement.addEventListener('pointerdown', (e) => { if (currentFloor === 2) handle3DPick(e); });
        window.addEventListener('pointermove', (e) => { if (currentFloor === 2) handle3DDragMove(e); });
        window.addEventListener('pointerup', () => { if (currentFloor === 2) handle3DDragUp(); });


        const gltfLoader = new THREE.GLTFLoader();
        if (typeof THREE.DRACOLoader !== 'undefined') {
            const dracoLoader = new THREE.DRACOLoader();
            dracoLoader.setDecoderPath('https://www.gstatic.com/draco/versioned/decoders/1.4.3/');
            gltfLoader.setDRACOLoader(dracoLoader);
        }

        let _bcModel = null; let _bcSize = new THREE.Vector3();
        fetchGLBBufferWithCache(floor2ModelUrl).then(buffer => {
            gltfLoader.parse(buffer, '', (gltf) => {
                const model = gltf.scene;
                _bcModel = model;
                const box = new THREE.Box3().setFromObject(model);
                const center = box.getCenter(new THREE.Vector3());
                const size = box.getSize(new THREE.Vector3());
                _bcSize.copy(size);

                model.position.x -= center.x;
                model.position.y -= box.min.y;
                model.position.z -= center.z;
                const mScale = parseFloat(current3DSettings.model_scale ?? 1.0);
                model.scale.set(mScale,mScale,mScale);
                const scaledBox = new THREE.Box3().setFromObject(model);
                const scaledSize = scaledBox.getSize(new THREE.Vector3());
                if(scaledSize.x>0.1){ size.copy(scaledSize); _bcSize.copy(scaledSize); }

                model.traverse((child) => {
                    if (child.isMesh) { child.castShadow = true; child.receiveShadow = true; }
                });
                scene.add(model);

                // Resolve posisi destination dari nama object Blender (Box3 center). Fallback x/y bila tak ketemu.
                try { resolveAllObjectAnchors(locationsData, model, _bcSize); } catch (e) { console.warn('[Robopath] resolve anchors fail', e); }

                // Add 3D Room labels — nempel atap (y = roof+0.32)
                for (let id in locationsData) {
                    const loc = locationsData[id];
                    if (Number(loc.floor) !== 2) continue;
                    const isStairs = id.includes('Stairs');
                    if (!loc.is_destination && !isStairs && loc.hidden) continue;

                    const sprite = createRoomLabelSprite(loc.name || id, loc.is_destination, isStairs);
                    const wp0 = worldPosForLoc(loc, _bcSize);
                    const posY = (size.y || 0.22) + 0.32;
                    sprite.position.set(wp0.x, posY, wp0.z);
                    labelsGroup.add(sprite);
                }

                // Eager-create semua node mesh 3D Lantai 2
                for (const id in locationsData) {
                    const loc = locationsData[id];
                    if (Number(loc.floor) !== 2) continue;
                    const mesh = getOrCreateNodeMesh(id);
                    if (mesh) {
                        const wp = worldPosForLoc(loc, _bcSize);
                        mesh.position.set(wp.x, 0.05, wp.z);
                    }
                }
                build3DEdges();

                // Eager-create semua robot mesh (avatar robot.glb)
                robotsData.forEach(r => {
                    const holder = getOrCreateRobotMesh(r);
                    const wp = worldPosForLoc({ x: r.current_x ?? 80.6, y: r.current_y ?? 68.48 }, _bcSize);
                    holder.position.set(wp.x, 0.02, wp.z);
                    holder.rotation.y = -((r.rotation || 0) * Math.PI / 180);
                    holder.visible = (Number(r.floor) === 2);
                });
                console.log('[Robopath bot_control] nodeMeshes:', nodeMeshes.size, 'robotMeshes:', robotMeshes.size);
                refreshRobotSelector();
                updateDriveReadout();

                const maxDim = Math.max(size.x, size.z);
                const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                const savedDist = 5 + (savedDistVal / 10) * 115;
                const dir0 = new THREE.Vector3(0, maxDim*0.45, maxDim*0.55).normalize();
                camera.position.copy(dir0.multiplyScalar(savedDist));
                controls.target.set(0, size.y * 0.15, 0);
                controls.update();
            }, undefined, (err) => console.error('Error parsing GLB model Lantai 2:', err));
        }).catch(err => console.error('Error fetching GLB:', err));

        let animationFrameId = null;
        const driveClock = new THREE.Clock();
        function snapHolderToFloor(holder) {
            if (!holder || !_bcModel) return;
            try {
                const rc = new THREE.Raycaster(
                    new THREE.Vector3(holder.position.x, holder.position.y + 5, holder.position.z),
                    new THREE.Vector3(0, -1, 0), 0, 20);
                const hits = rc.intersectObject(_bcModel, true);
                if (hits.length) holder.position.y = hits[0].point.y;
            } catch (e) {}
        }
        function stepManualDrive() {
            const dt = Math.min(driveClock.getDelta(), 0.05);
            if (Number(currentFloor) !== 2) return;
            const t = getDriveTarget();
            if (!t) return;
            const sp = DRIVE_SPEED * (driveKeys.shift ? 4 : 1);
            let mx = 0, mz = 0, my = 0;
            if (driveKeys.a) mx -= 1;
            if (driveKeys.d) mx += 1;
            if (driveKeys.w) mz -= 1;
            if (driveKeys.s) mz += 1;
            if (driveKeys.q) my -= 1;
            if (driveKeys.e) my += 1;
            const yHeld = driveKeys.q || driveKeys.e;
            if (mx || mz || my) {
                t.position.x += mx * sp * dt;
                t.position.z += mz * sp * dt;
                t.position.y += my * sp * dt;
                // clamp XZ ke bounds model (tidak ada collision)
                const bx = (_bcSize.x * 0.95) / 2, bz = (_bcSize.z * 0.95) / 2;
                t.position.x = Math.max(-bx, Math.min(bx, t.position.x));
                t.position.z = Math.max(-bz, Math.min(bz, t.position.z));
                if (!yHeld) snapHolderToFloor(t);
                updateDriveReadout();
            } else if (driveJustReleasedY && !yHeld) {
                // Q/E baru dilepas → snap kembali ke floor
                driveJustReleasedY = false;
                snapHolderToFloor(t);
                updateDriveReadout();
            }
        }
        function animate() {
            animationFrameId = requestAnimationFrame(animate);
            stepManualDrive();
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

        return {
            resize: onResize,
            destroy: () => { if (animationFrameId) cancelAnimationFrame(animationFrameId); window.removeEventListener('resize', onResize); renderer.dispose(); },
            scene, camera, renderer, controls,
            robotsGroup, nodesGroup, edgesGroup,
            robotMeshes, nodeMeshes,
            getOrCreateRobotMesh, getOrCreateNodeMesh, build3DEdges,
            getModelSize: () => _bcSize
        };
    }

    let currentFloor = 1;
    let currentTool = 'move';
    let showHiddenDots = true;
    let selectedNodeId = null;
    let connectStartNodeId = null;
    let draggedNodeId = null;

    let locationsData = @json($locations);
    let adjData = @json($adj);

    function toggleShowHiddenDots() {
        showHiddenDots = !showHiddenDots;
        const icon = document.getElementById('icon-toggle-hidden');
        const text = document.getElementById('text-toggle-hidden');
        const btn = document.getElementById('btn-toggle-hidden');

        if (showHiddenDots) {
            icon.className = "fa-solid fa-eye text-[#3b4cb8]";
            text.textContent = "Showing All Nodes";
            btn.className = "bg-blue-50 border border-blue-300 text-[#3b4cb8] font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
        } else {
            icon.className = "fa-solid fa-eye-slash text-gray-600";
            text.textContent = "Show Hidden Transit Nodes";
            btn.className = "bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
        }
        renderEditorMap();
    }

    // === 3D Object Control Functions ===
    function updateSelected3DObjectUI() {
        const info = document.getElementById('selected-3d-info');
        const slider = document.getElementById('slider-3d-rotation');
        const valRot = document.getElementById('val-3d-rotation');
        const inpX = document.getElementById('input-3d-x');
        const inpZ = document.getElementById('input-3d-z');
        if (!info) return;
        if (!selected3DObject) {
            info.textContent = 'Tidak ada object dipilih';
            if (slider) slider.value = 0;
            if (valRot) valRot.textContent = '0°';
            if (inpX) inpX.value = '';
            if (inpZ) inpZ.value = '';
            return;
        }
        const t = selected3DObject.userData?.type;
        const id = t === 'node' ? selected3DObject.userData.nodeId : ('Robot #' + selected3DObject.userData.robotId);
        info.textContent = `${t?.toUpperCase()}: ${id}`;
        if (inpX) inpX.value = selected3DObject.position.x.toFixed(2);
        if (inpZ) inpZ.value = selected3DObject.position.z.toFixed(2);
        const rotDeg = (selected3DObject.rotation.y * 180 / Math.PI) % 360;
        if (slider) slider.value = Math.round(rotDeg);
        if (valRot) valRot.textContent = Math.round(rotDeg) + '°';
        updateDriveReadout();
    }

    function move3DObject(dx, dz) {
        if (!selected3DObject) { alert('Pilih object di canvas 3D dulu.'); return; }
        selected3DObject.position.x += dx;
        selected3DObject.position.z += dz;
        // sync ke locationsData bila node
        if (selected3DObject.userData.type === 'node' && threeBotCtrl) {
            const sz = threeBotCtrl.getModelSize();
            const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) {
                locationsData[nodeId].x = pct.x;
                locationsData[nodeId].y = pct.y;
                if (selectedNodeId === nodeId) inspectNode(nodeId);
                if (threeBotCtrl.build3DEdges) threeBotCtrl.build3DEdges();
                renderEditorMap();
            }
        }
        updateSelected3DObjectUI();
    }

    function rotate3DObject(deltaDeg) {
        if (!selected3DObject) { alert('Pilih object di canvas 3D dulu.'); return; }
        selected3DObject.rotation.y += (deltaDeg * Math.PI / 180);
        updateSelected3DObjectUI();
    }

    function set3DRotation(val) {
        if (!selected3DObject) return;
        const deg = parseFloat(val);
        selected3DObject.rotation.y = deg * Math.PI / 180;
        const valEl = document.getElementById('val-3d-rotation');
        if (valEl) valEl.textContent = Math.round(deg) + '°';
    }

    function set3DWorldX(val) {
        if (!selected3DObject) return;
        const x = parseFloat(val); if (isNaN(x)) return;
        selected3DObject.position.x = x;
        if (selected3DObject.userData.type === 'node' && threeBotCtrl) {
            const sz = threeBotCtrl.getModelSize();
            const pct = locFromWorld(x, selected3DObject.position.z, sz);
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) { locationsData[nodeId].x = pct.x; if (selectedNodeId === nodeId) inspectNode(nodeId); if (threeBotCtrl.build3DEdges) threeBotCtrl.build3DEdges(); renderEditorMap(); }
        }
    }

    function set3DWorldZ(val) {
        if (!selected3DObject) return;
        const z = parseFloat(val); if (isNaN(z)) return;
        selected3DObject.position.z = z;
        if (selected3DObject.userData.type === 'node' && threeBotCtrl) {
            const sz = threeBotCtrl.getModelSize();
            const pct = locFromWorld(selected3DObject.position.x, z, sz);
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) { locationsData[nodeId].y = pct.y; if (selectedNodeId === nodeId) inspectNode(nodeId); if (threeBotCtrl.build3DEdges) threeBotCtrl.build3DEdges(); renderEditorMap(); }
        }
    }

    function reset3DObjectPosition() {
        if (!selected3DObject) { alert('Pilih object dulu.'); return; }
        selected3DObject.position.set(0, selected3DObject.position.y, 0);
        selected3DObject.rotation.y = 0;
        updateSelected3DObjectUI();
    }

    function save3DObjectToGraph() {
        if (!selected3DObject) { alert('Pilih object dulu.'); return; }
        if (selected3DObject.userData.type === 'node' && threeBotCtrl) {
            const sz = threeBotCtrl.getModelSize();
            const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) {
                locationsData[nodeId].x = pct.x;
                locationsData[nodeId].y = pct.y;
            }
        }
        saveGraphToServer();
    }

    function switchFloor(floorNum) {
        currentFloor = floorNum;
        document.getElementById('tab-floor-1').className = floorNum === 1 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
            
        document.getElementById('tab-floor-2').className = floorNum === 2 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";

        const editorContainer = document.getElementById('editor-map-container');
        const canvas3D = document.getElementById('botctrl-3d-canvas-container');
        const hint3D = document.getElementById('botctrl-3d-hint');

        if (floorNum === 1) {
            editorContainer.style.backgroundImage = `url('${floor1Img}')`;
            editorContainer.style.backgroundColor = '';
            document.getElementById('floor-badge').textContent = 'Showing Floor 1';
            if (canvas3D) canvas3D.classList.add('hidden');
            if (hint3D) hint3D.classList.add('hidden');
            const panel3D = document.getElementById('panel-3d-controls');
            if (panel3D) panel3D.classList.add('hidden');
        } else {
            editorContainer.style.backgroundImage = 'none';
            editorContainer.style.backgroundColor = '#0f172a';
            document.getElementById('floor-badge').textContent = 'Showing Floor 2 (3D)';
            if (canvas3D) {
                canvas3D.classList.remove('hidden');
                setTimeout(() => {
                    if (!threeBotCtrl) {
                        threeBotCtrl = initThreeViewer('botctrl-3d-canvas-container');
                    } else {
                        threeBotCtrl.resize();
                    }
                }, 50);
            }
            if (hint3D) hint3D.classList.remove('hidden');
            const panel3D = document.getElementById('panel-3d-controls');
            if (panel3D) panel3D.classList.remove('hidden');
        }

        selectedNodeId = null;
        clearInspector();
        renderEditorMap();
    }

    function setEditorTool(tool) {
        currentTool = tool;
        ['move', 'add', 'connect', 'delete'].forEach(t => {
            const btn = document.getElementById(`tool-${t}`);
            if (t === tool) {
                btn.className = "px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 font-bold transition";
            } else {
                btn.className = "px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition";
            }
        });

        const hint = document.getElementById('editor-hint');
        const is3DMode = (currentFloor === 2);
        if (tool === 'move') hint.textContent = is3DMode ? "Tool: Klik & drag node/robot di canvas 3D untuk pindah posisi. Gunakan D-pad di panel kontrol untuk presisi." : "Tool: Drag nodes to adjust coordinates. Click a node to edit room title & hidden flags.";
        if (tool === 'add') hint.textContent = is3DMode ? "Tool: Klik area kosong di canvas 3D untuk tambah node ruangan baru." : "Tool: Click anywhere on the map to add a new room node (e.g. Hall, Lobby).";
        if (tool === 'connect') hint.textContent = is3DMode ? "Tool: Klik node A lalu node B di canvas 3D untuk hubungkan jalur." : "Tool: Click Node A, then click Node B to draw a path line connection.";
        if (tool === 'delete') hint.textContent = is3DMode ? "Tool: Klik node di canvas 3D untuk hapus." : "Tool: Click any node to delete it from the graph.";
        
        connectStartNodeId = null;
        renderEditorMap();
    }

    function renderEditorMap() {
        const svg = document.getElementById('editor-svg');
        const nodesLayer = document.getElementById('editor-nodes-layer');
        const container = document.getElementById('editor-map-container');
        
        if (!svg || !nodesLayer || !container) return;
        svg.innerHTML = '';
        nodesLayer.innerHTML = '';

        const w = container.clientWidth || 800;
        const h = container.clientHeight || 450;

        // Render Edges for current floor using for...of loops
        const drawnEdges = new Set();
        for (let nodeA in adjData) {
            const locA = locationsData[nodeA];
            if (!locA || Number(locA.floor) !== Number(currentFloor)) continue;

            const neighbors = adjData[nodeA] || [];
            for (let nodeB of neighbors) {
                const locB = locationsData[nodeB];
                if (!locB || Number(locB.floor) !== Number(currentFloor)) continue;

                const edgeKey = [nodeA, nodeB].sort().join('--');
                if (drawnEdges.has(edgeKey)) continue;
                drawnEdges.add(edgeKey);

                const pxA = (locA.x / 100) * w;
                const pyA = (locA.y / 100) * h;
                const pxB = (locB.x / 100) * w;
                const pyB = (locB.y / 100) * h;

                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', pxA);
                line.setAttribute('y1', pyA);
                line.setAttribute('x2', pxB);
                line.setAttribute('y2', pyB);
                line.setAttribute('stroke', '#38bdf8');
                line.setAttribute('stroke-width', '2');
                line.setAttribute('stroke-dasharray', '4,4');
                svg.appendChild(line);
            }
        }

        // Render Nodes for current floor
        for (let nodeId in locationsData) {
            const loc = locationsData[nodeId];
            if (Number(loc.floor) !== Number(currentFloor)) continue;
            const displayName = loc.name || nodeId;
            const isNamed = loc.is_destination || (!loc.hidden);
            const isSelected = selectedNodeId === nodeId;
            const isConnectStart = connectStartNodeId === nodeId;

            const el = document.createElement('div');
            el.className = `editor-node ${isSelected ? 'selected' : ''}`;
            el.style.left = `${loc.x}%`;
            el.style.top = `${loc.y}%`;

            let dotBg = loc.hidden ? 'bg-gray-400 opacity-70' : (isNamed ? 'bg-[#3b4cb8]' : (isConnectStart ? 'bg-amber-500 animate-bounce' : 'bg-sky-500'));
            let dotSize = isNamed ? 'w-5 h-5' : 'w-3.5 h-3.5';

            el.innerHTML = `
                <div class="relative flex items-center justify-center group">
                    <div class="${dotSize} rounded-full ${dotBg} border-2 border-white shadow-md transition transform group-hover:scale-125"></div>
                    ${isNamed ? `<div class="absolute -top-6 bg-[#3b4cb8] text-white text-[9px] font-bold px-2 py-0.5 rounded shadow pointer-events-none whitespace-nowrap">${displayName}</div>` : ''}
                </div>
            `;

            el.addEventListener('click', (e) => handleNodeClick(e, nodeId));
            el.addEventListener('mousedown', (e) => handleNodeMouseDown(e, nodeId));

            nodesLayer.appendChild(el);
        }
    }

    function handleNodeClick(e, nodeId) {
        e.stopPropagation();
        selectedNodeId = nodeId;
        inspectNode(nodeId);

        if (currentTool === 'connect') {
            if (!connectStartNodeId) {
                connectStartNodeId = nodeId;
            } else if (connectStartNodeId !== nodeId) {
                if (!adjData[connectStartNodeId]) adjData[connectStartNodeId] = [];
                if (!adjData[nodeId]) adjData[nodeId] = [];

                if (!adjData[connectStartNodeId].includes(nodeId)) adjData[connectStartNodeId].push(nodeId);
                if (!adjData[nodeId].includes(connectStartNodeId)) adjData[nodeId].push(connectStartNodeId);

                connectStartNodeId = null;
                inspectNode(selectedNodeId);
            }
        } else if (currentTool === 'delete') {
            if (confirm(`Are you sure you want to delete node "${nodeId}"?`)) {
                delete locationsData[nodeId];
                delete adjData[nodeId];
                for (let k in adjData) {
                    adjData[k] = adjData[k].filter(n => n !== nodeId);
                }
                selectedNodeId = null;
                clearInspector();
            }
        }

        renderEditorMap();
    }

    function handleNodeMouseDown(e, nodeId) {
        if (currentTool !== 'move') return;
        draggedNodeId = nodeId;
        document.addEventListener('mousemove', handleNodeDrag);
        document.addEventListener('mouseup', handleNodeMouseUp);
    }

    function handleNodeDrag(e) {
        if (!draggedNodeId) return;
        const container = document.getElementById('editor-map-container');
        const rect = container.getBoundingClientRect();

        let xPct = ((e.clientX - rect.left) / rect.width) * 100;
        let yPct = ((e.clientY - rect.top) / rect.height) * 100;

        xPct = Math.max(0, Math.min(100, xPct));
        yPct = Math.max(0, Math.min(100, yPct));

        locationsData[draggedNodeId].x = parseFloat(xPct.toFixed(2));
        locationsData[draggedNodeId].y = parseFloat(yPct.toFixed(2));

        inspectNode(draggedNodeId);
        renderEditorMap();
    }

    function handleNodeMouseUp() {
        draggedNodeId = null;
        document.removeEventListener('mousemove', handleNodeDrag);
        document.removeEventListener('mouseup', handleNodeMouseUp);
    }

    function handleMapClick(e) {
        if (currentTool === 'add') {
            const container = document.getElementById('editor-map-container');
            const rect = container.getBoundingClientRect();

            let xPct = parseFloat((((e.clientX - rect.left) / rect.width) * 100).toFixed(2));
            let yPct = parseFloat((((e.clientY - rect.top) / rect.height) * 100).toFixed(2));

            const name = prompt("Enter new room / location name (e.g. Hall, Ruang Meeting 1):", `Hall_${Math.floor(Math.random() * 100)}`);
            if (name && name.trim()) {
                const cleanName = name.trim();
                const nodeKey = `${currentFloor}_${cleanName}`;
                locationsData[nodeKey] = { 
                    id: nodeKey,
                    name: cleanName, 
                    x: xPct, 
                    y: yPct, 
                    floor: currentFloor, 
                    hidden: false, 
                    is_destination: true 
                };
                adjData[nodeKey] = [];
                selectedNodeId = nodeKey;
                inspectNode(nodeKey);
                renderEditorMap();
            }
        }
    }

    function handleCoordinateChange() {
        if (!selectedNodeId || !locationsData[selectedNodeId]) return;
        const xVal = parseFloat(document.getElementById('inspect-x').value);
        const yVal = parseFloat(document.getElementById('inspect-y').value);

        if (!isNaN(xVal)) {
            locationsData[selectedNodeId].x = Math.max(0, Math.min(100, parseFloat(xVal.toFixed(2))));
        }
        if (!isNaN(yVal)) {
            locationsData[selectedNodeId].y = Math.max(0, Math.min(100, parseFloat(yVal.toFixed(2))));
        }

        renderEditorMap();
    }

    function disconnectEdge(nodeA, nodeB) {
        if (adjData[nodeA]) {
            adjData[nodeA] = adjData[nodeA].filter(n => n !== nodeB);
        }
        if (adjData[nodeB]) {
            adjData[nodeB] = adjData[nodeB].filter(n => n !== nodeA);
        }
        if (selectedNodeId) {
            inspectNode(selectedNodeId);
        }
        renderEditorMap();
    }

    function inspectNode(nodeId) {
        const loc = locationsData[nodeId];
        if (!loc) return;

        document.getElementById('inspect-node-name').value = loc.name || nodeId;
        document.getElementById('inspect-floor').value = loc.floor || 1;
        document.getElementById('inspect-x').value = loc.x;
        document.getElementById('inspect-y').value = loc.y;
        document.getElementById('inspect-is-destination').checked = !!loc.is_destination;
        document.getElementById('inspect-hidden').checked = !!loc.hidden;

        // Blender Object link panel
        const objSel = document.getElementById('inspect-object');
        if (objSel) {
            const cur = loc.objectName || '';
            let opts = '<option value="">— Manual (x/y) —</option>' + BLENDER_OBJECTS.map(o =>
                `<option value="${o}"${o === cur ? ' selected' : ''}>${o}</option>`).join('');
            if (cur && !BLENDER_OBJECTS.includes(cur)) {
                opts += `<option value="${cur}" selected>${cur} (custom)</option>`;
            }
            objSel.innerHTML = opts;
        }
        refreshObjectStatus();

        const neighbors = adjData[nodeId] || [];
        const badge = document.getElementById('neighbors-count-badge');
        if (badge) badge.textContent = `${neighbors.length} edge(s)`;

        const nbrsContainer = document.getElementById('inspect-neighbors');
        if (neighbors.length === 0) {
            nbrsContainer.innerHTML = '<span class="text-gray-400 italic">No neighbors connected</span>';
        } else {
            nbrsContainer.innerHTML = neighbors.map(nbr => {
                const nbrLoc = locationsData[nbr];
                const nbrLabel = nbrLoc ? `${nbrLoc.name || nbr} (Lt. ${nbrLoc.floor || 1})` : nbr;
                return `
                <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs shadow-xs">
                    <span class="font-bold text-gray-800 font-mono flex items-center gap-1.5 truncate">
                        <i class="fa-solid fa-link text-sky-500 text-[10px]"></i> ${nbrLabel}
                    </span>
                    <button onclick="disconnectEdge('${nodeId}', '${nbr}')" class="text-rose-500 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 p-1 rounded transition text-[11px] shrink-0 ml-2" title="Putus Garis Edge">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>`;
            }).join('');
        }
    }

    function clearInspector() {
        document.getElementById('inspect-node-name').value = '';
        document.getElementById('inspect-x').value = '';
        document.getElementById('inspect-y').value = '';
        document.getElementById('inspect-is-destination').checked = false;
        document.getElementById('inspect-hidden').checked = false;
        const objSel = document.getElementById('inspect-object');
        if (objSel) objSel.innerHTML = '<option value="">— Manual (x/y) —</option>';
        refreshObjectStatus();
        document.getElementById('inspect-neighbors').innerHTML = '<span class="text-gray-400 italic">No node selected</span>';
        const badge = document.getElementById('neighbors-count-badge');
        if (badge) badge.textContent = `0 edges`;
    }

    function refreshObjectStatus() {
        const st = document.getElementById('inspect-object-status');
        const xyz = document.getElementById('inspect-object-xyz');
        if (!st) return;
        const setBadge = (txt, cls) => { st.textContent = txt; st.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full border ' + cls; };
        const loc = selectedNodeId ? locationsData[selectedNodeId] : null;
        if (!loc || !loc.objectName) {
            setBadge('Manual', 'bg-gray-100 text-gray-500 border-gray-200');
            if (xyz) xyz.textContent = '—';
            return;
        }
        if (loc._u !== undefined) {
            setBadge('Linked ✓', 'bg-emerald-100 text-emerald-700 border-emerald-300');
            if (xyz) {
                let w = '';
                try {
                    if (threeBotCtrl && threeBotCtrl.getModelSize) {
                        const sz = threeBotCtrl.getModelSize();
                        if (sz && sz.x > 0.1) {
                            const wp = worldPosForLoc(loc, sz);
                            w = `X=${wp.x.toFixed(2)} Y=${(loc._fy ?? 0.05).toFixed(2)} Z=${wp.z.toFixed(2)}`;
                        }
                    }
                } catch (e) {}
                xyz.textContent = w || `u=${(loc._u * 100).toFixed(1)}% v=${(loc._v * 100).toFixed(1)}%`;
            }
        } else if (!BLENDER_OBJECTS.includes(loc.objectName)) {
            setBadge('Not found', 'bg-rose-100 text-rose-700 border-rose-300');
            if (xyz) xyz.textContent = 'Object tidak ada di GLB — fallback x/y';
        } else {
            setBadge('Belum resolve', 'bg-amber-100 text-amber-700 border-amber-300');
            if (xyz) xyz.textContent = 'Buka Lantai 2 (3D) untuk resolve posisi';
        }
    }

    function handleObjectNameChange(val) {
        if (!selectedNodeId || !locationsData[selectedNodeId]) return;
        const loc = locationsData[selectedNodeId];
        if (!val) {
            delete loc.objectName;
        } else {
            loc.objectName = val;
        }
        delete loc._u; delete loc._v; delete loc._fy;
        // resolve langsung bila viewer 3D sudah siap
        try {
            if (val && threeBotCtrl && threeBotCtrl.scene && threeBotCtrl.getModelSize) {
                const sz = threeBotCtrl.getModelSize();
                if (sz && sz.x > 0.1 && resolveObjectAnchor(loc, threeBotCtrl.scene, sz)) {
                    const mesh = threeBotCtrl.nodeMeshes ? threeBotCtrl.nodeMeshes.get(selectedNodeId) : null;
                    if (mesh) {
                        const wp = worldPosForLoc(loc, sz);
                        mesh.position.set(wp.x, loc._fy ?? 0.05, wp.z);
                    }
                }
            }
        } catch (e) { console.warn('[Robopath] resolve on select fail', e); }
        if (threeBotCtrl && threeBotCtrl.build3DEdges) threeBotCtrl.build3DEdges();
        refreshObjectStatus();
        renderEditorMap();
    }

    function handleRenameNode(newName) {
        if (!selectedNodeId || !newName || !newName.trim()) return;
        const cleanName = newName.trim();
        const currentLoc = locationsData[selectedNodeId];
        if (!currentLoc) return;

        // Check duplicates on the SAME floor only
        const hasDuplicateOnSameFloor = Object.values(locationsData).some(loc => 
            loc.id !== selectedNodeId && 
            Number(loc.floor) === Number(currentLoc.floor) && 
            (loc.name || '').toLowerCase() === cleanName.toLowerCase()
        );

        if (hasDuplicateOnSameFloor) {
            alert(`Ruangan bernama "${cleanName}" sudah ada di Lantai ${currentLoc.floor}! Anda bisa memberi nama yang sama di lantai yang berbeda.`);
            document.getElementById('inspect-node-name').value = currentLoc.name || selectedNodeId;
            return;
        }

        const newId = `${currentLoc.floor}_${cleanName}`;
        if (newId === selectedNodeId) {
            currentLoc.name = cleanName;
            renderEditorMap();
            return;
        }

        const oldId = selectedNodeId;
        currentLoc.id = newId;
        currentLoc.name = cleanName;
        locationsData[newId] = currentLoc;
        delete locationsData[oldId];

        adjData[newId] = adjData[oldId] || [];
        delete adjData[oldId];

        for (let k in adjData) {
            adjData[k] = adjData[k].map(n => n === oldId ? newId : n);
        }

        selectedNodeId = newId;
        inspectNode(newId);
        renderEditorMap();
    }

    function handleFloorChange(val) {
        if (!selectedNodeId || !locationsData[selectedNodeId]) return;
        const newFloor = parseInt(val, 10);
        const loc = locationsData[selectedNodeId];
        if (Number(loc.floor) === newFloor) return;

        const oldId = selectedNodeId;
        const newId = `${newFloor}_${loc.name || oldId}`;

        loc.floor = newFloor;
        loc.id = newId;
        locationsData[newId] = loc;
        delete locationsData[oldId];

        adjData[newId] = adjData[oldId] || [];
        delete adjData[oldId];
        for (let k in adjData) {
            adjData[k] = adjData[k].map(n => n === oldId ? newId : n);
        }

        selectedNodeId = newId;
        inspectNode(newId);
        renderEditorMap();
    }

    function handleIsDestinationChange(val) {
        if (!selectedNodeId) return;
        locationsData[selectedNodeId].is_destination = val;
        renderEditorMap();
    }

    function handleHiddenChange(val) {
        if (!selectedNodeId) return;
        locationsData[selectedNodeId].hidden = val;
        renderEditorMap();
    }

    function saveGraphToServer() {
        const formattedLocations = [];
        for (let id in locationsData) {
            formattedLocations.push({
                id: id,
                name: locationsData[id].name || id,
                x: locationsData[id].x,
                y: locationsData[id].y,
                floor: locationsData[id].floor || 1,
                hidden: !!locationsData[id].hidden,
                is_destination: !!locationsData[id].is_destination,
                ...(locationsData[id].objectName ? { objectName: locationsData[id].objectName } : {})
            });
        }

        fetch('/api/graph/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                locations: formattedLocations,
                adj: adjData
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(`Graph Map saved successfully! Total ${data.total_nodes} nodes updated.`);
            } else {
                alert('Failed to save graph map.');
            }
        })
        .catch(err => {
            console.error('Error saving graph:', err);
            alert('A network error occurred while saving the graph map.');
        });
    }

    function resetSystem() {
        if (confirm('Reset all robot units to base station?')) {
            fetch('/api/system/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) alert('Fleet reset successfully.');
            });
        }
    }

    window.addEventListener('load', () => {
        switchFloor(1);
    });
    window.addEventListener('resize', () => {
        renderEditorMap();
    });
</script>
@endsection
