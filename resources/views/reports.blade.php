@extends('layouts.layout')

@section('title', 'ROBOPATH - Faults & Maintenance Reports')
@section('page_title', 'Incident Reports & Hardware Logs')
@section('page_subtitle', 'Monitor active hardware alerts, report new incidents, and upload issue evidence')

@section('content')
<div class="space-y-8">

    <!-- Top Section: Manual Incident Simulation Form (Full Width Atas) -->
    <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
        <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500"></i> Log Manual Incident
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Manually report an obstacle or hardware issue with evidence attachment (Max 1MB)</p>
            </div>
        </div>

        <form id="incident-form" onsubmit="simulateIncident(event)" enctype="multipart/form-data" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Select Robot -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Select Affected Robot</label>
                    <select id="incident-robot" required class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition">
                        <option value="" disabled selected>Choose a robot...</option>
                        @foreach($robots as $robot)
                        <option value="{{ $robot->id }}">
                            {{ $robot->name }} ({{ $robot->status }} - Bat: {{ $robot->battery_level }}%)
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Select Incident Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Incident / Fault Type</label>
                    <select id="incident-type" required class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition">
                        <option value="Collision">Collision / Physical Obstacle</option>
                        <option value="Sensor Error">Sensor / LiDAR Fault</option>
                        <option value="Low Battery">Critical Low Battery</option>
                    </select>
                </div>

                <!-- Evidence Photo Upload (Max 1MB) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Evidence Photo <span class="text-gray-400 font-normal lowercase">(optional, max 1MB)</span>
                    </label>
                    <input type="file" id="incident-image" accept="image/*" onchange="validateImageSize(this)" class="w-full text-xs text-gray-600 bg-gray-50 border border-gray-300 rounded-xl file:mr-4 file:py-2.5 file:px-4 file:rounded-l-xl file:border-0 file:text-xs file:font-bold file:bg-[#3b4cb8] file:text-white hover:file:bg-blue-800 transition">
                    <p id="image-size-error" class="text-[11px] text-rose-500 font-semibold mt-1 hidden">File size exceeds 1MB! Please choose a smaller image.</p>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Detailed Incident Notes</label>
                <textarea id="incident-desc" rows="2" required placeholder="e.g. Unit collided with storage cabinet in central corridor..." class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition placeholder:text-gray-400"></textarea>
            </div>

            <div id="simulate-error" class="hidden text-xs text-rose-600 font-bold bg-rose-50 border border-rose-200 p-3 rounded-xl"></div>
            <div id="simulate-success" class="hidden text-xs text-emerald-600 font-bold bg-emerald-50 border border-emerald-200 p-3 rounded-xl"></div>

            <div class="flex justify-end">
                <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 px-6 rounded-xl text-sm transition duration-200 shadow-md hover:shadow-lg flex items-center gap-2">
                    <i class="fa-solid fa-bug"></i> Submit Incident Report
                </button>
            </div>
        </form>
    </div>

    <!-- Bottom Section: System Warnings & Alerts Log Table (Full Width Bawah) -->
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
        <!-- Blue Header Controls -->
        <div class="p-6 bg-[#3b4cb8] text-white flex items-center justify-between shadow-sm">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> System Warnings & Alerts Log
                </h3>
                <p class="text-xs text-blue-100/90 font-medium mt-1">Track and resolve current active hardware faults (Total {{ $reports->total() }} records)</p>
            </div>
            <div>
                <button onclick="confirmReset()" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-md transition duration-200">
                    <i class="fa-solid fa-trash-can"></i> Clear All Logs
                </button>
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left text-sm text-gray-700">
                <thead>
                    <tr class="bg-blue-50/70 border-b border-gray-200 text-[#3b4cb8] text-xs font-bold uppercase tracking-wider">
                        <th class="px-6 py-4">Time Logged</th>
                        <th class="px-6 py-4">Robot</th>
                        <th class="px-6 py-4">Issue Type</th>
                        <th class="px-6 py-4">Details</th>
                        <th class="px-6 py-4 text-center">Photo Evidence</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reports as $report)
                    <tr class="hover:bg-blue-50/40 transition duration-150">
                        <td class="px-6 py-4 text-gray-500 text-xs font-mono whitespace-nowrap">
                            {{ $report->created_at->format('d M, H:i') }}
                        </td>
                        <td class="px-6 py-4 font-bold text-gray-800">
                            {{ $report->robot->name }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold flex items-center gap-1.5 {{ $report->issue_type === 'Collision' || $report->issue_type === 'Sensor Error' ? 'text-rose-600' : 'text-amber-600' }}">
                                <i class="fa-solid @if($report->issue_type === 'Collision') fa-burst @elseif($report->issue_type === 'Low Battery') fa-battery-empty @else fa-microchip-exclamation @endif"></i>
                                {{ $report->issue_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-xs max-w-[200px] truncate" title="{{ $report->description }}">
                            {{ $report->description }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($report->image_path)
                            <button onclick="previewImage('{{ asset($report->image_path) }}')" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-[#3b4cb8] font-bold text-xs px-2.5 py-1 rounded-lg border border-blue-200 transition">
                                <i class="fa-solid fa-image"></i> View
                            </button>
                            @else
                            <span class="text-gray-400 text-xs font-semibold">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-[9px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider {{ $report->status === 'Active' ? 'bg-rose-100 text-rose-700 border border-rose-200 animate-pulse' : 'bg-gray-100 text-gray-500' }}">
                                {{ $report->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($report->status === 'Active')
                            <button onclick="resolveIncident({{ $report->id }})" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow transition duration-150">
                                <i class="fa-solid fa-check mr-1"></i> Fix Unit
                            </button>
                            @else
                            <span class="text-gray-400 text-xs font-semibold"><i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Cleared</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-gray-400 text-xs">
                            No warnings logged. All units operating within ideal boundaries.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Custom Blue-White Pagination -->
        <div class="p-4 border-t border-gray-200 bg-gray-50/60 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-gray-500 font-medium">
                Showing <span class="font-bold text-gray-700">{{ $reports->firstItem() ?? 0 }}</span> to <span class="font-bold text-gray-700">{{ $reports->lastItem() ?? 0 }}</span> of <span class="font-bold text-gray-700">{{ $reports->total() }}</span> records
            </div>
            <div>
                {{ $reports->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Image Modal Preview -->
<div id="image-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-4 max-w-lg w-full shadow-2xl relative">
        <button onclick="closeImageModal()" class="absolute top-3 right-3 bg-gray-100 hover:bg-gray-200 text-gray-700 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h4 class="font-bold text-sm text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-image text-[#3b4cb8]"></i> Evidence Photo
        </h4>
        <img id="modal-img-element" src="" alt="Evidence Preview" class="w-full h-auto max-h-[70vh] object-contain rounded-xl border border-gray-200">
    </div>
</div>
@endsection

@section('scripts')
<script>
    function validateImageSize(input) {
        const err = document.getElementById('image-size-error');
        if (input.files && input.files[0]) {
            const sizeMb = input.files[0].size / (1024 * 1024);
            if (sizeMb > 1.0) {
                err.classList.remove('hidden');
                input.value = '';
            } else {
                err.classList.add('hidden');
            }
        }
    }

    function previewImage(url) {
        document.getElementById('modal-img-element').src = url;
        document.getElementById('image-modal').classList.remove('hidden');
    }

    function closeImageModal() {
        document.getElementById('image-modal').classList.add('hidden');
    }

    function simulateIncident(e) {
        e.preventDefault();
        
        const robotId = document.getElementById('incident-robot').value;
        const type = document.getElementById('incident-type').value;
        const desc = document.getElementById('incident-desc').value;
        const imageFile = document.getElementById('incident-image').files[0];
        const errDiv = document.getElementById('simulate-error');
        const succDiv = document.getElementById('simulate-success');
        
        errDiv.classList.add('hidden');
        succDiv.classList.add('hidden');

        const formData = new FormData();
        formData.append('robot_id', robotId);
        formData.append('issue_type', type);
        formData.append('description', desc);
        if (imageFile) {
            formData.append('image', imageFile);
        }

        fetch('/api/reports', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                succDiv.textContent = `Incident triggered successfully! ${data.robot.name} status updated to ${data.robot.status}.`;
                succDiv.classList.remove('hidden');
                document.getElementById('incident-form').reset();
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                errDiv.textContent = data.message || 'Failed to trigger incident.';
                errDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error simulating incident:', err);
            errDiv.textContent = 'A network error occurred. Please try again.';
            errDiv.classList.remove('hidden');
        });
    }

    function resolveIncident(reportId) {
        fetch(`/api/reports/${reportId}/resolve`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(err => console.error('Error resolving incident:', err));
    }

    function confirmReset() {
        if (confirm('Are you sure you want to clear all system logs and reset the robots? This action cannot be undone.')) {
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
                if (data.success) {
                    alert('System reset successfully.');
                    window.location.reload();
                }
            })
            .catch(err => {
                console.error('Error resetting:', err);
                alert('An error occurred while resetting the logs.');
            });
        }
    }
</script>
@endsection
