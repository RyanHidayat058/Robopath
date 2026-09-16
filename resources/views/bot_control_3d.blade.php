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
        width: 24rem !important;
        max-height: calc(100vh - 5.5rem) !important;
        overflow-y: auto !important;
        background: rgba(255, 255, 255, 0.96) !important;
        backdrop-filter: blur(16px) !important;
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35) !important;
        border-radius: 1.25rem !important;
    }
</style>
@endsection

@section('content')
<div class="space-y-8">

    <!-- Top Control Bar: Floor Selection, Editor Tools, Show/Hide Hidden Dots, & Save Button -->
    <div class="bg-white border border-gray-200 p-4 rounded-2xl shadow-xl flex flex-wrap items-center gap-x-6 gap-y-3">
        <!-- FLOOR -->
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Floor</span>
            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-xl border border-gray-200">
            <button onclick="switchFloor(1)" id="tab-floor-1" class="px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white">
                <i class="fa-solid fa-layer-group mr-1.5"></i> Lantai 1 (Ground Floor)
            </button>
            <button onclick="switchFloor(2)" id="tab-floor-2" class="px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200">
                <i class="fa-solid fa-layer-group mr-1.5"></i> Lantai 2 (Second Floor)
            </button>
            </div>
        </div>

        <!-- EDIT -->
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Edit</span>
            <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                <button onclick="setEditorTool('hand')" id="tool-hand" class="px-3 py-2 rounded-lg text-gray-600 hover:text-gray-900 flex items-center gap-1.5 transition" title="Free Hand (Pan): Geser kanvas bebas tanpa menyentuh node">
                    <i class="fa-solid fa-hand"></i> Free Hand
                </button>
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
            </div>
        </div>

        <!-- VIEW -->
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">View</span>
            <!-- Toggle Robot Avatar (Default: Sembunyi saat pengeditan node) -->
            <button type="button" onclick="toggleShowRobots()" id="btn-toggle-robots" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-500 font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Sembunyikan / Tampilkan Avatar Robot 3D">
                <i class="fa-solid fa-robot text-gray-400" id="icon-toggle-robots"></i> <span id="text-toggle-robots">Robot: Sembunyi</span>
            </button>
            <!-- Label Size Controller (Perkecil/Perbesar Nama Ruangan) -->
            <div class="flex items-center gap-1 bg-gray-100 border border-gray-300 px-2.5 py-1.5 rounded-xl text-xs font-bold" title="Sesuaikan Ukuran Teks Nama Ruangan">
                <span class="text-gray-500 flex items-center gap-1 text-[11px]"><i class="fa-solid fa-font text-[#3b4cb8]"></i> Label:</span>
                <button type="button" onclick="adjustLabelScale(-0.1)" class="w-6 h-6 rounded-md bg-white hover:bg-gray-200 border border-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold transition active:scale-95" title="Perkecil Ukuran Label Teks">
                    <i class="fa-solid fa-minus text-[10px]"></i>
                </button>
                <span class="label-scale-val font-mono font-bold text-[#3b4cb8] w-9 text-center text-xs">0.8x</span>
                <button type="button" onclick="adjustLabelScale(0.1)" class="w-6 h-6 rounded-md bg-white hover:bg-gray-200 border border-gray-300 text-gray-700 flex items-center justify-center text-xs font-bold transition active:scale-95" title="Perbesar Ukuran Label Teks">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                </button>
            </div>
            <!-- Show/Hide Transit Dots Toggle (Default: Tampil titik biru terang tanpa nama) -->
            <button onclick="toggleShowHiddenDots()" id="btn-toggle-hidden" class="bg-blue-50 hover:bg-blue-100 border border-blue-300 text-[#3b4cb8] font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Tampilkan / Sembunyikan Titik Transit Tanpa Nama">
                <i class="fa-solid fa-eye text-[#3b4cb8]" id="icon-toggle-hidden"></i> <span id="text-toggle-hidden">Transit: Tampil</span>
            </button>
            <!-- Full Map 3D Mode Toggle -->
            <button onclick="toggleFullMap(true)" id="btn-open-fullmap" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-[#3b4cb8] font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 transition shadow-sm" title="Buka Denah 3D Layar Penuh">
                <i class="fa-solid fa-expand"></i> <span>Full Map 3D</span>
            </button>
        </div>

        <!-- ACTION -->
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Action</span>
            <button onclick="saveGraphToServer()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-md hover:shadow-lg transition">
                <i class="fa-solid fa-floppy-disk"></i> Save Graph Map
            </button>
        </div>
    </div>

    <!-- Main Workspace: Interactive Map Canvas (Left) & Inspector (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Interactive Map Canvas (2/3 Width) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl transition-all" id="editor-map-card">
                <!-- Dedicated Top Bar for Full Map Mode -->
                <div id="fullmap-top-bar" class="hidden flex flex-wrap items-center justify-between gap-3 pb-3 mb-2 border-b border-slate-700/60 select-none">
                    <!-- Left: Floor Switcher -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs">
                            <button type="button" onclick="switchFloor(1)" id="fullmap-tab-floor-1" class="px-3.5 py-1.5 rounded-lg font-bold transition bg-[#3b4cb8] text-white">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 1
                            </button>
                            <button type="button" onclick="switchFloor(2)" id="fullmap-tab-floor-2" class="px-3.5 py-1.5 rounded-lg font-bold transition text-gray-400 hover:bg-white/10">
                                <i class="fa-solid fa-layer-group mr-1"></i> Lantai 2
                            </button>
                        </div>
                    </div>

                    <!-- Center: Editor Tools (Free Hand, Move, Add, Connect, Delete) -->
                    <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-white/10 text-xs font-bold">
                        <button type="button" onclick="setEditorTool('hand')" id="fullmap-tool-hand" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Free Hand (Pan): Geser kanvas bebas tanpa menyentuh node">
                            <i class="fa-solid fa-hand"></i> <span>Free Hand</span>
                        </button>
                        <button type="button" onclick="setEditorTool('move')" id="fullmap-tool-move" class="px-3 py-1.5 rounded-lg bg-white shadow text-[#3b4cb8] flex items-center gap-1.5 transition" title="Move: Geser posisi node/robot">
                            <i class="fa-solid fa-up-down-left-right"></i> <span>Move</span>
                        </button>
                        <button type="button" onclick="setEditorTool('add')" id="fullmap-tool-add" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Add: Tambah node ruangan baru">
                            <i class="fa-solid fa-plus-circle"></i> <span>Add</span>
                        </button>
                        <button type="button" onclick="setEditorTool('connect')" id="fullmap-tool-connect" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Connect: Hubungkan jalur node">
                            <i class="fa-solid fa-diagram-project"></i> <span>Connect</span>
                        </button>
                        <button type="button" onclick="setEditorTool('delete')" id="fullmap-tool-delete" class="px-3 py-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center gap-1.5 transition" title="Delete: Hapus node">
                            <i class="fa-solid fa-trash-can"></i> <span>Delete</span>
                        </button>
                    </div>

                    <!-- Right: Robots toggle, Label scale, Transit toggle, Edit Manual XYZ button, Save, Exit Full Map -->
                    <div class="flex items-center gap-2">
                        <!-- Toggle Robot Avatar in Full Map -->
                        <button type="button" onclick="toggleShowRobots()" id="fullmap-btn-toggle-robots" class="bg-slate-900/90 hover:bg-slate-800 border border-white/10 text-gray-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Sembunyikan / Tampilkan Avatar Robot 3D">
                            <i class="fa-solid fa-robot text-gray-400" id="fullmap-icon-toggle-robots"></i> <span id="fullmap-text-toggle-robots">Robot: Sembunyi</span>
                        </button>

                        <!-- Label Size Controller in Full Map -->
                        <div class="flex items-center gap-1 bg-slate-900/90 border border-white/10 px-2 py-1 rounded-xl text-xs font-bold" title="Sesuaikan Ukuran Teks Nama Ruangan">
                            <span class="text-gray-400 flex items-center gap-1 text-[11px]"><i class="fa-solid fa-font text-indigo-400"></i> Label:</span>
                            <button type="button" onclick="adjustLabelScale(-0.1)" class="w-5 h-5 rounded bg-white/10 hover:bg-white/20 text-gray-200 flex items-center justify-center text-xs font-bold transition active:scale-95" title="Perkecil Ukuran Label Teks">
                                <i class="fa-solid fa-minus text-[9px]"></i>
                            </button>
                            <span class="label-scale-val font-mono font-bold text-sky-400 w-8 text-center text-xs">0.8x</span>
                            <button type="button" onclick="adjustLabelScale(0.1)" class="w-5 h-5 rounded bg-white/10 hover:bg-white/20 text-gray-200 flex items-center justify-center text-xs font-bold transition active:scale-95" title="Perbesar Ukuran Label Teks">
                                <i class="fa-solid fa-plus text-[9px]"></i>
                            </button>
                        </div>

                        <!-- Transit Toggle -->
                        <button type="button" onclick="toggleShowHiddenDots()" id="fullmap-btn-toggle-hidden" class="bg-sky-950/80 hover:bg-sky-900 border border-sky-500/40 text-sky-300 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition" title="Tampilkan / Sembunyikan Titik Transit Tanpa Nama">
                            <i class="fa-solid fa-eye text-sky-400" id="fullmap-icon-toggle-hidden"></i> <span id="fullmap-text-toggle-hidden">Transit: Tampil</span>
                        </button>

                        <!-- Inspector Toggle Button -->
                        <button type="button" onclick="toggleInspectorPanel()" id="fullmap-btn-inspector" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition" title="Tampilkan / Sembunyikan Panel Edit Manual XYZ">
                            <i class="fa-solid fa-sliders"></i> <span>Edit Manual XYZ</span>
                            <span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono hidden">Node</span>
                        </button>

                        <!-- Save Button -->
                        <button type="button" onclick="saveGraphToServer()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition">
                            <i class="fa-solid fa-floppy-disk"></i> <span>Simpan</span>
                        </button>

                        <!-- Exit Full Map Button -->
                        <button type="button" onclick="toggleFullMap(false)" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition" title="Keluar dari Full Map (Esc)">
                            <i class="fa-solid fa-compress"></i> <span>Exit</span>
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
            </div>
        </div>

        <!-- Node Inspector & Configuration Panel (1/3 Width) -->
        <div class="space-y-6">
            <!-- ROBOT CONTROL (Lantai 2 only) -->
            <div id="panel-3d-controls" class="hidden bg-white border border-gray-200 p-5 rounded-2xl shadow-xl">
                <h3 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-robot text-[#3b4cb8]"></i> Robot Control
                </h3>
                <p class="text-xs text-gray-500 mb-4">Pilih robot, lihat status & posisi, lalu kendalikan manual.</p>

                <div class="space-y-4 text-xs">
                    <!-- Robot -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Robot</label>
                        <select id="robot-selector" onchange="setActiveRobot(Number(this.value))" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-bold text-gray-800 focus:outline-none">
                            <option value="">Memuat robot...</option>
                        </select>
                        <div id="robot-active-name" class="font-bold text-gray-800 text-[13px] mt-1.5">-</div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="font-bold text-gray-800">Online</span>
                            <span id="robot-status-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">-</span>
                        </div>
                    </div>

                    <!-- Position -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Position</label>
                        <div id="drive-readout" class="bg-slate-900 text-emerald-300 border border-slate-700 rounded-xl px-3 py-2 font-mono text-[11px]">R- (model 3D belum siap)</div>
                    </div>

                    <!-- Manual Drive -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1.5">Manual Drive</label>
                        <div class="grid grid-cols-3 gap-1 max-w-[132px] mb-2 pointer-events-none select-none" aria-hidden="true">
                            <div></div>
                            <div class="bg-slate-100 border border-slate-300 text-slate-500 font-bold py-1.5 rounded-lg flex items-center justify-center text-[10px]">W</div>
                            <div></div>
                            <div class="bg-slate-100 border border-slate-300 text-slate-500 font-bold py-1.5 rounded-lg flex items-center justify-center text-[10px]">A</div>
                            <div class="bg-slate-100 border border-slate-300 text-slate-500 font-bold py-1.5 rounded-lg flex items-center justify-center text-[10px]">S</div>
                            <div class="bg-slate-100 border border-slate-300 text-slate-500 font-bold py-1.5 rounded-lg flex items-center justify-center text-[10px]">D</div>
                        </div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500">
                            <span><kbd class="bg-gray-100 border border-gray-300 rounded px-1 font-mono">W A S D</kbd> Move</span>
                            <span><kbd class="bg-gray-100 border border-gray-300 rounded px-1 font-mono">Q / E</kbd> Up / Down</span>
                            <span><kbd class="bg-gray-100 border border-gray-300 rounded px-1 font-mono">Shift</kbd> Fast</span>
                        </div>
                    </div>

                    <button onclick="resetActiveRobotPosition()" class="w-full bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 font-bold py-2 rounded-xl transition text-xs">
                        <i class="fa-solid fa-arrows-rotate mr-1"></i> Reset Position
                    </button>

                    <!-- Node / Object Editor (3D) -->
                    <details class="bg-gray-50 border border-gray-200 rounded-xl">
                        <summary class="cursor-pointer px-3.5 py-2.5 font-bold text-gray-500 uppercase tracking-wider select-none">Node / Object Editor</summary>
                        <div class="px-3.5 pb-3.5 space-y-3">
                        <div>
                            <div id="selected-3d-info" class="bg-white border border-gray-200 rounded-xl px-3 py-2 font-mono text-gray-800">Tidak ada object dipilih</div>
                            <p class="text-[10px] text-gray-400 mt-1">Klik object (node/robot) di canvas 3D untuk editor. Klik robot juga memilihnya di Robot Control.</p>
                        </div>

                    <!-- D-Pad 4 arah (editor: geser object terpilih) -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1.5">Geser Object (Editor)</label>
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

                    <!-- Ketinggian / Elevasi Y -->
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1.5">Ketinggian Object (Y Elev)</label>
                        <div class="flex items-center gap-2">
                            <button onclick="move3DObjectY(-0.05)" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-300 text-indigo-700 font-bold py-1.5 px-3 rounded-lg transition" title="Turun -0.05m">
                                <i class="fa-solid fa-arrow-down mr-1"></i> -0.05m
                            </button>
                            <input type="number" step="0.05" id="input-3d-y" oninput="set3DWorldY(this.value)" class="w-24 bg-white border border-gray-300 rounded-lg px-2 py-1.5 font-mono font-bold text-center text-gray-800 focus:border-[#3b4cb8] focus:outline-none" placeholder="0.00">
                            <button onclick="move3DObjectY(0.05)" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-300 text-indigo-700 font-bold py-1.5 px-3 rounded-lg transition" title="Naik +0.05m">
                                <i class="fa-solid fa-arrow-up mr-1"></i> +0.05m
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
                    <button onclick="focusOnActiveSelection()" class="w-full bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[#3b4cb8] font-bold py-2 rounded-xl transition text-xs flex items-center justify-center gap-1.5 mt-2">
                        <i class="fa-solid fa-crosshairs"></i> Zoom Dekat / Pusatkan Kamera ke Sini
                    </button>
                        </div>
                    </details>
                </div>
            </div>
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col transition-all" id="node-inspector-card">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 select-none">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-pen-to-square text-[#3b4cb8]"></i>
                        <h3 class="text-base font-bold text-gray-800">Node Properties Inspector</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-gray-400 font-semibold" id="inspector-mode-tag">SECONDARY</span>
                        <!-- Close / Hide Button (visible when in Full Map or floating) -->
                        <button type="button" onclick="toggleInspectorPanel(false)" id="btn-close-inspector" class="hidden text-gray-400 hover:text-gray-700 w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-sm font-bold transition" title="Tutup / Sembunyikan Panel (Biar Pandangan Luas)">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 flex-1 text-xs text-gray-700">
                    <div>
                        <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Node Name / Room Title <span class="text-gray-400 font-normal lowercase">(e.g. Hall, Lobby)</span></label>
                        <input type="text" id="inspect-node-name" onchange="handleRenameNode(this.value)" placeholder="Click a node to edit name..." class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm font-bold text-gray-800 focus:bg-white focus:border-[#3b4cb8] focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-4 gap-2">
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
                        <div>
                            <label class="block font-bold text-gray-500 uppercase tracking-wider mb-1">Elev (Y)</label>
                            <input type="number" step="0.05" id="inspect-y-elev" oninput="handleElevationChange(this.value)" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 font-mono font-bold text-gray-800 focus:border-[#3b4cb8] focus:outline-none transition" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Quick Elevation Step Buttons -->
                    <div class="flex items-center gap-2 bg-indigo-50/70 p-2 rounded-xl border border-indigo-100">
                        <span class="text-[10px] font-bold text-indigo-700">Quick Elev:</span>
                        <button type="button" onclick="move3DObjectY(-0.05)" class="flex-1 bg-white hover:bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold py-1 px-2 rounded-lg text-xs transition" title="Turun -0.05m">
                            <i class="fa-solid fa-arrow-down mr-1"></i> -0.05m
                        </button>
                        <button type="button" onclick="move3DObjectY(0.05)" class="flex-1 bg-white hover:bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold py-1 px-2 rounded-lg text-xs transition" title="Naik +0.05m">
                            <i class="fa-solid fa-arrow-up mr-1"></i> +0.05m
                        </button>
                        <button type="button" onclick="focusOnActiveSelection()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1 px-2.5 rounded-lg text-xs transition" title="Fokus / Zoom Dekat">
                            <i class="fa-solid fa-crosshairs"></i>
                        </button>
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

                    <!-- Bottom Action Buttons in Inspector -->
                    <div class="pt-2 flex gap-2 border-t border-gray-100">
                        <button type="button" onclick="saveGraphToServer()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl text-xs flex items-center justify-center gap-1.5 shadow transition">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan ke Graph
                        </button>
                        <button type="button" onclick="toggleInspectorPanel(false)" id="btn-close-inspector-bottom" class="hidden bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-3 py-2 rounded-xl text-xs transition">
                            Tutup
                        </button>
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
    let threeBotCtrl = null;
    let threeBotCtrlF1 = null;
    let modelLoadedByFloor = {1:false,2:false};
    function activeBotViewer(){ return Number(currentFloor)===1 ? threeBotCtrlF1 : threeBotCtrl; }
    function allBotViewers(){ return [threeBotCtrl, threeBotCtrlF1].filter(Boolean); }
    function parkCoordsForFloor(f){ return f===1 ? {x:72.1,y:85.71} : {x:72.3,y:66.3}; }
    function viewerOfHolder(holder){ if(!holder) return activeBotViewer(); if(threeBotCtrlF1 && holder.parent && threeBotCtrlF1.robotsGroup && holder.parent===threeBotCtrlF1.robotsGroup) return threeBotCtrlF1; if(threeBotCtrl && holder.parent && threeBotCtrl.robotsGroup && holder.parent===threeBotCtrl.robotsGroup) return threeBotCtrl; return activeBotViewer(); }
    let labelScaleMultiplier = parseFloat(localStorage.getItem('robopath_label_scale') || '{{ $labelScale ?? 0.85 }}');
    let showRobotsOnMap = false; // Default: sembunyikan avatar robot saat pengeditan node
    let settings3D = @json($settings3D ?? []);
    let current3DSettings = {
        camera: { dist: parseFloat(settings3D?.camera?.dist ?? 5.0), fov: parseFloat(settings3D?.camera?.fov ?? 5.0), preset: settings3D?.camera?.preset ?? 'iso' },
        lighting: { ambient: parseFloat(settings3D?.lighting?.ambient ?? 1.4), sun: parseFloat(settings3D?.lighting?.sun ?? 1.8), exposure: parseFloat(settings3D?.lighting?.exposure ?? 1.0), fill: parseFloat(settings3D?.lighting?.fill ?? 0.8) },
        model_scale: parseFloat(settings3D?.model_scale ?? 1.0),
        robot_scale: parseFloat(settings3D?.robot_scale ?? 0.6),
        node_scale: parseFloat(settings3D?.node_scale ?? 0.6),
        node_color: settings3D?.node_color ?? '#ff0000'
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

        const bgFill = isStairs ? 'rgba(217, 119, 6, 0.92)' : (isDest ? 'rgba(15, 23, 42, 0.90)' : 'rgba(30, 41, 59, 0.85)');
        const borderColor = isStairs ? '#fbbf24' : (isDest ? '#ff0000' : '#94a3b8');

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
        robotsGroup = new THREE.Group();
        robotsGroup.visible = showRobotsOnMap;
        scene.add(robotsGroup);
        nodesGroup = new THREE.Group();
        scene.add(nodesGroup);
        edgesGroup = new THREE.Group();
        scene.add(edgesGroup);

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

        // Helper: buat/update mesh robot 3D (avatar robot.glb + name badge)
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

        // Raycaster click action handler (dieksekusi saat pointerup tanpa pergeseran kamera)
        function handle3DClickAction(e) {
            const rect = renderer.domElement.getBoundingClientRect();
            const mouse = new THREE.Vector2(
                ((e.clientX - rect.left) / rect.width) * 2 - 1,
                -((e.clientY - rect.top) / rect.height) * 2 + 1
            );
            raycaster.setFromCamera(mouse, camera);
            // Gabungkan nodes (+ robots bila diizinkan tampil) untuk pick
            const pickTargets = [];
            nodesGroup.children.forEach(m => {
                if (m.isMesh) pickTargets.push(m);
                else if (m.isGroup) m.traverse(c => { if (c.isMesh) pickTargets.push(c); });
            });
            if (showRobotsOnMap) {
                robotsGroup.children.forEach(g => { g.children.forEach(m => { if (m.isMesh) pickTargets.push(m); }); });
            }
            const hits = raycaster.intersectObjects(pickTargets, false);
            if (hits.length > 0) {
                let target = hits[0].object;
                while (target && target.parent && target.parent !== nodesGroup && target.parent !== robotsGroup) {
                    if (target.userData?.type === 'node' || target.userData?.type === 'robot') break;
                    target = target.parent;
                }
                selected3DObject = target;
                updateSelected3DObjectUI();
                // Klik robot di canvas = shortcut pilih robot: sinkronkan selector + activeRobot.
                if (target && target.userData && target.userData.type === 'robot') {
                    setActiveRobot(target.userData.robotId, { selectHolder: false });
                } else if (target && target.userData && target.userData.type === 'node') {
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
                // Klik area kosong lantai
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
                if (locationsData[nodeId].objectName) {
                    delete locationsData[nodeId].objectName;
                }
                delete locationsData[nodeId]._u;
                delete locationsData[nodeId]._v;
                delete locationsData[nodeId]._fy;
                const inpX = document.getElementById('inspect-x');
                const inpY = document.getElementById('inspect-y');
                const inpElev = document.getElementById('inspect-y-elev');
                if (inpX) inpX.value = locationsData[nodeId].x;
                if (inpY) inpY.value = locationsData[nodeId].y;
                if (inpElev) inpElev.value = curY.toFixed(2);
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

            // Tool 'move': jika klik kiri mengenai objek node/robot, kunci OrbitControls untuk geser objek di lantai
            if (e.button === 0 && currentTool === 'move') {
                const rect = renderer.domElement.getBoundingClientRect();
                const mouse = new THREE.Vector2(
                    ((e.clientX - rect.left) / rect.width) * 2 - 1,
                    -((e.clientY - rect.top) / rect.height) * 2 + 1
                );
                raycaster.setFromCamera(mouse, camera);

                const pickTargets = [];
                nodesGroup.children.forEach(m => {
                    if (m.isMesh) pickTargets.push(m);
                    else if (m.isGroup) m.traverse(c => { if (c.isMesh) pickTargets.push(c); });
                });
                if (showRobotsOnMap) {
                    robotsGroup.children.forEach(g => { g.children.forEach(m => { if (m.isMesh) pickTargets.push(m); }); });
                }

                const hits = raycaster.intersectObjects(pickTargets, false);
                if (hits.length > 0) {
                    let target = hits[0].object;
                    while (target && target.parent && target.parent !== nodesGroup && target.parent !== robotsGroup) {
                        if (target.userData?.type === 'node' || target.userData?.type === 'robot') break;
                        target = target.parent;
                    }
                    if (target) {
                        dragged3D = target;
                        controls.enabled = false; // Kunci kamera agar tidak berputar saat menggeser objek!
                        dragOffset.copy(target.position).sub(hits[0].point);
                        dragOffset.y = 0;
                        selected3DObject = target;
                        if (target.userData?.type === 'node') {
                            selectedNodeId = target.userData.nodeId;
                            inspectNode(selectedNodeId);
                        } else if (target.userData?.type === 'robot') {
                            setActiveRobot(target.userData.robotId, { selectHolder: false });
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
                        mesh.position.set(wp.x, 0, wp.z);
                    }
                }
                build3DEdges();

                // Eager-create semua robot mesh (avatar robot.glb) — visible hanya robot yg floor == floorNum viewer ini
                robotsData.forEach(r => {
                    const holder = getOrCreateRobotMesh(r);
                    const wp = worldPosForLoc({ x: r.current_x ?? 80.6, y: r.current_y ?? 68.48 }, _bcSize);
                    holder.position.set(wp.x, 0.02, wp.z);
                    holder.rotation.y = -((r.rotation || 0) * Math.PI / 180);
                    holder.visible = (Number(r.floor) === floorNum);
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
                const maxDim = Math.max(size.x, size.z);
                // Centering via Box3
                try{
                    const bbox = new THREE.Box3().setFromObject(model);
                    if(!bbox.isEmpty()){
                        const c=bbox.getCenter(new THREE.Vector3());
                        _defaultCamTarget.set(c.x, c.y*0.5, c.z);
                    } else _defaultCamTarget.set(0,size.y*0.15,0);
                } catch(e){ _defaultCamTarget.set(0,size.y*0.15,0); }
                const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
                const savedDist = 5 + (savedDistVal / 10) * 115;
                const dir0 = new THREE.Vector3(0, maxDim*0.45, maxDim*0.55).normalize();
                camera.position.copy(_defaultCamTarget.clone().add(dir0.multiplyScalar(savedDist)));
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
            const maxDim = Math.max(_bcSize.x, _bcSize.z);
            const savedDistVal = parseFloat(current3DSettings.camera.dist ?? 5.0);
            const savedDist = 5 + (savedDistVal / 10) * 115;
            const dir0 = new THREE.Vector3(0, maxDim * 0.45, maxDim * 0.55).normalize();
            camera.position.copy(_defaultCamTarget.clone().add(dir0.multiplyScalar(savedDist)));
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
        } else if (activeRobotId != null && robotMeshes.has(activeRobotId)) {
            vw.focusOn(robotMeshes.get(activeRobotId).position, 1.8);
        } else {
            alert('Pilih sebuah node atau robot terlebih dahulu untuk fokus kamera.');
        }
    }

    function reset3DCameraView() {
        const vw = activeBotViewer();
        if (!vw || !vw.resetView) return;
        vw.resetView();
    }

    let currentFloor = 1;
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
        if (inp3DY) inp3DY.value = selected3DObject.position.y.toFixed(2);
        if (inpElev && t === 'node') inpElev.value = selected3DObject.position.y.toFixed(2);
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

    function move3DObjectY(dy) {
        if (!selected3DObject) { alert('Pilih object di canvas 3D dulu.'); return; }
        selected3DObject.position.y = parseFloat((selected3DObject.position.y + dy).toFixed(2));
        const vw = viewerOfHolder(selected3DObject);
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) {
                locationsData[nodeId].y_elev = selected3DObject.position.y;
                const inpElev = document.getElementById('inspect-y-elev');
                if (inpElev) inpElev.value = selected3DObject.position.y.toFixed(2);
                if (vw && vw.build3DEdges) vw.build3DEdges();
            }
        } else if (selected3DObject.userData.type === 'robot') {
            updateDriveReadout();
        }
        updateSelected3DObjectUI();
    }

    function set3DWorldY(val) {
        if (!selected3DObject) return;
        const y = parseFloat(val); if (isNaN(y)) return;
        selected3DObject.position.y = y;
        const vw = viewerOfHolder(selected3DObject);
        if (selected3DObject.userData.type === 'node') {
            const nodeId = selected3DObject.userData.nodeId;
            if (locationsData[nodeId]) {
                locationsData[nodeId].y_elev = y;
                const inpElev = document.getElementById('inspect-y-elev');
                if (inpElev) inpElev.value = y.toFixed(2);
                if (vw && vw.build3DEdges) vw.build3DEdges();
            }
        } else if (selected3DObject.userData.type === 'robot') {
            updateDriveReadout();
        }
    }

    function handleElevationChange(val) {
        if (!selectedNodeId || !locationsData[selectedNodeId]) return;
        const y = parseFloat(val);
        const numY = isNaN(y) ? 0 : y;
        locationsData[selectedNodeId].y_elev = numY;
        const vw = activeBotViewer();
        if (vw && vw.nodeMeshes && vw.nodeMeshes.has(selectedNodeId)) {
            const holder = vw.nodeMeshes.get(selectedNodeId);
            holder.position.y = numY;
            if (vw.build3DEdges) vw.build3DEdges();
        }
        const inpY = document.getElementById('input-3d-y');
        if (inpY) inpY.value = numY.toFixed(2);
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
                    locationsData[nodeId].y_elev = parseFloat(selected3DObject.position.y.toFixed(2));
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
        document.getElementById('tab-floor-1').className = floorNum === 1 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
        document.getElementById('tab-floor-2').className = floorNum === 2 
            ? "px-5 py-2.5 rounded-lg text-xs font-bold transition shadow-sm bg-[#3b4cb8] text-white"
            : "px-5 py-2.5 rounded-lg text-xs font-bold transition text-gray-600 hover:bg-gray-200";
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
                }, 50);
            }
            if (hint3D) hint3D.classList.remove('hidden');
            const panel3D = document.getElementById('panel-3d-controls');
            if (panel3D) panel3D.classList.remove('hidden');
        }
        selectedNodeId = null;
        clearInspector();
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
                mesh.position.set(w.x, 0, w.z);
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

    let isFullMap = false;
    function toggleFullMap(showFull) {
        if (showFull === undefined) isFullMap = !isFullMap;
        else isFullMap = !!showFull;

        const editorCard = document.getElementById('editor-map-card');
        const editorContainer = document.getElementById('editor-map-container');
        const fullmapTopBar = document.getElementById('fullmap-top-bar');
        const editorHeader = document.getElementById('editor-header-bar');
        const inspectorCard = document.getElementById('node-inspector-card');
        const btnFloatingFm = document.getElementById('btn-floating-fullmap');
        const btnOpenFm = document.getElementById('btn-open-fullmap');
        const btnCloseInspBottom = document.getElementById('btn-close-inspector-bottom');

        if (isFullMap) {
            if (editorCard) editorCard.classList.add('botctrl-fullmap-card');
            if (editorContainer) editorContainer.classList.add('botctrl-fullmap-canvas');
            if (fullmapTopBar) fullmapTopBar.classList.remove('hidden');
            if (editorHeader) editorHeader.classList.add('hidden');
            if (btnFloatingFm) {
                btnFloatingFm.innerHTML = '<i class="fa-solid fa-compress"></i>';
                btnFloatingFm.title = 'Kecilkan / Keluar Full Map (Esc)';
            }
            if (btnOpenFm) {
                btnOpenFm.innerHTML = '<i class="fa-solid fa-compress text-[#3b4cb8]"></i> <span>Exit Full Map</span>';
            }
            if (inspectorCard) {
                inspectorCard.classList.add('botctrl-inspector-floating');
                inspectorCard.classList.add('hidden'); // Default closed in full map for wide view
            }
            if (btnCloseInspBottom) btnCloseInspBottom.classList.remove('hidden');
        } else {
            if (editorCard) editorCard.classList.remove('botctrl-fullmap-card');
            if (editorContainer) editorContainer.classList.remove('botctrl-fullmap-canvas');
            if (fullmapTopBar) fullmapTopBar.classList.add('hidden');
            if (editorHeader) editorHeader.classList.remove('hidden');
            if (btnFloatingFm) {
                btnFloatingFm.innerHTML = '<i class="fa-solid fa-expand"></i>';
                btnFloatingFm.title = 'Buka Full Map 3D';
            }
            if (btnOpenFm) {
                btnOpenFm.innerHTML = '<i class="fa-solid fa-expand text-[#3b4cb8]"></i> <span>Full Map 3D</span>';
            }
            if (inspectorCard) {
                inspectorCard.classList.remove('botctrl-inspector-floating');
                inspectorCard.classList.remove('hidden'); // Return to standard 3-column layout
            }
            if (btnCloseInspBottom) btnCloseInspBottom.classList.add('hidden');
        }

        syncFullMapControls();

        // Trigger resize on active viewer so aspect ratio and canvas fill screen instantly
        setTimeout(() => {
            const vw = activeBotViewer();
            if (vw && vw.resize) vw.resize();
        }, 60);
    }

    function toggleInspectorPanel(forceState) {
        const inspectorCard = document.getElementById('node-inspector-card');
        if (!inspectorCard) return;
        if (forceState !== undefined) {
            if (forceState) inspectorCard.classList.remove('hidden');
            else inspectorCard.classList.add('hidden');
        } else {
            inspectorCard.classList.toggle('hidden');
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

        const inspectorCard = document.getElementById('node-inspector-card');
        const btnToggleInsp = document.getElementById('fullmap-btn-inspector');
        if (btnToggleInsp && inspectorCard) {
            const isShown = !inspectorCard.classList.contains('hidden');
            const locName = (selectedNodeId && locationsData[selectedNodeId]) ? (locationsData[selectedNodeId].name || selectedNodeId) : null;
            if (isShown) {
                btnToggleInsp.className = "bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition";
                btnToggleInsp.innerHTML = '<i class="fa-solid fa-eye-slash"></i> <span>Tutup Panel XYZ</span>' + (locName ? `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-black/20 px-1.5 py-0.5 rounded-md font-mono">${locName}</span>` : `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-black/20 px-1.5 py-0.5 rounded-md font-mono hidden"></span>`);
            } else {
                btnToggleInsp.className = "bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shadow transition";
                btnToggleInsp.innerHTML = '<i class="fa-solid fa-sliders"></i> <span>Edit Manual XYZ</span>' + (locName ? `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono">${locName}</span>` : `<span id="fullmap-node-badge" class="ml-1 text-[10px] bg-white/25 px-1.5 py-0.5 rounded-md font-mono hidden"></span>`);
            }
        }
    }

    function handleElevationChange(val) {
        if (!selectedNodeId || !locationsData[selectedNodeId]) return;
        const num = parseFloat(val);
        if (isNaN(num)) return;
        locationsData[selectedNodeId].y_elev = parseFloat(num.toFixed(2));
        locationsData[selectedNodeId]._fy = parseFloat(num.toFixed(2));

        const inpElev = document.getElementById('inspect-y-elev');
        if (inpElev && parseFloat(inpElev.value) !== num) inpElev.value = num.toFixed(2);
        const inp3DY = document.getElementById('input-3d-y');
        if (inp3DY && parseFloat(inp3DY.value) !== num) inp3DY.value = num.toFixed(2);

        const vw = activeBotViewer();
        if (vw && vw.nodeMeshes && vw.nodeMeshes.has(selectedNodeId)) {
            const mesh = vw.nodeMeshes.get(selectedNodeId);
            if (mesh) {
                mesh.position.y = num;
            }
        }
        if (vw && vw.build3DEdges) vw.build3DEdges();
        refreshObjectStatus();
    }

    function focusOnActiveSelection() {
        const vw = activeBotViewer();
        if (!vw || !vw.controls || !vw.camera) return;
        let targetPos = null;
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
        const vw = activeBotViewer();
        if (!vw || !vw.nodeMeshes) return;
        const sz = vw.getModelSize ? vw.getModelSize() : null;
        if (!sz || sz.x <= 0.1) return;

        for (const id in locationsData) {
            const loc = locationsData[id];
            if (Number(loc.floor) !== Number(currentFloor)) {
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
                holder.position.set(wp.x, 0, wp.z);
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
            if (!locationsData[id] || Number(locationsData[id].floor) !== Number(currentFloor)) {
                if (holder && holder.parent) holder.parent.remove(holder);
                vw.nodeMeshes.delete(id);
            }
        });
        if (vw.build3DEdges) vw.build3DEdges();
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
        if (inpElev) inpElev.value = Number(elev).toFixed(2);
        const inp3DY = document.getElementById('input-3d-y');
        if (inp3DY) inp3DY.value = Number(elev).toFixed(2);
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
        syncRobotVisibilityUI();
        syncTransitVisibilityUI();
        setLabelScale(labelScaleMultiplier);
        switchFloor(1);
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
