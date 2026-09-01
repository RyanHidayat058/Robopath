@extends('layouts.layout')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-blue/10 text-brand-blue flex items-center justify-center text-xl">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                Bot Control Center
            </h1>
            <p class="text-sm text-gray-500 mt-1">Kelola status operasional, daya baterai, dan pengaturan telemetri robot secara mandiri.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                System Active
            </span>
        </div>
    </div>

    <!-- Robot List Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($robots as $bot)
            @php
                $isMaint = $bot->status === 'Maintenance';
                $isDelivering = $bot->status === 'Delivering';
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md transition duration-300 flex flex-col justify-between space-y-6">
                <!-- Card Header -->
                <div>
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-brand-blue text-white flex items-center justify-center text-xl shadow-md">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">{{ $bot->name }}</h3>
                                <p class="text-xs text-gray-500 font-mono">ID: BOT-00{{ $bot->id }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full border {{ $isMaint ? 'bg-amber-50 text-amber-700 border-amber-200' : ($isDelivering ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') }}">
                            {{ $bot->status }}
                        </span>
                    </div>

                    <!-- Telemetry Stats -->
                    <div class="space-y-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <!-- Battery -->
                        <div>
                            <div class="flex justify-between text-xs font-semibold text-gray-700 mb-1.5">
                                <span class="flex items-center gap-1.5 text-gray-500">
                                    <i class="fa-solid fa-battery-three-quarters text-brand-blue"></i> Tingkat Baterai
                                </span>
                                <span class="font-bold {{ $bot->battery_level > 20 ? 'text-gray-900' : 'text-red-600' }}">{{ $bot->battery_level }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full {{ $bot->battery_level > 20 ? 'bg-emerald-500' : 'bg-red-500' }}" style="width: {{ $bot->battery_level }}%"></div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-gray-500 font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-brand-blue"></i> Koordinat Peta
                            </span>
                            <span class="font-mono font-bold text-gray-800 bg-white px-2.5 py-1 rounded border border-gray-200">
                                {{ round($bot->current_x, 1) }}%, {{ round($bot->current_y, 1) }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="pt-4 border-t border-gray-100 flex items-center gap-3">
                    <button onclick="updateBot({{ $bot->id }}, 'battery_level', 100)" class="flex-1 bg-gray-100 hover:bg-brand-blue hover:text-white text-gray-800 font-bold py-2.5 px-4 rounded-xl text-xs transition duration-200 flex items-center justify-center gap-2 border border-gray-200 shadow-sm">
                        <i class="fa-solid fa-bolt text-amber-500"></i> Charge 100%
                    </button>
                    
                    <button onclick="updateBot({{ $bot->id }}, 'status', '{{ $isMaint ? 'Idle' : 'Maintenance' }}')" class="flex-1 bg-white hover:bg-gray-50 {{ $isMaint ? 'text-emerald-600 border-emerald-300' : 'text-rose-600 border-rose-300' }} font-bold py-2.5 px-4 rounded-xl text-xs transition duration-200 flex items-center justify-center gap-2 border shadow-sm">
                        <i class="fa-solid {{ $isMaint ? 'fa-circle-check' : 'fa-wrench' }}"></i> {{ $isMaint ? 'Set Idle' : 'Set Maint.' }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    function updateBot(robotId, field, value) {
        const body = {};
        body[field] = value;
        
        fetch(`/api/robots/${robotId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(body)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(err => console.error(err));
    }
</script>
@endsection
