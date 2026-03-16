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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Odoo Configuration Card --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Odoo Configuration
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Server URL</label>
                        <input type="url" x-model="odooConfig.url" placeholder="https://your-odoo.com"
                            class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Database</label>
                        <input type="text" x-model="odooConfig.db" placeholder="odoo_db"
                            class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Username / Email</label>
                        <input type="text" x-model="odooConfig.user" placeholder="admin@example.com"
                            class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Password / API Key</label>
                        <input type="password" x-model="odooConfig.password" placeholder="••••••••"
                            class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>

                    <div class="flex gap-3">
                        <button @click="saveConfig()" :disabled="saving" class="flex-1 py-2.5 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700 disabled:opacity-50 transition-colors">
                            <span x-text="saving ? 'Saving...' : 'Save Config'"></span>
                        </button>
                        <button @click="testConnection()" :disabled="testing" class="flex-1 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                            <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
                        </button>
                    </div>

                    {{-- Status message --}}
                    <div x-show="configMsg" x-cloak x-transition class="p-3 rounded-lg text-sm" :class="configMsgType === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700'">
                        <span x-text="configMsg"></span>
                    </div>
                </div>
            </div>

            {{-- Sync Card --}}
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Sync Journal Entries
                </h3>

                <div class="space-y-4">
                    {{-- Date Range --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Date From</label>
                            <input type="date" x-model="syncConfig.dateFrom"
                                class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Date To</label>
                            <input type="date" x-model="syncConfig.dateTo"
                                class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                        </div>
                    </div>

                    {{-- Account Filter Checkboxes --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-2">Account Filter</label>
                        <div class="space-y-2 bg-slate-50 dark:bg-slate-900 rounded-lg p-4 border border-slate-200 dark:border-slate-700">
                            <label class="flex items-center gap-2 text-sm cursor-pointer mb-3">
                                <input type="checkbox" @change="toggleAllAccounts($event.target.checked)" 
                                    :checked="selectedAccounts.length === Object.keys(availableAccounts).length"
                                    class="w-4 h-4 text-emerald-500 bg-slate-900 border-slate-600 rounded focus:ring-emerald-500">
                                <span class="font-medium text-slate-700 dark:text-slate-300">Select All</span>
                            </label>
                            <hr class="border-slate-200 dark:border-slate-700">
                            <template x-for="(label, code) in availableAccounts" :key="code">
                                <label class="flex items-center gap-2 text-sm cursor-pointer py-1">
                                    <input type="checkbox" :value="code" x-model="selectedAccounts"
                                        class="w-4 h-4 text-emerald-500 bg-slate-900 border-slate-600 rounded focus:ring-emerald-500">
                                    <span class="text-slate-600 dark:text-slate-400 font-mono" x-text="code"></span>
                                    <span class="text-slate-800 dark:text-slate-200" x-text="label"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Sync Button --}}
                    <button @click="syncOdoo()" :disabled="syncing || !syncConfig.dateFrom || !syncConfig.dateTo" class="w-full py-3 bg-gradient-to-r from-emerald-500 to-cyan-500 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-cyan-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-emerald-500/25">
                        <span x-show="!syncing" class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Sync Now
                        </span>
                        <span x-show="syncing" class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-text="syncStatusText">Syncing...</span>
                        </span>
                    </button>

                    {{-- Progress bar (shown when chunked sync is running) --}}
                    <div x-show="syncing && chunkTotal > 1" x-cloak>
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span x-text="'Month ' + chunkCurrent + ' of ' + chunkTotal"></span>
                            <span x-text="Math.round((chunkCurrent - 1) / chunkTotal * 100) + '%'"></span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                :style="'width:' + Math.round((chunkCurrent - 1) / chunkTotal * 100) + '%'"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1" x-text="chunkLabel"></p>
                    </div>

                    {{-- Sync status --}}
                    <div x-show="syncMsg" x-cloak x-transition class="p-3 rounded-lg text-sm" :class="syncMsgType === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700'">
                        <span x-text="syncMsg"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schedule Card (full width below) --}}
        <div class="mt-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Auto-Sync Schedule
            </h3>
            <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" x-model="schedule.enabled" class="w-4 h-4 text-emerald-500 bg-slate-900 border-slate-600 rounded focus:ring-emerald-500">
                    <span>Enable Auto-Sync</span>
                </label>
                <select x-model="schedule.interval" :disabled="!schedule.enabled" class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 disabled:opacity-50 transition">
                    <option value="hourly">Every Hour</option>
                    <option value="every_2_hours">Every 2 Hours</option>
                    <option value="every_4_hours">Every 4 Hours</option>
                    <option value="every_6_hours">Every 6 Hours</option>
                    <option value="every_12_hours">Every 12 Hours</option>
                    <option value="daily">Daily</option>
                </select>
                <button @click="saveSchedule()" :disabled="scheduleSaving" class="px-6 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 disabled:opacity-50 transition-colors">
                    <span x-text="scheduleSaving ? 'Saving...' : 'Save Schedule'"></span>
                </button>
                <span x-show="scheduleMsg" x-cloak x-transition class="text-sm" :class="scheduleMsgType === 'success' ? 'text-emerald-500' : 'text-red-500'" x-text="scheduleMsg"></span>
            </div>
            <p x-show="schedule.lastSync" class="text-xs text-slate-500 mt-3">Last sync: <span x-text="schedule.lastSync"></span></p>
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
        
        // Odoo config
        odooConfig: {
            url: @json($odooConfig['url'] ?? ''),
            db: @json($odooConfig['db'] ?? ''),
            user: @json($odooConfig['user'] ?? ''),
            password: @json($odooConfig['password'] ?? ''),
        },
        saving: false,
        testing: false,
        configMsg: '',
        configMsgType: '',
        
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
        
        // Schedule
        schedule: { enabled: false, interval: 'daily', lastSync: null },
        scheduleSaving: false,
        scheduleMsg: '',
        scheduleMsgType: '',
        
        // History
        historyLogs: [],
        historyLoading: false,
        
        init() {
            this.loadSchedule();
        },
        
        toggleAllAccounts(checked) {
            if (checked) {
                this.selectedAccounts = Object.keys(this.availableAccounts);
            } else {
                this.selectedAccounts = [];
            }
        },
        
        async saveConfig() {
            this.saving = true;
            this.configMsg = '';
            try {
                const resp = await fetch('{{ route("import.odoo.config") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ odoo_url: this.odooConfig.url, odoo_db: this.odooConfig.db, odoo_user: this.odooConfig.user, odoo_password: this.odooConfig.password })
                });
                const data = await resp.json();
                this.configMsg = data.message;
                this.configMsgType = data.success ? 'success' : 'error';
            } catch (e) { this.configMsg = 'Network error'; this.configMsgType = 'error'; }
            this.saving = false;
        },
        
        async testConnection() {
            this.testing = true;
            this.configMsg = '';
            try {
                const resp = await fetch('{{ route("import.odoo.test") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const data = await resp.json();
                this.configMsg = data.message;
                this.configMsgType = data.success ? 'success' : 'error';
            } catch (e) { this.configMsg = 'Network error'; this.configMsgType = 'error'; }
            this.testing = false;
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
        
        async loadSchedule() {
            try {
                const resp = await fetch('{{ route("import.odoo.schedule.get") }}');
                const data = await resp.json();
                this.schedule = { enabled: data.enabled, interval: data.interval, lastSync: data.last_sync };
            } catch (e) {}
        },
        
        async saveSchedule() {
            this.scheduleSaving = true;
            this.scheduleMsg = '';
            try {
                const resp = await fetch('{{ route("import.odoo.schedule.save") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ enabled: this.schedule.enabled, interval: this.schedule.interval })
                });
                const data = await resp.json();
                this.scheduleMsg = data.message;
                this.scheduleMsgType = data.success ? 'success' : 'error';
                setTimeout(() => this.scheduleMsg = '', 3000);
            } catch (e) { this.scheduleMsg = 'Network error'; this.scheduleMsgType = 'error'; }
            this.scheduleSaving = false;
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
