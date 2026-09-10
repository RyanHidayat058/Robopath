import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Layouts/Layout';
import { 
    Layers, 
    Move, 
    PlusCircle, 
    Network, 
    Trash2, 
    Eye, 
    EyeOff, 
    Save, 
    RotateCcw, 
    Sliders, 
    Edit3, 
    CheckCircle2, 
    AlertCircle 
} from 'lucide-react';

export default function BotControl({
    robots = [],
    locations: initialLocations = {},
    adj: initialAdj = {},
}) {
    const { auth } = usePage().props;

    // Graph state
    const [locations, setLocations] = useState(initialLocations);
    const [adj, setAdj] = useState(initialAdj);
    const [currentFloor, setCurrentFloor] = useState(1);
    const [currentTool, setCurrentTool] = useState('move'); // 'move' | 'add' | 'connect' | 'delete'
    const [showHiddenDots, setShowHiddenDots] = useState(true);
    const [selectedNodeId, setSelectedNodeId] = useState(null);
    const [connectStartNodeId, setConnectStartNodeId] = useState(null);

    // Save & Feedback status
    const [isSaving, setIsSaving] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

    // Map container ref for drag calculations
    const mapContainerRef = useRef(null);
    const [draggingNodeId, setDraggingNodeId] = useState(null);

    // Selected node data
    const selectedNode = selectedNodeId ? locations[selectedNodeId] : null;
    const connectedNeighbors = selectedNodeId ? (adj[selectedNodeId] || []) : [];

    // Switch floor
    const handleSwitchFloor = (floorNum) => {
        setCurrentFloor(floorNum);
        setSelectedNodeId(null);
        setConnectStartNodeId(null);
    };

    // Add node
    const handleMapClick = (e) => {
        if (currentTool !== 'add') return;
        if (!mapContainerRef.current) return;

        const rect = mapContainerRef.current.getBoundingClientRect();
        const clientX = e.clientX - rect.left;
        const clientY = e.clientY - rect.top;

        const x = parseFloat(((clientX / rect.width) * 100).toFixed(1));
        const y = parseFloat(((clientY / rect.height) * 100).toFixed(1));

        // Generate unique node ID
        const prefix = currentFloor === 1 ? '1_N' : '2_N';
        let count = 1;
        while (locations[`${prefix}${count}`]) {
            count++;
        }
        const newId = `${prefix}${count}`;

        const newLoc = {
            id: newId,
            name: `Node ${newId}`,
            x,
            y,
            floor: currentFloor,
            hidden: false,
            is_destination: false,
        };

        setLocations(prev => ({ ...prev, [newId]: newLoc }));
        setAdj(prev => ({ ...prev, [newId]: [] }));
        setSelectedNodeId(newId);
        setCurrentTool('move');
        showStatus(`Node ${newId} added!`, 'success');
    };

    // Node drag handlers
    const handleMouseDownNode = (e, nodeId) => {
        e.stopPropagation();
        if (currentTool === 'delete') {
            handleDeleteNode(nodeId);
            return;
        }

        if (currentTool === 'connect') {
            if (!connectStartNodeId) {
                setConnectStartNodeId(nodeId);
                showStatus(`Selected ${nodeId}. Click another node to link edge.`, 'info');
            } else if (connectStartNodeId === nodeId) {
                setConnectStartNodeId(null);
            } else {
                // Connect or toggle edge
                toggleEdge(connectStartNodeId, nodeId);
                setConnectStartNodeId(null);
            }
            return;
        }

        setSelectedNodeId(nodeId);
        if (currentTool === 'move') {
            setDraggingNodeId(nodeId);
        }
    };

    const handleMouseMove = (e) => {
        if (!draggingNodeId || !mapContainerRef.current) return;

        const rect = mapContainerRef.current.getBoundingClientRect();
        const clientX = e.clientX - rect.left;
        const clientY = e.clientY - rect.top;

        let x = parseFloat(((clientX / rect.width) * 100).toFixed(1));
        let y = parseFloat(((clientY / rect.height) * 100).toFixed(1));

        x = Math.max(0, Math.min(100, x));
        y = Math.max(0, Math.min(100, y));

        setLocations(prev => ({
            ...prev,
            [draggingNodeId]: {
                ...prev[draggingNodeId],
                x,
                y,
            },
        }));
    };

    const handleMouseUp = () => {
        if (draggingNodeId) {
            setDraggingNodeId(null);
        }
    };

    // Toggle Edge
    const toggleEdge = (nodeA, nodeB) => {
        setAdj(prev => {
            const next = { ...prev };
            const listA = [...(next[nodeA] || [])];
            const listB = [...(next[nodeB] || [])];

            const indexB = listA.indexOf(nodeB);
            if (indexB > -1) {
                listA.splice(indexB, 1);
            } else {
                listA.push(nodeB);
            }

            const indexA = listB.indexOf(nodeA);
            if (indexA > -1) {
                listB.splice(indexA, 1);
            } else {
                listB.push(nodeA);
            }

            next[nodeA] = listA;
            next[nodeB] = listB;
            return next;
        });
        showStatus(`Edge updated between ${nodeA} and ${nodeB}.`, 'success');
    };

    // Delete Node
    const handleDeleteNode = (nodeId) => {
        if (!confirm(`Hapus node ${nodeId}?`)) return;

        setLocations(prev => {
            const next = { ...prev };
            delete next[nodeId];
            return next;
        });

        setAdj(prev => {
            const next = { ...prev };
            delete next[nodeId];
            // Remove references from other nodes
            Object.keys(next).forEach(n => {
                next[n] = next[n].filter(target => target !== nodeId);
            });
            return next;
        });

        if (selectedNodeId === nodeId) setSelectedNodeId(null);
        if (connectStartNodeId === nodeId) setConnectStartNodeId(null);
        showStatus(`Node ${nodeId} deleted.`, 'info');
    };

    // Save Graph Map
    const handleSaveGraph = async () => {
        setIsSaving(true);
        try {
            const formattedLocations = Object.values(locations);
            const payload = {
                locations: formattedLocations,
                adj: adj,
            };

            const res = await axios.post('/api/graph/save', payload);
            if (res.data.success) {
                showStatus('Graph map successfully saved to server!', 'success');
            } else {
                showStatus('Failed to save graph map.', 'error');
            }
        } catch (err) {
            console.error('Error saving graph:', err);
            showStatus('Error saving graph map to server.', 'error');
        } finally {
            setIsSaving(false);
        }
    };

    // Reset System
    const handleResetSystem = async () => {
        if (!confirm('Are you sure you want to reset all robot units to home base and restore idle status?')) return;
        try {
            const res = await axios.post('/api/system/reset');
            if (res.data.success) {
                showStatus('System reset successfully.', 'success');
            }
        } catch (err) {
            console.error('Reset error:', err);
            showStatus('Failed to reset system.', 'error');
        }
    };

    const showStatus = (msg, type = 'info') => {
        setStatusMessage({ text: msg, type });
        setTimeout(() => {
            setStatusMessage(null);
        }, 4000);
    };

    // Filter visible nodes on current floor
    const floorNodes = Object.values(locations).filter(loc => (loc.floor || 1) === currentFloor);

    // Compute unique edges to draw SVG lines on current floor
    const floorEdges = [];
    const drawnEdgeKeys = new Set();

    floorNodes.forEach(nodeA => {
        const neighbors = adj[nodeA.id] || [];
        neighbors.forEach(nodeBId => {
            const nodeB = locations[nodeBId];
            if (!nodeB) return;

            // Only draw if both or either is on current floor
            const key1 = `${nodeA.id}-${nodeBId}`;
            const key2 = `${nodeBId}-${nodeA.id}`;
            if (!drawnEdgeKeys.has(key1) && !drawnEdgeKeys.has(key2)) {
                drawnEdgeKeys.add(key1);
                drawnEdgeKeys.add(key2);
                floorEdges.push({ nodeA, nodeB });
            }
        });
    });

    return (
        <Layout>
            <Head title="Map Editor & Fleet Control - ROBOPATH" />

            <div className="space-y-6" onMouseMove={handleMouseMove} onMouseUp={handleMouseUp}>
                {/* Status Toast */}
                {statusMessage && (
                    <div
                        className={`p-3 rounded-xl border text-xs font-bold flex items-center gap-2 shadow-lg transition duration-200 ${
                            statusMessage.type === 'success'
                                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
                                : statusMessage.type === 'error'
                                ? 'bg-rose-500/10 border-rose-500/30 text-rose-400'
                                : 'bg-sky-500/10 border-sky-500/30 text-sky-400'
                        }`}
                    >
                        {statusMessage.type === 'success' ? (
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                        ) : (
                            <AlertCircle className="w-4 h-4 shrink-0" />
                        )}
                        <span>{statusMessage.text}</span>
                    </div>
                )}

                {/* Top Control Bar */}
                <div className="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-xl backdrop-blur-md flex flex-wrap items-center justify-between gap-4">
                    {/* Floor Selector Tabs */}
                    <div className="flex items-center gap-2 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                        <button
                            onClick={() => handleSwitchFloor(1)}
                            className={`px-4 py-2 rounded-lg transition flex items-center gap-1.5 ${
                                currentFloor === 1 ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white'
                            }`}
                        >
                            <Layers className="w-4 h-4" />
                            Lantai 1 (Ground Floor)
                        </button>
                        <button
                            onClick={() => handleSwitchFloor(2)}
                            className={`px-4 py-2 rounded-lg transition flex items-center gap-1.5 ${
                                currentFloor === 2 ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white'
                            }`}
                        >
                            <Layers className="w-4 h-4" />
                            Lantai 2 (Upper Floor)
                        </button>
                    </div>

                    {/* Tool Action Buttons */}
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-bold">
                            <button
                                onClick={() => {
                                    setCurrentTool('move');
                                    setConnectStartNodeId(null);
                                }}
                                className={`px-3 py-2 rounded-lg flex items-center gap-1.5 transition ${
                                    currentTool === 'move' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <Move className="w-3.5 h-3.5" /> Move
                            </button>
                            <button
                                onClick={() => {
                                    setCurrentTool('add');
                                    setConnectStartNodeId(null);
                                }}
                                className={`px-3 py-2 rounded-lg flex items-center gap-1.5 transition ${
                                    currentTool === 'add' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <PlusCircle className="w-3.5 h-3.5" /> Add Node
                            </button>
                            <button
                                onClick={() => {
                                    setCurrentTool('connect');
                                    setConnectStartNodeId(null);
                                }}
                                className={`px-3 py-2 rounded-lg flex items-center gap-1.5 transition ${
                                    currentTool === 'connect' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white'
                                }`}
                            >
                                <Network className="w-3.5 h-3.5" /> Connect Edges
                            </button>
                            <button
                                onClick={() => {
                                    setCurrentTool('delete');
                                    setConnectStartNodeId(null);
                                }}
                                className={`px-3 py-2 rounded-lg flex items-center gap-1.5 transition ${
                                    currentTool === 'delete' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-rose-400'
                                }`}
                            >
                                <Trash2 className="w-3.5 h-3.5" /> Delete
                            </button>
                        </div>

                        {/* Show/Hide Transit Dots Toggle */}
                        <button
                            onClick={() => setShowHiddenDots(!showHiddenDots)}
                            className="bg-slate-950 border border-slate-800 text-slate-300 hover:text-white font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition"
                        >
                            {showHiddenDots ? (
                                <>
                                    <Eye className="w-3.5 h-3.5 text-indigo-400" />
                                    <span>Showing All Nodes</span>
                                </>
                            ) : (
                                <>
                                    <EyeOff className="w-3.5 h-3.5 text-slate-500" />
                                    <span>Transit Dots Hidden</span>
                                </>
                            )}
                        </button>

                        {/* Save Graph Button */}
                        <button
                            onClick={handleSaveGraph}
                            disabled={isSaving}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition duration-200"
                        >
                            <Save className="w-4 h-4" />
                            <span>{isSaving ? 'Saving Graph...' : 'Save Graph Map'}</span>
                        </button>
                    </div>
                </div>

                {/* Main Workspace */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {/* Interactive Map Canvas (2/3 Width) */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-2xl backdrop-blur-md">
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <h3 className="text-base font-bold text-white flex items-center gap-2">
                                        <Network className="w-5 h-5 text-indigo-400" />
                                        Visual Map Node Editor
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        {currentTool === 'move' && 'Drag nodes to reposition them. Click a node to view/edit properties.'}
                                        {currentTool === 'add' && 'Click anywhere on the map floorplan to create a new node.'}
                                        {currentTool === 'connect' && (connectStartNodeId ? `Click a target node to connect with ${connectStartNodeId}.` : 'Click first node to start connecting edges.')}
                                        {currentTool === 'delete' && 'Click any node to delete it and remove connected edges.'}
                                    </p>
                                </div>
                                <span className="text-xs font-bold text-indigo-300 bg-indigo-500/20 px-3 py-1 rounded-full border border-indigo-500/30">
                                    Lantai {currentFloor}
                                </span>
                            </div>

                            {/* Canvas Container */}
                            <div
                                ref={mapContainerRef}
                                onClick={handleMapClick}
                                className="relative w-full aspect-[16/9] rounded-xl overflow-hidden border border-slate-800 shadow-inner select-none bg-cover bg-center cursor-crosshair"
                                style={{
                                    backgroundImage: `url('${currentFloor === 1 ? '/images/floor1.jpeg' : '/images/floor2.jpeg'}')`,
                                }}
                            >
                                {/* SVG Edges Layer */}
                                <svg className="absolute inset-0 w-full h-full pointer-events-none z-10">
                                    {floorEdges.map(({ nodeA, nodeB }) => {
                                        const isCrossFloor = (nodeA.floor || 1) !== (nodeB.floor || 1);
                                        const isSelectedEdge = selectedNodeId && (nodeA.id === selectedNodeId || nodeB.id === selectedNodeId);

                                        return (
                                            <line
                                                key={`edge-${nodeA.id}-${nodeB.id}`}
                                                x1={`${nodeA.x}%`}
                                                y1={`${nodeA.y}%`}
                                                x2={`${nodeB.x}%`}
                                                y2={`${nodeB.y}%`}
                                                stroke={isSelectedEdge ? '#f59e0b' : isCrossFloor ? '#a855f7' : '#6366f1'}
                                                strokeWidth={isSelectedEdge ? '3.5' : '2'}
                                                strokeDasharray={isCrossFloor ? '4 4' : 'none'}
                                                strokeOpacity={isSelectedEdge ? '1' : '0.6'}
                                            />
                                        );
                                    })}
                                </svg>

                                {/* Nodes Layer */}
                                <div className="absolute inset-0 z-20">
                                    {floorNodes.map(node => {
                                        if (node.hidden && !showHiddenDots && selectedNodeId !== node.id) {
                                            return null;
                                        }

                                        const isSelected = selectedNodeId === node.id;
                                        const isConnecting = connectStartNodeId === node.id;

                                        return (
                                            <div
                                                key={`node-${node.id}`}
                                                onMouseDown={(e) => handleMouseDownNode(e, node.id)}
                                                className={`absolute transform -translate-x-1/2 -translate-y-1/2 cursor-pointer transition-transform ${
                                                    isSelected ? 'scale-125 z-30' : 'hover:scale-110 z-20'
                                                }`}
                                                style={{ left: `${node.x}%`, top: `${node.y}%` }}
                                            >
                                                <div
                                                    className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-lg border-2 ${
                                                        isSelected
                                                            ? 'border-amber-400 bg-amber-500 ring-4 ring-amber-500/30'
                                                            : isConnecting
                                                            ? 'border-emerald-400 bg-emerald-500 ring-4 ring-emerald-500/30'
                                                            : node.is_destination
                                                            ? 'border-sky-300 bg-sky-600'
                                                            : node.hidden
                                                            ? 'border-slate-400 bg-slate-700/80 opacity-80'
                                                            : 'border-indigo-400 bg-indigo-600'
                                                    }`}
                                                >
                                                    {node.is_destination ? 'D' : node.id.replace(/^[12]_/, '')}
                                                </div>

                                                {/* Node Label Tooltip */}
                                                <span className="absolute top-7 left-1/2 transform -translate-x-1/2 text-[9px] font-bold bg-slate-950/90 text-slate-200 px-1.5 py-0.5 rounded shadow border border-slate-800 whitespace-nowrap pointer-events-none">
                                                    {node.name || node.id}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Node Inspector & Properties (1/3 Width) */}
                    <div className="space-y-6">
                        <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md flex flex-col">
                            <h3 className="text-base font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center gap-2">
                                <Edit3 className="w-4.5 h-4.5 text-indigo-400" />
                                Node Properties Inspector
                            </h3>

                            {selectedNode ? (
                                <div className="space-y-4 text-xs text-slate-300">
                                    <div>
                                        <label className="block font-bold text-slate-400 uppercase tracking-wider mb-1">
                                            Node Name / Room Title
                                        </label>
                                        <input
                                            type="text"
                                            value={selectedNode.name || ''}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setLocations(prev => ({
                                                    ...prev,
                                                    [selectedNode.id]: {
                                                        ...prev[selectedNode.id],
                                                        name: val,
                                                    },
                                                }));
                                            }}
                                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm font-bold text-white focus:outline-none focus:border-indigo-500 transition"
                                        />
                                    </div>

                                    <div className="grid grid-cols-3 gap-2">
                                        <div>
                                            <label className="block font-bold text-slate-400 uppercase tracking-wider mb-1">Floor</label>
                                            <select
                                                value={selectedNode.floor || 1}
                                                onChange={(e) => {
                                                    const floor = parseInt(e.target.value, 10);
                                                    setLocations(prev => ({
                                                        ...prev,
                                                        [selectedNode.id]: {
                                                            ...prev[selectedNode.id],
                                                            floor,
                                                        },
                                                    }));
                                                }}
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 font-bold text-slate-200 focus:outline-none"
                                            >
                                                <option value="1">Lantai 1</option>
                                                <option value="2">Lantai 2</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block font-bold text-slate-400 uppercase tracking-wider mb-1">X (%)</label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                max="100"
                                                value={selectedNode.x}
                                                onChange={(e) => {
                                                    const x = parseFloat(e.target.value) || 0;
                                                    setLocations(prev => ({
                                                        ...prev,
                                                        [selectedNode.id]: {
                                                            ...prev[selectedNode.id],
                                                            x,
                                                        },
                                                    }));
                                                }}
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 font-mono font-bold text-slate-200 focus:border-indigo-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block font-bold text-slate-400 uppercase tracking-wider mb-1">Y (%)</label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                max="100"
                                                value={selectedNode.y}
                                                onChange={(e) => {
                                                    const y = parseFloat(e.target.value) || 0;
                                                    setLocations(prev => ({
                                                        ...prev,
                                                        [selectedNode.id]: {
                                                            ...prev[selectedNode.id],
                                                            y,
                                                        },
                                                    }));
                                                }}
                                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 font-mono font-bold text-slate-200 focus:border-indigo-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div className="bg-slate-950/60 p-4 rounded-xl border border-slate-800/80 space-y-3">
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={!!selectedNode.is_destination}
                                                onChange={(e) => {
                                                    const checked = e.target.checked;
                                                    setLocations(prev => ({
                                                        ...prev,
                                                        [selectedNode.id]: {
                                                            ...prev[selectedNode.id],
                                                            is_destination: checked,
                                                        },
                                                    }));
                                                }}
                                                className="mt-0.5 rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div>
                                                <span className="font-bold text-slate-200 block">Use as Pickup / Destination Room</span>
                                                <span className="text-[11px] text-slate-400 block">Available in delivery room selection dropdowns</span>
                                            </div>
                                        </label>

                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={!!selectedNode.hidden}
                                                onChange={(e) => {
                                                    const checked = e.target.checked;
                                                    setLocations(prev => ({
                                                        ...prev,
                                                        [selectedNode.id]: {
                                                            ...prev[selectedNode.id],
                                                            hidden: checked,
                                                        },
                                                    }));
                                                }}
                                                className="mt-0.5 rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <div>
                                                <span className="font-bold text-slate-200 block">Hide Marker on Dashboard Map</span>
                                                <span className="text-[11px] text-slate-400 block">Functions for routing path transit only</span>
                                            </div>
                                        </label>
                                    </div>

                                    {/* Connected Neighbors Manager */}
                                    <div>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <label className="font-bold text-slate-400 uppercase tracking-wider">Connected Edges</label>
                                            <span className="text-[10px] text-slate-500 font-semibold">
                                                {connectedNeighbors.length} edges
                                            </span>
                                        </div>
                                        <div className="bg-slate-950 border border-slate-800 rounded-xl p-3 min-h-[70px] max-h-[160px] overflow-y-auto space-y-1.5">
                                            {connectedNeighbors.length === 0 ? (
                                                <span className="text-slate-500 italic text-xs">No connected edges yet.</span>
                                            ) : (
                                                connectedNeighbors.map(targetId => (
                                                    <div key={targetId} className="flex items-center justify-between bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-800">
                                                        <span className="font-semibold text-slate-200 text-xs">
                                                            {locations[targetId]?.name || targetId}
                                                        </span>
                                                        <button
                                                            onClick={() => toggleEdge(selectedNode.id, targetId)}
                                                            className="text-rose-400 hover:text-rose-300 text-xs font-bold px-1.5 py-0.5 rounded hover:bg-rose-500/10"
                                                        >
                                                            Disconnect
                                                        </button>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-10 text-slate-500 text-xs italic">
                                    Click any node on the map to inspect and edit its properties.
                                </div>
                            )}
                        </div>

                        {/* Fleet Reset Action Card */}
                        <div className="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl shadow-xl backdrop-blur-md">
                            <h3 className="text-base font-bold text-white mb-2 flex items-center gap-2">
                                <Sliders className="w-4.5 h-4.5 text-amber-400" />
                                Fleet System Controls
                            </h3>
                            <p className="text-xs text-slate-400 mb-4">
                                Emergency reset all robot units to home base (1_N7) and restore idle status.
                            </p>
                            <button
                                onClick={handleResetSystem}
                                className="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 rounded-xl text-xs transition duration-200 shadow-lg shadow-rose-600/30 flex items-center justify-center gap-2"
                            >
                                <RotateCcw className="w-4 h-4" />
                                <span>Reset All Units to Home Base</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
