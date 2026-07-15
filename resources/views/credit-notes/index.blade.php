@extends('layouts.app')

@section('title', 'Credit Notes Report')
@section('subtitle', 'Credit Notes entries from Odoo')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div x-data="{
    syncOpen: false,
    syncing: false,
    syncMessage: '',
    syncSuccess: null,
    syncTotal: 0,
    syncCurrent: 0,
    syncProgress: 0,
    detailOpen: false,
    detailTitle: '',
    detailLines: [],
    async doSync() {
        const dateFrom = document.getElementById('sync_date_from').value;
        const dateTo = document.getElementById('sync_date_to').value;
        if (!dateFrom || !dateTo) {
            this.syncMessage = 'Please select both dates.';
            this.syncSuccess = false;
            return;
        }
        
        this.syncing = true;
        this.syncMessage = 'Fetching IDs from Odoo...';
        this.syncSuccess = null;
        this.syncTotal = 0;
        this.syncCurrent = 0;
        this.syncProgress = 0;

        try {
            // Phase 1: Get IDs
            const idRes = await fetch('{{ route('credit-notes.sync-ids', [], false) }}', {
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
                this.syncSuccess = false;
                this.syncMessage = idData.message || 'Failed to fetch IDs.';
                this.syncing = false;
                return;
            }

            const allIds = idData.ids || [];
            this.syncTotal = allIds.length;
            
            if (this.syncTotal === 0) {
                this.syncSuccess = true;
                this.syncMessage = 'No credit notes found for the selected range.';
                this.syncing = false;
                return;
            }

            // Phase 2: Batch Sync
            const chunkSize = 500;
            let processedCount = 0;

            for (let i = 0; i < allIds.length; i += chunkSize) {
                const batch = allIds.slice(i, i + chunkSize);
                this.syncMessage = `Syncing batch ${Math.floor(i/chunkSize) + 1} (${i + 1} - ${Math.min(i + chunkSize, allIds.length)} of ${allIds.length})...`;
                
                const batchRes = await fetch('{{ route('credit-notes.sync-batch', [], false) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: batch })
                });
                
                const batchData = await batchRes.json();
                if (!batchData.success) {
                    throw new Error(batchData.message || 'Batch sync failed');
                }

                processedCount += (batchData.count || 0);
                this.syncCurrent = Math.min(i + chunkSize, allIds.length);
                this.syncProgress = Math.round((this.syncCurrent / this.syncTotal) * 100);
            }

            this.syncSuccess = true;
            this.syncMessage = `Successfully synced ${processedCount} credit notes!`;
            setTimeout(() => window.location.reload(), 1500);

        } catch (e) {
            this.syncSuccess = false;
            this.syncMessage = 'Sync error: ' + e.message;
        } finally {
            this.syncing = false;
        }
    }
}" class="space-y-6">

    <!-- Stats widgets -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 p-5 transition-all hover:scale-[1.02]">
            <div class="text-sm font-semibold text-slate-400">Total Credit Notes</div>
            <div class="text-3xl font-bold text-slate-200 mt-2">{{ number_format($stats['total_count'] ?? 0) }}</div>
        </div>
        <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 p-5 transition-all hover:scale-[1.02]">
            <div class="text-sm font-semibold text-slate-400">Total Tax Excluded (Untaxed)</div>
            <div class="text-3xl font-bold text-emerald-400 mt-2">
                {{ ($stats['total_untaxed'] ?? 0) > 0 ? 'Rp -' . number_format($stats['total_untaxed'], 0, ',', '.') : 'Rp 0' }}
            </div>
        </div>
        <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 p-5 transition-all hover:scale-[1.02]">
            <div class="text-sm font-semibold text-slate-400">Total Tax</div>
            <div class="text-3xl font-bold text-violet-400 mt-2">
                {{ ($stats['total_tax'] ?? 0) > 0 ? 'Rp -' . number_format($stats['total_tax'], 0, ',', '.') : 'Rp 0' }}
            </div>
        </div>
        <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 p-5 transition-all hover:scale-[1.02]">
            <div class="text-sm font-semibold text-slate-400">Total Amount</div>
            <div class="text-3xl font-bold text-sky-400 mt-2">
                {{ ($stats['total_amount'] ?? 0) > 0 ? 'Rp -' . number_format($stats['total_amount'], 0, ',', '.') : 'Rp 0' }}
            </div>
        </div>
    </div>

    <!-- Actions & Filters -->
    <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 p-5 space-y-4">
        <form method="GET" action="{{ route('credit-notes.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <!-- Search -->
            <div class="md:col-span-4">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Search Credit Note</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Number, Customer, Tax Number..."
                           class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-violet-500 transition-colors" />
                </div>
            </div>

            <!-- Date From -->
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-violet-500 transition-colors" />
            </div>

            <!-- Date To -->
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">To Date</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-violet-500 transition-colors" />
            </div>

            <!-- Filter Buttons -->
            <div class="md:col-span-2 flex gap-2 w-full">
                <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition-colors shadow-lg shadow-violet-600/20">
                    Filter
                </button>
                <a href="{{ route('credit-notes.index') }}" class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium px-4 py-2.5 rounded-xl text-sm transition-colors text-center">
                    Reset
                </a>
            </div>
        </form>

        <div class="h-px bg-slate-700/50 my-2"></div>

        <!-- Sync & Export Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button @click="syncOpen = true" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.228 10H18.22M4 9h5v5m12-9a8.001 8.001 0 11-16 0h3"/></svg>
                    Sync Odoo
                </button>
            </div>

            <div class="flex items-center gap-2">
                <!-- Export Excel -->
                <form method="POST" action="{{ route('credit-notes.export', request()->query()) }}">
                    @csrf
                    <input type="hidden" name="format" value="xls">
                    <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium px-4 py-2.5 rounded-xl text-sm transition-colors flex items-center gap-2 border border-slate-600">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Excel
                    </button>
                </form>

                <!-- Export CSV -->
                <form method="POST" action="{{ route('credit-notes.export', request()->query()) }}">
                    @csrf
                    <input type="hidden" name="format" value="csv">
                    <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium px-4 py-2.5 rounded-xl text-sm transition-colors flex items-center gap-2 border border-slate-600">
                        <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-slate-800/40 backdrop-blur-md rounded-2xl border border-slate-700/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900/40 border-b border-slate-700/50">
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => request('sort') === 'name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Number
                                @if(request('sort') === 'name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'partner_name', 'dir' => request('sort') === 'partner_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Customer
                                @if(request('sort') === 'partner_name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'ref', 'dir' => request('sort') === 'ref' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Reference
                                @if(request('sort') === 'ref')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_date', 'dir' => request('sort', 'invoice_date') === 'invoice_date' && request('dir', 'desc') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Date
                                @if(request('sort', 'invoice_date') === 'invoice_date')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">Due Date</th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_date', 'dir' => request('sort') === 'payment_date' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Paid On
                                @if(request('sort') === 'payment_date')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider">Tax Number</th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_untaxed', 'dir' => request('sort') === 'amount_untaxed' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors float-right">
                                Tax Excl.
                                @if(request('sort') === 'amount_untaxed')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_total', 'dir' => request('sort') === 'amount_total' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors float-right">
                                Total
                                @if(request('sort') === 'amount_total')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_state', 'dir' => request('sort') === 'payment_state' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Payment
                                @if(request('sort') === 'payment_state')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'state', 'dir' => request('sort') === 'state' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="inline-flex items-center hover:text-violet-400 transition-colors">
                                Status
                                @if(request('sort') === 'state')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse ($creditNotes as $note)
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-3 py-2.5 text-xs font-semibold text-slate-200 whitespace-nowrap">{{ $note->name }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 max-w-[140px] break-words">{{ $note->partner_name }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 font-medium max-w-[200px] break-words">{{ $note->ref ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-400 whitespace-nowrap">{{ $note->invoice_date ? $note->invoice_date->format('d/m/Y') : '-' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-400 whitespace-nowrap">{{ $note->invoice_date_due ? $note->invoice_date_due->format('d/m/Y') : '-' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-400 font-mono whitespace-nowrap">{{ $note->payment_date ? $note->payment_date->format('d/m/Y') : '-' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 max-w-[200px] break-words">
                                @php
                                    $lines = $note->description ? explode("\n", $note->description) : [];
                                @endphp
                                @if (empty($lines))
                                    -
                                @elseif (count($lines) === 1)
                                    {{ $lines[0] }}
                                @else
                                    <div class="line-clamp-2">{{ $lines[0] }}</div>
                                    <div class="mt-1">
                                        <button type="button" 
                                                @click="detailOpen = true; detailTitle = '{{ $note->name }}'; detailLines = {{ json_encode($lines) }};"
                                                class="inline-flex items-center gap-1 text-[10px] font-bold text-violet-400 hover:text-violet-300 transition-colors uppercase tracking-wider bg-violet-500/5 hover:bg-violet-500/10 border border-violet-500/10 rounded-md px-1.5 py-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View {{ count($lines) }} items
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 font-mono whitespace-nowrap">{{ $note->tax_number ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 font-mono text-right whitespace-nowrap">{{ $note->amount_untaxed > 0 ? 'Rp -' . number_format($note->amount_untaxed, 0, ',', '.') : 'Rp 0' }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-300 font-mono text-right whitespace-nowrap">{{ $note->amount_total > 0 ? 'Rp -' . number_format($note->amount_total, 0, ',', '.') : 'Rp 0' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                @if (strtolower($note->payment_state) === 'paid')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Paid</span>
                                @elseif (strtolower($note->payment_state) === 'not_paid')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">Unpaid</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20">{{ $note->payment_state ?: 'Unpaid' }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if (strtolower($note->state) === 'posted')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Posted</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ ucfirst($note->state) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-xs text-slate-500">
                                No credit notes found. Trigger a sync with Odoo to pull records.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($creditNotes->hasPages())
            <div class="px-6 py-4 border-t border-slate-700/50">
                {{ $creditNotes->links() }}
            </div>
        @endif
    </div>

    <!-- Sync Modal popup -->
    <div x-show="syncOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" x-cloak>
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-md shadow-2xl relative">
            <h3 class="text-lg font-bold text-slate-200 mb-4">Sync Credit Notes from Odoo</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Sync Range - From</label>
                    <input type="date" id="sync_date_from" value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-violet-500 transition-colors" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Sync Range - To</label>
                    <input type="date" id="sync_date_to" value="{{ now()->endOfMonth()->format('Y-m-d') }}"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-violet-500 transition-colors" />
                </div>

                <!-- Syncing progress -->
                <div x-show="syncing || syncSuccess !== null" class="mt-4 p-4 rounded-xl bg-slate-950/50 border border-slate-800/80">
                    <p class="text-sm font-medium text-slate-300" x-text="syncMessage"></p>
                    <div x-show="syncTotal > 0" class="mt-3">
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                            <span x-text="`${syncCurrent} / ${syncTotal}`"></span>
                            <span x-text="`${syncProgress}%`"></span>
                        </div>
                        <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div class="bg-gradient-to-r from-violet-500 to-indigo-600 h-full rounded-full transition-all duration-300" :style="`width: ${syncProgress}%`"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" @click="syncOpen = false" :disabled="syncing" class="bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-slate-300 px-4 py-2.5 rounded-xl text-sm transition-colors">
                    Close
                </button>
                <button type="button" @click="doSync()" :disabled="syncing" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 disabled:opacity-50 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all shadow-lg shadow-emerald-500/20">
                    <span x-show="!syncing">Start Sync</span>
                    <span x-show="syncing">Syncing...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Detail Modal popup -->
    <div x-show="detailOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" x-cloak @click.self="detailOpen = false">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-lg shadow-2xl relative">
            <h3 class="text-lg font-bold text-slate-200 mb-4 inline-flex items-center gap-2">
                <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Credit Note Lines - <span x-text="detailTitle" class="text-violet-400"></span>
            </h3>
            
            <div class="max-h-96 overflow-y-auto pr-2 space-y-2.5 custom-scrollbar">
                <template x-for="line in detailLines">
                    <div class="p-3 bg-slate-950/50 border border-slate-800 rounded-xl flex items-start gap-3">
                        <!-- Line Number -->
                        <span class="text-xs font-bold text-slate-500 mt-0.5" x-text="line.includes('[') ? line.substring(0, line.indexOf('[')) : ''"></span>
                        
                        <!-- Sale Order Badge -->
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[11px] font-bold bg-violet-500/10 text-violet-400 border border-violet-500/20 whitespace-nowrap" 
                              x-show="line.includes('[') && line.includes(']')"
                              x-text="line.substring(line.indexOf('[') + 1, line.indexOf(']'))"></span>
                        
                        <!-- Label -->
                        <span class="text-xs text-slate-300 break-words" 
                              x-text="line.includes('[') && line.includes(']') ? line.substring(line.indexOf(']') + 2) : line"></span>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-end mt-6">
                <button type="button" @click="detailOpen = false" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-5 py-2.5 rounded-xl text-sm transition-colors font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
