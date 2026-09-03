@extends('layouts.layout')

@section('title', 'ROBOPATH - Map Editor & Bot Control')
@section('page_title', 'Interactive Map Editor & Fleet Control')
@section('page_subtitle', 'Drag and drop nodes, connect floor paths, and manage robot fleet units')

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
    .editor-node.selected {
        outline: 3px solid #3b4cb8;
        outline-offset: 3px;
        border-radius: 9999px;
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

    <!-- Top Control Bar: Floor Selection & Editor Modes & Save Button -->
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
                <button onclick="setEditorTool('move')" id="tool-move" class="px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5">
                    <i class="fa-solid fa-up-down-left-right"></i> Move / Drag Node
                </button>
                <button onclick="setEditorTool('add')" id="tool-add" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-plus-circle"></i> Add Node
                </button>
                <button onclick="setEditorTool('connect')" id="tool-connect" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-diagram-project"></i> Connect Edges
                </button>
                <button onclick="setEditorTool('delete')" id="tool-delete" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>

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
                        <p class="text-xs text-gray-500" id="editor-hint">Tool: Drag nodes to position them. Click Save Graph when finished.</p>
                    </div>
                    <span class="text-xs font-bold text-[#3b4cb8] bg-blue-50 px-3 py-1 rounded-full border border-blue-200" id="floor-badge">
                        Showing Floor 1
                    </span>
                </div>

                <!-- Editor Canvas Container -->
                <div class="editor-map-container shadow-inner border border-gray-300 overflow-hidden" id="editor-map-container" onclick="handleMapClick(event)">
                    <svg class="editor-svg" id="editor-svg"></svg>
                    <div id="editor-nodes-layer"></div>
                </div>
            </div>
        </div>

        <!-- Node & Graph Inspector Panel (1/3 Width) -->
        <div class="space-y-6">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col">
                <h3 class="text-base font-bold text-gray-800 mb-4 pb-3 border-b border-gray-200 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-[#3b4cb8]"></i> Node & Path Inspector
                </h3>

                <div class="space-y-4 flex-1 text-xs text-gray-700">
                    <div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Selected Node</span>
                        <div class="p-3 bg-blue-50/50 border border-blue-200 rounded-xl font-bold text-[#3b4cb8] text-sm" id="inspect-node-id">
                            None selected (Click a node to inspect)
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">X Coordinate (%)</label>
                            <input type="number" step="0.01" id="inspect-x" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-2 font-mono font-bold text-gray-800">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Y Coordinate (%)</label>
                            <input type="number" step="0.01" id="inspect-y" readonly class="w-full bg-gray-100 border border-gray-200 rounded-lg p-2 font-mono font-bold text-gray-800">
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Connected Neighbors (Edges)</label>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl max-h-[120px] overflow-y-auto space-y-1 font-mono text-[11px]" id="inspect-neighbors">
                            <span class="text-gray-400 italic">No neighbors connected</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unit Fleet Quick Reset Card -->
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
                <h3 class="text-base font-bold text-gray-800 mb-3 flex items-center gap-2">
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
    let currentTool = 'move'; // 'move', 'add', 'connect', 'delete'
    let selectedNodeId = null;
    let connectStartNodeId = null;
    let draggedNodeId = null;

    let locationsData = {
        @foreach($locations as $id => $coords)
        '{{ $id }}': { x: {{ $coords['x'] }}, y: {{ $coords['y'] }}, floor: {{ $coords['floor'] ?? 1 }} },
        @endforeach
    };

    let adjData = {
        @foreach($adj as $node => $neighbors)
        '{{ $node }}': [ @foreach($neighbors as $nbr) '{{ $nbr }}', @endforeach ],
        @endforeach
    };

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

        renderEditorMap();
    }

    function setEditorTool(tool) {
        currentTool = tool;
        ['move', 'add', 'connect', 'delete'].forEach(t => {
            const btn = document.getElementById(`tool-${t}`);
            if (t === tool) {
                btn.className = "px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 font-bold";
            } else {
                btn.className = "px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5";
            }
        });

        const hint = document.getElementById('editor-hint');
        if (tool === 'move') hint.textContent = "Tool: Drag nodes to adjust coordinates. Click a node to inspect.";
        if (tool === 'add') hint.textContent = "Tool: Click anywhere on the map to add a new node.";
        if (tool === 'connect') hint.textContent = "Tool: Click Node A, then click Node B to draw a path line connection.";
        if (tool === 'delete') hint.textContent = "Tool: Click any node to delete it from the graph.";
        
        connectStartNodeId = null;
        renderEditorMap();
    }

    function renderEditorMap() {
        const svg = document.getElementById('editor-svg');
        const nodesLayer = document.getElementById('editor-nodes-layer');
        const container = document.getElementById('editor-map-container');
        
        svg.innerHTML = '';
        nodesLayer.innerHTML = '';

        if (!container) return;
        const w = container.clientWidth;
        const h = container.clientHeight;

        // Render Edges for current floor
        const drawnEdges = new Set();
        for (let nodeA in adjData) {
            const locA = locationsData[nodeA];
            if (!locA || locA.floor !== currentFloor) continue;

            const neighbors = adjData[nodeA] || [];
            neighbors.forEach(nodeB => {
                const locB = locationsData[nodeB];
                if (!locB || locB.floor !== currentFloor) continue;

                const edgeKey = [nodeA, nodeB].sort().join('--');
                if (drawnEdges.has(edgeKey)) return;
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
            });
        }

        // Render Nodes for current floor
        for (let nodeId in locationsData) {
            const loc = locationsData[nodeId];
            if (loc.floor !== currentFloor) continue;

            const isNamed = !nodeId.startsWith('N') || nodeId === 'N';
            const isSelected = selectedNodeId === nodeId;
            const isConnectStart = connectStartNodeId === nodeId;

            const el = document.createElement('div');
            el.className = `editor-node ${isSelected ? 'selected' : ''}`;
            el.style.left = `${loc.x}%`;
            el.style.top = `${loc.y}%`;

            let dotBg = isNamed ? 'bg-[#3b4cb8]' : (isConnectStart ? 'bg-amber-500 animate-bounce' : 'bg-sky-500');

            el.innerHTML = `
                <div class="relative flex items-center justify-center group" onclick="handleNodeClick(event, '${nodeId}')" onmousedown="handleNodeMouseDown(event, '${nodeId}')">
                    <div class="w-3.5 h-3.5 rounded-full ${dotBg} border-2 border-white shadow-md transition transform group-hover:scale-125"></div>
                    ${isNamed ? `<div class="absolute -top-5 bg-gray-800 text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow pointer-events-none whitespace-nowrap">${nodeId}</div>` : ''}
                </div>
            `;
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
                // Connect connectStartNodeId <-> nodeId
                if (!adjData[connectStartNodeId]) adjData[connectStartNodeId] = [];
                if (!adjData[nodeId]) adjData[nodeId] = [];

                if (!adjData[connectStartNodeId].includes(nodeId)) adjData[connectStartNodeId].push(nodeId);
                if (!adjData[nodeId].includes(connectStartNodeId)) adjData[nodeId].push(connectStartNodeId);

                connectStartNodeId = null;
            }
        } else if (currentTool === 'delete') {
            if (confirm(`Are you sure you want to delete node ${nodeId}?`)) {
                delete locationsData[nodeId];
                delete adjData[nodeId];
                for (let k in adjData) {
                    adjData[k] = adjData[k].filter(n => n !== nodeId);
                }
                selectedNodeId = null;
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

            const name = prompt("Enter new node name / ID:", `Node_${Math.floor(Math.random() * 1000)}`);
            if (name) {
                locationsData[name] = { x: xPct, y: yPct, floor: currentFloor };
                adjData[name] = [];
                selectedNodeId = name;
                inspectNode(name);
                renderEditorMap();
            }
        }
    }

    function inspectNode(nodeId) {
        const loc = locationsData[nodeId];
        if (!loc) return;

        document.getElementById('inspect-node-id').textContent = `${nodeId} (Floor ${loc.floor})`;
        document.getElementById('inspect-x').value = loc.x;
        document.getElementById('inspect-y').value = loc.y;

        const neighbors = adjData[nodeId] || [];
        const nbrsContainer = document.getElementById('inspect-neighbors');
        if (neighbors.length === 0) {
            nbrsContainer.innerHTML = '<span class="text-gray-400 italic">No neighbors connected</span>';
        } else {
            nbrsContainer.innerHTML = neighbors.map(nbr => `<div class="text-gray-800 font-bold">• ${nbr}</div>`).join('');
        }
    }

    function saveGraphToServer() {
        const formattedLocations = [];
        for (let id in locationsData) {
            formattedLocations.push({
                id: id,
                x: locationsData[id].x,
                y: locationsData[id].y,
                floor: locationsData[id].floor || 1
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

    document.addEventListener('DOMContentLoaded', () => {
        switchFloor(1);
    });
</script>
@endsection
