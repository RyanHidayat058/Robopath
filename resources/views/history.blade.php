@extends('layouts.layout')

@section('title', 'ROBOPATH - Delivery History')
@section('page_title', 'Delivery Logs')
@section('page_subtitle', 'Comprehensive record of all past operations and deliveries')

@section('content')
<div class="bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
    <!-- Blue Header Controls -->
    <div class="p-6 bg-[#3b4cb8] text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
        <div>
            <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left"></i> Operation Records
            </h3>
            <p class="text-xs text-blue-100/90 font-medium mt-1">Total of {{ $deliveries->total() }} records found in the database</p>
        </div>
        <div>
            <button onclick="confirmReset()" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-md hover:shadow-lg transition duration-200">
                <i class="fa-solid fa-trash-can"></i> Reset & Clear All Logs
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead>
                <tr class="bg-blue-50/70 border-b border-gray-200 text-[#3b4cb8] text-xs font-bold uppercase tracking-wider">
                    <th class="px-6 py-4">Mission ID</th>
                    <th class="px-6 py-4">Robot Unit</th>
                    <th class="px-6 py-4">Item (Cargo)</th>
                    <th class="px-6 py-4">From</th>
                    <th class="px-6 py-4">To</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4">Completed At</th>
                    <th class="px-6 py-4 text-right">Duration</th>
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
                            {{ $delivery->robot->name }}
                        </div>
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-700">
                        {{ $delivery->item_name }}
                    </td>
                    <td class="px-6 py-4 text-gray-600 text-xs">
                        {{ $delivery->start_location }}
                    </td>
                    <td class="px-6 py-4 text-gray-800 font-semibold text-xs">
                        {{ $delivery->destination_location }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider {{ $delivery->status === 'Completed' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-200' }}">
                            {{ $delivery->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500 font-mono text-xs">
                        {{ $delivery->completed_at ? $delivery->completed_at->format('d M Y, H:i') : '-' }}
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-gray-700 font-bold text-xs">
                        @if($delivery->completed_at && $delivery->started_at)
                            {{ $delivery->completed_at->diff($delivery->started_at)->format('%i m, %s s') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="py-12 text-center text-gray-400 text-xs">
                        No operations logs found in the archives.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Table Footer / Pagination -->
    <div class="p-4 border-t border-gray-200 bg-gray-50/60 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-xs text-gray-500 font-medium">
            Showing <span class="font-bold text-gray-700">{{ $deliveries->firstItem() ?? 0 }}</span> to <span class="font-bold text-gray-700">{{ $deliveries->lastItem() ?? 0 }}</span> of <span class="font-bold text-gray-700">{{ $deliveries->total() }}</span> records
        </div>
        <div class="pagination-wrapper">
            {{ $deliveries->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function confirmReset() {
        if (confirm('Are you sure you want to clear all history records and reset the robots? This action cannot be undone.')) {
            fetch('/api/system/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
