@extends('layouts.layout')

@section('title', 'ROBOPATH - Delivery Dispatch & Live Tracking')
@section('page_title', 'Deliveries Management')
@section('page_subtitle', 'Dispatch tasks, monitor active deliveries, and trace active units')

@section('styles')
<style>
    .map-container {
        position: relative;
        background-image: url('{{ asset("images/map.png") }}');
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center;
        aspect-ratio: 16/9;
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
                        <option value="" disabled selected>Choose starting location...</option>
                        @foreach($locations as $name => $coords)
                        <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Destination Location -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Destination Room</label>
                    <select id="dispatch-dest" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:border-sky-500 transition" required>
                        <option value="" disabled selected>Choose destination...</option>
                        <!-- Render locations dynamically -->
                        @foreach($locations as $name => $coords)
                        <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
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
                    <h3 class="text-base font-bold text-gray-800">Live Active Tracking</h3>
                    <p class="text-xs text-gray-500">Smooth navigation and real-time path visualization</p>
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
    // Locations configuration (Matching path.png)
    const locations = {
        'N0': { x: 10.68, y: 46.28 },
        'N1': { x: 10.68, y: 46.28 },
        'N2': { x: 12.79, y: 46.28 },
        'N4': { x: 12.79, y: 46.28 },
        'N7': { x: 14.98, y: 64.5 },
        'N10': { x: 14.98, y: 61.7 },
        'N11': { x: 15.29, y: 46.28 },
        'N12': { x: 15.23, y: 48.66 },
        'N13': { x: 15.16, y: 50.39 },
        'N14': { x: 15.1, y: 52.14 },
        'N15': { x: 15.05, y: 53.89 },
        'N16': { x: 15.01, y: 55.64 },
        'N17': { x: 14.99, y: 57.39 },
        'N18': { x: 15.0, y: 59.14 },
        'Kantin (Tempat Makan)': { x: 15.0, y: 61.7 },
        'N20': { x: 15.29, y: 46.28 },
        'N21': { x: 15.3, y: 43.09 },
        'Kantin (Display Makanan)': { x: 15.36, y: 34.68 },
        'N27': { x: 15.33, y: 36.69 },
        'N28': { x: 15.32, y: 38.44 },
        'N29': { x: 15.31, y: 40.18 },
        'N30': { x: 15.32, y: 43.09 },
        'N31': { x: 15.32, y: 43.09 },
        'N34': { x: 18.38, y: 43.09 },
        'N36': { x: 18.38, y: 43.09 },
        'N39': { x: 18.38, y: 43.09 },
        'N41': { x: 21.0, y: 54.22 },
        'N42': { x: 20.99, y: 54.22 },
        'N43': { x: 21.02, y: 43.06 },
        'Kantin (Masak Makanan)': { x: 20.99, y: 51.29 },
        'N45': { x: 21.02, y: 45.34 },
        'N46': { x: 21.0, y: 47.08 },
        'N47': { x: 21.01, y: 48.83 },
        'N48': { x: 21.01, y: 51.29 },
        'N51': { x: 21.02, y: 43.05 },
        'N52': { x: 24.16, y: 43.05 },
        'N54': { x: 24.16, y: 43.03 },
        'N56': { x: 24.16, y: 43.03 },
        'N59': { x: 26.7, y: 43.03 },
        'Blank Room 1': { x: 26.7, y: 34.68 },
        'N62': { x: 26.7, y: 36.69 },
        'N63': { x: 26.7, y: 38.44 },
        'N64': { x: 26.7, y: 40.18 },
        'N65': { x: 26.7, y: 43.03 },
        'Kamar Mandi 1': { x: 26.7, y: 29.45 },
        'N69': { x: 26.7, y: 43.03 },
        'N71': { x: 29.12, y: 43.03 },
        'N72': { x: 29.23, y: 29.45 },
        'N74': { x: 29.12, y: 43.03 },
        'N75': { x: 29.23, y: 29.45 },
        'N76': { x: 31.3, y: 29.45 },
        'N77': { x: 31.3, y: 43.03 },
        'N78': { x: 31.3, y: 32.84 },
        'N79': { x: 31.3, y: 35.03 },
        'N80': { x: 31.3, y: 36.78 },
        'N81': { x: 31.3, y: 38.53 },
        'N82': { x: 31.3, y: 40.28 },
        'N83': { x: 31.3, y: 43.03 },
        'N84': { x: 31.3, y: 32.84 },
        'N86': { x: 31.3, y: 43.03 },
        'N87': { x: 31.3, y: 32.84 },
        'N89': { x: 33.32, y: 43.03 },
        'N90': { x: 34.44, y: 32.84 },
        'N93': { x: 34.44, y: 32.86 },
        'Resepsionis': { x: 34.85, y: 43.03 },
        'N97': { x: 34.44, y: 32.86 },
        'N100': { x: 36.92, y: 32.86 },
        'Kamar Mandi 2': { x: 36.9, y: 28.56 },
        'N102': { x: 36.92, y: 30.56 },
        'N103': { x: 37.02, y: 32.86 },
        'N105': { x: 37.3, y: 43.03 },
        'N106': { x: 37.36, y: 45.34 },
        'N107': { x: 37.37, y: 47.08 },
        'Pintu Masuk': { x: 37.38, y: 51.32 },
        'Pintu Keluar': { x: 37.38, y: 51.32 },
        'N110': { x: 37.38, y: 48.91 },
        'N112': { x: 37.38, y: 48.91 },
        'N114': { x: 37.09, y: 32.86 },
        'N115': { x: 37.3, y: 43.03 },
        'N116': { x: 37.22, y: 35.13 },
        'N117': { x: 37.25, y: 37.7 },
        'N118': { x: 37.27, y: 40.21 },
        'N119': { x: 37.28, y: 43.03 },
        'N120': { x: 37.28, y: 37.7 },
        'N121': { x: 39.97, y: 48.91 },
        'N122': { x: 39.97, y: 48.91 },
        'N123': { x: 41.0, y: 37.72 },
        'N124': { x: 39.97, y: 48.91 },
        'N125': { x: 41.0, y: 37.72 },
        'N126': { x: 42.66, y: 29.23 },
        'N128': { x: 41.0, y: 37.72 },
        'N129': { x: 42.53, y: 48.91 },
        'N130': { x: 42.66, y: 29.23 },
        'N131': { x: 42.7, y: 37.72 },
        'Blank Room 2': { x: 42.67, y: 31.57 },
        'N133': { x: 42.69, y: 33.58 },
        'N134': { x: 42.7, y: 35.33 },
        'N136': { x: 42.67, y: 29.23 },
        'N137': { x: 42.7, y: 37.72 },
        'Meeting Room 1': { x: 42.53, y: 48.91 },
        'N140': { x: 44.45, y: 29.23 },
        'N141': { x: 45.77, y: 37.72 },
        'N142': { x: 45.53, y: 48.91 },
        'N144': { x: 45.77, y: 37.72 },
        'N146': { x: 45.53, y: 48.91 },
        'N147': { x: 45.77, y: 37.72 },
        'N148': { x: 45.53, y: 48.91 },
        'N149': { x: 48.66, y: 37.72 },
        'N150': { x: 48.7, y: 48.91 },
        'N152': { x: 48.66, y: 37.72 },
        'N153': { x: 48.7, y: 48.91 },
        'N154': { x: 48.67, y: 39.89 },
        'N155': { x: 48.69, y: 41.64 },
        'N156': { x: 48.7, y: 43.39 },
        'N157': { x: 48.7, y: 45.14 },
        'N158': { x: 48.7, y: 46.89 },
        'N159': { x: 48.7, y: 51.2 },
        'N160': { x: 48.7, y: 51.2 },
        'N162': { x: 51.78, y: 51.22 },
        'N163': { x: 51.78, y: 58.03 },
        'N164': { x: 51.78, y: 58.03 },
        'N165': { x: 51.78, y: 51.24 },
        'N166': { x: 51.78, y: 53.4 },
        'N167': { x: 51.78, y: 55.15 },
        'Meeting Room 2': { x: 51.78, y: 58.03 },
        'N170': { x: 53.52, y: 51.26 },
        'N171': { x: 55.41, y: 58.03 },
        'N172': { x: 55.41, y: 51.28 },
        'N173': { x: 55.41, y: 53.4 },
        'N174': { x: 55.41, y: 55.15 },
        'Meeting Room 3': { x: 55.41, y: 58.03 },
        'N176': { x: 55.41, y: 51.28 },
        'N180': { x: 57.54, y: 51.28 },
        'Ruang Kerja Utama': { x: 59.18, y: 51.28 },
        'N188': { x: 61.75, y: 51.28 },
        'N189': { x: 61.75, y: 53.4 },
        'N190': { x: 61.75, y: 55.15 },
        'Meeting Room 4': { x: 61.75, y: 58.03 },
        'N194': { x: 61.75, y: 51.28 },
        'N196': { x: 64.81, y: 51.28 },
        'N198': { x: 64.81, y: 51.28 },
        'N200': { x: 64.81, y: 51.28 },
        'N202': { x: 67.91, y: 51.28 },
        'N203': { x: 67.91, y: 51.28 },
        'N204': { x: 67.91, y: 58.03 },
        'N205': { x: 67.91, y: 53.4 },
        'N206': { x: 67.91, y: 55.15 },
        'Meeting Room 5': { x: 67.91, y: 58.03 },
        'N208': { x: 67.91, y: 51.28 },
        'N210': { x: 71.22, y: 51.28 },
        'N212': { x: 71.22, y: 51.28 },
        'N214': { x: 71.22, y: 51.28 },
        'N216': { x: 73.66, y: 51.28 },
        'Meeting Room 6': { x: 73.59, y: 30.16 },
        'N218': { x: 73.59, y: 32.22 },
        'N219': { x: 73.59, y: 37.12 },
        'N220': { x: 73.59, y: 38.88 },
        'N221': { x: 73.62, y: 41.47 },
        'N222': { x: 73.63, y: 41.47 },
        'N223': { x: 73.64, y: 44.12 },
        'N224': { x: 73.65, y: 45.87 },
        'N225': { x: 73.65, y: 47.61 },
        'N226': { x: 73.66, y: 49.44 },
        'N227': { x: 73.59, y: 34.56 },
        'N228': { x: 73.68, y: 52.86 },
        'N229': { x: 73.68, y: 55.22 },
        'N230': { x: 73.59, y: 34.56 },
        'N232': { x: 73.68, y: 55.22 },
        'N234': { x: 76.12, y: 55.22 },
        'N235': { x: 76.15, y: 34.47 },
        'N236': { x: 76.12, y: 55.22 },
        'N237': { x: 76.15, y: 37.12 },
        'N238': { x: 76.12, y: 38.88 },
        'N239': { x: 76.12, y: 41.47 },
        'N240': { x: 76.12, y: 44.12 },
        'N241': { x: 76.12, y: 45.87 },
        'N242': { x: 76.12, y: 47.61 },
        'N243': { x: 76.12, y: 49.44 },
        'N244': { x: 76.12, y: 50.97 },
        'N245': { x: 76.12, y: 52.86 },
        'Ruang Manajer': { x: 76.12, y: 41.47 },
        'N248': { x: 76.12, y: 55.22 },
        'N250': { x: 76.15, y: 34.43 },
        'N252': { x: 78.88, y: 55.22 },
        'N254': { x: 78.78, y: 34.39 },
        'N255': { x: 78.88, y: 55.22 },
        'N257': { x: 78.78, y: 34.32 },
        'N258': { x: 78.88, y: 55.22 },
        'N259': { x: 81.09, y: 34.29 },
        'N261': { x: 81.88, y: 55.22 },
        'N262': { x: 81.09, y: 34.28 },
        'N263': { x: 81.09, y: 34.28 },
        'N265': { x: 81.88, y: 55.22 },
        'N266': { x: 81.09, y: 34.15 },
        'N268': { x: 81.88, y: 55.22 },
        'Ruang Direktur': { x: 83.94, y: 27.82 },
        'N271': { x: 83.94, y: 29.88 },
        'N272': { x: 83.94, y: 31.63 },
        'N273': { x: 83.94, y: 34.03 },
        'N274': { x: 84.84, y: 55.22 },
        'N275': { x: 84.84, y: 55.22 },
        'N277': { x: 83.94, y: 33.98 },
        'N278': { x: 84.84, y: 55.22 },
        'N280': { x: 86.46, y: 33.98 },
        'N282': { x: 87.81, y: 55.22 },
        'N283': { x: 86.46, y: 33.98 },
        'N285': { x: 87.81, y: 55.22 },
        'Tangga dan Lift': { x: 88.47, y: 33.98 },
        'N289': { x: 87.81, y: 55.2 },
        'N290': { x: 90.56, y: 55.2 },
        'N292': { x: 90.56, y: 33.98 },
        'N293': { x: 90.56, y: 55.2 },
        'N294': { x: 90.56, y: 55.2 },
        'N295': { x: 90.56, y: 35.71 },
        'Kamar Mandi 3': { x: 90.56, y: 37.41 },
        'Kamar Mandi 4': { x: 90.56, y: 48.97 },
        'N298': { x: 90.56, y: 50.97 },
        'N299': { x: 90.56, y: 52.72 },
        'N310': { x: 39.14, y: 37.7 },
        'N311': { x: 69.56, y: 51.28 },
        'N312': { x: 72.44, y: 51.28 },
        'N313': { x: 63.28, y: 51.28 },
        'N314': { x: 66.36, y: 51.28 },
        'N315': { x: 50.24, y: 51.2 },
    };

    // Graph adjacency list for BFS pathfinding
    const adj = {
        'N0': ['N1', 'N2'],
        'N1': ['N0', 'N2', 'N4'],
        'N2': ['N0', 'N1', 'N4', 'N11'],
        'N4': ['N1', 'N2', 'N11', 'N20'],
        'N7': ['N10', 'Kantin (Tempat Makan)'],
        'N10': ['N7', 'N18', 'Kantin (Tempat Makan)'],
        'N11': ['N2', 'N4', 'N12', 'N13', 'N20', 'N21', 'N31'],
        'N12': ['N11', 'N13', 'N14', 'N20'],
        'N13': ['N11', 'N12', 'N14', 'N15'],
        'N14': ['N12', 'N13', 'N15', 'N16'],
        'N15': ['N13', 'N14', 'N16', 'N17'],
        'N16': ['N14', 'N15', 'N17', 'N18'],
        'N17': ['N15', 'N16', 'N18', 'Kantin (Tempat Makan)'],
        'N18': ['N10', 'N16', 'N17', 'Kantin (Tempat Makan)'],
        'Kantin (Tempat Makan)': ['N7', 'N10', 'N17', 'N18'],
        'N20': ['N4', 'N11', 'N12', 'N21', 'N30', 'N31'],
        'N21': ['N11', 'N20', 'N29', 'N30', 'N31'],
        'Kantin (Display Makanan)': ['N27', 'N28'],
        'N27': ['Kantin (Display Makanan)', 'N28', 'N29'],
        'N28': ['Kantin (Display Makanan)', 'N27', 'N29', 'N30'],
        'N29': ['N21', 'N27', 'N28', 'N30', 'N31'],
        'N30': ['N20', 'N21', 'N28', 'N29', 'N31', 'N34', 'N36'],
        'N31': ['N11', 'N20', 'N21', 'N29', 'N30', 'N34', 'N36'],
        'N34': ['N30', 'N31', 'N36', 'N39'],
        'N36': ['N30', 'N31', 'N34', 'N39', 'N43'],
        'N39': ['N34', 'N36', 'N43'],
        'N41': ['N42', 'Kantin (Masak Makanan)', 'N48'],
        'N42': ['N41', 'Kantin (Masak Makanan)', 'N48'],
        'N43': ['N36', 'N39', 'N45', 'N46', 'N51', 'N52'],
        'Kantin (Masak Makanan)': ['N41', 'N42', 'N47', 'N48'],
        'N45': ['N43', 'N46', 'N47', 'N51'],
        'N46': ['N43', 'N45', 'N47', 'N48'],
        'N47': ['Kantin (Masak Makanan)', 'N45', 'N46', 'N48'],
        'N48': ['N41', 'N42', 'Kantin (Masak Makanan)', 'N46', 'N47'],
        'N51': ['N43', 'N45', 'N52', 'N54'],
        'N52': ['N43', 'N51', 'N54', 'N56'],
        'N54': ['N51', 'N52', 'N56', 'N59', 'N65'],
        'N56': ['N52', 'N54', 'N59', 'N65'],
        'N59': ['N54', 'N56', 'N64', 'N65', 'N69', 'N71'],
        'Blank Room 1': ['N62', 'N63', 'Kamar Mandi 1'],
        'N62': ['Blank Room 1', 'N63', 'N64', 'Kamar Mandi 1'],
        'N63': ['Blank Room 1', 'N62', 'N64', 'N65'],
        'N64': ['N59', 'N62', 'N63', 'N65', 'N69'],
        'N65': ['N54', 'N56', 'N59', 'N63', 'N64', 'N69', 'N71'],
        'Kamar Mandi 1': ['N72', 'N75', 'N76', 'Blank Room 1', 'N62'],
        'N69': ['N59', 'N64', 'N65', 'N71', 'N74'],
        'N71': ['N59', 'N65', 'N69', 'N74', 'N77', 'N83'],
        'N72': ['Kamar Mandi 1', 'N75', 'N76'],
        'N74': ['N69', 'N71', 'N77', 'N83'],
        'N75': ['Kamar Mandi 1', 'N72', 'N76'],
        'N76': ['N72', 'N75', 'N78', 'N84', 'Kamar Mandi 1'],
        'N77': ['N71', 'N74', 'N82', 'N83', 'N86', 'N89', 'Resepsionis', 'N105'],
        'N78': ['N76', 'N79', 'N84', 'N87'],
        'N79': ['N78', 'N80', 'N81', 'N84', 'N87'],
        'N80': ['N79', 'N81', 'N82', 'N84'],
        'N81': ['N79', 'N80', 'N82', 'N83'],
        'N82': ['N77', 'N80', 'N81', 'N83', 'N86'],
        'N83': ['N71', 'N74', 'N77', 'N81', 'N82', 'N86', 'N89', 'Resepsionis', 'N105'],
        'N84': ['N76', 'N78', 'N79', 'N80', 'N87', 'N90'],
        'N86': ['N77', 'N82', 'N83', 'N89', 'Resepsionis', 'N105'],
        'N87': ['N78', 'N79', 'N84', 'N90', 'N93'],
        'N89': ['N77', 'N83', 'N86', 'Resepsionis', 'N105'],
        'N90': ['N84', 'N87', 'N93', 'N97'],
        'N93': ['N87', 'N90', 'N97', 'N100', 'N103'],
        'N97': ['N90', 'N93', 'N100', 'N103'],
        'N100': ['N93', 'N97', 'Kamar Mandi 2', 'N102', 'N103', 'N114'],
        'Kamar Mandi 2': ['N100', 'N102'],
        'N102': ['N100', 'Kamar Mandi 2', 'N103', 'N114'],
        'N103': ['N93', 'N97', 'N100', 'N102', 'N114', 'N116'],
        'N105': ['Resepsionis', 'N106', 'N107', 'N115', 'N118', 'N119', 'N89', 'N77', 'N86', 'N83'],
        'N106': ['N105', 'N107', 'N110', 'N112', 'N115'],
        'N107': ['N105', 'N106', 'Pintu Keluar', 'N110', 'N112'],
        'Pintu Masuk': ['Pintu Keluar', 'N110', 'N112'],
        'N110': ['N106', 'N107', 'Pintu Masuk', 'Pintu Keluar', 'N112', 'N121'],
        'N112': ['N106', 'N107', 'Pintu Masuk', 'Pintu Keluar', 'N110', 'N121', 'N122'],
        'N114': ['N100', 'N102', 'N103', 'N116', 'N117'],
        'N115': ['N105', 'N106', 'N118', 'N119'],
        'N116': ['N103', 'N114', 'N117', 'N120'],
        'N117': ['N114', 'N116', 'N118', 'N120', 'N123'],
        'N118': ['N105', 'N115', 'N117', 'N119', 'N120'],
        'N119': ['N105', 'N115', 'N118', 'N120'],
        'N120': ['N116', 'N117', 'N118', 'N119', 'N310'],
        'N121': ['N110', 'N112', 'N122', 'N124'],
        'N122': ['N112', 'N121', 'N124'],
        'N123': ['N117', 'N125', 'N128', 'N310'],
        'N124': ['N121', 'N122'],
        'N125': ['N123', 'N128', 'N131', 'N137'],
        'N126': ['N130', 'Blank Room 2', 'N136'],
        'N128': ['N123', 'N125', 'N131', 'N137'],
        'N129': ['Meeting Room 1'],
        'N130': ['N126', 'Blank Room 2', 'N136', 'N140'],
        'N131': ['N125', 'N128', 'N133', 'N134', 'N137', 'N141'],
        'Blank Room 2': ['N126', 'N130', 'N133', 'N134', 'N136'],
        'N133': ['N131', 'Blank Room 2', 'N134'],
        'N134': ['N131', 'Blank Room 2', 'N133', 'N137'],
        'N136': ['N126', 'N130', 'Blank Room 2', 'N140'],
        'N137': ['N125', 'N128', 'N131', 'N134', 'N141', 'N144'],
        'Meeting Room 1': ['N129'],
        'N140': ['N130', 'N136'],
        'N141': ['N131', 'N137', 'N144', 'N147'],
        'N142': ['N146', 'N148'],
        'N144': ['N137', 'N141', 'N147', 'N149'],
        'N146': ['N142', 'N148'],
        'N147': ['N141', 'N144', 'N149', 'N152'],
        'N148': ['N142', 'N146'],
        'N149': ['N144', 'N147', 'N152', 'N154'],
        'N150': ['N153', 'N157', 'N158', 'N159', 'N160'],
        'N152': ['N147', 'N149', 'N154', 'N155'],
        'N153': ['N150', 'N157', 'N158', 'N159', 'N160'],
        'N154': ['N149', 'N152', 'N155', 'N156'],
        'N155': ['N152', 'N154', 'N156', 'N157'],
        'N156': ['N154', 'N155', 'N157', 'N158'],
        'N157': ['N150', 'N153', 'N155', 'N156', 'N158'],
        'N158': ['N150', 'N153', 'N156', 'N157', 'N159'],
        'N159': ['N150', 'N153', 'N158', 'N160', 'N162'],
        'N160': ['N150', 'N153', 'N159', 'N162', 'N315'],
        'N162': ['N159', 'N160', 'N165', 'N166', 'N315'],
        'N163': ['N164', 'N167', 'Meeting Room 2'],
        'N164': ['N163', 'N167', 'Meeting Room 2'],
        'N165': ['N162', 'N166', 'N167', 'N170'],
        'N166': ['N162', 'N165', 'N167', 'Meeting Room 2'],
        'N167': ['N163', 'N164', 'N165', 'N166', 'Meeting Room 2'],
        'Meeting Room 2': ['N163', 'N164', 'N166', 'N167'],
        'N170': ['N165', 'N172'],
        'N171': ['N174', 'Meeting Room 3'],
        'N172': ['N170', 'N173', 'N174', 'N176', 'N180', 'Ruang Kerja Utama', 'N188', 'N194', 'N196'],
        'N173': ['N172', 'N174', 'Meeting Room 3', 'N176'],
        'N174': ['N171', 'N172', 'N173', 'Meeting Room 3'],
        'Meeting Room 3': ['N171', 'N173', 'N174'],
        'N176': ['N172', 'N173', 'N180', 'Ruang Kerja Utama', 'N188', 'N194', 'N196'],
        'N180': ['N172', 'N176', 'Ruang Kerja Utama', 'N188', 'N194', 'N196'],
        'Ruang Kerja Utama': ['N180', 'N188', 'N176', 'N172', 'N194', 'N196'],
        'N188': ['Ruang Kerja Utama', 'N189', 'N190', 'N194', 'N196', 'N176', 'N180', 'N172', 'N313'],
        'N189': ['N188', 'N190', 'Meeting Room 4', 'N194'],
        'N190': ['N188', 'N189', 'Meeting Room 4'],
        'Meeting Room 4': ['N189', 'N190'],
        'N194': ['N188', 'N189', 'N196', 'N198', 'Ruang Kerja Utama', 'N176', 'N180', 'N172'],
        'N196': ['N188', 'N194', 'N198', 'N200', 'Ruang Kerja Utama', 'N176', 'N180', 'N172'],
        'N198': ['N194', 'N196', 'N200', 'N202'],
        'N200': ['N196', 'N198', 'N202', 'N203', 'N313', 'N314'],
        'N202': ['N198', 'N200', 'N203', 'N205', 'N311', 'N314'],
        'N203': ['N200', 'N202', 'N205', 'N206', 'N208', 'N210'],
        'N204': ['N206', 'Meeting Room 5'],
        'N205': ['N202', 'N203', 'N206', 'Meeting Room 5', 'N208'],
        'N206': ['N203', 'N204', 'N205', 'Meeting Room 5'],
        'Meeting Room 5': ['N204', 'N205', 'N206'],
        'N208': ['N203', 'N205', 'N210', 'N212'],
        'N210': ['N203', 'N208', 'N212', 'N214'],
        'N212': ['N208', 'N210', 'N214', 'N216'],
        'N214': ['N210', 'N212', 'N216', 'N311', 'N312'],
        'N216': ['N212', 'N214', 'N225', 'N226', 'N228', 'N229', 'N312'],
        'Meeting Room 6': ['N218', 'N227'],
        'N218': ['Meeting Room 6', 'N227', 'N230'],
        'N219': ['N220', 'N221', 'N227', 'N230'],
        'N220': ['N219', 'N221', 'N222', 'N230'],
        'N221': ['N219', 'N220', 'N222', 'N223'],
        'N222': ['N220', 'N221', 'N223', 'N224'],
        'N223': ['N221', 'N222', 'N224', 'N225'],
        'N224': ['N222', 'N223', 'N225', 'N226'],
        'N225': ['N216', 'N223', 'N224', 'N226'],
        'N226': ['N216', 'N224', 'N225', 'N228'],
        'N227': ['Meeting Room 6', 'N218', 'N219', 'N230'],
        'N228': ['N216', 'N226', 'N229', 'N232'],
        'N229': ['N216', 'N228', 'N232'],
        'N230': ['N218', 'N219', 'N220', 'N227', 'N250'],
        'N232': ['N228', 'N229', 'N248'],
        'N234': ['N236', 'N245', 'N248', 'N252'],
        'N235': ['N237', 'N238', 'N250', 'N254'],
        'N236': ['N234', 'N244', 'N245', 'N248', 'N252'],
        'N237': ['N235', 'N238', 'N239', 'N250'],
        'N238': ['N235', 'N237', 'N239', 'Ruang Manajer', 'N240', 'N241'],
        'N239': ['N237', 'N238', 'N240', 'Ruang Manajer', 'N241'],
        'N240': ['N239', 'N241', 'N242', 'Ruang Manajer', 'N238'],
        'N241': ['N240', 'N242', 'N243', 'Ruang Manajer', 'N238', 'N239'],
        'N242': ['N240', 'N241', 'N243', 'N244'],
        'N243': ['N241', 'N242', 'N244', 'N245'],
        'N244': ['N236', 'N242', 'N243', 'N245'],
        'N245': ['N234', 'N236', 'N243', 'N244', 'N248'],
        'Ruang Manajer': ['N238', 'N239', 'N240', 'N241'],
        'N248': ['N232', 'N234', 'N236', 'N245', 'N252', 'N255'],
        'N250': ['N235', 'N237', 'N254', 'N257', 'N230'],
        'N252': ['N234', 'N236', 'N248', 'N255', 'N258'],
        'N254': ['N235', 'N250', 'N257', 'N259'],
        'N255': ['N248', 'N252', 'N258', 'N261'],
        'N257': ['N250', 'N254', 'N259', 'N262', 'N263'],
        'N258': ['N252', 'N255', 'N261', 'N265'],
        'N259': ['N254', 'N257', 'N262', 'N263', 'N266'],
        'N261': ['N255', 'N258', 'N265', 'N268'],
        'N262': ['N257', 'N259', 'N263', 'N266'],
        'N263': ['N257', 'N259', 'N262', 'N266'],
        'N265': ['N258', 'N261', 'N268', 'N274'],
        'N266': ['N259', 'N262', 'N263', 'N273'],
        'N268': ['N261', 'N265', 'N274', 'N275'],
        'Ruang Direktur': ['N271', 'N272'],
        'N271': ['Ruang Direktur', 'N272', 'N273', 'N277'],
        'N272': ['Ruang Direktur', 'N271', 'N273', 'N277'],
        'N273': ['N266', 'N271', 'N272', 'N277', 'N280'],
        'N274': ['N265', 'N268', 'N275', 'N278'],
        'N275': ['N268', 'N274', 'N278', 'N282'],
        'N277': ['N271', 'N272', 'N273', 'N280', 'N283', 'Tangga dan Lift', 'N292'],
        'N278': ['N274', 'N275', 'N282', 'N285'],
        'N280': ['N273', 'N277', 'N283', 'Tangga dan Lift', 'N292'],
        'N282': ['N275', 'N278', 'N285', 'N289'],
        'N283': ['N277', 'N280', 'Tangga dan Lift', 'N292'],
        'N285': ['N278', 'N282', 'N289', 'N290'],
        'Tangga dan Lift': ['N280', 'N283', 'N292', 'N277'],
        'N289': ['N282', 'N285', 'N290', 'N293', 'N294'],
        'N290': ['N285', 'N289', 'N293', 'N294', 'N299'],
        'N292': ['Tangga dan Lift', 'N295', 'Kamar Mandi 3', 'N283', 'N277', 'N280'],
        'N293': ['N289', 'N290', 'N294', 'N298', 'N299'],
        'N294': ['N289', 'N290', 'N293', 'N299'],
        'N295': ['N292', 'Kamar Mandi 3'],
        'Kamar Mandi 3': ['N292', 'N295'],
        'Kamar Mandi 4': ['N298', 'N299'],
        'N298': ['N293', 'Kamar Mandi 4', 'N299'],
        'N299': ['N290', 'N293', 'N294', 'Kamar Mandi 4', 'N298'],
        'Resepsionis': ['N89', 'N105', 'N77', 'N86', 'N83'],
        'N310': ['N120', 'N123'],
        'Pintu Keluar': ['N107', 'Pintu Masuk', 'N110', 'N112'],
        'N311': ['N214', 'N202'],
        'N312': ['N214', 'N216'],
        'N313': ['N200', 'N188'],
        'N314': ['N200', 'N202'],
        'N315': ['N160', 'N162'],
    };

    let robots = @json($robots);
    let activeDeliveries = [];
    let activeAlerts = [];
    let serverClientOffset = 0;
    
    let simulationInterval = null;
    let syncInterval = null;
    let autopilotEnabled = true;

    // BFS Pathfinding Algorithm
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
                    if (neighbor === end) {
                        return newPath;
                    }
                    queue.push(newPath);
                }
            }
        }
        return [];
    }

    // Resolves location name by percentage coordinates
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

    // Form change event: update starting location
    function updateStartLocation() {
        // Now a manual select dropdown, do not overwrite user choice
    }

    // Send API request to start a delivery
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

        // Get robot's current position to determine origin_location
        const select = document.getElementById('dispatch-robot');
        const selectedOpt = select.options[select.selectedIndex];
        const rx = parseFloat(selectedOpt.getAttribute('data-x'));
        const ry = parseFloat(selectedOpt.getAttribute('data-y'));
        const origin = resolveLocationName(rx, ry);

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
                // Prevent race condition: update local state IMMEDIATELY
                const bot = robots.find(r => Number(r.id) === Number(robotId));
                if (bot) bot.status = 'Delivering';
                
                // Clear form
                document.getElementById('dispatch-form').reset();
                
                // Fetch data immediately
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

    // Renders pins
    function drawLocationPins() {
        const overlay = document.getElementById('locations-overlay');
        overlay.innerHTML = '';
        
        for (let name in locations) {
            const loc = locations[name];
            if (loc.isHall) continue;
            
            const pin = document.createElement('div');
            pin.className = 'location-pin group z-20';
            pin.style.left = `${loc.x}%`;
            pin.style.top = `${loc.y}%`;
            
            pin.innerHTML = `
                <div class="w-3 h-3 rounded-full bg-cyan-600 border border-gray-200 transition group-hover:bg-brand-blue group-hover:scale-125 shadow"></div>
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-gray-50/50 /95 text-gray-800 border border-gray-200 text-[10px] font-bold px-2 py-0.5 rounded shadow opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                    ${name}
                </div>
            `;
            overlay.appendChild(pin);
        }
    }

    // Draw lines
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

    function drawRobotPaths() {
        const svg = document.getElementById('path-svg');
        svg.innerHTML = '';
        
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id));
            if (!robot || robot.status !== 'Delivering') return;
            
            const path = getDeliveryPath(delivery, robot);
            
            if (path.length < 2) return;
            
            let pointsStr = '';
            path.forEach((nodeName, idx) => {
                const node = locations[nodeName];
                if (!node) return;
                
                const container = document.getElementById('map-container');
                const w = container.clientWidth;
                const h = container.clientHeight;
                
                const px = (node.x / 100) * w;
                const py = (node.y / 100) * h;
                
                pointsStr += `${px},${py} `;
            });
            
            const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            polyline.setAttribute('points', pointsStr.trim());
            polyline.setAttribute('stroke', '#38bdf8');
            polyline.setAttribute('stroke-width', '2');
            polyline.setAttribute('stroke-dasharray', '5,5');
            polyline.setAttribute('fill', 'none');
            polyline.setAttribute('opacity', '0.6');
            svg.appendChild(polyline);
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

    // Move robots
    function runSimulationStep() {
        const now = new Date(new Date().getTime() + serverClientOffset);
        drawRobotPaths();
        
        const overlay = document.getElementById('robots-overlay');
        overlay.innerHTML = '';
        
        robots.forEach(robot => {
            const delivery = activeDeliveries.find(d => Number(d.robot_id) === Number(robot.id) && d.status === 'In Progress');
            let coords = { x: robot.current_x, y: robot.current_y };
            let statusColor = 'bg-emerald-500 ';
            
            if (robot.status === 'Charging') {
                statusColor = 'bg-orange-500 shadow-sm';
            } else if (robot.status === 'Maintenance') {
                statusColor = 'bg-rose-500 ';
            }
            
            if (robot.status === 'Delivering' && delivery) {
                statusColor = 'bg-sky-500 ';
                
                const path = getDeliveryPath(delivery, robot);
                const destLoc = delivery.destination_location;
                
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
                            const dx = p2.x - p1.x;
                            const dy = p2.y - p1.y;
                            if (dx !== 0 || dy !== 0) {
                                angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                            }
                        } else {
                            coords = p1 || p2 || locations['Blank Room 2'];
                        }
                        
                        robot.returnPath = null;
                        robot.returnStartedAt = null;
                        
                        } else {
                        coords = locations[path[path.length - 1]];
                        if (path.length >= 2) {
                            const p1 = locations[path[path.length - 2]];
                            const p2 = locations[path[path.length - 1]];
                            if (p1 && p2) {
                                const dx = p2.x - p1.x;
                                const dy = p2.y - p1.y;
                                if (dx !== 0 || dy !== 0) {
                                    angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                }
                            }
                        }
                        if (elapsedMs >= totalDurationMs) {
                            completeDeliveryAPI(delivery.id, coords.x, coords.y);
                        }
                    }
                    
                    robot.current_x = coords.x;
                    robot.current_y = coords.y;
                    robot.rotation = angle;
                }
            } else if (robot.status === 'Idle') {
                const baseLoc = locations['Blank Room 2'];
                const distToBase = Math.hypot(robot.current_x - baseLoc.x, robot.current_y - baseLoc.y);
                
                if (distToBase > 0.5) {
                    let currentLoc = resolveLocationName(robot.current_x, robot.current_y);
                    
                    if (!robot.returnPath) {
                        robot.returnPath = findShortestPath(currentLoc, 'Blank Room 2');
                        robot.returnStartedAt = now.getTime();
                        robot.returnDuration = 30000;
                    }
                    
                    const elapsedMs = Math.max(0, now.getTime() - robot.returnStartedAt);
                    const ratio = Math.max(0.0, Math.min(elapsedMs / robot.returnDuration, 1.0));
                    const path = robot.returnPath;
                    
                    if (path.length >= 2) {
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
                                const dx = p2.x - p1.x;
                                const dy = p2.y - p1.y;
                                if (dx !== 0 || dy !== 0) {
                                    angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
                                }
                            } else {
                                coords = p1 || p2 || locations['Blank Room 2'];
                            }
                        } else {
                            coords = baseLoc;
                            robot.returnPath = null;
                            robot.returnStartedAt = null;
                        }
                        
                        robot.current_x = coords.x;
                        robot.current_y = coords.y;
                        robot.rotation = angle;
                    }
                } else {
                    coords = baseLoc;
                    robot.returnPath = null;
                    robot.returnStartedAt = null;
                }
            } else {
                resolveLocationName(robot.current_x, robot.current_y);
            }
            
            const marker = document.createElement('div');
            marker.className = 'robot-marker z-30';
            marker.style.left = `${coords.x}%`;
            marker.style.top = `${coords.y}%`;
            
            marker.innerHTML = `
                <div class="relative flex items-center justify-center">
                    <span class="animate-ping absolute inline-flex h-10 w-10 rounded-full ${statusColor.split(' ')[0]} opacity-40"></span>
                    <div class="relative w-8 h-8 rounded-xl bg-gray-50/50  border-gray-300 border-blue-700 flex items-center justify-center shadow-lg transition duration-200 hover:scale-110" style="transform: rotate(${robot.rotation || 0}deg);">
                        <i class="fa-solid fa-robot text-xs ${robot.status === 'Delivering' ? 'text-brand-blue' : (robot.status === 'Charging' ? 'text-orange-400' : (robot.status === 'Maintenance' ? 'text-red-500' : 'text-green-600'))}"></i>
                    </div>
                    <div class="absolute -top-6 bg-gray-50/50 /90 text-gray-700 border border-gray-200 text-[8px] font-bold px-1.5 py-0.5 rounded shadow whitespace-nowrap pointer-events-none">
                        ${robot.name.split(' ')[1]} (${robot.battery_level}%)
                    </div>
                </div>
            `;
            overlay.appendChild(marker);
        });
        
        runAutopilotManager();
    }

    function completeDeliveryAPI(deliveryId, finalX, finalY) {
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
                current_y: finalY
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update local robot status IMMEDIATELY so syncTelemetry doesn't revert it
                const robot = robots.find(r => r.id === delivery.robot_id);
                if (robot && data.robot) {
                    robot.status = data.robot.status;
                }
                fetchData();
                reloadPageDropdowns();
            }
        })
        .catch(err => {
            console.error('Error completing delivery:', err);
            delivery.isCompleting = false;
        });
    }

    function reloadPageDropdowns() {
        const select = document.getElementById('dispatch-robot');
        const currentValue = select.value;
        
        select.innerHTML = '<option value="" disabled>Choose a robot...</option>';
        
        robots.forEach(robot => {
            const isBusy = robot.status !== 'Idle' || robot.battery_level <= 20;
            const option = document.createElement('option');
            option.value = robot.id;
            option.textContent = `${robot.name} (${robot.status} - Bat: ${robot.battery_level}%) ${isBusy ? (robot.status !== 'Idle' ? '[Busy]' : '[Low Battery]') : ''}`;
            option.setAttribute('data-status', robot.status);
            option.setAttribute('data-battery', robot.battery_level);
            option.setAttribute('data-x', robot.current_x);
            option.setAttribute('data-y', robot.current_y);
            if (isBusy) option.disabled = true;
            
            if (robot.id.toString() === currentValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        
        updateStartLocation();
    }

    function loadAutopilotState() {
        const stored = localStorage.getItem('autopilot_enabled');
        autopilotEnabled = stored !== null ? stored === 'true' : true;
    }

    function runAutopilotManager() {
        loadAutopilotState();
        if (!autopilotEnabled) return;
        
        const idleRobots = robots.filter(r => r.status === 'Idle' && r.battery_level > 20);
        
        idleRobots.forEach(robot => {
            if (robot.isDispatching) return;
            robot.isDispatching = true;
            
            setTimeout(() => {
                const stored = localStorage.getItem('autopilot_enabled');
                const curAutopilot = stored !== null ? stored === 'true' : true;
                if (!curAutopilot || robot.status !== 'Idle') {
                    robot.isDispatching = false;
                    return;
                }
                
                const items = ['Handuk', 'Makanan', 'Dokumen', 'Kopi', 'Paket', 'Botol Air', 'Sparepart'];
                const rooms = [
                    'Kantin (Display Makanan)', 'Kantin (Masak Makanan)', 'Kantin (Tempat Makan)',
                    'Kamar Mandi 1', 'Blank Room 1', 'Kamar Mandi 2', 'Blank Room 2',
                    'Resepsionis', 'Pintu Masuk', 'Meeting Room 1', 'Meeting Room 2',
                    'Meeting Room 3', 'Meeting Room 4', 'Meeting Room 5', 'Ruang Kerja Utama',
                    'Meeting Room 6', 'Ruang Direktur', 'Tangga dan Lift', 'Kamar Mandi 3',
                    'Kamar Mandi 4', 'Ruang Manajer'
                ];
                
                const item = items[Math.floor(Math.random() * items.length)];
                
                // Find current location of the robot using the robust resolver
                let currentLoc = resolveLocationName(robot.current_x, robot.current_y);
                
                let startLoc = rooms[Math.floor(Math.random() * rooms.length)];
                while (startLoc === currentLoc) {
                    startLoc = rooms[Math.floor(Math.random() * rooms.length)];
                }
                
                let dest = rooms[Math.floor(Math.random() * rooms.length)];
                while (dest === startLoc || dest === currentLoc) {
                    dest = rooms[Math.floor(Math.random() * rooms.length)];
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
                        start_location: startLoc,
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
            
            // Merge robots data keeping local animation properties
            data.robots.forEach(newRobot => {
                const existing = robots.find(r => Number(r.id) === Number(newRobot.id));
                if (existing) {
                    if (existing.status !== newRobot.status) {
                        existing.status = newRobot.status;
                        existing.current_x = newRobot.current_x;
                        existing.current_y = newRobot.current_y;
                        existing.returnPath = null;
                        existing.returnStartedAt = null;
                    }
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
            
            const startLoc = delivery.start_location;
            const destLoc = delivery.destination_location;
            const path = getDeliveryPath(delivery, robot);
            
            const totalDurationMs = 30000;
            const startedTime = parseServerDate(delivery.started_at);
            const now = new Date(new Date().getTime() + serverClientOffset);
            const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
            const ratio = Math.min(elapsedMs / totalDurationMs, 1.0);
            const pct = Math.round(ratio * 100);

            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-200/50 hover:bg-gray-50/50 /20 text-xs';
            tr.innerHTML = `
                <td class="py-3.5 font-bold text-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue"></span>
                        ${robot.name}
                    </div>
                </td>
                <td class="text-gray-500 font-semibold">${delivery.item_name}</td>
                <td class="text-gray-500 font-semibold">${startLoc}</td>
                <td class="text-gray-700 font-semibold">${destLoc}</td>
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




