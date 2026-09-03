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
                    <svg class="editor-svg" id="editor-svg"></svg>
                    <div id="editor-nodes-layer"></div>
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
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const floor1Img = "{{ asset('images/floor1.jpeg') }}";
    const floor2Img = "{{ asset('images/floor2.jpeg') }}";

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

    function switchFloor(floorNum) {
        currentFloor = floorNum;
        document.getElementById('tab-floor-1').className = floorNum === 1 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
            
        document.getElementById('tab-floor-2').className = floorNum === 2 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";

        document.getElementById('editor-map-container').style.backgroundImage = `url('${floorNum === 1 ? floor1Img : floor2Img}')`;
        document.getElementById('floor-badge').textContent = `Showing Floor ${floorNum}`;

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
        if (tool === 'move') hint.textContent = "Tool: Drag nodes to adjust coordinates. Click a node to edit room title & hidden flags.";
        if (tool === 'add') hint.textContent = "Tool: Click anywhere on the map to add a new room node (e.g. Hall, Lobby).";
        if (tool === 'connect') hint.textContent = "Tool: Click Node A, then click Node B to draw a path line connection.";
        if (tool === 'delete') hint.textContent = "Tool: Click any node to delete it from the graph.";
        
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
            if (loc.hidden && !showHiddenDots && selectedNodeId !== nodeId) continue;

            const isNamed = loc.is_destination || (!nodeId.startsWith('N') || nodeId === 'N');
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
                    ${isNamed ? `<div class="absolute -top-6 bg-[#3b4cb8] text-white text-[9px] font-bold px-2 py-0.5 rounded shadow pointer-events-none whitespace-nowrap">${nodeId}</div>` : ''}
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

            const name = prompt("Enter new room / location name (e.g. Hall, Meeting Room):", `Hall_${Math.floor(Math.random() * 100)}`);
            if (name && name.trim()) {
                const nodeKey = name.trim();
                locationsData[nodeKey] = { 
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

        document.getElementById('inspect-node-name').value = nodeId;
        document.getElementById('inspect-floor').value = loc.floor || 1;
        document.getElementById('inspect-x').value = loc.x;
        document.getElementById('inspect-y').value = loc.y;
        document.getElementById('inspect-is-destination').checked = !!loc.is_destination;
        document.getElementById('inspect-hidden').checked = !!loc.hidden;

        const neighbors = adjData[nodeId] || [];
        const badge = document.getElementById('neighbors-count-badge');
        if (badge) badge.textContent = `${neighbors.length} edge(s)`;

        const nbrsContainer = document.getElementById('inspect-neighbors');
        if (neighbors.length === 0) {
            nbrsContainer.innerHTML = '<span class="text-gray-400 italic">No neighbors connected</span>';
        } else {
            nbrsContainer.innerHTML = neighbors.map(nbr => `
                <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs shadow-xs">
                    <span class="font-bold text-gray-800 font-mono flex items-center gap-1.5">
                        <i class="fa-solid fa-link text-sky-500 text-[10px]"></i> ${nbr}
                    </span>
                    <button onclick="disconnectEdge('${nodeId}', '${nbr}')" class="text-rose-500 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 p-1 rounded transition text-[11px]" title="Putus Garis Edge (${nodeId} <-> ${nbr})">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `).join('');
        }
    }

    function clearInspector() {
        document.getElementById('inspect-node-name').value = '';
        document.getElementById('inspect-x').value = '';
        document.getElementById('inspect-y').value = '';
        document.getElementById('inspect-is-destination').checked = false;
        document.getElementById('inspect-hidden').checked = false;
        document.getElementById('inspect-neighbors').innerHTML = '<span class="text-gray-400 italic">No node selected</span>';
        const badge = document.getElementById('neighbors-count-badge');
        if (badge) badge.textContent = `0 edges`;
    }

    function handleRenameNode(newName) {
        if (!selectedNodeId || !newName || !newName.trim() || newName === selectedNodeId) return;
        const cleanName = newName.trim();
        if (locationsData[cleanName]) {
            alert(`A node named "${cleanName}" already exists! Please use a unique name.`);
            document.getElementById('inspect-node-name').value = selectedNodeId;
            return;
        }

        const oldId = selectedNodeId;
        locationsData[cleanName] = { ...locationsData[oldId] };
        delete locationsData[oldId];

        adjData[cleanName] = adjData[oldId] || [];
        delete adjData[oldId];

        for (let k in adjData) {
            adjData[k] = adjData[k].map(n => n === oldId ? cleanName : n);
        }

        selectedNodeId = cleanName;
        inspectNode(cleanName);
        renderEditorMap();
    }

    function handleFloorChange(val) {
        if (!selectedNodeId) return;
        locationsData[selectedNodeId].floor = parseInt(val, 10);
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
                name: id,
                x: locationsData[id].x,
                y: locationsData[id].y,
                floor: locationsData[id].floor || 1,
                hidden: !!locationsData[id].hidden,
                is_destination: !!locationsData[id].is_destination
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
