@extends('layouts.layout')

@section('title', 'ROBOPATH - Control')
@section('page_title', 'Control')
@section('page_subtitle', 'Pusat kendali robot, perbaikan, dan editor jalur robot')

@section('topbar_actions')
<div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl border border-gray-200 shadow-xs">
    <button type="button" onclick="handleEditRobotClick()" id="topbar-btn-edit-robot" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-white text-[#3b4cb8] shadow-xs hover:bg-gray-50 active:scale-95" title="Buka Mode Edit Robot (Atur Posisi 3D & Elevasi)">
        <i class="fa-solid fa-robot text-[#3b4cb8]"></i> <span>Edit Robot</span>
    </button>
    <button type="button" onclick="handleEditJalurClick()" id="topbar-btn-edit-jalur" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 text-gray-700 hover:text-[#3b4cb8] hover:bg-white/80 active:scale-95" title="Buka Mode Edit Jalur &amp; Ruangan (Layar Penuh)">
        <i class="fa-solid fa-route text-indigo-600"></i> <span>Edit Jalur Robot</span>
    </button>
</div>
@endsection

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
    #botctrl-3d-canvas-container, #botctrl-3d-canvas-f1 {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }
    #botctrl-3d-canvas-container canvas, #botctrl-3d-canvas-f1 canvas {
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
    /* Full Map 3D Mode Styles */
    .botctrl-fullmap-card {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        z-index: 9999 !important;
        margin: 0 !important;
        border-radius: 0 !important;
        background-color: #0b1120 !important;
        padding: 0.75rem !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .botctrl-fullmap-canvas {
        flex: 1 1 0% !important;
        height: 100% !important;
        aspect-ratio: auto !important;
        border-radius: 0.75rem !important;
    }
    /* Floating Collapsible Inspector in Full Map */
    .botctrl-inspector-floating {
        position: fixed !important;
        top: 4.5rem !important;
        right: 1.25rem !important;
        z-index: 10001 !important;
        width: 25rem !important;
        max-height: calc(100vh - 5.5rem) !important;
        overflow-y: auto !important;
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(16px) !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.4) !important;
    }
    .botctrl-inspector-floating::-webkit-scrollbar {
        width: 6px;
    }
    .botctrl-inspector-floating::-webkit-scrollbar-track {
        background: transparent;
    }
    .botctrl-inspector-floating::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    /* Hide sidebar and prioritize main container when Full Map 3D is active */
    body.body-in-fullmap > aside {
        display: none !important;
    }
    body.body-in-fullmap > main {
        z-index: 9999 !important;
    }
</style>
@endsection

@section('content')
<div class="space-y-8">

    <!-- Pusat Perbaikan Robot Status Card -->
    <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 shadow-xs">
                    <i class="fa-solid fa-screwdriver-wrench text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        Pusat Perbaikan Robot
                    </h3>
                    <p class="text-xs text-gray-500">Pantau status robot, pulihkan robot yang menabrak, atau kelola perbaikan tugas yang tertunda.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full border border-emerald-200 flex items-center gap-1.5 font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Sistem Aktif (Live)
                </span>
            </div>
        </div>

        <!-- Dynamic Robot Fleet List -->
        <div id="fleet-control-list" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div class="col-span-full text-center py-8 text-xs text-gray-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat telemetri robot...
            </div>
        </div>

        <!-- Emergency Reset All Button -->
        <div class="pt-4 border-t border-gray-100 flex justify-end">
            <button onclick="resetSystem()" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold px-6 py-2.5 rounded-xl text-xs transition duration-200 shadow-sm flex items-center justify-center gap-2 active:scale-95">
                <i class="fa-solid fa-rotate-left text-rose-500"></i> Reset Semua Unit ke Pangkalan
            </button>
        </div>
    </div>

    <!-- Main Workspace: Interactive Map Canvas (Full Width, hidden by default in normal view) -->
    <div class="w-full space-y-4">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl transition-all hidden" id="editor-map-card">
                <!-- Dedicated Top Bar for Full Map Mode (Single Sleek Contextual Row) -->
                <div id="fullmap-top-bar" class="hidden flex items-center justify-between gap-3 pb-2.5 mb-2 border-b border-slate-700/60 select-none text-xs">
                    <!-- Left: Floor Switcher & Mode Switcher -->
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Floor Switcher -->
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                            <button type="button" onclick="switchFloor(1)" id="fullmap-tab-floor-1" class="px-3 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 1
                            </button>
                            <button type="button" onclick="switchFloor(2)" id="fullmap-tab-floor-2" class="px-3 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 2
                            </button>
                        </div>

                        <!-- Mode Switcher -->
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                            <button type="button" onclick="setEditTargetMode('node')" id="fullmap-tab-mode-node" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Mode Edit Jalur &amp; Ruangan">
                                <i class="fa-solid fa-circle-dot"></i> <span>Edit Ruangan</span>
                            </button>
                            <button type="button" onclick="setEditTargetMode('robot')" id="fullmap-tab-mode-robot" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white shadow font-bold flex items-center gap-1.5 transition" title="Mode Edit Robot">
                                <i class="fa-solid fa-robot"></i> <span>Edit Robot</span>
                            </button>
                        </div>
                    </div>

                    <!-- Center 1: Dedicated Node Tools (ONLY SHOWN IN NODE MODE) -->
                    <div id="fullmap-node-contextual" class="hidden flex items-center gap-2">
                        <!-- Node Tool Actions -->
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                            <button type="button" onclick="setEditorTool('hand')" id="fullmap-tool-hand" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Free Hand: Geser kanvas / navigasi bebas 3D">
                                <i class="fa-solid fa-hand"></i> <span>Free Hand</span>
                            </button>
                            <button type="button" onclick="setEditorTool('move')" id="fullmap-tool-move" class="px-3 py-1.5 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 transition" title="Geser posisi node">
                                <i class="fa-solid fa-up-down-left-right"></i> <span>Move Node</span>
                            </button>
                            <button type="button" onclick="setEditorTool('add')" id="fullmap-tool-add" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Tambah titik node baru">
                                <i class="fa-solid fa-plus-circle"></i> <span>Add</span>
                            </button>
                            <button type="button" onclick="setEditorTool('connect')" id="fullmap-tool-connect" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Hubungkan rute jalur antar node">
                                <i class="fa-solid fa-diagram-project"></i> <span>Connect</span>
                            </button>
                            <button type="button" onclick="setEditorTool('delete')" id="fullmap-tool-delete" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Hapus node">
                                <i class="fa-solid fa-trash-can"></i> <span>Delete</span>
                            </button>
                        </div>

                        <!-- Transit Dots Toggle -->
                        <button type="button" onclick="toggleShowHiddenDots()" id="fullmap-btn-toggle-hidden" class="bg-sky-950/80 hover:bg-sky-900 border border-sky-500/40 text-sky-300 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap" title="Tampilkan / Sembunyikan Titik Transit Tanpa Nama">
                            <i class="fa-solid fa-eye text-sky-400" id="fullmap-icon-toggle-hidden"></i> <span id="fullmap-text-toggle-hidden">Transit: Tampil</span>
                        </button>

                        <!-- Label Size Scale -->
                        <div class="flex items-center gap-1 bg-slate-900/90 border border-white/10 px-2 py-1 rounded-xl text-xs font-bold whitespace-nowrap" title="Sesuaikan Ukuran Label Teks">
                            <span class="text-gray-400 text-[11px]"><i class="fa-solid fa-font text-indigo-400 mr-1"></i>Label:</span>
                            <button type="button" onclick="adjustLabelScale(-0.1)" class="w-5 h-5 rounded bg-white/10 hover:bg-white/20 text-gray-200 flex items-center justify-center text-xs font-bold transition active:scale-95">
                                <i class="fa-solid fa-minus text-[9px]"></i>
                            </button>
                            <span class="label-scale-val font-mono font-bold text-sky-400 w-8 text-center text-xs">0.8x</span>
                            <button type="button" onclick="adjustLabelScale(0.1)" class="w-5 h-5 rounded bg-white/10 hover:bg-white/20 text-gray-200 flex items-center justify-center text-xs font-bold transition active:scale-95">
                                <i class="fa-solid fa-plus text-[9px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Center 2: Dedicated Robot Tools (ONLY SHOWN IN ROBOT MODE) -->
                    <div id="fullmap-robot-contextual" class="flex items-center gap-2">
                        <!-- Robot Actions Group -->
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                            <button type="button" onclick="setEditorTool('hand')" id="fullmap-tool-robot-hand" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Free Hand: Navigasi bebas 3D (Pan &amp; Orbit)">
                                <i class="fa-solid fa-hand"></i> <span>Free Hand</span>
                            </button>
                            <button type="button" onclick="setEditorTool('move')" id="fullmap-tool-robot-move" class="px-3 py-1.5 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 transition" title="Move Robot: Geser posisi robot di kanvas 3D">
                                <i class="fa-solid fa-arrows-up-down-left-right"></i> <span>Move Robot</span>
                            </button>
                            <button type="button" onclick="focusOnActiveSelection()" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Pusatkan kamera ke posisi robot aktif">
                                <i class="fa-solid fa-crosshairs text-amber-400"></i> <span>Fokus</span>
                            </button>
                            <button type="button" onclick="resetActiveRobotPosition()" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Reset posisi robot">
                                <i class="fa-solid fa-arrows-rotate"></i> <span>Reset</span>
                            </button>
                        </div>

                        <!-- Toggle Robot Avatar -->
                        <button type="button" onclick="toggleShowRobots()" id="fullmap-btn-toggle-robots" class="bg-sky-950/80 border border-sky-500/40 text-sky-300 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition whitespace-nowrap" title="Sembunyikan / Tampilkan Avatar Robot 3D">
                            <i class="fa-solid fa-robot text-sky-400" id="fullmap-icon-toggle-robots"></i> <span id="fullmap-text-toggle-robots">Robot: Tampil</span>
                        </button>
                    </div>

                    <!-- Right: Inspector Toggle & Exit -->
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Inspector Toggle Button -->
                        <button type="button" onclick="toggleInspectorPanel()" id="fullmap-btn-inspector" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition" title="Tampilkan / Sembunyikan Panel Edit Manual XYZ">
                            <i class="fa-solid fa-sliders"></i> <span id="fullmap-inspector-btn-label">Edit Manual XYZ</span>
                            <span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono hidden"></span>
                        </button>

                        <!-- Exit Full Map Button -->
                        <button type="button" onclick="toggleFullMap(false)" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition" title="Keluar dari Full Map (Esc)">
                            <i class="fa-solid fa-compress"></i> <span>Keluar</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4" id="editor-header-bar">
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

                <!-- Editor Canvas Container — kedua lantai 3D -->
                <div class="editor-map-container shadow-inner border border-gray-300 overflow-hidden" id="editor-map-container" style="background-color:#0f172a;">
                    <!-- 3D Canvas Layer for Floor 2 -->
                    <div id="botctrl-3d-canvas-container" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>
                    <!-- 3D Canvas Layer for Floor 1 -->
                    <div id="botctrl-3d-canvas-f1" class="absolute inset-0 z-0 hidden pointer-events-auto"></div>
                    <!-- 3D Loading Overlay (shared) -->
                    <div id="botctrl-3d-loader" class="hidden absolute inset-0 z-30 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center text-white">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#3b4cb8] to-sky-400 p-0.5 shadow-2xl mb-4 animate-bounce">
                            <div class="w-full h-full bg-slate-900 rounded-2xl flex items-center justify-center">
                                <i class="fa-solid fa-cube text-2xl text-sky-400 animate-spin"></i>
                            </div>
                        </div>
                        <h4 class="font-bold text-sm tracking-wide text-gray-100 mb-1" id="botctrl-3d-loader-title">Memuat Model 3D Lantai 1...</h4>
                        <p class="text-xs text-gray-400 mb-4" id="botctrl-3d-loader-status">Mengunduh aset GLB (8 MB)...</p>
                        <div class="w-56 bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                            <div id="botctrl-3d-loader-bar" class="bg-gradient-to-r from-[#3b4cb8] to-sky-400 h-2 rounded-full transition-all duration-200" style="width:5%"></div>
                        </div>
                        <span id="botctrl-3d-loader-pct" class="text-[11px] font-mono text-sky-400 font-bold mt-2">5%</span>
                    </div>
                    <svg class="editor-svg" id="editor-svg"></svg>
                    <div id="editor-nodes-layer"></div>
                    <!-- Floating 3D Navigation & Zoom Controls -->
                    <div id="botctrl-3d-nav-controls" class="absolute top-3 right-3 z-20 flex flex-col gap-1.5 pointer-events-auto">
                        <button type="button" onclick="zoom3DCamera(0.65)" title="Zoom In / Mendekat (+)" class="w-8 h-8 rounded-lg bg-slate-900/85 hover:bg-slate-800 text-white flex items-center justify-center text-xs shadow border border-white/10 transition backdrop-blur-sm active:scale-95">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <button type="button" onclick="zoom3DCamera(1.5)" title="Zoom Out / Menjauh (-)" class="w-8 h-8 rounded-lg bg-slate-900/85 hover:bg-slate-800 text-white flex items-center justify-center text-xs shadow border border-white/10 transition backdrop-blur-sm active:scale-95">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <button type="button" onclick="focusOnActiveSelection()" title="Fokus Kamera ke Node / Robot Terpilih" class="w-8 h-8 rounded-lg bg-slate-900/85 hover:bg-slate-800 text-amber-400 flex items-center justify-center text-xs shadow border border-white/10 transition backdrop-blur-sm active:scale-95">
                            <i class="fa-solid fa-crosshairs"></i>
                        </button>
                        <button type="button" onclick="toggleFullMap()" id="btn-floating-fullmap" title="Full Map 3D / Layar Penuh" class="w-8 h-8 rounded-lg bg-slate-900/85 hover:bg-slate-800 text-sky-400 flex items-center justify-center text-xs shadow border border-white/10 transition backdrop-blur-sm active:scale-95">
                            <i class="fa-solid fa-expand" id="icon-floating-fullmap"></i>
                        </button>
                    </div>

                    <!-- 3D Hint -->
                    <div id="botctrl-3d-hint" class="hidden absolute bottom-2 right-2 z-30 bg-slate-900/80 backdrop-blur-md text-white px-2.5 py-1 rounded-lg text-[10px] font-semibold border border-white/10 shadow flex items-center gap-1.5 pointer-events-none">
                        <i class="fa-solid fa-cube text-sky-400"></i> Putar (Klik Kiri) &bull; Pan/Geser (Klik Kanan) &bull; Zoom Dekat (Scroll/Tombol)
                    </div>
                </div>

                <!-- Floating Inspector Card: Node / Ruangan -->
                <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col transition-all botctrl-inspector-floating hidden" id="node-inspector-card">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 select-none">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600 shadow-xs">
                                <i class="fa-solid fa-circle-dot text-base"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-800" id="inspector-title-text">Node Properties Inspector</h3>
                                <p class="text-[11px] text-gray-400">Atur rute, nama ruangan, titik transit, dan elevasi lantai.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200" id="inspector-mode-tag">NODE EDIT</span>
                            <!-- Close Button -->
                            <button type="button" onclick="toggleInspectorPanel(false)" id="btn-close-inspector" class="text-gray-400 hover:text-gray-700 w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-sm font-bold transition" title="Tutup Panel">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                <!-- Node Properties Inspector Body -->
                <div id="inspector-node-body" class="space-y-4 flex-1 text-xs text-gray-700">
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Node Name / Room Title <span class="text-gray-400 font-normal lowercase">(contoh: Hall, Lobby, R.Meeting)</span></label>
                        <input type="text" id="inspect-node-name" onchange="handleRenameNode(this.value)" placeholder="Pilih / klik sebuah titik node untuk edit..." class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold text-gray-800 focus:bg-white focus:border-[#3b4cb8] focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-4 gap-2.5">
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1 text-[10px]">Floor</label>
                            <select id="inspect-floor" onchange="handleFloorChange(this.value)" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-2 py-2 font-bold text-gray-800 focus:outline-none text-xs">
                                <option value="1">Lantai 1</option>
                                <option value="2">Lantai 2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1 text-[10px]">X (%)</label>
                            <input type="number" step="0.1" min="0" max="100" id="inspect-x" oninput="handleCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1 text-[10px]">Y (%)</label>
                            <input type="number" step="0.1" min="0" max="100" id="inspect-y" oninput="handleCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1 text-[10px]">Elev (Y)</label>
                            <input type="number" step="any" id="inspect-y-elev" oninput="handleElevationChange(this.value)" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition text-xs" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Room Flags & Destination -->
                    <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2.5">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" id="inspect-is-destination" onchange="handleIsDestinationChange(this.checked)" class="mt-0.5 rounded border-gray-300 text-[#3b4cb8] focus:ring-[#3b4cb8]">
                            <div>
                                <span class="font-bold text-gray-800 block">Use as Destination Room</span>
                                <span class="text-[10px] text-gray-500 block">Tampil di menu tujuan pengiriman</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" id="inspect-hidden" onchange="handleHiddenChange(this.checked)" class="mt-0.5 rounded border-gray-300 text-[#3b4cb8] focus:ring-[#3b4cb8]">
                            <div>
                                <span class="font-bold text-gray-800 block">Hide Marker on Map</span>
                                <span class="text-[10px] text-gray-500 block">Hanya untuk rute perantara (transit)</span>
                            </div>
                        </label>
                    </div>

                    <!-- Hidden fallbacks for object linking compatibility -->
                    <select id="inspect-object" class="hidden" onchange="handleObjectNameChange(this.value)"><option value="">— Manual (x/y) —</option></select>
                    <span id="inspect-object-status" class="hidden">Manual</span>
                    <span id="inspect-object-xyz" class="hidden">—</span>

                    <!-- Connected Neighbors (Edges) Manager -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-gray-500 uppercase tracking-wider text-[10px]">Connected Edges / Jalur Terhubung</label>
                            <span class="text-[10px] text-gray-400 font-semibold" id="neighbors-count-badge">0 edges</span>
                        </div>
                        <div id="inspect-neighbors" class="bg-gray-50 border border-gray-200 rounded-xl p-3 min-h-[60px] max-h-[140px] overflow-y-auto space-y-1.5">
                            <span class="text-gray-400 italic">No node selected</span>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons in Node Inspector -->
                    <div class="pt-2 border-t border-gray-100">
                        <button type="button" onclick="saveGraphToServer()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2 shadow transition">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Denah
                        </button>
                    </div>
                </div>
            </div>

            <!-- Floating Inspector Card: Robot -->
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col transition-all botctrl-inspector-floating hidden" id="robot-control-card">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 select-none">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-[#3b4cb8] shadow-xs">
                            <i class="fa-solid fa-robot text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-800">Pengaturan Robot</h3>
                            <p class="text-[11px] text-gray-400">Sesuaikan skala universal dan ketinggian roda robot.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200">ROBOT</span>
                        <!-- Close Button -->
                        <button type="button" onclick="toggleInspectorPanel(false)" id="btn-close-inspector-robot" class="text-gray-400 hover:text-gray-700 w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-sm font-bold transition" title="Tutup Panel">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 flex-1 text-xs text-gray-700">
                    <!-- Row 1: Robot Selector & Floor Switcher -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-blue-50/50 p-3.5 rounded-xl border border-blue-100">
                        <!-- Robot Selector -->
                        <div>
                            <label class="block font-bold text-gray-600 uppercase tracking-wider mb-1 text-[10px]">Pilih Robot</label>
                            <select id="robot-selector" onchange="setActiveRobot(Number(this.value)); inspectRobot(Number(this.value));" class="w-full bg-white border border-gray-300 rounded-xl px-2.5 py-2 font-bold text-gray-800 focus:outline-none focus:border-[#3b4cb8] text-xs">
                                <option value="">Memuat robot...</option>
                            </select>
                            <input type="hidden" id="inspect-robot-selector">
                            <div class="flex items-center justify-between mt-1.5 px-0.5">
                                <span id="robot-active-name" class="font-bold text-gray-800 text-xs truncate max-w-[120px]">-</span>
                                <span id="robot-status-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-300">Online</span>
                            </div>
                        </div>

                        <!-- Floor Switcher -->
                        <div>
                            <label class="block font-bold text-gray-600 uppercase tracking-wider mb-1 text-[10px]">Lantai Robot</label>
                            <div class="grid grid-cols-2 gap-1.5 p-1 bg-white rounded-xl border border-gray-200">
                                <button type="button" id="btn-robot-floor-1" onclick="setActiveRobotFloor(1); handleInspectRobotFloorChange(1);" class="py-1.5 rounded-lg font-bold text-xs transition bg-[#3b4cb8] text-white shadow-sm flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-layer-group text-[10px]"></i> Lt 1
                                </button>
                                <button type="button" id="btn-robot-floor-2" onclick="setActiveRobotFloor(2); handleInspectRobotFloorChange(2);" class="py-1.5 rounded-lg font-bold text-xs transition text-gray-600 hover:bg-gray-100 flex items-center justify-center gap-1">
                                    <i class="fa-solid fa-layer-group text-[10px]"></i> Lt 2
                                </button>
                            </div>
                            <select id="inspect-robot-floor" onchange="handleInspectRobotFloorChange(this.value)" class="hidden">
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Universal Scale & Elevation (Per-floor) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Universal Scale -->
                        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2">
                            <label class="block font-bold text-gray-600 uppercase tracking-wider text-[10px]">Skala Robot (Universal)</label>
                            <div class="flex items-center gap-1 bg-white border border-gray-200 p-1 rounded-xl">
                                <button type="button" onclick="changeRobotScale(-0.05)" class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-200 border border-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold transition shadow-xs active:scale-95" title="Perkecil Robot (-0.05x)">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <span id="sidebar-robot-scale-val" class="flex-1 text-center font-mono font-bold text-[#3b4cb8] text-xs">0.60x</span>
                                <button type="button" onclick="changeRobotScale(0.05)" class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-200 border border-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold transition shadow-xs active:scale-95" title="Perbesar Robot (+0.05x)">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Elevation (Per-floor) -->
                        <div class="bg-amber-50/60 p-3.5 rounded-xl border border-amber-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-amber-900 uppercase tracking-wider text-[10px]">
                                    Tinggi / Z Roda (<span id="sidebar-label-robot-elev-floor">Lt 1</span>)
                                </label>
                                <button type="button" onclick="lockRobotElevation()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-2 py-0.5 rounded-md text-[10px] transition shadow-xs flex items-center gap-1" title="Kunci Ketinggian Robot di Lantai Ini">
                                    <i class="fa-solid fa-lock text-[9px]"></i> Kunci
                                </button>
                            </div>
                            <div class="flex items-center gap-1 bg-white border border-amber-300 p-1 rounded-xl">
                                <button type="button" onclick="adjustRobotElevation(-0.005)" class="px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-900 font-mono font-bold text-[11px] transition shadow-xs active:scale-95" title="Turun -0.005m">
                                    -0.005
                                </button>
                                <input type="number" step="any" id="sidebar-input-robot-elev" onchange="updateActiveRobotElevation(this.value, false)" oninput="updateActiveRobotElevation(this.value, false)" class="flex-1 bg-white rounded-lg py-1 px-1 text-center font-mono font-bold text-amber-600 text-xs focus:outline-none" value="0.019">
                                <input type="hidden" id="inspect-robot-elev">
                                <button type="button" onclick="adjustRobotElevation(0.005)" class="px-2 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-900 font-mono font-bold text-[11px] transition shadow-xs active:scale-95" title="Naik +0.005m">
                                    +0.005
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Test Posisi & Rotasi (Hanya untuk Tes - Tidak Disimpan) -->
                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <span class="font-bold text-slate-700 uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                                <i class="fa-solid fa-flask text-indigo-500"></i> Uji Coba Posisi &amp; Rotasi
                            </span>
                            <span class="text-[9px] font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Hanya untuk tes</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Coordinate X & Y -->
                            <div>
                                <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1 text-[10px]">Koordinat Tes (%)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-[9px] text-gray-400 block">X Axis (%)</span>
                                        <input type="number" step="0.1" min="0" max="100" id="inspect-robot-x" oninput="handleRobotCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-1.5 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition text-xs">
                                    </div>
                                    <div>
                                        <span class="text-[9px] text-gray-400 block">Y Axis (%)</span>
                                        <input type="number" step="0.1" min="0" max="100" id="inspect-robot-y" oninput="handleRobotCoordinateChange()" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-1.5 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition text-xs">
                                    </div>
                                </div>
                            </div>

                            <!-- Rotation -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="font-bold text-gray-500 uppercase tracking-wider text-[10px]">Arah Hadap / Rotasi</label>
                                    <span id="inspect-robot-rot-val" class="font-mono font-bold text-indigo-700 text-xs">0°</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" onclick="adjustInspectRobotRotation(-15)" class="bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 font-bold py-1 px-2 rounded-lg text-xs transition active:scale-95" title="Putar CCW -15°">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <input type="range" min="0" max="360" step="1" value="0" id="inspect-robot-rotation" oninput="handleInspectRobotRotation(this.value)" class="flex-1 accent-[#3b4cb8]">
                                    <button type="button" onclick="adjustInspectRobotRotation(15)" class="bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 font-bold py-1 px-2 rounded-lg text-xs transition active:scale-95" title="Putar CW +15°">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Actions for Robot -->
                    <div class="pt-3 flex gap-2 border-t border-gray-200">
                        <button type="button" onclick="saveRobotPosition()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2 shadow transition">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan Robot
                        </button>
                        <button type="button" onclick="focusOnActiveSelection()" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[#3b4cb8] font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Pusatkan Kamera ke Robot Ini">
                            <i class="fa-solid fa-crosshairs"></i> Fokus
                        </button>
                        <button type="button" onclick="resetActiveRobotPosition()" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-xl text-xs transition" title="Reset Posisi Robot">
                            <i class="fa-solid fa-arrows-rotate"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Modal Tambah Node 3D (In-Page, Anti-Lock OrbitControls) -->
    <div id="modal-add-node-3d" class="hidden fixed inset-0 z-[10005] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 select-none">
        <div class="bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl w-full max-w-md p-6 text-white transform transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600/30 border border-indigo-500/40 text-indigo-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-plus-circle"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-gray-100">Tambah Node Ruangan</h4>
                        <p class="text-[11px] text-gray-400">Tentukan nama lokasi untuk titik baru di Lantai <span id="modal-add-floor-label" class="text-indigo-400 font-bold">1</span></p>
                    </div>
                </div>
                <button type="button" onclick="closeAddNodeModal()" class="text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form onsubmit="confirmAddNodeModal(event)" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Nama Ruangan / Lokasi</label>
                    <input type="text" id="modal-add-node-name" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 font-bold text-gray-100 text-sm focus:border-indigo-500 focus:outline-none transition" placeholder="Contoh: Ruang Meeting 1, Hall, dsb.">
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-950/60 p-3 rounded-xl border border-slate-800 font-mono text-gray-400">
                    <div>Koordinat X: <span id="modal-add-x" class="text-amber-400 font-bold">0%</span></div>
                    <div>Koordinat Y: <span id="modal-add-y" class="text-amber-400 font-bold">0%</span></div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" onclick="closeAddNodeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-400 hover:text-white hover:bg-slate-800 transition">
                        Batal (Esc)
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 flex items-center gap-1.5 transition">
                        <i class="fa-solid fa-check"></i> Tambah Node
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Node 3D (In-Page, Anti-Lock OrbitControls) -->
    <div id="modal-delete-node-3d" class="hidden fixed inset-0 z-[10005] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 select-none">
        <div class="bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl w-full max-w-md p-6 text-white transform transition-all">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                <div class="w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/30 text-rose-400 flex items-center justify-center text-lg shrink-0">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-100">Hapus Node Ruangan?</h4>
                    <p class="text-[11px] text-gray-400">Tindakan ini juga akan memutus semua garis koneksi (edges) yang terhubung.</p>
                </div>
            </div>

            <div class="my-4 bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 text-xs space-y-1">
                <div class="text-gray-400">Node yang akan dihapus:</div>
                <div class="font-bold text-rose-400 text-sm font-mono" id="modal-delete-node-name">-</div>
                <div class="text-[11px] text-gray-500" id="modal-delete-node-details">-</div>
            </div>

            <div class="flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeDeleteNodeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-400 hover:text-white hover:bg-slate-800 transition">
                    Batal (Esc)
                </button>
                <button type="button" onclick="confirmDeleteNodeModal()" class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/30 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-trash-can"></i> Hapus Sekarang
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const floor1ModelUrl = "{{ asset('models/Denah_Lantai_1-opt.glb') }}";
    const floor2ModelUrl = "{{ asset('models/Lantai_2-final.glb') }}";
    const MODEL_CACHE_NAME = 'robopath-glb-cache-v1';
    let currentFloor = 1;
    let threeBotCtrl = null;
    let threeBotCtrlF1 = null;
    let modelLoadedByFloor = {1:false,2:false};
    function activeBotViewer(){ return Number(currentFloor)===1 ? threeBotCtrlF1 : threeBotCtrl; }
    function allBotViewers(){ return [threeBotCtrl, threeBotCtrlF1].filter(Boolean); }
    function parkCoordsForFloor(f){ return f===1 ? {x:72.1,y:85.71} : {x:72.3,y:66.3}; }
    function viewerOfHolder(holder){
        if(!holder) return activeBotViewer();
        for (const vw of allBotViewers()) {
            if (vw.robotsGroup && (holder === vw.robotsGroup || holder.parent === vw.robotsGroup)) return vw;
            if (vw.nodesGroup && (holder === vw.nodesGroup || holder.parent === vw.nodesGroup)) return vw;
            if (vw.nodeMeshes && holder.userData?.nodeId && vw.nodeMeshes.has(holder.userData.nodeId)) return vw;
            if (vw.robotMeshes && holder.userData?.robotId && vw.robotMeshes.has(holder.userData.robotId)) return vw;
        }
        return activeBotViewer();
    }
    let labelScaleMultiplier = parseFloat(localStorage.getItem('robopath_label_scale') || '{{ $labelScale ?? 0.85 }}');
    let showRobotsOnMap = true; // Default: tampilkan avatar robot 3D di peta
    let settings3D = @json($settings3D ?? []);
    let current3DSettings = {
        camera: { dist: parseFloat(settings3D?.camera?.dist ?? 5.0), fov: parseFloat(settings3D?.camera?.fov ?? 5.0), preset: settings3D?.camera?.preset ?? 'iso' },
        lighting: { ambient: parseFloat(settings3D?.lighting?.ambient ?? 1.4), sun: parseFloat(settings3D?.lighting?.sun ?? 1.8), exposure: parseFloat(settings3D?.lighting?.exposure ?? 1.0), fill: parseFloat(settings3D?.lighting?.fill ?? 0.8) },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0),
        robot_scale: parseFloat(settings3D?.robot_scale ?? 0.6),
        robot_elevation_f1: parseFloat(settings3D?.robot_elevation_f1 ?? 0.019),
        robot_elevation_f2: parseFloat(settings3D?.robot_elevation_f2 ?? 0.073),
        node_scale: parseFloat(settings3D?.node_scale ?? 0.6),
        node_color: settings3D?.node_color ?? '#ff0000'
    };

    // === 3D Robot Avatar & Node Editor State ===
    const robotModelUrl = "{{ asset('models/robot.glb') }}";
    let robotTemplate = null;
    let robotTemplateReady = false;
    let robotTemplateLoading = false;
    let robotTemplateCallbacks = [];
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
        const vw = activeBotViewer();
        if (vw && vw.robotMeshes && vw.robotMeshes.has(1)) return 1;
        const first = vw && vw.robotMeshes ? vw.robotMeshes.values().next() : null;
        if (first && !first.done) return first.value.userData.robotId;
        if (robotsData.length) return Number(robotsData[0].id);
        return null;
    }
    function setActiveRobot(id, opts = {}) {
        id = Number(id);
        if (!id) return;
        activeRobotId = id;
        const sel = document.getElementById('robot-selector');
        if (sel && sel.value !== String(id)) sel.value = String(id);
        const vw = activeBotViewer();
        if (opts.selectHolder !== false && vw && vw.robotMeshes && vw.robotMeshes.has(id)) {
            selected3DObject = vw.robotMeshes.get(id);
            updateSelected3DObjectUI();
        }
        refreshRobotPanel();
        updateDriveReadout();
    }
    function refreshRobotSelector() {
        const sel = document.getElementById('robot-selector');
        if (sel) {
            sel.innerHTML = robotsData.map(r =>
                `<option value="${r.id}">Robot #${r.id} ${r.name || ''}</option>`).join('') || '<option value="">(tidak ada robot)</option>';
            if (activeRobotId == null) activeRobotId = resolveDefaultRobotId();
            if (activeRobotId != null) sel.value = String(activeRobotId);
        }
        const inspSel = document.getElementById('inspect-robot-selector');
        if (inspSel) {
            inspSel.innerHTML = robotsData.map(r =>
                `<option value="${r.id}">Robot #${r.id} ${r.name || ''} (${r.status || 'Idle'})</option>`).join('') || '<option value="">(tidak ada robot)</option>';
            if (activeRobotId != null) inspSel.value = String(activeRobotId);
        }
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
        const floorElev = getRobotElevation(currentFloor);
        t.position.set(0, floorElev, 0);
        t.rotation.y = 0;
        updateDriveReadout();
    }
    function getDriveTarget() {
        const vw = activeBotViewer();
        if (!vw || !vw.robotMeshes) return null;
        if (activeRobotId != null && vw.robotMeshes.has(activeRobotId)) return vw.robotMeshes.get(activeRobotId);
        if (vw.robotMeshes.has(1)) return vw.robotMeshes.get(1);
        const first = vw.robotMeshes.values().next();
        return first && !first.done ? first.value : null;
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

    // Helper: mapping 2D percent -> 3D world (XZ plane + Y elevation)
    // _u/_v runtime (hasil resolveObjectAnchor dari Box3 GLB) diutamakan; fallback x/y persen.
    function worldPosForLoc(loc, size) {
        const u = (loc._u ?? loc.x / 100), v = (loc._v ?? loc.y / 100);
        const yElev = (loc.y_elev !== undefined && loc.y_elev !== null) ? Number(loc.y_elev) : (loc._fy ?? 0);
        return new THREE.Vector3((u - 0.5) * (size.x * 0.95), yElev, (v - 0.5) * (size.z * 0.95));
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
                d.setDecoderPath("{{ asset('draco') }}/");
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

        const isMarkas = String(text).toLowerCase().includes('markas');
        const bgFill = isMarkas 
            ? 'rgba(16, 185, 129, 0.95)' 
            : (isStairs ? 'rgba(217, 119, 6, 0.92)' : (isDest ? 'rgba(15, 23, 42, 0.90)' : 'rgba(30, 41, 59, 0.85)'));
        const borderColor = isMarkas 
            ? '#34d399' 
            : (isStairs ? '#fbbf24' : (isDest ? '#ff0000' : '#94a3b8'));

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
        ctx.fillText((isMarkas ? '🏠 ' : '') + cleanText, 52, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        const spriteMaterial = new THREE.SpriteMaterial({ map: texture, transparent: true, depthTest: false, depthWrite: false });
        const sprite = new THREE.Sprite(spriteMaterial);
        sprite.scale.set(BASE_LABEL_W * labelScaleMultiplier, BASE_LABEL_H * labelScaleMultiplier, 1);
        sprite.renderOrder = 999;
        return sprite;
    }

    // Helper: Dynamic Room Label Scaling (Kecil, ringkas, dan dapat diskalakan hingga 0.1x)
    const BASE_LABEL_W = 0.65;
    const BASE_LABEL_H = 0.1625;

    function setLabelScale(val) {
        val = Math.max(0.1, Math.min(2.5, parseFloat(Number(val).toFixed(2))));
        labelScaleMultiplier = val;
        try { localStorage.setItem('robopath_label_scale', String(val)); } catch (e) {}

        document.querySelectorAll('.label-scale-val').forEach(el => {
            el.textContent = `${val.toFixed(1)}x`;
        });

        allBotViewers().forEach(vw => {
            if (vw && vw.nodeMeshes) {
                vw.nodeMeshes.forEach(holder => {
                    if (holder.userData && holder.userData.labelSprite) {
                        holder.userData.labelSprite.scale.set(BASE_LABEL_W * labelScaleMultiplier, BASE_LABEL_H * labelScaleMultiplier, 1);
                    }
                });
            }
        });
    }

    function adjustLabelScale(delta) {
        setLabelScale(labelScaleMultiplier + delta);
    }

    // === Dynamic Robot Scale Controller (Gede/Kecil Robot 3D) ===
    let robotScaleMultiplier = parseFloat(current3DSettings.robot_scale ?? 0.6);

    function setRobotScale(val, persist = true) {
        val = Math.max(0.02, Math.min(3.0, parseFloat(Number(val).toFixed(2))));
        robotScaleMultiplier = val;
        current3DSettings.robot_scale = val;

        const valText = `${val.toFixed(2)}x`;
        const elMain = document.getElementById('robot-scale-val');
        if (elMain) elMain.textContent = valText;
        const elFull = document.getElementById('fullmap-robot-scale-val');
        if (elFull) elFull.textContent = valText;
        const elSide = document.getElementById('sidebar-robot-scale-val');
        if (elSide) elSide.textContent = valText;

        allBotViewers().forEach(vw => {
            if (vw && vw.robotMeshes) {
                vw.robotMeshes.forEach(holder => {
                    if (holder) {
                        holder.scale.set(val, val, val);
                    }
                });
            }
        });

        if (persist) {
            save3DSettingsToServer();
        }
    }

    function changeRobotScale(delta) {
        setRobotScale(robotScaleMultiplier + delta, true);
    }

    // === Robot Elevation / Vertical Z Controller per Floor ===
    let activeRobotFloor = 1;

    function getRobotElevation(floorNum) {
        const f = Number(floorNum) === 2 ? 2 : 1;
        if (f === 2) {
            return parseFloat(current3DSettings.robot_elevation_f2 ?? 0.073);
        }
        return parseFloat(current3DSettings.robot_elevation_f1 ?? 0.019);
    }

    function updateRobotElevationUI() {
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        const val = getRobotElevation(f);
        const fText = `Lt ${f}`;

        const fmLabel = document.getElementById('fullmap-label-robot-elev-floor');
        if (fmLabel) fmLabel.textContent = fText;
        const fmInput = document.getElementById('fullmap-input-robot-elev');
        if (fmInput) fmInput.value = val;

        const sideLabel = document.getElementById('sidebar-label-robot-elev-floor');
        if (sideLabel) sideLabel.textContent = fText;
        const sideInput = document.getElementById('sidebar-input-robot-elev');
        if (sideInput) sideInput.value = val;
    }

    function updateRobotFloorUI() {
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        const b1 = document.getElementById('btn-robot-floor-1');
        const b2 = document.getElementById('btn-robot-floor-2');
        if (b1 && b2) {
            if (f === 1) {
                b1.className = "py-1.5 rounded-lg font-bold text-xs transition bg-[#3b4cb8] text-white shadow-sm flex items-center justify-center gap-1";
                b2.className = "py-1.5 rounded-lg font-bold text-xs transition text-gray-600 hover:bg-gray-200 flex items-center justify-center gap-1";
            } else {
                b1.className = "py-1.5 rounded-lg font-bold text-xs transition text-gray-600 hover:bg-gray-200 flex items-center justify-center gap-1";
                b2.className = "py-1.5 rounded-lg font-bold text-xs transition bg-[#3b4cb8] text-white shadow-sm flex items-center justify-center gap-1";
            }
        }
        updateRobotElevationUI();
    }

    function setActiveRobotFloor(f) {
        activeRobotFloor = Number(f) === 2 ? 2 : 1;
        updateRobotFloorUI();
        if (Number(currentFloor) !== activeRobotFloor) {
            switchFloor(activeRobotFloor);
        }
    }

    function updateActiveRobotElevation(val, persist = false) {
        const num = parseFloat(val);
        if (isNaN(num)) return;
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        if (f === 2) {
            current3DSettings.robot_elevation_f2 = num;
        } else {
            current3DSettings.robot_elevation_f1 = num;
        }

        const targetViewer = f === 1 ? threeBotCtrlF1 : threeBotCtrl;
        if (targetViewer && targetViewer.robotMeshes) {
            targetViewer.robotMeshes.forEach(holder => {
                if (holder) holder.position.y = num;
            });
        }

        const fmInput = document.getElementById('fullmap-input-robot-elev');
        if (fmInput && fmInput.value != num) fmInput.value = num;
        const sideInput = document.getElementById('sidebar-input-robot-elev');
        if (sideInput && sideInput.value != num) sideInput.value = num;

        updateDriveReadout();

        if (persist) {
            save3DSettingsToServer();
        }
    }

    function adjustRobotElevation(delta) {
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        const cur = getRobotElevation(f);
        const next = Number(parseFloat((cur + delta).toFixed(4)));
        updateActiveRobotElevation(next, false);
    }

    function lockRobotElevation() {
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        const val = getRobotElevation(f);
        save3DSettingsToServer(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Ketinggian Dikunci!',
                    text: `Ketinggian robot Lantai ${f} berhasil dikunci permanen pada ${val}m.`,
                    timer: 2500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                alert(`✓ Ketinggian robot Lantai ${f} berhasil dikunci permanen pada ${val}m!`);
            }
        });
    }

    function save3DSettingsToServer(onSuccess) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('/api/settings/label-scale', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                scale: labelScaleMultiplier,
                settings_3d: current3DSettings
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success && typeof onSuccess === 'function') onSuccess();
        })
        .catch(e => console.warn('[Robopath] Save 3D settings fail:', e));
    }

    // Helper: Cached GLB buffer loader with progress (Streaming + CacheStorage)
    async function fetchGLBBufferWithCache(url, onProgress) {
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cachedResponse = await cache.match(url);
                if (cachedResponse) {
                    if (onProgress) onProgress(1, 1, true);
                    return await cachedResponse.arrayBuffer();
                }
            } catch (e) {
                console.warn('[Robopath Cache] read bypass:', e);
            }
        }
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const contentLength = response.headers.get('content-length');
        const totalBytes = contentLength ? parseInt(contentLength, 10) : (url.includes('Lantai_1') ? 8750000 : 14080452);
        let loadedBytes = 0;
        if (response.body && response.body.getReader && onProgress) {
            const reader = response.body.getReader();
            const chunks = [];
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                loadedBytes += value.length;
                onProgress(loadedBytes, totalBytes, false);
            }
            const all = new Uint8Array(loadedBytes);
            let pos = 0; for (const c of chunks) { all.set(c, pos); pos += c.length; }
            const buffer = all.buffer;
            if ('caches' in window) {
                try {
                    const cache = await caches.open(MODEL_CACHE_NAME);
                    const cacheResponse = new Response(buffer.slice(0), { headers: { 'Content-Type': 'model/gltf-binary', 'Content-Length': String(buffer.byteLength) } });
                    await cache.put(url, cacheResponse);
                } catch (e) {}
            }
            return buffer;
        }
        const buffer = await response.arrayBuffer();
        if ('caches' in window) {
            try {
                const cache = await caches.open(MODEL_CACHE_NAME);
                const cacheResponse = new Response(buffer.slice(0), { headers: { 'Content-Type': 'model/gltf-binary', 'Content-Length': String(buffer.byteLength) } });
                await cache.put(url, cacheResponse);
            } catch (e) {}
        }
        return buffer;
    }

    function initThreeViewer(containerId, floorNum) {
        const container = document.getElementById(containerId);
        if (!container) return null;
        floorNum = Number(floorNum) === 1 ? 1 : 2;
        const modelUrl = floorNum === 1 ? floor1ModelUrl : floor2ModelUrl;
        const _loaderEl = document.getElementById('botctrl-3d-loader');
        const _loaderBar = document.getElementById('botctrl-3d-loader-bar');
        const _loaderPct = document.getElementById('botctrl-3d-loader-pct');
        const _loaderStatus = document.getElementById('botctrl-3d-loader-status');
        const _loaderTitle = document.getElementById('botctrl-3d-loader-title');
        if (_loaderEl && !modelLoadedByFloor[floorNum]) {
            _loaderEl.classList.remove('hidden');
            if (_loaderTitle) _loaderTitle.textContent = `Memuat Model 3D Lantai ${floorNum}...`;
            if (_loaderStatus) _loaderStatus.textContent = `Mengunduh aset GLB (${floorNum===1?'8':'14'} MB)...`;
            if (_loaderBar) _loaderBar.style.width = '5%';
            if (_loaderPct) _loaderPct.textContent = '5%';
        }

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0f172a);
        const width = container.clientWidth || 800;
        const height = container.clientHeight || 450;

        const initFovVal = parseFloat(current3DSettings.camera.fov ?? 5.0);
        const initFov = 20 + (initFovVal / 10) * 70;
        const camera = new THREE.PerspectiveCamera(initFov, width / height, 0.02, 1000);
        camera.position.set(3.8, 16, 24);

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
        controls.target.set(3.8, 0.5, 0);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.maxPolarAngle = Math.PI / 2.02;
        controls.minDistance = 0.05; // Memungkinkan zoom sangat dekat hingga 5 cm di atas objek/node
        controls.maxDistance = 250;
        controls.enablePan = true;
        controls.screenSpacePanning = true; // Pan terasa natural mengikuti layar
        controls.panSpeed = 1.2;
        controls.zoomSpeed = 1.3;
        if (currentTool === 'hand') {
            controls.mouseButtons.LEFT = THREE.MOUSE.PAN;
            controls.mouseButtons.RIGHT = THREE.MOUSE.ROTATE;
        } else {
            controls.mouseButtons.LEFT = THREE.MOUSE.ROTATE;
            controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
        }

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
        const robotsGroup = new THREE.Group();
        robotsGroup.visible = showRobotsOnMap;
        scene.add(robotsGroup);
        const nodesGroup = new THREE.Group();
        scene.add(nodesGroup);
        const edgesGroup = new THREE.Group();
        scene.add(edgesGroup);
        const nodeMeshes = new Map();
        const robotMeshes = new Map();

        // Pre-load robot.glb early
        try { ensureRobotTemplate(() => {}); } catch (e) {}

        // Helper: update tampilan mesh node 3D secara dinamis (destinasi vs transit vs stairs)
        function updateNodeMeshAppearance(holder, loc) {
            if (!holder || !loc) return;
            const id = loc.id || holder.userData.nodeId;
            const isStairs = id.includes('Stairs');
            const isDest = !!loc.is_destination;
            const isHidden = !!loc.hidden;
            // Ruangan bertitel/nama hanya jika destinasi (tidak di-hide) atau tangga
            const isNamed = (isDest && !isHidden) || isStairs;

            // Ukuran kompak: 0.045 / 0.016 untuk named/destination, 0.025 / 0.010 untuk transit dot
            const radius = isNamed ? 0.045 : 0.025;
            const sphereRad = isNamed ? 0.016 : 0.010;
            // Warna: Amber untuk tangga, Merah untuk destinasi, Biru terang rada abu (0x38bdf8) untuk transit
            const baseCol = isStairs ? 0xf59e0b : (isNamed ? 0xff0000 : 0x38bdf8);

            // 1. Disc Mesh
            if (holder.userData.discMesh) {
                if (holder.userData.discMesh.geometry) holder.userData.discMesh.geometry.dispose();
                holder.userData.discMesh.geometry = new THREE.CylinderGeometry(radius, radius, 0.006, 20);
                holder.userData.discMesh.material.color.setHex(baseCol);
                holder.userData.discMesh.material.opacity = isNamed ? 1.0 : 0.95;
            }

            // 2. Center beacon pin (sphere)
            if (holder.userData.sphereMesh) {
                if (holder.userData.sphereMesh.geometry) holder.userData.sphereMesh.geometry.dispose();
                holder.userData.sphereMesh.geometry = new THREE.SphereGeometry(sphereRad, 16, 16);
                holder.userData.sphereMesh.material.color.setHex(baseCol);
            }

            // 3. Selection ring di lantai
            if (holder.userData.selectionRing) {
                if (holder.userData.selectionRing.geometry) holder.userData.selectionRing.geometry.dispose();
                holder.userData.selectionRing.geometry = new THREE.RingGeometry(radius + 0.012, radius + 0.028, 20);
            }

            // 4. Label teks ruangan: Hanya ada/tampil untuk ruangan destinasi & tangga ("ga ada namanya" untuk transit)
            if (isNamed) {
                if (!holder.userData.labelSprite) {
                    const sprite = createRoomLabelSprite(loc.name || id, isDest, isStairs);
                    sprite.scale.set(BASE_LABEL_W * labelScaleMultiplier, BASE_LABEL_H * labelScaleMultiplier, 1);
                    sprite.position.set(0, 0.08, 0);
                    sprite.material.depthTest = true;
                    holder.add(sprite);
                    holder.userData.labelSprite = sprite;
                } else {
                    holder.userData.labelSprite.visible = true;
                    holder.userData.labelSprite.scale.set(BASE_LABEL_W * labelScaleMultiplier, BASE_LABEL_H * labelScaleMultiplier, 1);
                }
            } else {
                if (holder.userData.labelSprite) {
                    holder.userData.labelSprite.visible = false;
                }
            }

            // 5. Node visibility: Titik transit selalu tampil di editor saat showHiddenDots = true
            const isTransit = !isDest || isHidden;
            holder.visible = (!isTransit || showHiddenDots);
        }

        // Helper: buat/update mesh node 3D yang menempel langsung di lantai 3D
        function getOrCreateNodeMesh(nodeId) {
            const id = nodeId;
            const loc = locationsData[id];
            if (!loc) return null;
            if (nodeMeshes.has(id)) {
                const existing = nodeMeshes.get(id);
                updateNodeMeshAppearance(existing, loc);
                return existing;
            }

            const isStairs = id.includes('Stairs');
            const isDest = !!loc.is_destination;
            const isHidden = !!loc.hidden;
            const isNamed = (isDest && !isHidden) || isStairs;

            const holder = new THREE.Group();
            holder.userData.nodeId = id;
            holder.userData.type = 'node';

            const radius = isNamed ? 0.045 : 0.025;
            const sphereRad = isNamed ? 0.016 : 0.010;
            const baseCol = isStairs ? 0xf59e0b : (isNamed ? 0xff0000 : 0x38bdf8);

            const discGeo = new THREE.CylinderGeometry(radius, radius, 0.006, 20);
            const discMat = new THREE.MeshBasicMaterial({
                color: baseCol,
                transparent: true,
                opacity: isNamed ? 1.0 : 0.95
            });
            const discMesh = new THREE.Mesh(discGeo, discMat);
            discMesh.position.y = 0.004;
            discMesh.castShadow = true;
            discMesh.receiveShadow = true;
            holder.add(discMesh);
            holder.userData.discMesh = discMesh;

            const sphereGeo = new THREE.SphereGeometry(sphereRad, 16, 16);
            const sphereMat = new THREE.MeshBasicMaterial({ color: baseCol });
            const sphereMesh = new THREE.Mesh(sphereGeo, sphereMat);
            sphereMesh.position.y = 0.018;
            sphereMesh.castShadow = true;
            holder.add(sphereMesh);
            holder.userData.sphereMesh = sphereMesh;

            const ringGeo = new THREE.RingGeometry(radius + 0.012, radius + 0.028, 20);
            const ringMat = new THREE.MeshBasicMaterial({ color: 0x10b981, side: THREE.DoubleSide });
            const ringMesh = new THREE.Mesh(ringGeo, ringMat);
            ringMesh.rotation.x = -Math.PI / 2;
            ringMesh.position.y = 0.008;
            ringMesh.visible = (selectedNodeId === id);
            holder.add(ringMesh);
            holder.userData.selectionRing = ringMesh;

            if (isNamed) {
                const sprite = createRoomLabelSprite(loc.name || id, isDest, isStairs);
                sprite.scale.set(BASE_LABEL_W * labelScaleMultiplier, BASE_LABEL_H * labelScaleMultiplier, 1);
                sprite.position.set(0, 0.08, 0);
                sprite.material.depthTest = true;
                holder.add(sprite);
                holder.userData.labelSprite = sprite;
            }

            const wp = worldPosForLoc(loc, _bcSize);
            holder.position.set(wp.x, wp.y, wp.z);
            const isTransit = !isDest || isHidden;
            holder.visible = (!isTransit || showHiddenDots);

            nodesGroup.add(holder);
            nodeMeshes.set(id, holder);
            return holder;
        }

        function updateNodeSelectionHighlights() {
            nodeMeshes.forEach((holder, id) => {
                if (holder.userData && holder.userData.selectionRing) {
                    const isSel = (selectedNodeId === id);
                    const isConn = (connectStart3DNode === id);
                    holder.userData.selectionRing.visible = (isSel || isConn);
                    if (isConn) holder.userData.selectionRing.material.color.setHex(0xf59e0b);
                    else holder.userData.selectionRing.material.color.setHex(0x10b981);
                }
            });
        }

        // Helper: buat/update mesh robot 3D (avatar robot.glb + 3D direction cone + name badge)
        function getOrCreateRobotMesh(robot) {
            const id = Number(robot.id);
            if (robotMeshes.has(id)) return robotMeshes.get(id);
            const holder = new THREE.Group();
            holder.userData.robotId = id;
            holder.userData.type = 'robot';
            const rSc = parseFloat(current3DSettings.robot_scale ?? 0.6);
            holder.scale.set(rSc, rSc, rSc);
            // placeholder box
            const boxMesh = new THREE.Mesh(
                new THREE.BoxGeometry(0.35, 0.5, 0.35),
                new THREE.MeshStandardMaterial({ color: getRobotColor(id) })
            );
            boxMesh.position.y = 0.25; boxMesh.castShadow = true; boxMesh.receiveShadow = true;
            holder.add(boxMesh); holder.userData.boxMesh = boxMesh;

            // Panah 3D ke bawah (cone) - indicator arah robot
            const robotCol = getRobotColor(id);
            const coneGeo = new THREE.ConeGeometry(0.14, 0.32, 8);
            const coneMat = new THREE.MeshStandardMaterial({
                color: robotCol,
                emissive: robotCol,
                emissiveIntensity: 0.35,
                metalness: 0.3,
                roughness: 0.5
            });
            const cone = new THREE.Mesh(coneGeo, coneMat);
            cone.position.set(0, 1.0, 0);
            cone.rotation.x = Math.PI; // flip to point down
            cone.castShadow = true;
            holder.add(cone); holder.userData.arrowCone = cone;

            // name badge sprite
            const c = document.createElement('canvas'); c.width = 256; c.height = 64;
            const cx = c.getContext('2d');
            cx.fillStyle = 'rgba(15,23,42,0.92)'; cx.strokeStyle = robotCol; cx.lineWidth = 3;
            cx.beginPath(); cx.roundRect(6, 6, 244, 52, 12); cx.fill(); cx.stroke();
            cx.fillStyle = '#fff'; cx.font = 'bold 22px sans-serif'; cx.textAlign = 'center'; cx.textBaseline = 'middle';
            cx.fillText(robot.name || ('Robot ' + id), 128, 32);
            const tex = new THREE.CanvasTexture(c); tex.minFilter = THREE.LinearFilter;
            const spr = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false, depthWrite: false }));
            spr.scale.set(1.2, 0.3, 1); spr.position.set(0, 1.25, 0); spr.renderOrder = 999;
            holder.add(spr); holder.userData.nameSprite = spr;

            robotsGroup.add(holder); robotMeshes.set(id, holder);

            // replace placeholder with glb clone when ready (or immediately if ready)
            const swapBoxForGlb = (tpl) => {
                if (!tpl || !holder.parent) return;
                if (holder.userData.glbClone) return;
                const clone = tpl.clone(true);
                const col = new THREE.Color(robotCol);
                clone.traverse(n => {
                    if (n.isMesh && n.material) {
                        n.material = n.material.clone();
                        if (n.material.color) n.material.color.lerp(col, 0.25);
                        n.castShadow = true; n.receiveShadow = true;
                    }
                    if (n.isMesh) {
                        n.userData = n.userData || {};
                        n.userData.type = 'robot_mesh';
                        n.userData.robotId = id;
                    }
                });
                clone.position.set(0, 0, 0);
                holder.remove(boxMesh); boxMesh.geometry.dispose();
                holder.add(clone); holder.userData.glbClone = clone;
            };

            if (robotTemplateReady) {
                swapBoxForGlb(robotTemplate);
            } else {
                try { ensureRobotTemplate(swapBoxForGlb); } catch (e) {}
            }

            return holder;
        }

        // Raycaster click action handler (dieksekusi saat pointerup tanpa pergeseran kamera)
        function handle3DClickAction(e) {
            const rect = renderer.domElement.getBoundingClientRect();
            const mouse = new THREE.Vector2(
                ((e.clientX - rect.left) / rect.width) * 2 - 1,
                -((e.clientY - rect.top) / rect.height) * 2 + 1
            );
            raycaster.setFromCamera(mouse, camera);

            // Isolasi pick target secara ketat berdasarkan Mode aktif (Ruangan vs Robot)
            const pickTargets = [];
            if (currentEditTarget === 'robot') {
                // Di Mode Robot: HANYA targetkan avatar robot (jangan sentuh node/ruangan sama sekali!)
                if (showRobotsOnMap) {
                    robotsGroup.traverse(c => { if (c.isMesh) pickTargets.push(c); });
                }
            } else {
                // Di Mode Ruangan: HANYA targetkan node (jangan sentuh robot sama sekali!)
                nodesGroup.children.forEach(m => {
                    if (m.isMesh) pickTargets.push(m);
                    else if (m.isGroup) m.traverse(c => { if (c.isMesh) pickTargets.push(c); });
                });
            }

            const hits = raycaster.intersectObjects(pickTargets, false);
            if (hits.length > 0) {
                let target = hits[0].object;
                while (target && target.parent && target.parent !== nodesGroup && target.parent !== robotsGroup) {
                    target = target.parent;
                }
                selected3DObject = target;

                if (target && target.userData && target.userData.type === 'robot' && currentEditTarget === 'robot') {
                    const rid = Number(target.userData.robotId);
                    setActiveRobot(rid, { selectHolder: false });
                    inspectRobot(rid);
                } else if (target && target.userData && target.userData.type === 'node' && currentEditTarget === 'node') {
                    const nodeId = target.userData.nodeId;
                    selectedNodeId = nodeId;
                    inspectNode(selectedNodeId);
                    updateNodeSelectionHighlights();

                    if (currentTool === 'connect') {
                        if (!connectStart3DNode) {
                            connectStart3DNode = nodeId;
                            updateNodeSelectionHighlights();
                        } else if (connectStart3DNode === nodeId) {
                            // Klik node yang sama -> batalkan pilihan koneksi
                            connectStart3DNode = null;
                            updateNodeSelectionHighlights();
                        } else {
                            if (!adjData[connectStart3DNode]) adjData[connectStart3DNode] = [];
                            if (!adjData[nodeId]) adjData[nodeId] = [];
                            if (!adjData[connectStart3DNode].includes(nodeId)) adjData[connectStart3DNode].push(nodeId);
                            if (!adjData[nodeId].includes(connectStart3DNode)) adjData[nodeId].push(connectStart3DNode);
                            connectStart3DNode = null;
                            inspectNode(selectedNodeId);
                            updateNodeSelectionHighlights();
                            build3DEdges();
                        }
                        return;
                    } else if (currentTool === 'delete') {
                        openDeleteNodeModal(nodeId);
                        return;
                    }
                }
            } else {
                // Klik area kosong: hanya berlaku aksi jika sedang di Mode Ruangan
                if (currentEditTarget === 'node') {
                    if (currentTool === 'add') {
                        const hitPoint = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                        if (hitPoint) {
                            const pct = locFromWorld(hitPoint.x, hitPoint.z, _bcSize);
                            openAddNodeModal(floorNum, parseFloat(pct.x.toFixed(2)), parseFloat(pct.y.toFixed(2)));
                        }
                    } else {
                        if (currentTool === 'connect') {
                            connectStart3DNode = null;
                        }
                        selectedNodeId = null;
                        selected3DObject = null;
                        clearInspector();
                        updateSelected3DObjectUI();
                        updateNodeSelectionHighlights();
                        build3DEdges();
                    }
                }
            }
        }

        function handle3DDragMove(e) {
            if (currentTool === 'hand') return;
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
            // sync ke locationsData bila node
            if (dragged3D.userData.type === 'node') {
                const nodeId = dragged3D.userData.nodeId;
                const curY = (locationsData[nodeId]?.y_elev !== undefined && locationsData[nodeId]?.y_elev !== null)
                    ? Number(locationsData[nodeId].y_elev)
                    : (locationsData[nodeId]?._fy ?? 0);
                dragged3D.position.y = curY;
                const pct = locFromWorld(newPos.x, newPos.z, _bcSize);
                locationsData[nodeId].x = parseFloat(pct.x.toFixed(2));
                locationsData[nodeId].y = parseFloat(pct.y.toFixed(2));
                locationsData[nodeId].y_elev = curY;
                locationsData[nodeId]._fy = curY;
                if (locationsData[nodeId].objectName) {
                    delete locationsData[nodeId].objectName;
                }
                delete locationsData[nodeId]._u;
                delete locationsData[nodeId]._v;
                const inpX = document.getElementById('inspect-x');
                const inpY = document.getElementById('inspect-y');
                const inpElev = document.getElementById('inspect-y-elev');
                if (inpX) inpX.value = locationsData[nodeId].x;
                if (inpY) inpY.value = locationsData[nodeId].y;
                if (inpElev) inpElev.value = curY;
                build3DEdges();
                updateSelected3DObjectUI();
            } else if (dragged3D.userData.type === 'robot') {
                const rid = Number(dragged3D.userData.robotId);
                const r = robotsData.find(x => Number(x.id) === rid);
                const vw = viewerOfHolder(dragged3D);
                const sz = vw ? vw.getModelSize() : _bcSize;
                if (r && sz && sz.x > 0.1) {
                    const pct = locFromWorld(dragged3D.position.x, dragged3D.position.z, sz);
                    r.current_x = parseFloat(pct.x.toFixed(2));
                    r.current_y = parseFloat(pct.y.toFixed(2));
                    syncRobotInspectorInputs(rid);
                }
                updateDriveReadout();
                updateSelected3DObjectUI();
            }
        }

        function handle3DDragUp() {
            if (dragged3D) { dragged3D = null; controls.enabled = true; }
        }

        function build3DEdges() {
            if (!edgesGroup) return;
            edgesGroup.clear();
            const seen = new Set();
            for (const a in adjData) {
                if (!locationsData[a] || Number(locationsData[a].floor) !== floorNum) continue;
                for (const b of (adjData[a] || [])) {
                    if (!locationsData[b] || Number(locationsData[b].floor) !== floorNum) continue;
                    const key = [a, b].sort().join('|');
                    if (seen.has(key)) continue; seen.add(key);
                    const pA = worldPosForLoc(locationsData[a], _bcSize); pA.y += 0.015;
                    const pB = worldPosForLoc(locationsData[b], _bcSize); pB.y += 0.015;
                    const geo = new THREE.BufferGeometry().setFromPoints([pA, pB]);
                    const isConnected = (selectedNodeId === a || selectedNodeId === b || connectStart3DNode === a || connectStart3DNode === b);
                    const mat = new THREE.LineBasicMaterial({
                        color: isConnected ? 0x38bdf8 : 0x0284c7,
                        transparent: true,
                        opacity: isConnected ? 0.95 : 0.65
                    });
                    edgesGroup.add(new THREE.Line(geo, mat));
                }
            }
        }

        // Disable context menu on canvas agar drag klik kanan (pan) lancar tanpa gangguan popup browser
        renderer.domElement.addEventListener('contextmenu', (e) => e.preventDefault());

        // Hook raycaster ke renderer canvas — dengan pemisahan mutlak antara Click dan Drag/Orbit
        let pointerDownPos = { x: 0, y: 0 };
        let isPointerDown = false;
        let pointerMoved = false;

        renderer.domElement.addEventListener('pointerdown', (e) => {
            if (Number(currentFloor) !== floorNum) return;
            isPointerDown = true;
            pointerMoved = false;
            pointerDownPos = { x: e.clientX, y: e.clientY };

            if (currentTool === 'hand') {
                renderer.domElement.style.cursor = 'grabbing';
                return;
            }

            // Tool 'move': jika klik kiri mengenai objek, kunci OrbitControls untuk geser objek di lantai
            if (e.button === 0 && currentTool === 'move') {
                const rect = renderer.domElement.getBoundingClientRect();
                const mouse = new THREE.Vector2(
                    ((e.clientX - rect.left) / rect.width) * 2 - 1,
                    -((e.clientY - rect.top) / rect.height) * 2 + 1
                );
                raycaster.setFromCamera(mouse, camera);

                // Isolasi target dragging berdasar Mode aktif (Ruangan vs Robot)
                const pickTargets = [];
                if (currentEditTarget === 'robot') {
                    if (showRobotsOnMap) {
                        robotsGroup.traverse(c => { if (c.isMesh) pickTargets.push(c); });
                    }
                } else {
                    nodesGroup.children.forEach(m => {
                        if (m.isMesh) pickTargets.push(m);
                        else if (m.isGroup) m.traverse(c => { if (c.isMesh) pickTargets.push(c); });
                    });
                }

                const hits = raycaster.intersectObjects(pickTargets, false);
                if (hits.length > 0) {
                    let target = hits[0].object;
                    while (target && target.parent && target.parent !== nodesGroup && target.parent !== robotsGroup) {
                        target = target.parent;
                    }
                    if (target) {
                        dragged3D = target;
                        controls.enabled = false; // Kunci kamera agar tidak berputar saat menggeser objek!
                        dragOffset.copy(target.position).sub(hits[0].point);
                        dragOffset.y = 0;
                        selected3DObject = target;
                        if (target.userData?.type === 'node' && currentEditTarget === 'node') {
                            selectedNodeId = target.userData.nodeId;
                            inspectNode(selectedNodeId);
                        } else if (target.userData?.type === 'robot' && currentEditTarget === 'robot') {
                            setActiveRobot(target.userData.robotId, { selectHolder: false });
                            inspectRobot(target.userData.robotId);
                        }
                        updateSelected3DObjectUI();
                        updateNodeSelectionHighlights();
                    }
                }
            }
        });

        window.addEventListener('pointermove', (e) => {
            if (Number(currentFloor) !== floorNum) return;
            if (isPointerDown) {
                if (Math.hypot(e.clientX - pointerDownPos.x, e.clientY - pointerDownPos.y) > 6) {
                    pointerMoved = true;
                }
            }
            handle3DDragMove(e);
        });

        window.addEventListener('pointerup', (e) => {
            if (currentTool === 'hand') {
                renderer.domElement.style.cursor = 'grab';
            }
            if (Number(currentFloor) !== floorNum) return;

            const wasDraggingObject = !!dragged3D;
            handle3DDragUp(); // Mereset dragged3D dan mengaktifkan kembali controls.enabled = true

            if (!isPointerDown) return;
            isPointerDown = false;

            // Jika sedang menggeser objek, atau kursor bergerak >6px (kamera diputar/geser), JANGAN eksekusi klik tindakan
            if (wasDraggingObject || pointerMoved || currentTool === 'hand') {
                return;
            }

            // Hanya klik kiri murni (button 0) yang memicu pemilihan / penambahan / penghapusan
            if (e.button !== 0) return;

            handle3DClickAction(e);
        });

        // Zoom pintar mendekat ke arah kursor mouse saat scroll wheel ke dalam
        renderer.domElement.addEventListener('wheel', (e) => {
            if (Number(currentFloor) !== floorNum) return;
            if (e.deltaY < 0) { // Zoom in
                const rect = renderer.domElement.getBoundingClientRect();
                const mouse = new THREE.Vector2(
                    ((e.clientX - rect.left) / rect.width) * 2 - 1,
                    -((e.clientY - rect.top) / rect.height) * 2 + 1
                );
                raycaster.setFromCamera(mouse, camera);
                const hitPoint = raycaster.ray.intersectPlane(dragPlane, new THREE.Vector3());
                if (hitPoint) {
                    const shift = hitPoint.clone().sub(controls.target).multiplyScalar(0.08);
                    controls.target.add(shift);
                    camera.position.add(shift);
                }
            }
        }, { passive: true });

        // Double-click untuk langsung zoom/fokus ke node atau robot terpilih
        renderer.domElement.addEventListener('dblclick', (e) => {
            if (Number(currentFloor) !== floorNum || e.button !== 0) return;
            if (selected3DObject) {
                focusOn(selected3DObject.position, 1.8);
            }
        });


        const gltfLoader = new THREE.GLTFLoader();
        if (typeof THREE.DRACOLoader !== 'undefined') {
            const dracoLoader = new THREE.DRACOLoader();
            dracoLoader.setDecoderPath("{{ asset('draco') }}/");
            gltfLoader.setDRACOLoader(dracoLoader);
        }

        let _bcModel = null; let _bcSize = new THREE.Vector3();
        let _defaultCamTarget = new THREE.Vector3(0,0,0);
        fetchGLBBufferWithCache(modelUrl, (loadedBytes, totalBytes, fromCache) => {
            if (!_loaderEl) return;
            if (fromCache) {
                if (_loaderBar) _loaderBar.style.width = '90%';
                if (_loaderPct) _loaderPct.textContent = '90%';
                if (_loaderStatus) _loaderStatus.textContent = 'Memuat dari Cache Lokal (Instan)...';
            } else {
                const pct = Math.min(Math.round((loadedBytes/totalBytes)*100),99);
                if (_loaderBar) _loaderBar.style.width = pct+'%';
                if (_loaderPct) _loaderPct.textContent = pct+'%';
                if (_loaderStatus) _loaderStatus.textContent = `Mengunduh: ${(loadedBytes/1048576).toFixed(1)} MB / ${(totalBytes/1048576).toFixed(1)} MB`;
            }
        }).then(buffer => {
            gltfLoader.parse(buffer, '', (gltf) => {
                modelLoadedByFloor[floorNum]=true;
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
                try { resolveAllObjectAnchors(locationsData, model, _bcSize, floorNum); } catch (e) { console.warn('[Robopath] resolve anchors fail', e); }

                // Eager-create semua node mesh di lantai 3D
                for (const id in locationsData) {
                    const loc = locationsData[id];
                    if (Number(loc.floor) !== floorNum) continue;
                    const mesh = getOrCreateNodeMesh(id);
                    if (mesh) {
                        const wp = worldPosForLoc(loc, _bcSize);
                        mesh.position.set(wp.x, wp.y, wp.z);
                    }
                }
                build3DEdges();

                // Eager-create semua robot mesh (avatar robot.glb)
                const floorElev = getRobotElevation(floorNum);
                robotsData.forEach(r => {
                    const holder = getOrCreateRobotMesh(r);
                    let targetCoords = { x: r.current_x ?? 80.6, y: r.current_y ?? 68.48 };
                    // Bila robot tercatat di database berada di lantai lain, tempatkan di dekat Tangga lantai ini agar user bisa melihat robot
                    if (Number(r.floor) !== floorNum) {
                        const stairsKey = floorNum === 2 ? '2_Tangga' : '1_Tangga';
                        if (locationsData[stairsKey]) {
                            targetCoords = { x: locationsData[stairsKey].x, y: locationsData[stairsKey].y };
                        }
                    }
                    const wp = worldPosForLoc(targetCoords, _bcSize);
                    holder.position.set(wp.x, floorElev, wp.z);
                    holder.rotation.y = -((r.rotation || 0) * Math.PI / 180);
                    // Tampilkan robot di viewer lantai ini (dikontrol visibilitasnya oleh showRobotsOnMap)
                    holder.visible = true;
                });
                console.log('[Robopath bot_control] floor',floorNum,'nodeMeshes:', nodeMeshes.size, 'robotMeshes:', robotMeshes.size);
                try{ refreshRobotSelector(); updateDriveReadout(); }catch(e){}
                // Guard hide loader: hanya jika lantai aktif masih viewer ini
                if (_loaderEl && Number(currentFloor)===floorNum) {
                    if (_loaderBar) _loaderBar.style.width='100%';
                    if (_loaderPct) _loaderPct.textContent='100%';
                    if (_loaderStatus) _loaderStatus.textContent='Model siap!';
                    setTimeout(()=>{ if(Number(currentFloor)===floorNum && _loaderEl) _loaderEl.classList.add('hidden'); },200);
                }
                // Framing kamera denah bangunan presisi (Foto 2)
                _defaultCamTarget.set(3.8, 0.5, 0);
                camera.position.set(3.8, 16, 24);
                controls.target.copy(_defaultCamTarget);
                controls.update();
            }, undefined, (err) => {
                console.error('Error parsing GLB model Lantai '+floorNum+':', err);
                if (_loaderStatus) _loaderStatus.textContent='Gagal memproses model 3D!';
            });
        }).catch(err => {
            console.error('Error fetching GLB Lantai '+floorNum+':', err);
            if (_loaderStatus) _loaderStatus.textContent='Gagal mengunduh aset 3D!';
        });

        let animationFrameId = null;
        const driveClock = new THREE.Clock();
        function snapHolderToFloor(holder) {
            if (!holder) return;
            const targetFloorElev = getRobotElevation(floorNum);
            if (!_bcModel) { holder.position.y = targetFloorElev; return; }
            try {
                const rc = new THREE.Raycaster(
                    new THREE.Vector3(holder.position.x, holder.position.y + 5, holder.position.z),
                    new THREE.Vector3(0, -1, 0), 0, 20);
                const hits = rc.intersectObject(_bcModel, true);
                if (hits.length) holder.position.y = hits[0].point.y;
                else holder.position.y = targetFloorElev;
            } catch (e) { holder.position.y = targetFloorElev; }
        }
        function stepManualDrive() {
            const dt = Math.min(driveClock.getDelta(), 0.05);
            if (Number(currentFloor) !== floorNum) return;
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
                syncRobotPositionFromMesh(t, _bcSize);
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

        function focusOn(pos, distance = 2.0) {
            if (!pos) return;
            const t = new THREE.Vector3(pos.x, pos.y || 0, pos.z);
            controls.target.copy(t);
            const dir = camera.position.clone().sub(controls.target).normalize();
            if (dir.lengthSq() < 0.01) dir.set(0, 0.7, 0.7).normalize();
            camera.position.copy(t.clone().add(dir.multiplyScalar(distance)));
            controls.update();
        }

        function resetView() {
            _defaultCamTarget.set(3.8, 0.5, 0);
            camera.position.set(3.8, 16, 24);
            controls.target.copy(_defaultCamTarget);
            controls.update();
        }

        return {
            resize: onResize,
            destroy: () => { if (animationFrameId) cancelAnimationFrame(animationFrameId); window.removeEventListener('resize', onResize); renderer.dispose(); },
            scene, camera, renderer, controls,
            robotsGroup, nodesGroup, edgesGroup,
            robotMeshes, nodeMeshes,
            getOrCreateRobotMesh, getOrCreateNodeMesh, updateNodeMeshAppearance, build3DEdges,
            getModelSize: () => _bcSize,
            floor: floorNum,
            getDefaultCamTarget: () => _defaultCamTarget.clone(),
            focusOn,
            resetView
        };
    }

    function zoom3DCamera(factor) {
        const vw = activeBotViewer();
        if (!vw || !vw.controls || !vw.camera) return;
        const c = vw.camera;
        const t = vw.controls.target;
        const dir = c.position.clone().sub(t);
        const newLen = Math.max(0.06, Math.min(250, dir.length() * factor));
        dir.setLength(newLen);
        c.position.copy(t.clone().add(dir));
        vw.controls.update();
    }

    function focusOnActiveSelection() {
        const vw = activeBotViewer();
        if (!vw || !vw.focusOn) return;
        if (selected3DObject) {
            vw.focusOn(selected3DObject.position, 1.8);
        } else if (selectedNodeId && locationsData[selectedNodeId]) {
            const wp = worldPosForLoc(locationsData[selectedNodeId], vw.getModelSize());
            vw.focusOn(wp, 1.8);
        } else if (activeRobotId != null && vw.robotMeshes && vw.robotMeshes.has(activeRobotId)) {
            vw.focusOn(vw.robotMeshes.get(activeRobotId).position, 1.8);
        } else {
            alert('Pilih sebuah node atau robot terlebih dahulu untuk fokus kamera.');
        }
    }

    function reset3DCameraView() {
        const vw = activeBotViewer();
        if (!vw || !vw.resetView) return;
        vw.resetView();
    }

    let currentTool = 'move';
    let showHiddenDots = true; // Default: TRUE agar titik transit (biru terang tanpa nama) selalu tampil di denah editor
    let selectedNodeId = null;
    let connectStartNodeId = null;
    let draggedNodeId = null;

    let locationsData = @json($locations);
    let adjData = @json($adj);

    function toggleShowHiddenDots(forceVal) {
        if (typeof forceVal === 'boolean') {
            showHiddenDots = forceVal;
        } else {
            showHiddenDots = !showHiddenDots;
        }
        syncTransitVisibilityUI();
        renderEditorMap();
    }

    function syncTransitVisibilityUI() {
        const icon = document.getElementById('icon-toggle-hidden');
        const text = document.getElementById('text-toggle-hidden');
        const btn = document.getElementById('btn-toggle-hidden');

        const fmIcon = document.getElementById('fullmap-icon-toggle-hidden');
        const fmText = document.getElementById('fullmap-text-toggle-hidden');
        const fmBtn = document.getElementById('fullmap-btn-toggle-hidden');

        if (showHiddenDots) {
            if (icon) icon.className = "fa-solid fa-eye text-[#3b4cb8]";
            if (text) text.textContent = "Transit: Tampil";
            if (btn) btn.className = "bg-blue-50 border border-blue-300 text-[#3b4cb8] font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (fmIcon) fmIcon.className = "fa-solid fa-eye text-sky-400";
            if (fmText) fmText.textContent = "Transit: Tampil";
            if (fmBtn) fmBtn.className = "bg-sky-950/80 border border-sky-500/40 text-sky-300 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition";
        } else {
            if (icon) icon.className = "fa-solid fa-eye-slash text-gray-400";
            if (text) text.textContent = "Transit: Sembunyi";
            if (btn) btn.className = "bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-500 font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (fmIcon) fmIcon.className = "fa-solid fa-eye-slash text-gray-400";
            if (fmText) fmText.textContent = "Transit: Sembunyi";
            if (fmBtn) fmBtn.className = "bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition";
        }
    }

    // Toggle Tampilkan / Sembunyikan Avatar Robot 3D (Default: sembunyi saat pengeditan node)
    function toggleShowRobots(forceVal) {
        if (typeof forceVal === 'boolean') {
            showRobotsOnMap = forceVal;
        } else {
            showRobotsOnMap = !showRobotsOnMap;
        }

        allBotViewers().forEach(vw => {
            if (vw && vw.robotsGroup) {
                vw.robotsGroup.visible = showRobotsOnMap;
            }
        });

        syncRobotVisibilityUI();
    }

    function syncRobotVisibilityUI() {
        const btn = document.getElementById('btn-toggle-robots');
        const icon = document.getElementById('icon-toggle-robots');
        const text = document.getElementById('text-toggle-robots');

        const fmBtn = document.getElementById('fullmap-btn-toggle-robots');
        const fmIcon = document.getElementById('fullmap-icon-toggle-robots');
        const fmText = document.getElementById('fullmap-text-toggle-robots');

        if (showRobotsOnMap) {
            if (btn) btn.className = "bg-blue-50 border border-blue-300 text-[#3b4cb8] font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (icon) icon.className = "fa-solid fa-robot text-[#3b4cb8]";
            if (text) text.textContent = "Robot: Tampil";

            if (fmBtn) fmBtn.className = "bg-sky-950/80 border border-sky-500/40 text-sky-300 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (fmIcon) fmIcon.className = "fa-solid fa-robot text-sky-400";
            if (fmText) fmText.textContent = "Robot: Tampil";
        } else {
            if (btn) btn.className = "bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-500 font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (icon) icon.className = "fa-solid fa-robot text-gray-400";
            if (text) text.textContent = "Robot: Sembunyi";

            if (fmBtn) fmBtn.className = "bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition";
            if (fmIcon) fmIcon.className = "fa-solid fa-robot text-gray-400";
            if (fmText) fmText.textContent = "Robot: Sembunyi";
        }
    }

    // === 3D Object Control Functions ===
    function updateSelected3DObjectUI() {
        const info = document.getElementById('selected-3d-info');
        const slider = document.getElementById('slider-3d-rotation');
        const valRot = document.getElementById('val-3d-rotation');
        const inpX = document.getElementById('input-3d-x');
        const inpZ = document.getElementById('input-3d-z');
        const inp3DY = document.getElementById('input-3d-y');
        const inpElev = document.getElementById('inspect-y-elev');
        if (!info) return;
        if (!selected3DObject) {
            info.textContent = 'Tidak ada object dipilih';
            if (slider) slider.value = 0;
            if (valRot) valRot.textContent = '0°';
            if (inpX) inpX.value = '';
            if (inpZ) inpZ.value = '';
            if (inp3DY) inp3DY.value = '';
            return;
        }
        const t = selected3DObject.userData?.type;
        const id = t === 'node' ? selected3DObject.userData.nodeId : ('Robot #' + selected3DObject.userData.robotId);
        info.textContent = `${t?.toUpperCase()}: ${id}`;
        if (inpX) inpX.value = selected3DObject.position.x.toFixed(2);
        if (inpZ) inpZ.value = selected3DObject.position.z.toFixed(2);
        const curElevVal = (t === 'node' && locationsData[selected3DObject.userData?.nodeId]?.y_elev !== undefined && locationsData[selected3DObject.userData?.nodeId]?.y_elev !== null)
            ? locationsData[selected3DObject.userData.nodeId].y_elev
            : Number(parseFloat(selected3DObject.position.y.toFixed(4)));
        if (inp3DY) inp3DY.value = curElevVal;
        if (inpElev && t === 'node') inpElev.value = curElevVal;
        const rotDeg = (selected3DObject.rotation.y * 180 / Math.PI) % 360;
        if (slider) slider.value = Math.round(rotDeg);
        if (valRot) valRot.textContent = Math.round(rotDeg) + '°';
        updateDriveReadout();
    }

    function move3DObject(dx, dz) {
        if (!selected3DObject) { alert('Pilih object di canvas 3D dulu.'); return; }
        selected3DObject.position.x += dx;
        selected3DObject.position.z += dz;
        const vw = viewerOfHolder(selected3DObject);
        const sz = vw ? vw.getModelSize() : (activeBotViewer()?activeBotViewer().getModelSize():null);
        if (selected3DObject.userData.type === 'node') {
            if (!sz) { updateSelected3DObjectUI(); return; }
            const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) {
                locationsData[nodeId].x = pct.x;
                locationsData[nodeId].y = pct.y;
                if (selectedNodeId === nodeId) inspectNode(nodeId);
                if (vw && vw.build3DEdges) vw.build3DEdges();
                renderEditorMap();
            }
        } else if (selected3DObject.userData.type === 'robot') {
            if (sz && sz.x > 0.1) {
                const rid = Number(selected3DObject.userData.robotId);
                const r = robotsData.find(x => Number(x.id) === rid);
                if (r) {
                    const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
                    r.current_x = parseFloat(pct.x.toFixed(2));
                    r.current_y = parseFloat(pct.y.toFixed(2));
                }
            }
            updateDriveReadout();
        }
        updateSelected3DObjectUI();
    }

    function updateNodeElevation(nodeId, val, sourceInputId = null) {
        if (!nodeId || !locationsData[nodeId]) return;
        const num = parseFloat(val);
        if (isNaN(num)) return;
        locationsData[nodeId].y_elev = num;
        locationsData[nodeId]._fy = num;

        const inpElev = document.getElementById('inspect-y-elev');
        if (inpElev && sourceInputId !== 'inspect-y-elev') {
            inpElev.value = num;
        }
        const inp3DY = document.getElementById('input-3d-y');
        if (inp3DY && sourceInputId !== 'input-3d-y') {
            inp3DY.value = num;
        }

        const vw = activeBotViewer();
        if (vw && vw.nodeMeshes && vw.nodeMeshes.has(nodeId)) {
            const mesh = vw.nodeMeshes.get(nodeId);
            if (mesh) mesh.position.y = num;
        }
        if (selected3DObject && selected3DObject.userData?.nodeId === nodeId) {
            selected3DObject.position.y = num;
        }
        if (vw && vw.build3DEdges) vw.build3DEdges();
        refreshObjectStatus();
    }

    function move3DObjectY(dy) {
        if (!selected3DObject) { alert('Pilih object di canvas 3D dulu.'); return; }
        const curY = (selected3DObject.userData.type === 'node' && locationsData[selected3DObject.userData.nodeId]?.y_elev !== undefined && locationsData[selected3DObject.userData.nodeId]?.y_elev !== null)
            ? Number(locationsData[selected3DObject.userData.nodeId].y_elev)
            : Number(selected3DObject.position.y || 0);
        const newY = Number(parseFloat((curY + dy).toFixed(4)));
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            updateNodeElevation(nodeId, newY);
        } else if (selected3DObject.userData.type === 'robot') {
            selected3DObject.position.y = newY;
            updateDriveReadout();
        }
        updateSelected3DObjectUI();
    }

    function set3DWorldY(val) {
        if (!selected3DObject) return;
        const y = parseFloat(val); if (isNaN(y)) return;
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            updateNodeElevation(nodeId, y, 'input-3d-y');
        } else if (selected3DObject.userData.type === 'robot') {
            selected3DObject.position.y = y;
            updateDriveReadout();
        }
    }

    function handleElevationChange(val) {
        const nodeId = selectedNodeId || (selected3DObject?.userData?.type === 'node' ? selected3DObject.userData.nodeId : null);
        updateNodeElevation(nodeId, val, 'inspect-y-elev');
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
        const vw = viewerOfHolder(selected3DObject);
        const sz = vw ? vw.getModelSize() : null; if(!sz) return;
        const pct = locFromWorld(x, selected3DObject.position.z, sz);
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) { locationsData[nodeId].x = pct.x; if (selectedNodeId === nodeId) inspectNode(nodeId); if (vw.build3DEdges) vw.build3DEdges(); renderEditorMap(); }
        } else if (selected3DObject.userData.type === 'robot') {
            const rid = Number(selected3DObject.userData.robotId);
            const r = robotsData.find(x => Number(x.id) === rid);
            if (r) {
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
            updateDriveReadout();
        }
    }
    function set3DWorldZ(val) {
        if (!selected3DObject) return;
        const z = parseFloat(val); if (isNaN(z)) return;
        selected3DObject.position.z = z;
        const vw = viewerOfHolder(selected3DObject);
        const sz = vw ? vw.getModelSize() : null; if(!sz) return;
        const pct = locFromWorld(selected3DObject.position.x, z, sz);
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) { locationsData[nodeId].y = pct.y; if (selectedNodeId === nodeId) inspectNode(nodeId); if (vw.build3DEdges) vw.build3DEdges(); renderEditorMap(); }
        } else if (selected3DObject.userData.type === 'robot') {
            const rid = Number(selected3DObject.userData.robotId);
            const r = robotsData.find(x => Number(x.id) === rid);
            if (r) {
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
            updateDriveReadout();
        }
    }

    function reset3DObjectPosition() {
        if (!selected3DObject) { alert('Pilih object dulu.'); return; }
        selected3DObject.position.set(0, 0, 0);
        selected3DObject.rotation.y = 0;
        updateSelected3DObjectUI();
    }

    function save3DObjectToGraph() {
        if (!selected3DObject) { alert('Pilih object dulu.'); return; }
        if (selected3DObject.userData.type === 'node') {
            const vw = viewerOfHolder(selected3DObject);
            const sz = vw ? vw.getModelSize() : null;
            if (sz) {
                const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
                const nodeId = selected3DObject.userData.nodeId;
                if (locationsData[nodeId]) {
                    locationsData[nodeId].x = pct.x;
                    locationsData[nodeId].y = pct.y;
                    const elev = (locationsData[nodeId].y_elev !== undefined && locationsData[nodeId].y_elev !== null)
                        ? Number(locationsData[nodeId].y_elev)
                        : Number(selected3DObject.position.y);
                    locationsData[nodeId].y_elev = elev;
                    locationsData[nodeId]._fy = elev;
                }
            }
            saveGraphToServer();
        } else if (selected3DObject.userData.type === 'robot') {
            const rid = Number(selected3DObject.userData.robotId);
            const r = robotsData.find(x => Number(x.id) === rid);
            if (!r) { alert('Data robot tidak ditemukan.'); return; }
            const vw = viewerOfHolder(selected3DObject);
            const sz = vw ? vw.getModelSize() : null;
            if (sz && sz.x > 0.1) {
                const pct = locFromWorld(selected3DObject.position.x, selected3DObject.position.z, sz);
                r.current_x = parseFloat(pct.x.toFixed(2));
                r.current_y = parseFloat(pct.y.toFixed(2));
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fetch(`/api/robots/${rid}/telemetry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    current_x: r.current_x,
                    current_y: r.current_y,
                    floor: Number(currentFloor) || 1
                })
            })
            .then(res => res.json())
            .then(d => {
                // Sync settings_3d juga
                fetch('/api/settings/label-scale', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        scale: labelScaleMultiplier,
                        settings_3d: current3DSettings
                    })
                }).catch(e => console.warn('Sync settings_3d fail:', e));
                alert(`✓ Posisi Robot #${rid} berhasil disimpan di database!`);
            })
            .catch(err => {
                console.error('Error saving robot telemetry:', err);
                alert('Gagal menyimpan posisi robot: ' + err.message);
            });
        }
    }

    function switchFloor(floorNum) {
        currentFloor = floorNum;
        activeRobotFloor = floorNum;
        updateRobotFloorUI();
        const tFloor1 = document.getElementById('tab-floor-1');
        const tFloor2 = document.getElementById('tab-floor-2');
        if (tFloor1) {
            tFloor1.className = floorNum === 1 
                ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
                : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
        }
        if (tFloor2) {
            tFloor2.className = floorNum === 2 
                ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
                : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
        }

        const fmTab1 = document.getElementById('fullmap-tab-floor-1');
        const fmTab2 = document.getElementById('fullmap-tab-floor-2');
        if (fmTab1) fmTab1.className = floorNum === 1 
            ? "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white" 
            : "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10";
        if (fmTab2) fmTab2.className = floorNum === 2 
            ? "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white" 
            : "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10";

        const editorContainer = document.getElementById('editor-map-container');
        const canvas3D = document.getElementById('botctrl-3d-canvas-container');
        const canvas3DF1 = document.getElementById('botctrl-3d-canvas-f1');
        const hint3D = document.getElementById('botctrl-3d-hint');
        const loaderEl = document.getElementById('botctrl-3d-loader');
        // Both floors 3D
        editorContainer.style.backgroundImage = 'none';
        editorContainer.style.backgroundColor = '#0f172a';
        if (floorNum === 1) {
            document.getElementById('floor-badge').textContent = 'Showing Floor 1 (3D)';
            if (canvas3D) canvas3D.classList.add('hidden');
            if (canvas3DF1) {
                canvas3DF1.classList.remove('hidden');
                // show loader before init
                if (loaderEl && !modelLoadedByFloor[1]) {
                    loaderEl.classList.remove('hidden');
                    const t=document.getElementById('botctrl-3d-loader-title'); if(t) t.textContent='Memuat Model 3D Lantai 1...';
                    const s=document.getElementById('botctrl-3d-loader-status'); if(s) s.textContent='Mengunduh aset GLB (8 MB)...';
                }
                setTimeout(() => {
                    if (!threeBotCtrlF1) {
                        threeBotCtrlF1 = initThreeViewer('botctrl-3d-canvas-f1', 1);
                    } else { threeBotCtrlF1.resize(); if(modelLoadedByFloor[1] && loaderEl) loaderEl.classList.add('hidden'); }
                    sync3DNodesToData();
                }, 50);
            }
            if (hint3D) hint3D.classList.remove('hidden');
            const panel3D = document.getElementById('panel-3d-controls');
            if (panel3D) panel3D.classList.remove('hidden');
        } else {
            document.getElementById('floor-badge').textContent = 'Showing Floor 2 (3D)';
            if (canvas3DF1) canvas3DF1.classList.add('hidden');
            if (canvas3D) {
                canvas3D.classList.remove('hidden');
                if (loaderEl && !modelLoadedByFloor[2]) {
                    loaderEl.classList.remove('hidden');
                    const t=document.getElementById('botctrl-3d-loader-title'); if(t) t.textContent='Memuat Model 3D Lantai 2...';
                    const s=document.getElementById('botctrl-3d-loader-status'); if(s) s.textContent='Mengunduh aset GLB (14 MB)...';
                }
                setTimeout(() => {
                    if (!threeBotCtrl) {
                        threeBotCtrl = initThreeViewer('botctrl-3d-canvas-container', 2);
                    } else { threeBotCtrl.resize(); if(modelLoadedByFloor[2] && loaderEl) loaderEl.classList.add('hidden'); }
                    sync3DNodesToData();
                }, 50);
            }
            if (hint3D) hint3D.classList.remove('hidden');
            const panel3D = document.getElementById('panel-3d-controls');
            if (panel3D) panel3D.classList.remove('hidden');
        }
        selectedNodeId = null;
        clearInspector();
        activeRobotFloor = floorNum;
        updateRobotFloorUI();
        updateRobotElevationUI();
        syncFullMapControls();
        syncRobotVisibilityUI();
        renderEditorMap();
    }

    function setEditorTool(tool) {
        currentTool = tool;
        ['hand', 'move', 'add', 'connect', 'delete'].forEach(t => {
            const btn = document.getElementById(`tool-${t}`);
            if (btn) {
                if (t === tool) {
                    btn.className = "px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 font-bold transition";
                } else {
                    btn.className = "px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition";
                }
            }
            const fmBtn = document.getElementById(`fullmap-tool-${t}`);
            if (fmBtn) {
                if (t === tool) {
                    fmBtn.className = "px-3 py-1.5 rounded-lg bg-white text-[#3b4cb8] shadow font-bold flex items-center gap-1.5 transition";
                } else {
                    fmBtn.className = "px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition";
                }
            }
        });

        // Sync Robot-specific tool buttons (Hand & Move Robot)
        ['hand', 'move'].forEach(t => {
            const rBtn = document.getElementById(`tool-robot-${t}`);
            if (rBtn) {
                if (t === tool) {
                    rBtn.className = "px-3 py-2 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 font-bold transition";
                } else {
                    rBtn.className = "px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition";
                }
            }
            const rFmBtn = document.getElementById(`fullmap-tool-robot-${t}`);
            if (rFmBtn) {
                if (t === tool) {
                    rFmBtn.className = "px-3 py-1.5 rounded-lg bg-white text-[#3b4cb8] shadow font-bold flex items-center gap-1.5 transition";
                } else {
                    rFmBtn.className = "px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition";
                }
            }
        });

        // Switch OrbitControls button mapping:
        // In 'hand' mode:
        //   - LEFT drag: PAN (geser kanvas)
        //   - RIGHT drag: ROTATE (putar sudut pandang 3D)
        // In other modes ('move', 'add', 'connect', 'delete'):
        //   - LEFT drag: ROTATE (putar 3D) / drag objek
        //   - RIGHT drag: PAN (geser kamera)
        allBotViewers().forEach(vw => {
            if (vw && vw.controls) {
                if (tool === 'hand') {
                    vw.controls.mouseButtons.LEFT = THREE.MOUSE.PAN;
                    vw.controls.mouseButtons.RIGHT = THREE.MOUSE.ROTATE;
                } else {
                    vw.controls.mouseButtons.LEFT = THREE.MOUSE.ROTATE;
                    vw.controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
                }
            }
            if (vw && vw.renderer && vw.renderer.domElement) {
                vw.renderer.domElement.style.cursor = (tool === 'hand') ? 'grab' : 'default';
            }
        });

        const hint = document.getElementById('editor-hint');
        if (hint) {
            if (tool === 'hand') hint.textContent = "Tool Free Hand: Klik KIRI geser (pan), Klik KANAN putar (rotate) sudut pandang tanpa menyentuh node.";
            if (tool === 'move') hint.textContent = "Tool Move: Klik & drag node/robot di canvas 3D untuk pindah posisi. Gunakan D-pad di panel kontrol untuk presisi.";
            if (tool === 'add') hint.textContent = "Tool Add: Klik area kosong di canvas 3D untuk tambah node ruangan baru.";
            if (tool === 'connect') hint.textContent = "Tool Connect: Klik node A lalu node B di canvas 3D untuk hubungkan jalur.";
            if (tool === 'delete') hint.textContent = "Tool Delete: Klik node di canvas 3D untuk hapus.";
        }
        
        connectStartNodeId = null;
        connectStart3DNode = null;
        renderEditorMap();
    }

    // === Add & Delete Node Modals (In-Page, Anti-Freeze, Anti-Lock) ===
    let pendingAddNodeData = null;
    let pendingDeleteNodeId = null;

    function openAddNodeModal(floor, xPct, yPct) {
        pendingAddNodeData = { floor: Number(floor) || 1, xPct: parseFloat(xPct), yPct: parseFloat(yPct) };
        const modal = document.getElementById('modal-add-node-3d');
        const input = document.getElementById('modal-add-node-name');
        const floorLabel = document.getElementById('modal-add-floor-label');
        const xLabel = document.getElementById('modal-add-x');
        const yLabel = document.getElementById('modal-add-y');
        if (!modal) return;

        if (floorLabel) floorLabel.textContent = floor;
        if (xLabel) xLabel.textContent = `${xPct.toFixed(2)}%`;
        if (yLabel) yLabel.textContent = `${yPct.toFixed(2)}%`;
        if (input) {
            input.value = `Ruang_${Math.floor(Math.random() * 100)}`;
        }
        modal.classList.remove('hidden');
        setTimeout(() => {
            if (input) { input.focus(); input.select(); }
        }, 50);
    }

    function closeAddNodeModal() {
        const modal = document.getElementById('modal-add-node-3d');
        if (modal) modal.classList.add('hidden');
        pendingAddNodeData = null;
        allBotViewers().forEach(vw => { if (vw && vw.controls) vw.controls.enabled = true; });
    }

    function confirmAddNodeModal(e) {
        if (e) e.preventDefault();
        if (!pendingAddNodeData) return;
        const input = document.getElementById('modal-add-node-name');
        const rawName = input ? input.value.trim() : '';
        if (!rawName) return;

        const { floor, xPct, yPct } = pendingAddNodeData;
        const clean = rawName;
        const key = `${floor}_${clean}`;

        locationsData[key] = {
            id: key,
            name: clean,
            x: xPct,
            y: yPct,
            floor: floor,
            hidden: false,
            is_destination: true
        };
        adjData[key] = [];

        const vw = activeBotViewer();
        if (vw && vw.getOrCreateNodeMesh) {
            const mesh = vw.getOrCreateNodeMesh(key);
            const sz = vw.getModelSize ? vw.getModelSize() : null;
            if (mesh && sz) {
                const w = worldPosForLoc(locationsData[key], sz);
                mesh.position.set(w.x, w.y, w.z);
            }
        }

        selectedNodeId = key;
        inspectNode(key);
        renderEditorMap();
        closeAddNodeModal();
    }

    function openDeleteNodeModal(nodeId) {
        if (!locationsData[nodeId]) return;
        pendingDeleteNodeId = nodeId;
        const modal = document.getElementById('modal-delete-node-3d');
        const nameEl = document.getElementById('modal-delete-node-name');
        const detEl = document.getElementById('modal-delete-node-details');
        if (!modal) return;

        const loc = locationsData[nodeId];
        if (nameEl) nameEl.textContent = loc.name || nodeId;
        if (detEl) detEl.textContent = `Lantai ${loc.floor || 1} • X: ${loc.x}% • Y: ${loc.y}% • ${(adjData[nodeId] || []).length} koneksi`;

        modal.classList.remove('hidden');
    }

    function closeDeleteNodeModal() {
        const modal = document.getElementById('modal-delete-node-3d');
        if (modal) modal.classList.add('hidden');
        pendingDeleteNodeId = null;
        allBotViewers().forEach(vw => { if (vw && vw.controls) vw.controls.enabled = true; });
    }

    function confirmDeleteNodeModal() {
        if (!pendingDeleteNodeId) return;
        const nodeId = pendingDeleteNodeId;

        delete locationsData[nodeId];
        delete adjData[nodeId];
        for (let k in adjData) {
            adjData[k] = adjData[k].filter(n => n !== nodeId);
        }

        allBotViewers().forEach(vw => {
            if (vw && vw.nodeMeshes && vw.nodeMeshes.has(nodeId)) {
                const m = vw.nodeMeshes.get(nodeId);
                if (m && m.parent) m.parent.remove(m);
                vw.nodeMeshes.delete(nodeId);
            }
        });

        selectedNodeId = null;
        selected3DObject = null;
        connectStart3DNode = null;
        clearInspector();
        updateSelected3DObjectUI();
        renderEditorMap();
        closeDeleteNodeModal();
    }

    function handleEditRobotClick() {
        setEditTargetMode('robot');
        toggleFullMap(true);
        toggleInspectorPanel(true);
    }

    function handleEditJalurClick() {
        setEditTargetMode('node');
        toggleFullMap(true);
        toggleInspectorPanel(false);
    }

    function handleEditRuanganClick() {
        handleEditJalurClick();
    }

    function handleFullMapSave() {
        if (currentEditTarget === 'robot') {
            saveRobotPosition();
        } else {
            saveGraphToServer();
        }
    }

    let isFullMap = false;
    function toggleFullMap(showFull) {
        if (showFull === undefined) isFullMap = !isFullMap;
        else isFullMap = !!showFull;

        const editorCard = document.getElementById('editor-map-card');
        const editorContainer = document.getElementById('editor-map-container');
        const fullmapTopBar = document.getElementById('fullmap-top-bar');
        const editorHeader = document.getElementById('editor-header-bar');
        const cardNode = document.getElementById('node-inspector-card');
        const cardRobot = document.getElementById('robot-control-card');
        const btnFloatingFm = document.getElementById('btn-floating-fullmap');
        const btnOpenFm = document.getElementById('btn-open-fullmap');

        const asideEl = document.querySelector('body > aside') || document.querySelector('aside');
        if (asideEl) {
            asideEl.style.display = isFullMap ? 'none' : '';
        }
        document.body.classList.toggle('body-in-fullmap', isFullMap);

        if (isFullMap) {
            if (editorCard) {
                editorCard.classList.remove('hidden');
                editorCard.classList.add('botctrl-fullmap-card');
            }
            if (editorContainer) editorContainer.classList.add('botctrl-fullmap-canvas');
            if (fullmapTopBar) fullmapTopBar.classList.remove('hidden');
            if (editorHeader) editorHeader.classList.add('hidden');
            if (btnFloatingFm) {
                btnFloatingFm.innerHTML = '<i class="fa-solid fa-compress"></i>';
                btnFloatingFm.title = 'Kecilkan / Keluar Full Map (Esc)';
            }
            if (btnOpenFm) {
                btnOpenFm.innerHTML = '<i class="fa-solid fa-compress text-[#3b4cb8]"></i> <span>Keluar Full Map</span>';
            }

            if (currentEditTarget === 'robot') {
                if (cardRobot) cardRobot.classList.remove('hidden');
                if (cardNode) cardNode.classList.add('hidden');
            } else {
                if (cardRobot) cardRobot.classList.add('hidden');
                if (cardNode) {
                    if (selectedNodeId) cardNode.classList.remove('hidden');
                    else cardNode.classList.add('hidden');
                }
            }
        } else {
            if (editorCard) {
                editorCard.classList.remove('botctrl-fullmap-card');
                editorCard.classList.add('hidden');
            }
            if (editorContainer) editorContainer.classList.remove('botctrl-fullmap-canvas');
            if (fullmapTopBar) fullmapTopBar.classList.add('hidden');
            if (editorHeader) editorHeader.classList.remove('hidden');
            if (btnFloatingFm) {
                btnFloatingFm.innerHTML = '<i class="fa-solid fa-expand"></i>';
                btnFloatingFm.title = 'Buka Peta 3D (Full Map)';
            }
            if (btnOpenFm) {
                btnOpenFm.innerHTML = '<i class="fa-solid fa-expand text-[#3b4cb8]"></i> <span>Buka Peta 3D (Full Map)</span>';
            }

            if (cardNode) cardNode.classList.add('hidden');
            if (cardRobot) cardRobot.classList.add('hidden');
            setEditTargetMode('robot');
        }

        syncFullMapControls();

        // Trigger resize on active viewer so aspect ratio and canvas fill screen instantly
        setTimeout(() => {
            const vw = activeBotViewer();
            if (vw && vw.resize) vw.resize();
        }, 60);
    }

    function toggleInspectorPanel(forceState) {
        const cardNode = document.getElementById('node-inspector-card');
        const cardRobot = document.getElementById('robot-control-card');
        const activeCard = currentEditTarget === 'robot' ? cardRobot : cardNode;
        const otherCard = currentEditTarget === 'robot' ? cardNode : cardRobot;
        if (otherCard) otherCard.classList.add('hidden');
        if (!activeCard) return;

        if (forceState !== undefined) {
            if (forceState) activeCard.classList.remove('hidden');
            else activeCard.classList.add('hidden');
        } else {
            activeCard.classList.toggle('hidden');
        }
        syncFullMapControls();
    }

    function syncFullMapControls() {
        const tabF1 = document.getElementById('fullmap-tab-floor-1');
        const tabF2 = document.getElementById('fullmap-tab-floor-2');
        if (tabF1 && tabF2) {
            if (currentFloor === 1) {
                tabF1.className = "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow";
                tabF2.className = "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10";
            } else {
                tabF1.className = "px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10";
                tabF2.className = "px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white shadow";
            }
        }

        // Dedicated contextual toolbars by mode
        const fmTbNode = document.getElementById('fullmap-node-contextual');
        const fmTbRobot = document.getElementById('fullmap-robot-contextual');
        if (fmTbNode && fmTbRobot) {
            if (currentEditTarget === 'robot') {
                fmTbRobot.classList.remove('hidden');
                fmTbRobot.classList.add('flex');
                fmTbNode.classList.add('hidden');
                fmTbNode.classList.remove('flex');
            } else {
                fmTbNode.classList.remove('hidden');
                fmTbNode.classList.add('flex');
                fmTbRobot.classList.add('hidden');
                fmTbRobot.classList.remove('flex');
            }
        }

        // Contextual save button label
        const saveLabel = document.getElementById('fullmap-save-label');
        if (saveLabel) {
            saveLabel.textContent = currentEditTarget === 'robot' ? 'Simpan Posisi' : 'Simpan Denah';
        }

        const cardNode = document.getElementById('node-inspector-card');
        const cardRobot = document.getElementById('robot-control-card');
        const activeCard = currentEditTarget === 'robot' ? cardRobot : cardNode;
        const btnToggleInsp = document.getElementById('fullmap-btn-inspector');
        if (btnToggleInsp && activeCard) {
            const isShown = !activeCard.classList.contains('hidden');
            let badgeText = '';
            if (currentEditTarget === 'robot') {
                const r = robotsData.find(x => Number(x.id) === Number(activeRobotId));
                badgeText = r ? (`Bot #${r.id}`) : 'Robot';
            } else {
                badgeText = (selectedNodeId && locationsData[selectedNodeId]) ? (locationsData[selectedNodeId].name || selectedNodeId) : '';
            }
            if (isShown) {
                btnToggleInsp.className = "bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition";
                btnToggleInsp.innerHTML = '<i class="fa-solid fa-eye-slash"></i> <span>Tutup Inspector</span>' + (badgeText ? `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-black/20 px-1.5 py-0.5 rounded-md font-mono">${badgeText}</span>` : `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-black/20 px-1.5 py-0.5 rounded-md font-mono hidden"></span>`);
            } else {
                btnToggleInsp.className = "bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition";
                btnToggleInsp.innerHTML = '<i class="fa-solid fa-sliders"></i> <span>Edit Manual XYZ</span>' + (badgeText ? `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono">${badgeText}</span>` : `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono hidden"></span>`);
            }
        }
    }



    function focusOnActiveSelection() {
        const vw = activeBotViewer();
        if (!vw || !vw.controls || !vw.camera) return;
        let targetPos = null;
        if (currentEditTarget === 'robot') {
            if (activeRobotId != null && vw.robotMeshes && vw.robotMeshes.has(activeRobotId)) {
                targetPos = vw.robotMeshes.get(activeRobotId).position.clone();
            } else if (selected3DObject && selected3DObject.userData && selected3DObject.userData.isRobot) {
                targetPos = selected3DObject.position.clone();
            }
        } else {
            if (selectedNodeId && locationsData[selectedNodeId]) {
                const loc = locationsData[selectedNodeId];
                const sz = vw.getModelSize ? vw.getModelSize() : null;
                if (sz) {
                    const wp = worldPosForLoc(loc, sz);
                    targetPos = new THREE.Vector3(wp.x, Number(loc.y_elev ?? loc._fy ?? 0.05), wp.z);
                }
            } else if (selected3DObject) {
                targetPos = selected3DObject.position.clone();
            }
        }
        if (targetPos) {
            vw.controls.target.copy(targetPos);
            const offset = new THREE.Vector3(0, 4, 5);
            vw.camera.position.copy(targetPos.clone().add(offset));
            vw.controls.update();
        }
    }

    function renderEditorMap() {
        const svg = document.getElementById('editor-svg');
        const nodesLayer = document.getElementById('editor-nodes-layer');
        if (svg) { svg.innerHTML = ''; svg.style.display = 'none'; }
        if (nodesLayer) { nodesLayer.innerHTML = ''; nodesLayer.style.display = 'none'; }

        sync3DNodesToData();
    }

    function sync3DNodesToData() {
        allBotViewers().forEach(vw => {
            if (!vw || !vw.nodeMeshes) return;
            const sz = vw.getModelSize ? vw.getModelSize() : null;
            if (!sz || sz.x <= 0.1) return;
            const floorNum = Number(vw.floor);

            for (const id in locationsData) {
                const loc = locationsData[id];
                if (Number(loc.floor) !== floorNum) {
                    if (vw.nodeMeshes.has(id)) {
                        const m = vw.nodeMeshes.get(id);
                        if (m && m.parent) m.parent.remove(m);
                        vw.nodeMeshes.delete(id);
                    }
                    continue;
                }
                let holder = vw.nodeMeshes.get(id);
                if (!holder && vw.getOrCreateNodeMesh) {
                    holder = vw.getOrCreateNodeMesh(id);
                } else if (holder && vw.updateNodeMeshAppearance) {
                    vw.updateNodeMeshAppearance(holder, loc);
                }
                if (holder) {
                    const wp = worldPosForLoc(loc, sz);
                    holder.position.set(wp.x, wp.y, wp.z);
                    const isTransit = !loc.is_destination || loc.hidden;
                    holder.visible = (!isTransit || showHiddenDots);
                    if (holder.userData && holder.userData.selectionRing) {
                        const isSel = (selectedNodeId === id);
                        const isConn = (connectStart3DNode === id);
                        holder.userData.selectionRing.visible = (isSel || isConn);
                        if (isConn) holder.userData.selectionRing.material.color.setHex(0xf59e0b);
                        else holder.userData.selectionRing.material.color.setHex(0x10b981);
                    }
                }
            }
            vw.nodeMeshes.forEach((holder, id) => {
                if (!locationsData[id] || Number(locationsData[id].floor) !== floorNum) {
                    if (holder && holder.parent) holder.parent.remove(holder);
                    vw.nodeMeshes.delete(id);
                }
            });
            if (vw.build3DEdges) vw.build3DEdges();
        });
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

        const vw = activeBotViewer();
        if (vw && vw.nodeMeshes && vw.nodeMeshes.has(nodeId)) {
            selected3DObject = vw.nodeMeshes.get(nodeId);
            updateSelected3DObjectUI();
        }

        document.getElementById('inspect-node-name').value = loc.name || nodeId;
        document.getElementById('inspect-floor').value = loc.floor || 1;
        document.getElementById('inspect-x').value = loc.x;
        document.getElementById('inspect-y').value = loc.y;
        const elev = (loc.y_elev !== undefined && loc.y_elev !== null) ? loc.y_elev : (loc._fy ?? 0);
        const inpElev = document.getElementById('inspect-y-elev');
        if (inpElev) inpElev.value = Number(elev);
        const inp3DY = document.getElementById('input-3d-y');
        if (inp3DY) inp3DY.value = Number(elev);
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
        if (isFullMap && currentEditTarget === 'node') {
            const cardNode = document.getElementById('node-inspector-card');
            if (cardNode) cardNode.classList.remove('hidden');
        }
        syncFullMapControls();
    }

    function clearInspector() {
        document.getElementById('inspect-node-name').value = '';
        document.getElementById('inspect-x').value = '';
        document.getElementById('inspect-y').value = '';
        const inpElev = document.getElementById('inspect-y-elev');
        if (inpElev) inpElev.value = '';
        const inp3DY = document.getElementById('input-3d-y');
        if (inp3DY) inp3DY.value = '';
        document.getElementById('inspect-is-destination').checked = false;
        document.getElementById('inspect-hidden').checked = false;
        const objSel = document.getElementById('inspect-object');
        if (objSel) objSel.innerHTML = '<option value="">— Manual (x/y) —</option>';
        refreshObjectStatus();
        document.getElementById('inspect-neighbors').innerHTML = '<span class="text-gray-400 italic">No node selected</span>';
        const badge = document.getElementById('neighbors-count-badge');
        if (badge) badge.textContent = `0 edges`;
        syncFullMapControls();
    }

    // === Mode Edit Target (Node vs Robot) & Inspector Switching ===
    let currentEditTarget = 'node'; // 'node' | 'robot'

    function toggleEditDropdown(which) {
        const ddMain = document.getElementById('dropdown-edit-target-main');
        const ddFull = document.getElementById('dropdown-edit-target-fullmap');
        if (which === 'main') {
            if (ddMain) ddMain.classList.toggle('hidden');
            if (ddFull) ddFull.classList.add('hidden');
        } else if (which === 'fullmap') {
            if (ddFull) ddFull.classList.toggle('hidden');
            if (ddMain) ddMain.classList.add('hidden');
        } else {
            if (ddMain) ddMain.classList.add('hidden');
            if (ddFull) ddFull.classList.add('hidden');
        }
    }

    // Close edit dropdowns on click outside
    window.addEventListener('click', (e) => {
        if (!e.target.closest('#btn-edit-target-main') && !e.target.closest('#dropdown-edit-target-main') &&
            !e.target.closest('#btn-edit-target-fullmap') && !e.target.closest('#dropdown-edit-target-fullmap')) {
            document.getElementById('dropdown-edit-target-main')?.classList.add('hidden');
            document.getElementById('dropdown-edit-target-fullmap')?.classList.add('hidden');
        }
    });

    function setEditTargetMode(mode) {
        currentEditTarget = mode;

        // 1. Sync Top Bar Mode Tabs (Normal View)
        const tabNode = document.getElementById('tab-mode-node');
        const tabRobot = document.getElementById('tab-mode-robot');
        if (tabNode && tabRobot) {
            if (mode === 'node') {
                tabNode.className = "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white flex items-center gap-2";
                tabRobot.className = "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200 flex items-center gap-2";
            } else {
                tabRobot.className = "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white flex items-center gap-2";
                tabNode.className = "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200 flex items-center gap-2";
            }
        }

        // 2. Sync Top Bar Dedicated Contextual Toolbars (Normal View)
        const tbNode = document.getElementById('toolbar-node-tools');
        const tbRobot = document.getElementById('toolbar-robot-tools');
        if (tbNode && tbRobot) {
            if (mode === 'node') {
                tbNode.classList.remove('hidden');
                tbNode.classList.add('flex');
                tbRobot.classList.add('hidden');
                tbRobot.classList.remove('flex');
            } else {
                tbRobot.classList.remove('hidden');
                tbRobot.classList.add('flex');
                tbNode.classList.add('hidden');
                tbNode.classList.remove('flex');
            }
        }

        // 3. Sync Full Map Mode Tabs
        const fmTabNode = document.getElementById('fullmap-tab-mode-node');
        const fmTabRobot = document.getElementById('fullmap-tab-mode-robot');
        if (fmTabNode && fmTabRobot) {
            if (mode === 'node') {
                fmTabNode.className = "px-3 py-1.5 rounded-lg bg-indigo-600 text-white shadow font-bold flex items-center gap-1.5 transition";
                fmTabRobot.className = "px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition";
            } else {
                fmTabRobot.className = "px-3 py-1.5 rounded-lg bg-indigo-600 text-white shadow font-bold flex items-center gap-1.5 transition";
                fmTabNode.className = "px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition";
            }
        }

        // 4. Sync Floating Inspector Cards
        const cardNode = document.getElementById('node-inspector-card');
        const cardRobot = document.getElementById('robot-control-card');

        if (mode === 'robot') {
            if (cardNode) cardNode.classList.add('hidden');
            if (isFullMap) {
                if (cardRobot) cardRobot.classList.remove('hidden');
            } else {
                if (cardRobot) cardRobot.classList.add('hidden');
            }
            inspectRobot(activeRobotId || resolveDefaultRobotId());
        } else {
            if (cardRobot) cardRobot.classList.add('hidden');
            if (isFullMap) {
                if (cardNode) {
                    if (selectedNodeId) cardNode.classList.remove('hidden');
                    else cardNode.classList.add('hidden');
                }
            } else {
                if (cardNode) cardNode.classList.add('hidden');
            }

            if (selectedNodeId) inspectNode(selectedNodeId);
            else clearInspector();
        }

        setEditorTool(currentTool || 'move');
        syncFullMapControls();
    }

    function inspectRobot(robotId) {
        const rid = Number(robotId || activeRobotId || resolveDefaultRobotId());
        if (!rid) return;
        activeRobotId = rid;
        const r = robotsData.find(x => Number(x.id) === rid);
        if (!r) return;

        setActiveRobot(rid, { selectHolder: true });

        const sel = document.getElementById('robot-selector');
        if (sel && sel.value !== String(rid)) sel.value = String(rid);
        const inspSel = document.getElementById('inspect-robot-selector');
        if (inspSel && inspSel.value !== String(rid)) inspSel.value = String(rid);

        const floorSel = document.getElementById('inspect-robot-floor');
        if (floorSel) floorSel.value = String(r.floor || currentFloor || 1);

        const inpX = document.getElementById('inspect-robot-x');
        if (inpX) inpX.value = (r.current_x !== undefined && r.current_x !== null) ? Number(r.current_x).toFixed(2) : '80.60';

        const inpY = document.getElementById('inspect-robot-y');
        if (inpY) inpY.value = (r.current_y !== undefined && r.current_y !== null) ? Number(r.current_y).toFixed(2) : '68.48';

        const inpElev = document.getElementById('inspect-robot-elev');
        const sideInpElev = document.getElementById('sidebar-input-robot-elev');
        const curElev = getRobotElevation(r.floor || currentFloor);
        if (inpElev) inpElev.value = curElev;
        if (sideInpElev) sideInpElev.value = curElev;

        const sliderRot = document.getElementById('inspect-robot-rotation');
        const lblRot = document.getElementById('inspect-robot-rot-val');
        const rot = Math.round(Number(r.rotation || 0) % 360);
        if (sliderRot) sliderRot.value = rot;
        if (lblRot) lblRot.textContent = `${rot}°`;

        const statusBadge = document.getElementById('inspect-robot-status');
        if (statusBadge) {
            statusBadge.textContent = r.status || 'Idle';
        }
        const badge2 = document.getElementById('robot-status-badge');
        if (badge2) {
            badge2.textContent = r.status || 'Online';
        }
        if (isFullMap && currentEditTarget === 'robot') {
            const cardRobot = document.getElementById('robot-control-card');
            if (cardRobot) cardRobot.classList.remove('hidden');
        }
        syncFullMapControls();
    }

    function syncRobotInspectorInputs(rid) {
        const inspSel = document.getElementById('robot-selector');
        if (!inspSel || Number(inspSel.value || activeRobotId) !== Number(rid)) return;
        const r = robotsData.find(x => Number(x.id) === Number(rid));
        if (!r) return;

        const inpX = document.getElementById('inspect-robot-x');
        const inpY = document.getElementById('inspect-robot-y');
        if (inpX && r.current_x !== undefined) inpX.value = Number(r.current_x).toFixed(2);
        if (inpY && r.current_y !== undefined) inpY.value = Number(r.current_y).toFixed(2);
    }

    function handleInspectRobotFloorChange(newFloor) {
        const rid = Number(activeRobotId);
        const r = robotsData.find(x => Number(x.id) === rid);
        if (!r) return;
        r.floor = Number(newFloor);
        switchFloor(Number(newFloor));
        const floorElev = getRobotElevation(newFloor);
        const inpElev = document.getElementById('inspect-robot-elev');
        if (inpElev) inpElev.value = floorElev;
        syncRobotMeshToCoordinates(r);
    }

    function handleRobotCoordinateChange() {
        const rid = Number(document.getElementById('inspect-robot-selector')?.value || activeRobotId);
        const r = robotsData.find(x => Number(x.id) === rid);
        if (!r) return;

        const inpX = document.getElementById('inspect-robot-x');
        const inpY = document.getElementById('inspect-robot-y');
        if (!inpX || !inpY) return;

        const xVal = parseFloat(inpX.value);
        const yVal = parseFloat(inpY.value);

        if (!isNaN(xVal)) r.current_x = Math.max(0, Math.min(100, parseFloat(xVal.toFixed(2))));
        if (!isNaN(yVal)) r.current_y = Math.max(0, Math.min(100, parseFloat(yVal.toFixed(2))));

        syncRobotMeshToCoordinates(r);
        updateDriveReadout();
    }

    function syncRobotMeshToCoordinates(r) {
        if (!r) return;
        allBotViewers().forEach(vw => {
            if (!vw || !vw.robotMeshes) return;
            const holder = vw.robotMeshes.get(Number(r.id));
            const sz = vw.getModelSize ? vw.getModelSize() : null;
            if (holder && sz && sz.x > 0.1) {
                const targetCoords = { x: r.current_x, y: r.current_y };
                const wp = worldPosForLoc(targetCoords, sz);
                holder.position.x = wp.x;
                holder.position.z = wp.z;
            }
        });
    }

    function syncRobotPositionFromMesh(holder, size) {
        if (!holder || !holder.userData || holder.userData.type !== 'robot') return;
        const rid = Number(holder.userData.robotId);
        const r = robotsData.find(x => Number(x.id) === rid);
        if (!r) return;
        const sz = size || (activeBotViewer() ? activeBotViewer().getModelSize() : null);
        if (sz && sz.x > 0.1) {
            const pct = locFromWorld(holder.position.x, holder.position.z, sz);
            r.current_x = parseFloat(pct.x.toFixed(2));
            r.current_y = parseFloat(pct.y.toFixed(2));
        }
        syncRobotInspectorInputs(rid);
    }

    function handleInspectRobotRotation(deg) {
        deg = parseFloat(deg);
        if (isNaN(deg)) return;
        const rid = Number(activeRobotId);
        const r = robotsData.find(x => Number(x.id) === rid);
        if (r) r.rotation = deg;
        const lbl = document.getElementById('inspect-robot-rot-val');
        if (lbl) lbl.textContent = `${Math.round(deg)}°`;
        allBotViewers().forEach(vw => {
            if (!vw || !vw.robotMeshes) return;
            const holder = vw.robotMeshes.get(rid);
            if (holder) {
                holder.rotation.y = -(deg * Math.PI / 180);
            }
        });
    }

    function adjustInspectRobotRotation(delta) {
        const slider = document.getElementById('inspect-robot-rotation');
        let cur = parseFloat(slider?.value || 0);
        cur = (cur + delta + 360) % 360;
        if (slider) slider.value = Math.round(cur);
        handleInspectRobotRotation(cur);
    }

    function saveRobotPosition(robotId) {
        const f = Number(activeRobotFloor) === 2 ? 2 : 1;
        const val = getRobotElevation(f);
        const scale = Number(robotScaleMultiplier).toFixed(2);

        current3DSettings.robot_scale = robotScaleMultiplier;
        if (f === 2) {
            current3DSettings.robot_elevation_f2 = val;
        } else {
            current3DSettings.robot_elevation_f1 = val;
        }

        save3DSettingsToServer(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Pengaturan Robot Disimpan!',
                    text: `Skala robot universal (${scale}x) dan Ketinggian Lantai ${f} (${val}m) berhasil disimpan.`,
                    timer: 2500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                alert(`✓ Pengaturan robot berhasil disimpan!`);
            }
        });
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
                    const vw = activeBotViewer();
                    if (vw && vw.getModelSize) {
                        const sz = vw.getModelSize();
                        if (sz && sz.x > 0.1) {
                            const wp = worldPosForLoc(loc, sz);
                            w = `X=${wp.x.toFixed(2)} Y=${wp.y} Z=${wp.z.toFixed(2)}`;
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
            const vw = activeBotViewer();
            if (val && vw && vw.scene && vw.getModelSize) {
                const sz = vw.getModelSize();
                if (sz && sz.x > 0.1 && resolveObjectAnchor(loc, vw.scene, sz)) {
                    const mesh = vw.nodeMeshes ? vw.nodeMeshes.get(selectedNodeId) : null;
                    if (mesh) {
                        const wp = worldPosForLoc(loc, sz);
                        mesh.position.set(wp.x, wp.y, wp.z);
                    }
                }
            }
        } catch (e) { console.warn('[Robopath] resolve on select fail', e); }
        const vw = activeBotViewer();
        if (vw && vw.build3DEdges) vw.build3DEdges();
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
        locationsData[selectedNodeId].is_destination = !!val;
        allBotViewers().forEach(vw => {
            if (vw && vw.nodeMeshes && vw.nodeMeshes.has(selectedNodeId)) {
                const holder = vw.nodeMeshes.get(selectedNodeId);
                if (vw.updateNodeMeshAppearance) vw.updateNodeMeshAppearance(holder, locationsData[selectedNodeId]);
            }
        });
        renderEditorMap();
    }

    function handleHiddenChange(val) {
        if (!selectedNodeId) return;
        locationsData[selectedNodeId].hidden = !!val;
        allBotViewers().forEach(vw => {
            if (vw && vw.nodeMeshes && vw.nodeMeshes.has(selectedNodeId)) {
                const holder = vw.nodeMeshes.get(selectedNodeId);
                if (vw.updateNodeMeshAppearance) vw.updateNodeMeshAppearance(holder, locationsData[selectedNodeId]);
            }
        });
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
                ...(locationsData[id].y_elev !== undefined && locationsData[id].y_elev !== null ? { y_elev: Number(locationsData[id].y_elev) } : {}),
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
                is_3d: true,
                locations: formattedLocations,
                adj: adjData,
                settings_3d: current3DSettings
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

    // --- IT Repair Center & Fleet Telemetry (Foto 5) ---
    let fleetRobots = [];
    let fleetDeliveries = [];
    let fleetAlerts = [];

    function fetchFleetTelemetry() {
        fetch('/api/telemetry')
            .then(res => res.json())
            .then(data => {
                fleetRobots = data.robots || [];
                fleetDeliveries = data.active_deliveries || [];
                fleetAlerts = data.active_alerts || [];
                renderFleetList();
            })
            .catch(err => console.error('Error fetching fleet telemetry:', err));
    }

    function renderFleetList() {
        const container = document.getElementById('fleet-control-list');
        if (!container) return;

        if (!fleetRobots || fleetRobots.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center py-8 text-xs text-gray-400"><i class="fa-solid fa-circle-info mr-1"></i> Tidak ada unit robot yang terhubung.</div>';
            return;
        }

        let html = '';
        fleetRobots.forEach(robot => {
            const delivery = fleetDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && (d.status === 'In Progress' || d.status === 'Pending'));
            const alert = fleetAlerts.find(a => Number(a.robot_id) === Number(robot.id) && a.status === 'Active');
            const hasIssue = !!alert || robot.status === 'Maintenance' || (delivery && delivery.status === 'Pending');

            const batLevel = Math.max(0, Math.min(100, Number(robot.battery_level) || 0));
            const batColor = batLevel > 50 ? 'bg-emerald-500' : (batLevel > 20 ? 'bg-amber-500' : 'bg-rose-500');
            const batTextCol = batLevel <= 20 ? 'text-rose-600 font-bold' : 'text-gray-700';

            html += `
            <div class="border ${hasIssue ? 'border-rose-300 bg-rose-50/70 shadow-md' : 'border-gray-200 bg-gray-50/70 hover:shadow-md'} p-4 rounded-2xl space-y-3 transition">
                <!-- Header: Robot Info & Status Selector -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-xl ${hasIssue ? 'bg-rose-500 text-white animate-bounce' : 'bg-[#3b4cb8] text-white'} flex items-center justify-center text-xs font-bold shadow-sm shrink-0">
                            <i class="fa-solid ${hasIssue ? 'fa-triangle-exclamation' : 'fa-robot'}"></i>
                        </div>
                        <div class="truncate">
                            <span class="font-black text-gray-800 text-xs block leading-tight truncate">${robot.name}</span>
                            <span class="text-[10px] text-gray-500">Lt ${robot.floor || 1} &bull; (${Math.round(robot.current_x || 0)}%, ${Math.round(robot.current_y || 0)}%)</span>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5">
                        <select onchange="changeRobotStatus(${robot.id}, this.value)" title="Ubah Status Robot" class="text-[10px] font-bold py-1 px-2 rounded-lg border shadow-xs transition cursor-pointer ${
                            hasIssue ? 'bg-rose-100 text-rose-700 border-rose-300' :
                            robot.status === 'Charging' ? 'bg-amber-100 text-amber-700 border-amber-300' :
                            robot.status === 'Delivering' ? 'bg-blue-100 text-blue-700 border-blue-300' :
                            'bg-emerald-100 text-emerald-700 border-emerald-300'
                        }">
                            <option value="Idle" ${robot.status === 'Idle' && !hasIssue ? 'selected' : ''}>Idle</option>
                            <option value="Delivering" ${robot.status === 'Delivering' ? 'selected' : ''}>Delivering</option>
                            <option value="Charging" ${robot.status === 'Charging' ? 'selected' : ''}>Charging</option>
                            <option value="Maintenance" ${robot.status === 'Maintenance' || hasIssue ? 'selected' : ''}>Maintenance</option>
                        </select>
                    </div>
                </div>

                <!-- Battery Bar & Edit Battery -->
                <div class="bg-white p-2.5 rounded-xl border border-gray-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-gray-500 font-semibold flex items-center gap-1.5">
                            <i class="fa-solid fa-battery-half ${batLevel <= 20 ? 'text-rose-500 animate-pulse' : 'text-emerald-600'}"></i>
                            Baterai
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="${batTextCol} font-mono font-bold">${batLevel}%</span>
                            <button type="button" onclick="editRobotBattery(${robot.id}, ${batLevel})" class="text-[10px] bg-gray-100 hover:bg-[#3b4cb8] hover:text-white text-gray-700 px-2 py-0.5 rounded-md border border-gray-300 font-semibold transition active:scale-95" title="Edit Persentase Baterai">
                                <i class="fa-solid fa-pen-to-square mr-0.5"></i> Edit
                            </button>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                        <div class="${batColor} h-full transition-all duration-300 rounded-full" style="width: ${batLevel}%"></div>
                    </div>
                </div>

                <!-- Active / Pending Mission Info -->
                ${delivery ? `
                    <div class="text-[11px] bg-white p-2.5 rounded-xl border border-gray-200 font-medium space-y-0.5">
                        <span class="text-gray-400 block text-[9px] uppercase font-bold tracking-wider">Tugas Misi:</span>
                        <div class="text-gray-800 flex items-center justify-between gap-1">
                            <span class="truncate"><i class="fa-solid fa-box text-blue-500 mr-1"></i> ${delivery.item_name} ke <strong>${delivery.destination_location}</strong></span>
                            <span class="font-bold text-[10px] px-1.5 py-0.5 rounded ${delivery.status === 'Pending' ? 'bg-rose-100 text-rose-700 animate-pulse' : 'bg-blue-100 text-blue-700'}">[${delivery.status}]</span>
                        </div>
                    </div>
                ` : ''}

                <!-- Repair & Status Actions -->
                ${hasIssue ? `
                    <div class="space-y-1.5 pt-1">
                        <button type="button" onclick="fixRobotUnit(${robot.id}, 'resume')" class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black py-2.5 px-3 rounded-xl text-xs shadow-md flex items-center justify-center gap-2 transition duration-150">
                            <i class="fa-solid fa-wrench"></i> Benerin Robot (Fix &amp; Lanjut Tugas)
                        </button>
                        <button type="button" onclick="fixRobotUnit(${robot.id}, 'idle')" class="w-full bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-black py-2 px-3 rounded-xl text-xs shadow-sm flex items-center justify-center gap-2 transition duration-150">
                            <i class="fa-solid fa-power-off"></i> Ubah Jadi Idle (Batalkan Tugas)
                        </button>
                    </div>
                ` : `
                    <div class="pt-1">
                        <button type="button" onclick="fixRobotUnit(${robot.id}, 'idle')" class="w-full text-center text-xs font-semibold py-1.5 rounded-lg text-gray-600 hover:text-indigo-600 hover:bg-white border border-transparent hover:border-gray-200 transition">
                            <i class="fa-solid fa-arrows-rotate text-gray-400 mr-1"></i> Setel Status: Idle
                        </button>
                    </div>
                `}

                <!-- Simulation Tools (Tabrak Dinding & Batre Habis) -->
                <div class="flex items-center justify-between pt-2 border-t border-gray-200/80 text-[10px]">
                    <span class="text-gray-400 font-semibold"><i class="fa-solid fa-flask text-indigo-400 mr-1"></i>Simulasi:</span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="simulateUnitIssue(${robot.id}, 'Collision')" class="text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 transition active:scale-95">
                            <i class="fa-solid fa-burst mr-0.5"></i> Tabrak Dinding
                        </button>
                        <button type="button" onclick="simulateUnitIssue(${robot.id}, 'Low Battery')" class="text-amber-600 hover:text-amber-800 font-bold bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded border border-amber-200 transition active:scale-95">
                            <i class="fa-solid fa-battery-empty mr-0.5"></i> Batre Habis
                        </button>
                    </div>
                </div>
            </div>
            `;
        });

        container.innerHTML = html;
    }

    async function editRobotBattery(robotId, currentLevel) {
        if (window.Swal) {
            const { value: newBat } = await Swal.fire({
                title: 'Edit Persentase Baterai',
                input: 'number',
                inputLabel: 'Masukkan level baterai baru (0 - 100%):',
                inputValue: currentLevel,
                inputAttributes: {
                    min: 0,
                    max: 100,
                    step: 1
                },
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3b4cb8',
                inputValidator: (value) => {
                    if (value === '' || isNaN(value) || value < 0 || value > 100) {
                        return 'Harap masukkan angka baterai yang valid antara 0 dan 100!';
                    }
                }
            });

            if (newBat !== undefined && newBat !== null) {
                saveRobotBatteryToServer(robotId, parseInt(newBat, 10));
            }
        } else {
            const promptVal = prompt('Masukkan level baterai baru (0 - 100%):', currentLevel);
            if (promptVal !== null && promptVal !== '') {
                const num = parseInt(promptVal, 10);
                if (!isNaN(num) && num >= 0 && num <= 100) {
                    saveRobotBatteryToServer(robotId, num);
                }
            }
        }
    }

    function saveRobotBatteryToServer(robotId, batteryLevel) {
        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ battery_level: batteryLevel })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (window.showToast) window.showToast(`Baterai robot diperbarui ke ${batteryLevel}%!`, 'success');
                fetchFleetTelemetry();
            } else {
                if (window.showErrorAlert) window.showErrorAlert('Gagal Memperbarui Baterai', data.message || 'Terjadi kesalahan.');
            }
        })
        .catch(err => {
            console.error('Error updating battery:', err);
            if (window.showErrorAlert) window.showErrorAlert('Kesalahan Jaringan', 'Gagal menghubungi server.');
        });
    }

    function changeRobotStatus(robotId, newStatus) {
        if (newStatus === 'Idle') {
            fixRobotUnit(robotId, 'idle');
            return;
        }
        if (newStatus === 'Maintenance') {
            simulateUnitIssue(robotId, 'Maintenance');
            return;
        }

        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (window.showToast) window.showToast(`Status robot diubah ke ${newStatus}!`, 'success');
                fetchFleetTelemetry();
            } else {
                if (window.showErrorAlert) window.showErrorAlert('Gagal Mengubah Status', data.message || 'Terjadi kesalahan.');
                fetchFleetTelemetry();
            }
        })
        .catch(err => {
            console.error('Error changing status:', err);
            if (window.showErrorAlert) window.showErrorAlert('Kesalahan Jaringan', 'Gagal menghubungi server.');
        });
    }

    function fixRobotUnit(robotId, action = 'resume') {
        const robot = fleetRobots.find(r => Number(r.id) === Number(robotId));
        const delivery = fleetDeliveries.find(d => Number(d.robot_id) === Number(robotId) && (d.status === 'In Progress' || d.status === 'Pending'));
        let pausedElapsed = null;
        if (delivery && delivery.started_at) {
            const started = new Date(delivery.started_at.replace(' ', 'T')).getTime();
            pausedElapsed = Math.max(0, Date.now() - started);
        }

        fetch(`/api/robots/${robotId}/fix`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: action,
                paused_elapsed_ms: pausedElapsed
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchFleetTelemetry();
                if (window.showToast) window.showToast(`Robot berhasil ${action === 'charge' ? 'dicas' : 'diperbaiki'}!`, 'success');
            } else {
                if (window.showErrorAlert) window.showErrorAlert('Gagal Memproses Robot', data.message || 'Terjadi kesalahan sistem.');
            }
        })
        .catch(err => {
            console.error('Error fixing robot:', err);
            if (window.showErrorAlert) window.showErrorAlert('Kesalahan Jaringan', 'Terjadi masalah saat menghubungi server.');
        });
    }

    function simulateUnitIssue(robotId, issueType) {
        const robot = fleetRobots.find(r => Number(r.id) === Number(robotId));
        const delivery = fleetDeliveries.find(d => Number(d.robot_id) === Number(robotId) && (d.status === 'In Progress' || d.status === 'Pending'));
        let pausedElapsed = null;
        if (delivery && delivery.started_at) {
            const started = new Date(delivery.started_at.replace(' ', 'T')).getTime();
            pausedElapsed = Math.max(0, Date.now() - started);
        }

        fetch(`/api/robots/${robotId}/simulate-issue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                issue_type: issueType,
                current_x: robot ? robot.current_x : null,
                current_y: robot ? robot.current_y : null,
                floor: robot ? robot.floor : 1,
                paused_elapsed_ms: pausedElapsed
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchFleetTelemetry();
                if (window.showToast) window.showToast(`Simulasi masalah (${issueType}) aktif!`, 'warning');
            }
        })
        .catch(err => console.error('Error simulating issue:', err));
    }

    async function resetSystem() {
        if (window.showConfirmDialog) {
            const confirmed = await window.showConfirmDialog({
                title: 'Reset Armada Robot ke Markas?',
                text: 'Semua unit robot akan segera dikembalikan ke Base Station (1_N7), misi pengantaran aktif dibatalkan, dan status robot disetel ke standby.',
                confirmText: '<i class="fa-solid fa-rotate-left mr-1.5"></i> Ya, Reset Armada',
                cancelText: 'Batal',
                icon: 'warning',
                isDanger: true
            });
            if (!confirmed) return;
        } else {
            if (!confirm('Reset semua robot unit ke base station?')) return;
        }

        if (window.RobopathSwal) {
            RobopathSwal.fire({
                title: 'Mereset Armada Robot...',
                html: '<p class="text-xs text-gray-500 mt-1">Mengembalikan seluruh unit robot ke markas dan membersihkan penugasan...</p>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    if (window.Swal) Swal.showLoading();
                }
            });
        }

        try {
            const res = await fetch('/api/system/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                if (window.showSuccessAlert) {
                    await window.showSuccessAlert(
                        'Armada Berhasil Direset!',
                        'Seluruh robot telah diposisikan kembali di Base Station N7 dan siap menerima instruksi baru.'
                    );
                }
                fetchFleetTelemetry();
            } else {
                if (window.showErrorAlert) window.showErrorAlert('Gagal Mereset Armada', data.message || 'Terjadi kendala saat mereset sistem.');
            }
        } catch (err) {
            console.error('Error resetting fleet:', err);
            if (window.showErrorAlert) window.showErrorAlert('Kesalahan Jaringan', 'Gagal menghubungi server untuk mereset armada.');
        }
    }

    window.addEventListener('load', () => {
        syncRobotVisibilityUI();
        syncTransitVisibilityUI();
        setLabelScale(labelScaleMultiplier);
        setRobotScale(robotScaleMultiplier, false);
        updateRobotFloorUI();
        updateRobotElevationUI();
        setEditTargetMode('robot');
        switchFloor(1);

        // Polling telemetri armada untuk IT Repair Center
        fetchFleetTelemetry();
        setInterval(fetchFleetTelemetry, 2500);
    });
    window.addEventListener('resize', () => {
        renderEditorMap();
    });
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const addModal = document.getElementById('modal-add-node-3d');
            if (addModal && !addModal.classList.contains('hidden')) {
                closeAddNodeModal();
                return;
            }
            const delModal = document.getElementById('modal-delete-node-3d');
            if (delModal && !delModal.classList.contains('hidden')) {
                closeDeleteNodeModal();
                return;
            }
            if (isFullMap) {
                toggleFullMap(false);
            }
        }
    });
</script>
@endsection
