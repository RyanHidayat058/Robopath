@extends('layouts.layout')

@section('title', 'ROBOPATH - Manajemen Pengiriman')
@section('page_title', 'Manajemen Pengiriman')
@section('page_subtitle', 'Pantau misi pengantaran berjalan dan riwayat aktivitas pengiriman hari ini')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Recent Activity Timeline Today (1/3 width) -->
    <div class="lg:col-span-1">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col h-full">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-brand-blue"></i>
                    Riwayat Aktivitas Hari Ini
                </h3>
                <span class="text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" id="timeline-count-badge">
                    {{ $recentActivity->count() }} Aktivitas
                </span>
            </div>
            
            <div class="space-y-4 overflow-y-auto max-h-[600px] pr-2" id="timeline-container">
                @forelse($recentActivity as $act)
                <div class="relative pl-6 border-l border-gray-200">
                    <!-- Glowing indicator dot -->
                    <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full {{ $act->status === 'Completed' ? 'bg-green-500' : ($act->status === 'Failed' ? 'bg-rose-500' : 'bg-brand-blue animate-pulse') }}"></span>
                    
                    <span class="text-[10px] text-gray-400 font-semibold block">
                        {{ $act->updated_at ? $act->updated_at->format('H:i:s') : '-' }} ({{ $act->updated_at ? $act->updated_at->diffForHumans() : '-' }})
                    </span>
                    <p class="text-xs font-bold text-gray-800 mt-0.5">
                        {{ $act->robot ? $act->robot->name : 'Robot' }}
                    </p>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        @if($act->status === 'Completed')
                        Berhasil mengantar <strong class="text-gray-700">{{ $act->item_name }}</strong> ke <strong class="text-gray-700">{{ $act->formatted_destination_location }}</strong>
                        @elseif($act->status === 'In Progress')
                        Sedang mengantar <strong class="text-gray-700">{{ $act->item_name }}</strong> ke <strong class="text-gray-700">{{ $act->formatted_destination_location }}</strong>
                        @elseif($act->status === 'Pending')
                        Menunggu antaran <strong class="text-gray-700">{{ $act->item_name }}</strong> ke <strong class="text-gray-700">{{ $act->formatted_destination_location }}</strong>
                        @else
                        Gagal mengantar <strong class="text-gray-700">{{ $act->item_name }}</strong>
                        @endif
                    </p>
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

    <!-- Right Column: Current Active Deliveries (2/3 width) -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-gray-200 p-6 rounded-2xl shadow-xl flex flex-col h-full">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-truck-ramp-box text-[#3b4cb8]"></i>
                    Misi Pengantaran Berjalan
                </h3>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                    <span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span>
                    <span id="active-count-text">{{ $activeDeliveries->count() }} Aktif</span>
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead>
                        <tr class="text-gray-400 text-xs font-bold uppercase border-b border-gray-200">
                            <th class="py-3 px-2">Robot</th>
                            <th class="py-3 px-2">Muatan</th>
                            <th class="py-3 px-2">Titik Jemput</th>
                            <th class="py-3 px-2">Tujuan</th>
                            <th class="py-3 px-2">Progres</th>
                        </tr>
                    </thead>
                    <tbody id="active-deliveries-table-body">
                        @forelse($activeDeliveries as $deliv)
                        <tr class="border-b border-gray-100 hover:bg-gray-50/70 transition text-xs">
                            <td class="py-3.5 px-2 font-bold text-gray-700">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                    <span>{{ $deliv->robot ? $deliv->robot->name : 'Robot' }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-2 text-gray-600 font-semibold">{{ $deliv->item_name }}</td>
                            <td class="py-3.5 px-2 text-gray-500 font-medium">{{ $deliv->formatted_start_location }}</td>
                            <td class="py-3.5 px-2 text-gray-800 font-semibold">{{ $deliv->formatted_destination_location }}</td>
                            <td class="py-3.5 px-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-28 bg-gray-100 rounded-full h-2 overflow-hidden border border-gray-200">
                                        <div class="bg-brand-blue h-2 rounded-full transition-all duration-300" style="width: 25%"></div>
                                    </div>
                                    <span class="font-bold text-brand-blue font-mono text-xs">25%</span>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-400 text-xs">
                                <i class="fa-solid fa-circle-check text-2xl mb-2 text-gray-300 block"></i>
                                Tidak ada misi pengantaran aktif saat ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let robots = @json($robots);
    let activeDeliveries = @json($activeDeliveries);
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

    function parseServerDate(dateStr) {
        if (!dateStr) return new Date();
        const d = new Date(dateStr);
        return isNaN(d.getTime()) ? new Date() : d;
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

            robots = data.robots || [];
            activeDeliveries = data.active_deliveries || [];

            updateActiveMissionsTable();
            updateTimeline(data.recent_deliveries || []);
        })
        .catch(err => console.error('Error fetching telemetry:', err));
    }

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
            const destName = act.formatted_destination_location || formatLocationDisplay(act.destination_location);
            
            let statusText = '';
            if (isCompleted) {
                statusText = `Berhasil mengantar <strong class="text-gray-700">${act.item_name}</strong> ke <strong class="text-gray-700">${destName}</strong>`;
            } else if (act.status === 'In Progress') {
                statusText = `Sedang mengantar <strong class="text-gray-700">${act.item_name}</strong> ke <strong class="text-gray-700">${destName}</strong>`;
            } else if (act.status === 'Pending') {
                statusText = `Menunggu antaran <strong class="text-gray-700">${act.item_name}</strong> ke <strong class="text-gray-700">${destName}</strong>`;
            } else {
                statusText = `Gagal mengantar <strong class="text-gray-700">${act.item_name}</strong>`;
            }

            const div = document.createElement('div');
            div.className = 'relative pl-6 border-l border-gray-200';
            div.innerHTML = `
                <span class="absolute left-[-4.5px] top-1.5 w-2.5 h-2.5 rounded-full ${dotColor}"></span>
                <span class="text-[10px] text-gray-400 font-semibold block">${timeStr}</span>
                <p class="text-xs font-bold text-gray-800 mt-0.5">${robotName}</p>
                <p class="text-[11px] text-gray-500 mt-0.5">${statusText}</p>
            `;
            container.appendChild(div);
        });
    }

    function updateActiveMissionsTable() {
        const tbody = document.getElementById('active-deliveries-table-body');
        const countText = document.getElementById('active-count-text');
        if (!tbody) return;

        if (countText) {
            countText.textContent = `${activeDeliveries.length} Aktif`;
        }

        if (activeDeliveries.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="py-12 text-center text-gray-400 text-xs">
                        <i class="fa-solid fa-circle-check text-2xl mb-2 text-gray-300 block"></i>
                        Tidak ada misi pengantaran aktif saat ini.
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = '';
        activeDeliveries.forEach(delivery => {
            const robot = robots.find(r => Number(r.id) === Number(delivery.robot_id)) || delivery.robot || { name: 'Robot' };
            
            const totalDurationMs = 30000; // standard estimated mission duration for UI progression
            const startedTime = parseServerDate(delivery.started_at || delivery.created_at);
            const now = new Date(new Date().getTime() + serverClientOffset);
            const elapsedMs = Math.max(0, now.getTime() - startedTime.getTime());
            const ratio = Math.min(elapsedMs / totalDurationMs, 0.95);
            const pct = Math.max(10, Math.round(ratio * 100));

            const startName = delivery.formatted_start_location || formatLocationDisplay(delivery.start_location);
            const destName = delivery.formatted_destination_location || formatLocationDisplay(delivery.destination_location);

            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-100 hover:bg-gray-50/70 transition text-xs';
            tr.innerHTML = `
                <td class="py-3.5 px-2 font-bold text-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                        <span>${robot.name}</span>
                    </div>
                </td>
                <td class="py-3.5 px-2 text-gray-600 font-semibold">${delivery.item_name}</td>
                <td class="py-3.5 px-2 text-gray-500 font-medium">${startName}</td>
                <td class="py-3.5 px-2 text-gray-800 font-semibold">${destName}</td>
                <td class="py-3.5 px-2">
                    <div class="flex items-center gap-3">
                        <div class="w-28 bg-gray-100 rounded-full h-2 overflow-hidden border border-gray-200">
                            <div class="bg-brand-blue h-2 rounded-full transition-all duration-300" style="width: ${pct}%"></div>
                        </div>
                        <span class="font-bold text-brand-blue font-mono text-xs">${pct}%</span>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchData();
        setInterval(fetchData, 2000);
    });
</script>
@endsection
