@extends('layouts.tata_letak')

@section('title', 'ROBOPATH - Sistem Pemanggilan Robot')
@section('page_title', 'Pengiriman Robot')
@section('page_subtitle', 'Sistem pemanggilan armada robot cerdas dan pemantauan misi pengantaran barang')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto pb-12">
    <!-- ========================================== -->
    <!-- BAGIAN ATAS: PANGGIL ROBOT & KONDISI ROBOT -->
    <!-- ========================================== -->
    
    <!-- Bagian A: Panggil Robot (Card Utama) -->
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden" id="card-panggil-robot-wrapper">
        <!-- Sub-State 1: Form Pemanggilan Robot Awal -->
        <div id="section-form-panggil" class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-gray-100 mb-6 gap-2">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2.5">
                        <i class="fa-solid fa-bell-concierge text-[#3b4cb8]"></i> Panggil Robot
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        Tentukan lokasi pengambilan barang dan tujuan pengiriman.
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-[#3b4cb8] border border-blue-200 self-start sm:self-auto">
                    <i class="fa-solid fa-bolt text-xs"></i> Seleksi Otomatis Aktif
                </span>
            </div>

            <!-- Error Notice Box -->
            <div id="panggil-error-box" class="hidden mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-start justify-between gap-3">
                <div class="flex items-start gap-2.5">
                    <i class="fa-solid fa-circle-exclamation text-rose-500 mt-0.5"></i>
                    <span id="panggil-error-text">Terjadi kesalahan pada formulir pemanggilan.</span>
                </div>
                <button type="button" onclick="document.getElementById('panggil-error-box').classList.add('hidden')" class="text-rose-500 hover:text-rose-700 font-bold text-xs">
                    Tutup
                </button>
            </div>

            <form id="form-panggil-robot" onsubmit="handleSummonSubmit(event)" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Lokasi Pengambilan -->
                    <div>
                        <label for="input-lokasi-jemput" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                            Lokasi Saya / Lokasi Pengambilan <span class="text-rose-500">*</span>
                        </label>
                        <select id="input-lokasi-jemput" name="start_location" onchange="handlePickupChange(this.value)" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 transition shadow-sm py-2.5 px-3 bg-gray-50/50">
                            <option value="">-- Pilih Lokasi Pengambilan --</option>
                            <option value="CURRENT_LOCATION" class="font-semibold text-[#3b4cb8]">
                                Gunakan Lokasi Saya Saat Ini (Lobby Utama)
                            </option>
                            @if(!empty($destinationsFloor1))
                            <optgroup label="Lantai 1">
                                @foreach($destinationsFloor1 as $id => $name)
                                    <option value="{{ $id }}">{{ $name }} (Lantai 1)</option>
                                @endforeach
                            </optgroup>
                            @endif
                            @if(!empty($destinationsFloor2))
                            <optgroup label="Lantai 2">
                                @foreach($destinationsFloor2 as $id => $name)
                                    <option value="{{ $id }}">{{ $name }} (Lantai 2)</option>
                                @endforeach
                            </optgroup>
                            @endif
                            <option value="CUSTOM">Input Lokasi Manual...</option>
                        </select>
                        <input type="text" id="input-lokasi-jemput-manual" placeholder="Ketik lokasi penjemputan manual..." class="hidden mt-2 w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 transition shadow-sm py-2 px-3">
                        <p class="text-[11px] text-gray-400 mt-1.5">
                            Robot akan otomatis dipilihkan oleh sistem berdasarkan kesiapan.
                        </p>
                    </div>

                    <!-- Nama Barang -->
                    <div>
                        <label for="input-nama-barang" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                            Barang yang Akan Diantar <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="input-nama-barang" name="item_name" placeholder="Contoh: Dokumen rapat, Paket makanan, Laptop" required class="w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 transition shadow-sm py-2.5 px-3 bg-gray-50/50">
                        <p class="text-[11px] text-gray-400 mt-1.5">
                            Tuliskan nama atau jenis muatan yang akan dibawa robot.
                        </p>
                    </div>

                    <!-- Tujuan Pengantaran -->
                    <div>
                        <label for="input-tujuan" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                            Tujuan <span class="text-rose-500">*</span>
                        </label>
                        <select id="input-tujuan" name="destination_location" onchange="handleDestChange(this.value)" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 transition shadow-sm py-2.5 px-3 bg-gray-50/50">
                            <option value="">-- Pilih Tujuan Pengantaran --</option>
                            @if(!empty($destinationsFloor1))
                            <optgroup label="Lantai 1">
                                @foreach($destinationsFloor1 as $id => $name)
                                    <option value="{{ $id }}">{{ $name }} (Lantai 1)</option>
                                @endforeach
                            </optgroup>
                            @endif
                            @if(!empty($destinationsFloor2))
                            <optgroup label="Lantai 2">
                                @foreach($destinationsFloor2 as $id => $name)
                                    <option value="{{ $id }}">{{ $name }} (Lantai 2)</option>
                                @endforeach
                            </optgroup>
                            @endif
                            <option value="CUSTOM">Input Tujuan Manual...</option>
                        </select>
                        <input type="text" id="input-tujuan-manual" placeholder="Ketik lokasi tujuan manual..." class="hidden mt-2 w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 transition shadow-sm py-2 px-3">
                        <p class="text-[11px] text-gray-400 mt-1.5">
                            Pastikan tujuan berbeda dengan lokasi pengambilan barang.
                        </p>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-xs text-gray-400">
                        Sistem memprioritaskan robot Siaga, kemudian mengalihkan robot yang sedang Kembali.
                    </span>
                    <button type="submit" id="btn-submit-panggil" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 rounded-xl bg-[#3b4cb8] hover:bg-[#2f3d96] text-white font-bold text-sm shadow-md transition duration-200">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Panggil Robot</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Sub-State 2: Card Robot Sedang Menuju Lokasi (Heading to Pickup) -->
        <div id="section-robot-menuju" class="hidden p-6 sm:p-8 bg-gradient-to-r from-amber-50/40 via-white to-blue-50/30">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-gray-200 mb-6 gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800" id="menuju-robot-name">Robot Sedang Menuju Lokasi</h2>
                        <p class="text-xs text-gray-500 mt-0.5" id="menuju-robot-note">
                            Robot telah ditugaskan dan sedang menuju titik jemput.
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Menuju Lokasi Pengambilan
                </span>
            </div>

            <!-- Detail Box -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white p-5 rounded-xl border border-gray-200 shadow-sm mb-6">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Lokasi Pengambilan</span>
                    <p class="text-sm font-bold text-gray-800" id="menuju-lokasi-jemput">-</p>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Barang</span>
                    <p class="text-sm font-semibold text-gray-700" id="menuju-nama-barang">-</p>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Tujuan</span>
                    <p class="text-sm font-bold text-gray-800" id="menuju-tujuan">-</p>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Posisi Robot Saat Ini</span>
                    <p class="text-sm font-semibold text-[#3b4cb8]" id="menuju-posisi-robot">-</p>
                </div>
            </div>

            <!-- Stepper Progres Menuju Lokasi -->
            <div class="mb-6">
                <div class="flex items-center justify-between text-xs font-bold mb-2">
                    <span class="text-[#3b4cb8] flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-green-600"></i> Robot Dipanggil
                    </span>
                    <span class="text-amber-600 flex items-center gap-1.5">
                        <i class="fa-solid fa-spinner fa-spin text-amber-500"></i> Menuju Lokasi Jemput
                    </span>
                    <span class="text-gray-400 flex items-center gap-1.5">
                        <i class="fa-regular fa-circle text-gray-300"></i> Tiba di Lokasi
                    </span>
                </div>
                <div class="w-full bg-gray-200 h-2.5 rounded-full overflow-hidden">
                    <div id="menuju-progress-bar" class="bg-amber-500 h-2.5 rounded-full transition-all duration-500" style="width: 45%"></div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <span class="text-xs text-gray-500 italic">
                    Robot akan otomatis berhenti ketika mencapai titik penjemputan.
                </span>
                <button type="button" onclick="simulateRobotArrivalNow()" class="text-xs font-semibold text-gray-400 hover:text-[#3b4cb8] underline transition">
                    Simulasikan Robot Tiba Sekarang
                </button>
            </div>
        </div>

        <!-- Sub-State 3: Card Robot Telah Tiba (Waiting for Item) -->
        <div id="section-robot-tiba" class="hidden p-6 sm:p-8 bg-gradient-to-r from-orange-50/40 via-white to-blue-50/30">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-gray-200 mb-6 gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-box text-orange-600"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800" id="tiba-robot-header">Robot Telah Tiba</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Silakan masukkan barang ke dalam robot sebelum memulai pengantaran.
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold bg-orange-100 text-orange-800 border border-orange-300">
                    <span class="w-2 h-2 rounded-full bg-orange-500 animate-ping"></span>
                    Menunggu Barang
                </span>
            </div>

            <!-- Alert Box Instruksi Tiba -->
            <div class="mb-6 p-4 rounded-xl bg-orange-50/80 border border-orange-200 text-orange-900 text-xs sm:text-sm flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-orange-500 mt-0.5 text-base"></i>
                <div>
                    <strong class="font-bold block text-orange-950" id="tiba-alert-title">Robot telah tiba di lokasi.</strong>
                    <span class="text-orange-800" id="tiba-alert-desc">Robot sedang berhenti sementara. Pastikan barang diletakkan dengan aman di kompartemen robot.</span>
                </div>
            </div>

            <!-- Detail Box -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-white p-5 rounded-xl border border-gray-200 shadow-sm mb-6">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Barang yang Akan Diantar</span>
                    <p class="text-sm font-bold text-gray-800" id="tiba-nama-barang">-</p>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Tujuan Pengantaran</span>
                    <p class="text-sm font-bold text-gray-800" id="tiba-tujuan">-</p>
                </div>
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">Kondisi Robot</span>
                    <p class="text-sm font-semibold text-orange-700">Berhenti Menunggu Barang</p>
                </div>
            </div>

            <!-- Tombol Aksi: Antarkan Barang & Edit Detail -->
            <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-end gap-3">
                <button type="button" onclick="openEditDetailModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Edit Detail</span>
                </button>
                <button type="button" onclick="openConfirmDispatchModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-2.5 rounded-xl bg-[#3b4cb8] hover:bg-[#2f3d96] text-white font-bold text-sm shadow-md transition">
                    <i class="fa-solid fa-truck-fast"></i>
                    <span>Antarkan Barang</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Bagian B: Kondisi Robot (Berada di Atas) -->
    <div class="bg-white border border-gray-200 p-6 sm:p-7 rounded-2xl shadow-xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-5">
            <div>
                <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-microchip text-[#3b4cb8]"></i> Kondisi Robot
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Status operasional dan posisi terkini armada robot Robopath
                </p>
            </div>
            <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-3 py-1 rounded-full" id="robot-total-badge">
                {{ $robots->count() }} Robot Terhubung
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5" id="robot-condition-grid">
            @forelse($robots as $bot)
            @php
                $statusColorClass = match($bot->status) {
                    'Idle' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'Returning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'Delivering' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'Waiting for Item' => 'bg-orange-50 text-orange-700 border-orange-200',
                    'Heading to Pickup' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'Maintenance' => 'bg-rose-50 text-rose-700 border-rose-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                };
                $dotColorClass = match($bot->status) {
                    'Idle' => 'bg-emerald-500',
                    'Returning' => 'bg-yellow-500',
                    'Delivering' => 'bg-sky-500 animate-pulse',
                    'Waiting for Item' => 'bg-orange-500 animate-ping',
                    'Heading to Pickup' => 'bg-amber-500 animate-pulse',
                    'Maintenance' => 'bg-rose-500',
                    default => 'bg-gray-400',
                };
            @endphp
            <div class="p-4 rounded-xl border border-gray-200 hover:border-blue-200 hover:shadow-md transition bg-gray-50/30 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-sm text-gray-800">{{ $bot->name }}</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusColorClass }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColorClass }}"></span>
                            {{ $bot->status_indonesian }}
                        </span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-gray-500">
                            <span class="text-gray-400">Posisi:</span>
                            <span class="font-bold text-gray-700 text-right">{{ $bot->position_name }}</span>
                        </div>
                        <div class="flex items-center justify-between text-gray-500">
                            <span class="text-gray-400">Baterai:</span>
                            <span class="font-semibold text-gray-700">{{ $bot->battery_level }}%</span>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-3 text-center py-6 text-xs text-gray-400">
                Tidak ada data robot ditemukan.
            </div>
            @endforelse
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- BAGIAN BAWAH: MISI PENGANTARAN BERJALAN & RIWAYAT AKTIVITAS    -->
    <!-- ============================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Kolom Kiri: Misi Pengantaran Berjalan (2/3 lebar) -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col h-full">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-route text-[#3b4cb8]"></i> Misi Pengantaran Berjalan
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Misi yang sedang aktif membawa barang menuju tujuan</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span>
                        <span id="active-count-text">{{ $activeDeliveries->where('status', 'In Progress')->count() }} Aktif</span>
                    </span>
                </div>
                
                <div class="space-y-4" id="active-deliveries-list">
                    @php
                        $inProgressDeliveries = $activeDeliveries->where('status', 'In Progress');
                    @endphp
                    @forelse($inProgressDeliveries as $deliv)
                    <div class="p-5 rounded-xl border border-blue-100 bg-blue-50/20 hover:bg-blue-50/40 transition">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-sky-500 animate-pulse"></span>
                                <h4 class="font-bold text-sm text-gray-800">{{ $deliv->robot?->name ?? 'Robot' }}</h4>
                                <span class="text-xs text-gray-400 font-mono">#MSN-{{ str_pad($deliv->id, 4, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 border border-sky-200 self-start sm:self-auto">
                                Sedang Mengantar
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs mb-4">
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase">Muatan</span>
                                <span class="font-semibold text-gray-800">{{ $deliv->item_name }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase">Dari (Titik Jemput)</span>
                                <span class="font-medium text-gray-700">{{ $deliv->formatted_start_location }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] font-bold uppercase">Tujuan</span>
                                <span class="font-bold text-gray-800">{{ $deliv->formatted_destination_location }}</span>
                            </div>
                        </div>

                        <!-- Stepper Progres Mengantar -->
                        <div class="border-t border-gray-100 pt-3">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-gray-500 mb-2">
                                <span class="text-green-600 font-bold">Dipanggil</span>
                                <span class="text-green-600 font-bold">Tiba di Lokasi</span>
                                <span class="text-green-600 font-bold">Barang Dimasukkan</span>
                                <span class="text-[#3b4cb8] font-bold">Sedang Mengantar</span>
                                <span class="text-gray-400">Selesai</span>
                            </div>
                            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden border border-gray-200">
                                <div class="bg-[#3b4cb8] h-2 rounded-full transition-all duration-300" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="py-12 text-center text-gray-400 text-xs">
                        <i class="fa-solid fa-circle-check text-2xl mb-2 text-gray-300 block"></i>
                        Tidak ada misi pengantaran aktif saat ini.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Riwayat Aktivitas Hari Ini (1/3 lebar) -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col h-full">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-[#3b4cb8]"></i> Riwayat Hari Ini
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Catatan aktivitas lengkap</p>
                    </div>
                    <span class="text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" id="timeline-count-badge">
                        {{ $recentActivity->count() }} Aktivitas
                    </span>
                </div>
                
                <div class="space-y-4 overflow-y-auto max-h-[560px] pr-2" id="timeline-container">
                    @forelse($recentActivity as $act)
                    @php
                        $isCompleted = $act->status === 'Completed';
                        $isFailed = $act->status === 'Failed';
                        $dotColor = $isCompleted ? 'bg-green-500' : ($isFailed ? 'bg-rose-500' : 'bg-brand-blue animate-pulse');
                    @endphp
                    <div class="relative pl-6 border-l border-gray-200">
                        <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full {{ $dotColor }}"></span>
                        
                        <span class="text-[10px] text-gray-400 font-semibold block">
                            {{ $act->updated_at ? $act->updated_at->format('H:i:s') : '-' }} ({{ $act->updated_at ? $act->updated_at->diffForHumans() : '-' }})
                        </span>
                        <p class="text-xs font-bold text-gray-800 mt-0.5">
                            {{ $act->robot ? $act->robot->name : 'Robot' }}
                        </p>
                        <div class="text-[11px] text-gray-600 mt-0.5 space-y-0.5">
                            <p>
                                @if($isCompleted)
                                    <span class="text-emerald-700 font-bold">Berhasil mengantar</span> <strong class="text-gray-800">{{ $act->item_name }}</strong>
                                @elseif($act->status === 'In Progress')
                                    <span class="text-sky-700 font-bold">Sedang mengantar</span> <strong class="text-gray-800">{{ $act->item_name }}</strong>
                                @elseif($act->status === 'Pending')
                                    <span class="text-amber-700 font-bold">Menunggu barang</span> <strong class="text-gray-800">{{ $act->item_name }}</strong>
                                @else
                                    <span class="text-rose-700 font-bold">Gagal mengantar</span> <strong class="text-gray-800">{{ $act->item_name }}</strong>
                                @endif
                            </p>
                            <p class="text-[10px] text-gray-500">
                                <span>Dari:</span> <strong class="text-gray-700">{{ $act->formatted_start_location }}</strong>
                                <span class="mx-1">&rarr;</span>
                                <span>Tujuan:</span> <strong class="text-gray-700">{{ $act->formatted_destination_location }}</strong>
                            </p>
                        </div>
                    </div>
                    @empty
                    <div class="text-xs text-gray-400 font-medium text-center py-12">
                        <i class="fa-solid fa-box-open text-2xl mb-2 text-gray-300 block"></i>
                        Belum ada riwayat aktivitas untuk hari ini.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT DETAIL PENGANTARAN             -->
<!-- ========================================== -->
<div id="modal-edit-detail" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
            <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-[#3b4cb8]"></i> Edit Detail Pengantaran
            </h3>
            <button type="button" onclick="closeEditDetailModal()" class="text-gray-400 hover:text-gray-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="modal-edit-error" class="hidden mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
            Terjadi kesalahan saat menyimpan data.
        </div>

        <form id="form-edit-detail" onsubmit="handleSaveEditDetail(event)" class="space-y-4">
            <div>
                <label for="edit-input-item-name" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                    Nama Barang
                </label>
                <input type="text" id="edit-input-item-name" required class="w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 py-2.5 px-3">
            </div>

            <div>
                <label for="edit-input-destination" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                    Tujuan Pengantaran
                </label>
                <select id="edit-input-destination" required class="w-full text-sm rounded-xl border-gray-300 focus:border-[#3b4cb8] focus:ring focus:ring-blue-100 py-2.5 px-3">
                    <option value="">-- Pilih Tujuan --</option>
                    @if(!empty($destinationsFloor1))
                    <optgroup label="Lantai 1">
                        @foreach($destinationsFloor1 as $id => $name)
                            <option value="{{ $id }}">{{ $name }} (Lantai 1)</option>
                        @endforeach
                    </optgroup>
                    @endif
                    @if(!empty($destinationsFloor2))
                    <optgroup label="Lantai 2">
                        @foreach($destinationsFloor2 as $id => $name)
                            <option value="{{ $id }}">{{ $name }} (Lantai 2)</option>
                        @endforeach
                    </optgroup>
                    @endif
                </select>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeEditDetailModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">
                    Batal
                </button>
                <button type="submit" id="btn-save-edit-detail" class="px-5 py-2 rounded-xl bg-[#3b4cb8] hover:bg-[#2f3d96] text-white text-xs font-bold transition shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: KONFIRMASI PENGANTARAN BARANG       -->
<!-- ========================================== -->
<div id="modal-confirm-dispatch" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 animate-in fade-in zoom-in duration-200">
        <div class="flex items-center gap-3 pb-3 border-b border-gray-100 mb-4">
            <div class="w-9 h-9 rounded-xl bg-blue-100 text-[#3b4cb8] flex items-center justify-center font-bold">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-800">Konfirmasi Pengantaran</h3>
                <p class="text-xs text-gray-400">Verifikasi muatan sebelum pengantaran dimulai</p>
            </div>
        </div>

        <div class="mb-5 space-y-3">
            <p class="text-sm text-gray-700" id="confirm-modal-prompt">
                Pastikan barang sudah dimasukkan dengan baik ke dalam robot.
            </p>

            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-gray-400">Barang:</span>
                    <strong class="text-gray-800" id="confirm-modal-item">-</strong>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-400">Tujuan:</span>
                    <strong class="text-gray-800" id="confirm-modal-dest">-</strong>
                </div>
            </div>
        </div>

        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2.5">
            <button type="button" onclick="closeConfirmDispatchModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">
                Batal
            </button>
            <button type="button" id="btn-execute-dispatch" onclick="executeDispatchNow()" class="px-6 py-2 rounded-xl bg-[#3b4cb8] hover:bg-[#2f3d96] text-white text-xs font-bold transition shadow-md">
                Mulai Pengantaran
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // State Initialization
    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);
    let destinationsFloor1 = @json($destinationsFloor1 ?? []);
    let destinationsFloor2 = @json($destinationsFloor2 ?? []);
    let currentPendingSummon = null;
    let summonApproachTimer = null;
    let serverClientOffset = 0;

    function formatLocationDisplay(loc) {
        if (!loc) return '-';
        const str = String(loc).trim();
        const match = str.match(/^(\d+)_(.+)$/);
        if (match) {
            const floor = match[1];
            const name = match[2].trim();
            return `${name} (Lantai ${floor})`;
        }
        return str;
    }

    function handlePickupChange(val) {
        const manualInput = document.getElementById('input-lokasi-jemput-manual');
        if (val === 'CUSTOM') {
            manualInput.classList.remove('hidden');
            manualInput.focus();
        } else {
            manualInput.classList.add('hidden');
        }
    }

    function handleDestChange(val) {
        const manualInput = document.getElementById('input-tujuan-manual');
        if (val === 'CUSTOM') {
            manualInput.classList.remove('hidden');
            manualInput.focus();
        } else {
            manualInput.classList.add('hidden');
        }
    }

    // Submit Summon Form (Panggil Robot)
    function handleSummonSubmit(e) {
        e.preventDefault();
        const errBox = document.getElementById('panggil-error-box');
        const errText = document.getElementById('panggil-error-text');
        const submitBtn = document.getElementById('btn-submit-panggil');

        errBox.classList.add('hidden');

        let startLoc = document.getElementById('input-lokasi-jemput').value;
        if (startLoc === 'CUSTOM') {
            startLoc = document.getElementById('input-lokasi-jemput-manual').value.trim();
        } else if (startLoc === 'CURRENT_LOCATION') {
            startLoc = '1_Resepsionis'; // Titik default lokasi saat ini
        }

        let destLoc = document.getElementById('input-tujuan').value;
        if (destLoc === 'CUSTOM') {
            destLoc = document.getElementById('input-tujuan-manual').value.trim();
        }

        const itemName = document.getElementById('input-nama-barang').value.trim();

        if (!startLoc) {
            errText.textContent = 'Lokasi penjemputan barang wajib dipilih atau diisi.';
            errBox.classList.remove('hidden');
            return;
        }

        if (!itemName) {
            errText.textContent = 'Nama barang yang akan diantar wajib diisi.';
            errBox.classList.remove('hidden');
            return;
        }

        if (!destLoc) {
            errText.textContent = 'Tujuan pengantaran barang wajib dipilih atau diisi.';
            errBox.classList.remove('hidden');
            return;
        }

        if (startLoc.toLowerCase() === destLoc.toLowerCase()) {
            errText.textContent = 'Lokasi pengambilan tidak boleh sama dengan tujuan pengantaran.';
            errBox.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memeriksa Robot...';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch('/api/deliveries/summon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                start_location: startLoc,
                destination_location: destLoc,
                item_name: itemName
            })
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Panggil Robot</span>';

            if (data.success && data.delivery) {
                currentPendingSummon = data.delivery;
                showSubStateHeadingToPickup(data.delivery, data.robot, data.message);
                fetchData();
            } else {
                errText.textContent = data.message || 'Semua robot sedang tidak tersedia. Silakan coba beberapa saat lagi.';
                errBox.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error summoning robot:', err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Panggil Robot</span>';
            errText.textContent = 'Gagal menghubungi server untuk pemanggilan robot. Silakan coba lagi.';
            errBox.classList.remove('hidden');
        });
    }

    // Tampilkan Sub-State: Robot Sedang Menuju Lokasi
    function showSubStateHeadingToPickup(delivery, robot, customNote) {
        document.getElementById('section-form-panggil').classList.add('hidden');
        document.getElementById('section-robot-menuju').classList.remove('hidden');
        document.getElementById('section-robot-tiba').classList.add('hidden');

        const botName = robot?.name || delivery.robot?.name || 'Robot';
        document.getElementById('menuju-robot-name').textContent = botName + ' Sedang Menuju Lokasi';
        
        const note = customNote || `${botName} sedang menuju ke ${formatLocationDisplay(delivery.start_location)}.`;
        document.getElementById('menuju-robot-note').textContent = note;

        document.getElementById('menuju-lokasi-jemput').textContent = formatLocationDisplay(delivery.start_location);
        document.getElementById('menuju-nama-barang').textContent = delivery.item_name;
        document.getElementById('menuju-tujuan').textContent = formatLocationDisplay(delivery.destination_location);
        document.getElementById('menuju-posisi-robot').textContent = robot?.position_name || 'Dalam Perjalanan';

        const pBar = document.getElementById('menuju-progress-bar');
        pBar.style.width = '10%';

        if (summonApproachTimer) {
            clearInterval(summonApproachTimer);
            summonApproachTimer = null;
        }
        
        // Pergerakan menuju titik jemput realistis (~20 detik sesuai peta 3D)
        const startedTimeMs = delivery.started_at ? new Date(delivery.started_at).getTime() : Date.now();
        const estimatedDurationMs = 20000;

        const updateApproachProgress = () => {
            const now = Date.now();
            const elapsed = Math.max(0, now - startedTimeMs);
            const ratio = Math.min(Math.max(elapsed / estimatedDurationMs, 0.1), 0.95);
            if (pBar) {
                pBar.style.width = `${Math.round(ratio * 100)}%`;
            }

            if (elapsed >= estimatedDurationMs) {
                if (summonApproachTimer) {
                    clearInterval(summonApproachTimer);
                    summonApproachTimer = null;
                }
                if (currentPendingSummon && currentPendingSummon.status === 'Pending') {
                    simulateRobotArrivalNow();
                }
            }
        };

        updateApproachProgress();
        summonApproachTimer = setInterval(updateApproachProgress, 500);
    }

    // Robot Tiba di Lokasi Jemput -> Pindah ke Sub-State: Robot Telah Tiba
    function simulateRobotArrivalNow() {
        if (summonApproachTimer) {
            clearInterval(summonApproachTimer);
            summonApproachTimer = null;
        }
        if (!currentPendingSummon) return;

        const deliveryId = currentPendingSummon.id;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/api/deliveries/${deliveryId}/arrive-pickup`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentPendingSummon = data.delivery;
                showSubStateWaitingForItem(data.delivery, data.robot);
                fetchData();
            }
        })
        .catch(err => {
            console.error('Error arriving pickup:', err);
            // Tetap pindahkan tampilan ke waiting for item agar user tidak tertahan
            showSubStateWaitingForItem(currentPendingSummon, currentPendingSummon.robot);
        });
    }

    // Tampilkan Sub-State: Robot Telah Tiba (Waiting for Item)
    function showSubStateWaitingForItem(delivery, robot) {
        document.getElementById('section-form-panggil').classList.add('hidden');
        document.getElementById('section-robot-menuju').classList.add('hidden');
        document.getElementById('section-robot-tiba').classList.remove('hidden');

        const botName = robot?.name || delivery.robot?.name || 'Robot';
        const startName = formatLocationDisplay(delivery.start_location);

        document.getElementById('tiba-robot-header').textContent = `${botName} Telah Tiba di ${startName}`;
        document.getElementById('tiba-alert-title').textContent = `${botName} telah tiba di ${startName}.`;
        document.getElementById('tiba-alert-desc').textContent = `Silakan masukkan barang (${delivery.item_name}) ke dalam robot sebelum memulai pengantaran.`;

        document.getElementById('tiba-nama-barang').textContent = delivery.item_name;
        document.getElementById('tiba-tujuan').textContent = formatLocationDisplay(delivery.destination_location);
    }

    // Kembalikan ke form awal
    function resetSummonViewToDefault() {
        if (summonApproachTimer) clearTimeout(summonApproachTimer);
        currentPendingSummon = null;
        document.getElementById('section-form-panggil').classList.remove('hidden');
        document.getElementById('section-robot-menuju').classList.add('hidden');
        document.getElementById('section-robot-tiba').classList.add('hidden');
        document.getElementById('input-nama-barang').value = '';
    }

    // Edit Detail Modal
    function openEditDetailModal() {
        if (!currentPendingSummon) return;
        document.getElementById('edit-input-item-name').value = currentPendingSummon.item_name;
        document.getElementById('edit-input-destination').value = currentPendingSummon.destination_location;
        document.getElementById('modal-edit-error').classList.add('hidden');
        document.getElementById('modal-edit-detail').classList.remove('hidden');
    }

    function closeEditDetailModal() {
        document.getElementById('modal-edit-detail').classList.add('hidden');
    }

    function handleSaveEditDetail(e) {
        e.preventDefault();
        if (!currentPendingSummon) return;

        const newItemName = document.getElementById('edit-input-item-name').value.trim();
        const newDest = document.getElementById('edit-input-destination').value;
        const errBox = document.getElementById('modal-edit-error');
        const saveBtn = document.getElementById('btn-save-edit-detail');

        errBox.classList.add('hidden');

        if (!newItemName || !newDest) {
            errBox.textContent = 'Nama barang dan tujuan pengantaran wajib diisi.';
            errBox.classList.remove('hidden');
            return;
        }

        if (newDest.toLowerCase() === currentPendingSummon.start_location.toLowerCase()) {
            errBox.textContent = 'Tujuan pengantaran tidak boleh sama dengan lokasi penjemputan.';
            errBox.classList.remove('hidden');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/api/deliveries/${currentPendingSummon.id}/update-details`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                item_name: newItemName,
                destination_location: newDest
            })
        })
        .then(res => res.json())
        .then(data => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Simpan Perubahan';

            if (data.success && data.delivery) {
                currentPendingSummon = data.delivery;
                showSubStateWaitingForItem(data.delivery, data.delivery.robot);
                closeEditDetailModal();
                fetchData();
            } else {
                errBox.textContent = data.message || 'Gagal menyimpan perubahan detail.';
                errBox.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error updating details:', err);
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Simpan Perubahan';
            errBox.textContent = 'Terjadi kesalahan jaringan saat menyimpan.';
            errBox.classList.remove('hidden');
        });
    }

    // Modal Konfirmasi Pengantaran
    function openConfirmDispatchModal() {
        if (!currentPendingSummon) return;
        const botName = currentPendingSummon.robot?.name || 'Robot';
        document.getElementById('confirm-modal-prompt').textContent = `Pastikan barang sudah dimasukkan ke dalam ${botName}.`;
        document.getElementById('confirm-modal-item').textContent = currentPendingSummon.item_name;
        document.getElementById('confirm-modal-dest').textContent = formatLocationDisplay(currentPendingSummon.destination_location);
        document.getElementById('modal-confirm-dispatch').classList.remove('hidden');
    }

    function closeConfirmDispatchModal() {
        document.getElementById('modal-confirm-dispatch').classList.add('hidden');
    }

    function executeDispatchNow() {
        if (!currentPendingSummon) return;
        const dispatchBtn = document.getElementById('btn-execute-dispatch');
        dispatchBtn.disabled = true;
        dispatchBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memulai...';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/api/deliveries/${currentPendingSummon.id}/dispatch`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            dispatchBtn.disabled = false;
            dispatchBtn.innerHTML = 'Mulai Pengantaran';
            closeConfirmDispatchModal();

            if (data.success) {
                resetSummonViewToDefault();
                fetchData();
            } else {
                alert(data.message || 'Gagal memulai pengantaran.');
            }
        })
        .catch(err => {
            console.error('Error dispatching delivery:', err);
            dispatchBtn.disabled = false;
            dispatchBtn.innerHTML = 'Mulai Pengantaran';
            closeConfirmDispatchModal();
            alert('Terjadi kesalahan jaringan.');
        });
    }

    // Sinkronisasi data Telemetry
    function fetchData() {
        fetch('/api/telemetry')
        .then(res => res.json())
        .then(data => {
            robots = data.robots || [];
            activeDeliveries = data.active_deliveries || [];

            updateRobotConditionGrid(robots);
            updateActiveMissions(activeDeliveries);
            updateTimeline(data.recent_deliveries || []);

            // Sinkronkan state pemanggilan jika ada delivery berstatus Pending untuk sesi ini
            const activePending = activeDeliveries.find(d => d.status === 'Pending');
            if (activePending) {
                currentPendingSummon = activePending;
                const bot = robots.find(r => Number(r.id) === Number(activePending.robot_id));
                const isTibaVisible = !document.getElementById('section-robot-tiba').classList.contains('hidden');
                const isMenujuVisible = !document.getElementById('section-robot-menuju').classList.contains('hidden');

                if (bot && bot.status === 'Waiting for Item') {
                    if (summonApproachTimer) {
                        clearInterval(summonApproachTimer);
                        summonApproachTimer = null;
                    }
                    if (!isTibaVisible) {
                        showSubStateWaitingForItem(activePending, bot);
                    }
                } else if (bot && bot.status === 'Heading to Pickup') {
                    if (!isMenujuVisible) {
                        showSubStateHeadingToPickup(activePending, bot);
                    }
                }
            } else if (!activePending && currentPendingSummon && currentPendingSummon.status === 'Pending') {
                resetSummonViewToDefault();
            }
        })
        .catch(err => console.error('Error fetching telemetry:', err));
    }

    // Perbarui Tampilan Card Kondisi Robot
    function updateRobotConditionGrid(robotList) {
        const grid = document.getElementById('robot-condition-grid');
        const badge = document.getElementById('robot-total-badge');
        if (!grid) return;

        if (badge) {
            badge.textContent = `${robotList.length} Robot Terhubung`;
        }

        grid.innerHTML = '';
        robotList.forEach(bot => {
            let statusColor = 'bg-gray-50 text-gray-700 border-gray-200';
            let dotColor = 'bg-gray-400';

            if (bot.status === 'Idle') {
                statusColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                dotColor = 'bg-emerald-500';
            } else if (bot.status === 'Returning') {
                statusColor = 'bg-yellow-50 text-yellow-700 border-yellow-200';
                dotColor = 'bg-yellow-500';
            } else if (bot.status === 'Delivering') {
                statusColor = 'bg-sky-50 text-sky-700 border-sky-200';
                dotColor = 'bg-sky-500 animate-pulse';
            } else if (bot.status === 'Waiting for Item') {
                statusColor = 'bg-orange-50 text-orange-700 border-orange-200';
                dotColor = 'bg-orange-500 animate-ping';
            } else if (bot.status === 'Heading to Pickup') {
                statusColor = 'bg-amber-50 text-amber-700 border-amber-200';
                dotColor = 'bg-amber-500 animate-pulse';
            } else if (bot.status === 'Maintenance') {
                statusColor = 'bg-rose-50 text-rose-700 border-rose-200';
                dotColor = 'bg-rose-500';
            }

            const div = document.createElement('div');
            div.className = 'p-4 rounded-xl border border-gray-200 hover:border-blue-200 hover:shadow-md transition bg-gray-50/30 flex flex-col justify-between';
            div.innerHTML = `
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-sm text-gray-800">${bot.name}</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border ${statusColor}">
                            <span class="w-1.5 h-1.5 rounded-full ${dotColor}"></span>
                            ${bot.status_indonesian || bot.status}
                        </span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-gray-500">
                            <span class="text-gray-400">Posisi:</span>
                            <span class="font-bold text-gray-700 text-right">${bot.position_name || '-'}</span>
                        </div>
                        <div class="flex items-center justify-between text-gray-500">
                            <span class="text-gray-400">Baterai:</span>
                            <span class="font-semibold text-gray-700">${bot.battery_level}%</span>
                        </div>
                    </div>
                </div>
            `;
            grid.appendChild(div);
        });
    }

    // Perbarui Card Misi Pengantaran Berjalan
    function updateActiveMissions(deliveries) {
        const container = document.getElementById('active-deliveries-list');
        const countText = document.getElementById('active-count-text');
        if (!container) return;

        const inProgress = deliveries.filter(d => d.status === 'In Progress');

        if (countText) {
            countText.textContent = `${inProgress.length} Aktif`;
        }

        if (inProgress.length === 0) {
            container.innerHTML = `
                <div class="py-12 text-center text-gray-400 text-xs">
                    <i class="fa-solid fa-circle-check text-2xl mb-2 text-gray-300 block"></i>
                    Tidak ada misi pengantaran aktif saat ini.
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        inProgress.forEach(deliv => {
            const bot = robots.find(r => Number(r.id) === Number(deliv.robot_id)) || deliv.robot || { name: 'Robot' };
            const startName = deliv.formatted_start_location || formatLocationDisplay(deliv.start_location);
            const destName = deliv.formatted_destination_location || formatLocationDisplay(deliv.destination_location);

            const div = document.createElement('div');
            div.className = 'p-5 rounded-xl border border-blue-100 bg-blue-50/20 hover:bg-blue-50/40 transition';
            div.innerHTML = `
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-500 animate-pulse"></span>
                        <h4 class="font-bold text-sm text-gray-800">${bot.name}</h4>
                        <span class="text-xs text-gray-400 font-mono">#MSN-${String(deliv.id).padStart(4, '0')}</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 border border-sky-200 self-start sm:self-auto">
                        Sedang Mengantar
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs mb-4">
                    <div>
                        <span class="text-gray-400 block text-[10px] font-bold uppercase">Muatan</span>
                        <span class="font-semibold text-gray-800">${deliv.item_name}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[10px] font-bold uppercase">Dari (Titik Jemput)</span>
                        <span class="font-medium text-gray-700">${startName}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[10px] font-bold uppercase">Tujuan</span>
                        <span class="font-bold text-gray-800">${destName}</span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-gray-500 mb-2">
                        <span class="text-green-600 font-bold">Dipanggil</span>
                        <span class="text-green-600 font-bold">Tiba di Lokasi</span>
                        <span class="text-green-600 font-bold">Barang Dimasukkan</span>
                        <span class="text-[#3b4cb8] font-bold">Sedang Mengantar</span>
                        <span class="text-gray-400">Selesai</span>
                    </div>
                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden border border-gray-200">
                        <div class="bg-[#3b4cb8] h-2 rounded-full transition-all duration-300" style="width: 75%"></div>
                    </div>
                </div>
            `;
            container.appendChild(div);
        });
    }

    // Perbarui Riwayat Aktivitas Hari Ini
    function updateTimeline(recentDeliveries) {
        const container = document.getElementById('timeline-container');
        const badge = document.getElementById('timeline-count-badge');
        if (!container) return;

        if (badge) {
            badge.textContent = `${recentDeliveries.length} Aktivitas`;
        }

        if (!recentDeliveries || recentDeliveries.length === 0) {
            container.innerHTML = `
                <div class="text-xs text-gray-400 font-medium text-center py-12">
                    <i class="fa-solid fa-box-open text-2xl mb-2 text-gray-300 block"></i>
                    Belum ada riwayat aktivitas untuk hari ini.
                </div>
            `;
            return;
        }
        
        container.innerHTML = '';
        recentDeliveries.forEach(act => {
            const dateObj = new Date(act.updated_at || act.created_at);
            const timeStr = !isNaN(dateObj.getTime()) ? dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '-';
            const isCompleted = act.status === 'Completed';
            const isFailed = act.status === 'Failed';
            const dotColor = isCompleted ? 'bg-green-500' : (isFailed ? 'bg-rose-500' : 'bg-brand-blue animate-pulse');
            
            const robotName = act.robot ? act.robot.name : 'Robot';
            const startName = act.formatted_start_location || formatLocationDisplay(act.start_location);
            const destName = act.formatted_destination_location || formatLocationDisplay(act.destination_location);
            
            let statusText = '';
            if (isCompleted) {
                statusText = `<span class="text-emerald-700 font-bold">Berhasil mengantar</span> <strong class="text-gray-800">${act.item_name}</strong>`;
            } else if (act.status === 'In Progress') {
                statusText = `<span class="text-sky-700 font-bold">Sedang mengantar</span> <strong class="text-gray-800">${act.item_name}</strong>`;
            } else if (act.status === 'Pending') {
                statusText = `<span class="text-amber-700 font-bold">Menunggu barang</span> <strong class="text-gray-800">${act.item_name}</strong>`;
            } else {
                statusText = `<span class="text-rose-700 font-bold">Gagal mengantar</span> <strong class="text-gray-800">${act.item_name}</strong>`;
            }

            const div = document.createElement('div');
            div.className = 'relative pl-6 border-l border-gray-200';
            div.innerHTML = `
                <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full ${dotColor}"></span>
                <span class="text-[10px] text-gray-400 font-semibold block">${timeStr}</span>
                <p class="text-xs font-bold text-gray-800 mt-0.5">${robotName}</p>
                <div class="text-[11px] text-gray-600 mt-0.5 space-y-0.5">
                    <p>${statusText}</p>
                    <p class="text-[10px] text-gray-500">
                        <span>Dari:</span> <strong class="text-gray-700">${startName}</strong>
                        <span class="mx-1">&rarr;</span>
                        <span>Tujuan:</span> <strong class="text-gray-700">${destName}</strong>
                    </p>
                </div>
            `;
            container.appendChild(div);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchData();
        setInterval(fetchData, 2000);
    });

    window.onPengirimanViewActivated = function() {
        fetchData();
    };
</script>
@endsection
