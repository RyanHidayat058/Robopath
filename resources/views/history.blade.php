@extends('layouts.layout')

@section('title', 'ROBOPATH - Riwayat Pengiriman')
@section('page_title', 'Catatan Pengiriman')
@section('page_subtitle', 'Catatan lengkap mengenai semua operasi dan pengiriman yang telah dilakukan')

@section('content')
<div class="bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
    <!-- Blue Header Controls -->
    <div class="p-6 bg-[#3b4cb8] text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
        <div>
            <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left"></i> Catatan Operasi
            </h3>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead>
                <tr class="bg-blue-50/70 border-b border-gray-200 text-[#3b4cb8] text-xs font-bold uppercase tracking-wider">
                    <th class="px-6 py-4">ID Misi</th>
                    <th class="px-6 py-4">Nama Robot</th>
                    <th class="px-6 py-4">Nama Barang</th>
                    <th class="px-6 py-4">Dari (Asal)</th>
                    <th class="px-6 py-4">Tujuan</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4">Waktu Selesai</th>
                    <th class="px-6 py-4 text-right">Durasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($deliveries as $delivery)
                <tr class="hover:bg-blue-50/40 transition duration-150">
                    <td class="px-6 py-4 font-mono font-bold text-[#3b4cb8] text-xs">
                        #MSN-{{ str_pad($delivery->id, 4, '0', STR_PAD_LEFT) }}
                    </td>
                    <td class="px-6 py-4 font-bold text-gray-800">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-robot text-[#3b4cb8]"></i>
                            {{ $delivery->robot?->name ?? 'Robot Tidak Diketahui' }}
                        </div>
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-700">
                        {{ $delivery->item_name }}
                    </td>
                    <td class="px-6 py-4 text-gray-600 text-xs">
                        {{ $delivery->formatted_start_location }}
                    </td>
                    <td class="px-6 py-4 text-gray-800 font-semibold text-xs">
                        {{ $delivery->formatted_destination_location }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider {{ $delivery->status === 'Completed' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-200' }}">
                            {{ $delivery->status === 'Completed' ? 'Selesai' : ($delivery->status === 'Failed' ? 'Gagal' : ($delivery->status === 'In Progress' ? 'Berlangsung' : $delivery->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500 font-mono text-xs">
                        {{ $delivery->completed_at ? $delivery->completed_at->format('d M Y, H:i') : '-' }}
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-gray-700 font-bold text-xs">
                        @if($delivery->completed_at && $delivery->started_at)
                            {{ $delivery->completed_at->diff($delivery->started_at)->format('%i m, %s dtk') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-12 text-center text-gray-400 text-xs">
                        Tidak ditemukan catatan operasi di arsip riwayat.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Table Footer / Pagination -->
    <div class="p-4 border-t border-gray-200 bg-gray-50/60 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-xs text-gray-500 font-medium">
            Menampilkan <span class="font-bold text-gray-700">{{ $deliveries->firstItem() ?? 0 }}</span> sampai <span class="font-bold text-gray-700">{{ $deliveries->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-700">{{ $deliveries->total() }}</span> data
        </div>
        <div class="pagination-wrapper">
            {{ $deliveries->links() }}
        </div>
    </div>
</div>
@endsection
