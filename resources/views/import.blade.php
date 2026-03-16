@extends('layouts.app')

@section('title', 'Import Data')
@section('subtitle', 'Sync journal entries from Odoo')

@section('content')
<div x-data="importManager()" x-init="init()">
    {{-- Tabs --}}
    <div class="flex border-b border-slate-200 dark:border-slate-700 mb-6">
        <button @click="activeTab = 'odoo'" :class="activeTab === 'odoo' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Odoo API
            </span>
        </button>
        <button @click="activeTab = 'history'; loadHistory()" :class="activeTab === 'history' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-6 py-3 text-sm font-medium border-b-2 transition-colors">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </span>
        </button>
    </div>

    {{-- ═══ ODOO API TAB ═══ --}}
    <div x-show="activeTab === 'odoo'" x-cloak>
        <div class="max-w-4xl mx-auto">
            {{-- Sync Card --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-8 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Sync Journal Entries</h3>
                        <p class="text-sm text-slate-500">Fetch financial data from Odoo for a specific period</p>
                    </div>
                </div>

                <div class="space-y-6">
                    {{-- Date Range --}}
                    <div class="grid grid-cols-2 gap-6 p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Date From</label>
                            <input type="date" x-model="syncConfig.dateFrom"
                                class="w-full px-4 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Date To</label>
                            <input type="date" x-model="syncConfig.dateTo"
                                class="w-full px-4 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm">
                        </div>
                    </div>

                    {{-- Account Filter --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider px-2">Account Filter</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                            <label class="col-span-full flex items-center gap-3 text-sm cursor-pointer pb-2 border-b border-slate-200 dark:border-slate-700 mb-1">
                                <input type="checkbox" @change="toggleAllAccounts($event.target.checked)" 
                                    :checked="selectedAccounts.length === Object.keys(availableAccounts).length"
                                    class="w-5 h-5 text-emerald-600 bg-white border-slate-300 rounded focus:ring-emerald-500">
                                <span class="font-bold text-slate-700 dark:text-slate-200">Select All Accounts</span>
                            </label>
                            <template x-for="(label, code) in availableAccounts" :key="code">
                                <label class="flex items-center gap-3 text-sm cursor-pointer p-2 rounded-lg hover:bg-white dark:hover:bg-slate-800 transition-colors group">
                                    <input type="checkbox" :value="code" x-model="selectedAccounts"
                                        class="w-5 h-5 text-emerald-600 bg-white border-slate-300 rounded focus:ring-emerald-500">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-slate-400 font-mono" x-text="code"></span>
                                        <span class="text-slate-700 dark:text-slate-300 group-hover:text-emerald-600 transition-colors" x-text="label"></span>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Sync Button --}}
                    <div class="pt-2">
                        <button @click="syncOdoo()" :disabled="syncing || !syncConfig.dateFrom || !syncConfig.dateTo" 
                            class="w-full py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-bold rounded-xl hover:from-emerald-700 hover:to-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-emerald-500/20 active:scale-[0.98]">
                            <span x-show="!syncing" class="flex items-center justify-center gap-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                SYNC DATA NOW
                            </span>
                            <span x-show="syncing" class="flex items-center justify-center gap-3">
                                <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                <span x-text="syncStatusText">SYNCING IN PROGRESS...</span>
                            </span>
                        </button>
                    </div>

                    {{-- Progress bar --}}
                    <div x-show="syncing && chunkTotal > 1" x-cloak class="pt-2">
                        <div class="flex justify-between text-[10px] font-bold text-slate-400 mb-2 uppercase tracking-widest">
                            <span x-text="'Processing ' + chunkCurrent + ' of ' + chunkTotal"></span>
                            <span x-text="Math.round((chunkCurrent - 1) / chunkTotal * 100) + '%'"></span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-3 overflow-hidden shadow-inner border border-slate-200 dark:border-slate-600">
                            <div class="bg-emerald-500 h-full rounded-full transition-all duration-700 relative"
                                :style="'width:' + Math.round((chunkCurrent - 1) / chunkTotal * 100) + '%'">
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse"></div>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2 font-medium" x-text="chunkLabel"></p>
                    </div>

                    {{-- Sync status --}}
                    <div x-show="syncMsg" x-cloak x-transition class="p-4 rounded-xl text-sm font-medium border" :class="syncMsgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'">
                        <div class="flex gap-3">
                            <svg x-show="syncMsgType === 'success'" class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <svg x-show="syncMsgType !== 'success'" class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="syncMsg"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ HISTORY TAB ═══ --}}
    <div x-show="activeTab === 'history'" x-cloak>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Source</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Items</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <template x-if="historyLoading">
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Loading...</td></tr>
                        </template>
                        <template x-if="!historyLoading && historyLogs.length === 0">
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No import history yet.</td></tr>
                        </template>
                        <template x-for="log in historyLogs" :key="log.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap" x-text="new Date(log.imported_at).toLocaleString()"></td>
                                <td class="px-4 py-3" x-text="log.source_label"></td>
                                <td class="px-4 py-3 font-mono" x-text="log.items_count"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium" :class="log.status === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300'" x-text="log.status"></span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500" x-text="log.error_message || (log.summary ? 'From ' + (log.summary.date_from || '') + ' to ' + (log.summary.date_to || '') : '-')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function importManager() {
    return {
        activeTab: 'odoo',
        
        // Sync
        syncConfig: {
            dateFrom: new Date().toISOString().slice(0, 10),
            dateTo: new Date().toISOString().slice(0, 10),
        },
        availableAccounts: @json($availableAccounts),
        selectedAccounts: @json($accountCodes),
        syncing: false,
        syncMsg: '',
        syncMsgType: '',
        syncStatusText: 'Syncing...',
        chunkTotal: 0,
        chunkCurrent: 0,
        chunkLabel: '',
        
        // History
        historyLogs: [],
        historyLoading: false,
        
        init() {
        },
        
        toggleAllAccounts(checked) {
            if (checked) {
                this.selectedAccounts = Object.keys(this.availableAccounts);
            } else {
                this.selectedAccounts = [];
            }
        },
        
        // Build array of monthly chunks between two dates (time-zone safe)
        buildMonthChunks(dateFrom, dateTo) {
            const chunks = [];
            
            if (!dateFrom || !dateTo || dateFrom > dateTo) return chunks;

            const [yF, mF] = dateFrom.split('-');
            const [yT, mT] = dateTo.split('-');

            let curY = parseInt(yF, 10);
            let curM = parseInt(mF, 10);
            const endY = parseInt(yT, 10);
            const endM = parseInt(mT, 10);

            while (curY < endY || (curY === endY && curM <= endM)) {
                const mStr = String(curM).padStart(2, '0');
                const firstDay = curY + '-' + mStr + '-01';
                
                // Find last day of this month
                const lastDayObj = new Date(curY, curM, 0); // 0th day of next month = last day of current month
                const lastDayStr = curY + '-' + mStr + '-' + String(lastDayObj.getDate()).padStart(2, '0');
                
                const chunkStart = firstDay < dateFrom ? dateFrom : firstDay;
                const chunkEnd = lastDayStr > dateTo ? dateTo : lastDayStr;
                
                chunks.push({ from: chunkStart, to: chunkEnd, label: curY + '-' + mStr });
                
                curM++;
                if (curM > 12) {
                    curM = 1;
                    curY++;
                }
            }

            return chunks;
        },

        async syncOdoo() {
            this.syncing = true;
            this.syncMsg = '';
            const chunks = this.buildMonthChunks(this.syncConfig.dateFrom, this.syncConfig.dateTo);
            this.chunkTotal = chunks.length;
            this.chunkCurrent = 0;
            let totalCount = 0;
            let errors = [];
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            for (let i = 0; i < chunks.length; i++) {
                this.chunkCurrent = i + 1;
                this.chunkLabel = 'Syncing ' + chunks[i].label + '...';
                this.syncStatusText = chunks.length > 1 ? ('Month ' + (i+1) + '/' + chunks.length) : 'Syncing...';
                try {
                    const resp = await fetch('{{ route("import.odoo.sync") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ date_from: chunks[i].from, date_to: chunks[i].to, account_codes: this.selectedAccounts })
                    });
                    if (!resp.ok) { errors.push(chunks[i].label + ': HTTP ' + resp.status); continue; }
                    const data = await resp.json();
                    if (data.success) {
                        totalCount += (data.count || 0);
                    } else {
                        errors.push(chunks[i].label + ': ' + (data.message || 'failed'));
                    }
                } catch (e) {
                    errors.push(chunks[i].label + ': network error');
                }
            }

            this.syncing = false;
            this.chunkCurrent = this.chunkTotal;
            if (errors.length === 0) {
                this.syncMsg = 'Synced ' + totalCount + ' entries across ' + chunks.length + ' month(s).';
                this.syncMsgType = 'success';
            } else if (errors.length < chunks.length) {
                this.syncMsg = 'Partially synced ' + totalCount + ' entries. Errors: ' + errors.join('; ');
                this.syncMsgType = 'error';
            } else {
                this.syncMsg = 'Sync failed: ' + errors.join('; ');
                this.syncMsgType = 'error';
            }
        },
        
        async loadHistory() {
            this.historyLoading = true;
            try {
                const resp = await fetch('{{ route("import.history") }}');
                this.historyLogs = await resp.json();
            } catch (e) {}
            this.historyLoading = false;
        },
    }
}
</script>
@endsection
