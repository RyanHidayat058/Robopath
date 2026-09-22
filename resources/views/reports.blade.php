@extends('layouts.layout')

@section('title', 'ROBOPATH - Laporan Insiden & Log Gangguan')
@section('page_title', 'Laporan Insiden & Log Gangguan')
@section('page_subtitle', 'Pantau peringatan perangkat keras aktif, laporkan insiden baru, dan unggah foto bukti kendala')

@section('content')
<div class="space-y-8">

    <!-- Top Section: Manual Incident Simulation Form (Full Width Atas) -->
    <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl">
        <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500"></i> Catat Insiden Manual
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Laporkan kendala rintangan fisik atau kerusakan robot secara manual dengan lampiran bukti (Maks. 1MB)</p>
            </div>
        </div>

        <form id="incident-form" onsubmit="simulateIncident(event)" enctype="multipart/form-data" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Select Robot -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Pilih Robot Terdampak</label>
                    <select id="incident-robot" required class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition">
                        <option value="" disabled selected>Pilih robot...</option>
                        @foreach($robots as $robot)
                        <option value="{{ $robot->id }}">
                            {{ $robot->name }} ({{ $robot->status === 'Idle' ? 'Siaga' : ($robot->status === 'Charging' ? 'Mengisi Daya' : ($robot->status === 'Delivering' ? 'Mengantar' : 'Perbaikan')) }} - Baterai: {{ $robot->battery_level }}%)
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Select Incident Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenis Insiden / Kendala</label>
                    <select id="incident-type" required class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition">
                        <option value="Collision">Tabrakan / Rintangan Fisik</option>
                        <option value="Sensor Error">Kerusakan Sensor / LiDAR</option>
                        <option value="Low Battery">Baterai Kritis / Lemah</option>
                    </select>
                </div>

                <!-- Evidence Photo Upload (Max 1MB) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Foto Bukti <span class="text-gray-400 font-normal lowercase">(opsional, maks. 1MB)</span>
                    </label>
                    <input type="file" id="incident-image" accept="image/*" onchange="validateImageSize(this)" class="w-full text-xs text-gray-600 bg-gray-50 border border-gray-300 rounded-xl file:mr-4 file:py-2.5 file:px-4 file:rounded-l-xl file:border-0 file:text-xs file:font-bold file:bg-[#3b4cb8] file:text-white hover:file:bg-blue-800 transition">
                    <p id="image-size-error" class="text-[11px] text-rose-500 font-semibold mt-1 hidden">Ukuran file melebihi 1MB! Silakan pilih gambar yang lebih kecil.</p>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Catatan Rinci Insiden</label>
                <textarea id="incident-desc" rows="2" required placeholder="Contoh: Unit menabrak lemari penyimpanan di lorong tengah lantai 1..." class="w-full bg-gray-50 border border-gray-300 text-gray-800 text-sm rounded-xl p-3 focus:outline-none focus:border-[#3b4cb8] transition placeholder:text-gray-400"></textarea>
            </div>

            <div id="simulate-error" class="hidden text-xs text-rose-600 font-bold bg-rose-50 border border-rose-200 p-3 rounded-xl"></div>
            <div id="simulate-success" class="hidden text-xs text-emerald-600 font-bold bg-emerald-50 border border-emerald-200 p-3 rounded-xl"></div>

            <div class="flex justify-end">
                <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-3 px-6 rounded-xl text-sm transition duration-200 shadow-md hover:shadow-lg flex items-center gap-2">
                    <i class="fa-solid fa-bug"></i> Kirim Laporan Insiden
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
                    <i class="fa-solid fa-triangle-exclamation"></i> Log Peringatan & Gangguan Sistem
                </h3>
                <p class="text-xs text-blue-100/90 font-medium mt-1">Pantau dan selesaikan kendala perangkat keras aktif (Total {{ $reports->total() }} data)</p>
            </div>
            <div>
                <button onclick="confirmReset()" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-md transition duration-200">
                    <i class="fa-solid fa-trash-can"></i> Bersihkan Semua Log
                </button>
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left text-sm text-gray-700">
                <thead>
                    <tr class="bg-blue-50/70 border-b border-gray-200 text-[#3b4cb8] text-xs font-bold uppercase tracking-wider">
                        <th class="px-6 py-4">Waktu Dicatat</th>
                        <th class="px-6 py-4">Robot</th>
                        <th class="px-6 py-4">Jenis Kendala</th>
                        <th class="px-6 py-4">Keterangan</th>
                        <th class="px-6 py-4 text-center">Foto Bukti</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($reports as $report)
                    <tr class="hover:bg-blue-50/40 transition duration-150">
                        <td class="px-6 py-4 text-gray-500 text-xs font-mono whitespace-nowrap">
                            {{ $report->created_at->format('d M, H:i') }}
                        </td>
                        <td class="px-6 py-4 font-bold text-gray-800">
                            {{ $report->robot?->name ?? 'Robot Tidak Diketahui' }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold flex items-center gap-1.5 {{ $report->issue_type === 'Collision' || $report->issue_type === 'Sensor Error' ? 'text-rose-600' : 'text-amber-600' }}">
                                <i class="fa-solid @if($report->issue_type === 'Collision') fa-burst @elseif($report->issue_type === 'Low Battery') fa-battery-empty @else fa-microchip-exclamation @endif"></i>
                                {{ $report->issue_type === 'Collision' ? 'Tabrakan' : ($report->issue_type === 'Low Battery' ? 'Baterai Lemah' : ($report->issue_type === 'Sensor Error' ? 'Kerusakan Sensor' : $report->issue_type)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-xs max-w-[200px] truncate" title="{{ $report->description }}">
                            {{ $report->description }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($report->image_path)
                            <button onclick="previewImage('{{ asset($report->image_path) }}')" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-[#3b4cb8] font-bold text-xs px-2.5 py-1 rounded-lg border border-blue-200 transition">
                                <i class="fa-solid fa-image"></i> Lihat
                            </button>
                            @else
                            <span class="text-gray-400 text-xs font-semibold">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-[9px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider {{ $report->status === 'Active' ? 'bg-rose-100 text-rose-700 border border-rose-200 animate-pulse' : 'bg-gray-100 text-gray-500' }}">
                                {{ $report->status === 'Active' ? 'Aktif' : 'Selesai' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($report->status === 'Active')
                            <button onclick="resolveIncident({{ $report->id }})" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow transition duration-150">
                                <i class="fa-solid fa-check mr-1"></i> Perbaiki Unit
                            </button>
                            @else
                            <span class="text-gray-400 text-xs font-semibold"><i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Terselesaikan</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-gray-400 text-xs">
                            Tidak ada peringatan tercatat. Semua unit beroperasi dalam kondisi ideal.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Custom Blue-White Pagination -->
        <div class="p-4 border-t border-gray-200 bg-gray-50/60 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-gray-500 font-medium">
                Menampilkan <span class="font-bold text-gray-700">{{ $reports->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-700">{{ $reports->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-700">{{ $reports->total() }}</span> data
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
            <i class="fa-solid fa-image text-[#3b4cb8]"></i> Foto Bukti Kendala
        </h4>
        <img id="modal-img-element" src="" alt="Bukti Kendala" class="w-full h-auto max-h-[70vh] object-contain rounded-xl border border-gray-200">
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
                succDiv.textContent = `Insiden berhasil dilaporkan! Status ${data.robot.name} diperbarui menjadi ${data.robot.status}.`;
                succDiv.classList.remove('hidden');
                document.getElementById('incident-form').reset();
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                errDiv.textContent = data.message || 'Gagal melaporkan insiden.';
                errDiv.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error simulating incident:', err);
            errDiv.textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
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

    async function confirmReset() {
        const confirmed = await window.showConfirmDialog({
            title: 'Hapus Log Masalah & Reset Robot?',
            text: 'Semua riwayat laporan masalah/insiden akan dibersihkan dan armada robot dikembalikan ke Base Station (Markas Robot). Tindakan ini permanen.',
            confirmText: '<i class="fa-solid fa-trash-can mr-1.5"></i> Ya, Bersihkan Log',
            cancelText: 'Batal',
            icon: 'warning',
            isDanger: true
        });

        if (!confirmed) return;

        RobopathSwal.fire({
            title: 'Membersihkan Log & Reset Armada...',
            html: '<p class="text-xs text-gray-500 mt-1">Menghapus seluruh log insiden dan mereset status armada...</p>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

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
                await window.showSuccessAlert(
                    'Log Berhasil Dibersihkan!',
                    'Seluruh log insiden telah dibersihkan dan unit robot telah dikembalikan ke base.'
                );
                window.location.reload();
            } else {
                window.showErrorAlert('Gagal Mereset Log', data.message || 'Terjadi kendala saat mereset sistem.');
            }
        } catch (err) {
            console.error('Error resetting:', err);
            window.showErrorAlert('Kesalahan Jaringan', 'Terjadi kesalahan saat menghubungi server untuk mereset log.');
        }
    }
</script>
@endsection
