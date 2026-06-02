@extends('layouts.app')

@section('title', 'Accounting Report List')
@section('subtitle', 'Consolidated Invoice Report from ' . \Carbon\Carbon::parse($dateFrom)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($dateTo)->format('d M Y'))

@section('content')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
    [x-cloak] { display: none !important; }
    .scrollbar-thin::-webkit-scrollbar { width: 4px; height: 4px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 10px; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.4); }
</style>

<div x-data="{ 
    exportOpen: false,
    syncOpen: false,
    syncing: false,
    syncMessage: '',
    syncProgress: 0,
    syncCurrentStep: '',
    syncResults: [],
    
    async syncBatchData(name, baseUrl, dateFrom, dateTo) {
        this.syncCurrentStep = `Fetching IDs for ${name}...`;
        const idRes = await fetch(`${baseUrl}/sync-ids`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ date_from: dateFrom, date_to: dateTo })
        });
        const idData = await idRes.json();
        if (!idData.success) {
            throw new Error(idData.message || `Failed to fetch IDs for ${name}`);
        }
        const allIds = idData.ids || [];
        if (allIds.length === 0) {
            this.syncResults.push({ label: name, success: true, count: 0, message: 'No new records' });
            return;
        }
        
        const chunkSize = 500;
        let processedCount = 0;
        for (let i = 0; i < allIds.length; i += chunkSize) {
            const batch = allIds.slice(i, i + chunkSize);
            this.syncCurrentStep = `Syncing ${name} (${i + 1}-${Math.min(i + chunkSize, allIds.length)} of ${allIds.length})...`;
            const batchRes = await fetch(`${baseUrl}/sync-batch`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ids: batch })
            });
            const batchData = await batchRes.json();
            if (!batchData.success) throw new Error(batchData.message || 'Batch sync failed');
            processedCount += (batchData.count || 0);
        }
        this.syncResults.push({ label: name, success: true, count: processedCount, message: 'Synced' });
    },

    async doSyncAll() {
        const dateFrom = document.getElementById('sync_date_from').value;
        const dateTo = document.getElementById('sync_date_to').value;
        if (!dateFrom || !dateTo) {
            alert('Please specify Date From and Date To for the sync.');
            return;
        }

        this.syncing = true;
        this.syncMessage = 'Starting full sync...';
        this.syncResults = [];
        this.syncProgress = 0;
        
        const tasks = [
            { name: 'Subscription', url: '{{ url('/invoice-subscription') }}', type: 'single' },
            { name: 'Rental', url: '{{ url('/invoice-rental') }}', type: 'batch' },
            { name: 'Driver', url: '{{ url('/invoice-driver') }}', type: 'batch' },
            { name: 'Other', url: '{{ url('/invoice-other') }}', type: 'batch' },
            { name: 'Vehicle', url: '{{ url('/invoice-vehicle') }}', type: 'batch' }
        ];

        try {
            for (let i = 0; i < tasks.length; i++) {
                const task = tasks[i];
                if (task.type === 'single') {
                    this.syncCurrentStep = `Syncing ${task.name}...`;
                    const res = await fetch(`${task.url}/sync?from=${dateFrom}&to=${dateTo}&truncate=0`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    this.syncResults.push({ 
                        label: task.name, 
                        success: data.success !== false, 
                        count: data.count || 0,
                        message: data.message || 'Synced'
                    });
                } else {
                    await this.syncBatchData(task.name, task.url, dateFrom, dateTo);
                }
                this.syncProgress = Math.round(((i + 1) / tasks.length) * 100);
            }
            this.syncMessage = 'All modules synced successfully!';
            setTimeout(() => {
                window.location.href = window.location.pathname + '?date_from=' + dateFrom + '&date_to=' + dateTo;
            }, 2000);
        } catch (e) {
            this.syncMessage = 'Sync Error: ' + e.message;
        } finally {
            this.syncing = false;
        }
    }
}">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-6 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <form method="GET" action="{{ route('invoice-subscription.accounting-report') }}" class="flex flex-wrap items-end gap-4 m-0">
            
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Invoice #, customer, NPWP..."
                    class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                    class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>
            
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                    class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>
            
            <div class="flex gap-2 items-end">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">Filter</button>
                <a href="{{ route('invoice-subscription.accounting-report') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Reset</a>
            </div>
        </form>

        <div class="flex gap-2 items-end">
            {{-- Sync Button (Toggles Panel) --}}
                <button type="button" @click="syncOpen = !syncOpen" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sync Odoo</span>
                </button>

                {{-- Export Menu --}}
                <div class="relative">
                    <button type="button" @click="exportOpen = !exportOpen" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-1 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M16 9l-4-4-4 4M12 5v13"/></svg>
                        Export
                        <svg class="w-3 h-3 transition-transform" :class="exportOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="exportOpen" @click.away="exportOpen = false" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl z-50 py-1">
                        <form method="POST" action="{{ route('invoice-subscription.accounting-report.export') }}">
                            @csrf
                            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                            <input type="hidden" name="date_to" value="{{ $dateTo }}">
                            <input type="hidden" name="search" value="{{ $search ?? '' }}">
                            <button type="submit" name="format" value="excel" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M15.8,20H14L12,16.6L10,20H8.2L11,15.5L8.2,11H10L12,14.4L14,11H15.8L13,15.5L15.8,20M13,9V3.5L18.5,9H13Z"/></svg>
                                Excel (.xls)
                            </button>
                            <button type="submit" name="format" value="csv" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                CSV File
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync Progress Panel --}}
    <div x-show="syncOpen" x-cloak x-transition class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/50 rounded-xl p-4 mb-6">
        <h3 class="text-sm font-bold text-blue-800 dark:text-blue-300 mb-3">Sync All Data from Odoo</h3>
        
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
                <input type="date" id="sync_date_from" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
                <input type="date" id="sync_date_to" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <button type="button" @click="doSyncAll()" :disabled="syncing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center gap-2">
                <svg x-show="syncing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="syncing ? 'Syncing...' : 'Start Sync'"></span>
            </button>
        </div>

        <div x-show="syncing || syncResults.length > 0" class="space-y-2 mb-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-blue-700 dark:text-blue-400 font-medium" x-text="syncCurrentStep"></span>
                <span class="font-bold text-blue-700 dark:text-blue-400" x-text="`${syncProgress}%`"></span>
            </div>
            <div class="w-full bg-blue-100 dark:bg-blue-900/50 rounded-full h-2 overflow-hidden border border-blue-200 dark:border-blue-800">
                <div class="bg-blue-600 h-2 transition-all duration-300" :style="`width: ${syncProgress}%`"></div>
            </div>
        </div>

        <div class="max-h-40 overflow-y-auto space-y-1 text-xs scrollbar-thin">
            <template x-for="res in syncResults" :key="res.label">
                <div class="flex items-center justify-between py-1 border-b border-blue-100/50 dark:border-blue-800/30">
                    <span class="text-slate-600 dark:text-slate-400" x-text="res.label"></span>
                    <div class="flex items-center gap-2">
                        <span x-show="res.success" class="text-emerald-600 font-bold" x-text="`+${res.count} records`"></span>
                        <span x-show="!res.success" class="text-red-500" x-text="res.message || 'Failed'"></span>
                    </div>
                </div>
            </template>
        </div>
        
        <div x-show="!syncing && syncMessage" class="mt-4 p-2 bg-emerald-100 text-emerald-800 rounded-lg text-sm font-medium" x-text="syncMessage"></div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto overflow-y-auto max-h-[75vh] scrollbar-thin">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">No.</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Customer</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">Tgl Invoice</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">No Invoice</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">No NPWP</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">Kode Transaksi</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Jumlah</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">Product Lain - Lain</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Deskripsi</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">Sub Total</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">PPN</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Total</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($records as $idx => $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-4 py-3 text-slate-500">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-white min-w-[200px]">{{ $row->customer }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 font-mono text-xs">{{ $row->tgl_invoice }}</td>
                            <td class="px-4 py-3 font-mono font-medium text-emerald-600 dark:text-emerald-400 whitespace-nowrap">{{ $row->no_invoice }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $row->no_npwp ?: '-' }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                @if($row->kode_transaksi)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">
                                        {{ $row->kode_transaksi }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-700 dark:text-slate-200">{{ number_format($row->jumlah, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $row->lain_lain_amount < 0 ? 'text-red-600' : 'text-slate-700 dark:text-slate-200' }}">
                                {{ $row->lain_lain_amount ? number_format($row->lain_lain_amount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs min-w-[150px] whitespace-pre-wrap">{{ $row->lain_lain_ket ?: '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-800 dark:text-slate-100">{{ number_format($row->sub_total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">{{ number_format($row->ppn, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($row->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-4 py-8 text-center text-slate-500">
                                No invoice data found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
