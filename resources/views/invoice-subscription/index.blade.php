@extends('layouts.app')

@section('title', 'Cek Invoice Subscription')
@section('subtitle', 'Monitoring Invoice Subscription dari ' . \Carbon\Carbon::parse($dateWindow['from'])->format('d M Y') . ' s/d ' . \Carbon\Carbon::parse($dateWindow['to'])->format('d M Y'))

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .resizer {
        position: absolute;
        top: 0;
        right: 0;
        width: 4px;
        cursor: col-resize;
        user-select: none;
        height: 100%;
        z-index: 50;
    }
    .resizer:hover, .resizing .resizer {
        background: rgba(16, 185, 129, 0.5);
    }
    .dragging-column {
        opacity: 0.5;
        background: rgba(236, 253, 245, 1) !important;
    }
    .sortable-ghost {
        opacity: 0.2;
        background: #10b981 !important;
    }
    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
    }
    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.2);
        border-radius: 10px;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.4);
    }
</style>

<div x-data="{
    syncOpen: false,
    syncing: false,
    syncProgress: 0,
    syncCurrentStep: '',
    syncResults: [],
    selectedIds: [],
    exportOpen: false,
    columns: {{ json_encode($tablePrefs['columns']) }},
    generateChunks() {
        // Start from 2025-04-01
        const start = new Date(2025, 3, 1); 
        const end = new Date();
        end.setDate(end.getDate() + 15);
        
        const chunks = [];
        let current = new Date(start.getFullYear(), start.getMonth(), 1);
        
        while (current <= end) {
            const chunkStart = new Date(current);
            const chunkEnd = new Date(current.getFullYear(), current.getMonth() + 1, 0);
            
            const formatDate = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            chunks.push({
                from: formatDate(chunkStart),
                to: formatDate(chunkEnd),
                label: chunkStart.toLocaleString('default', { month: 'long', year: 'numeric' })
            });
            
            current.setMonth(current.getMonth() + 1);
        }
        return chunks;
    },
    async doSync() {
        const chunks = this.generateChunks();
        this.syncing = true;
        this.syncProgress = 0;
        this.syncResults = [];
        
        for (let i = 0; i < chunks.length; i++) {
            const chunk = chunks[i];
            this.syncCurrentStep = `Processing ${chunk.label}...`;
            
            try {
                const url = `{{ route('invoice-subscription.sync', [], false) }}?from=${chunk.from}&to=${chunk.to}`;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                this.syncResults.push({ 
                    label: chunk.label, 
                    success: data.success, 
                    count: data.count || 0,
                    message: data.message
                });
            } catch (e) {
                this.syncResults.push({ 
                    label: chunk.label, 
                    success: false, 
                    error: e.message 
                });
            }
            
            this.syncProgress = Math.round(((i + 1) / chunks.length) * 100);
        }
        
        this.syncCurrentStep = 'Sync Finished!';
        setTimeout(() => window.location.reload(), 2000);
    },
    toggleAll(checked) {
        if (checked) {
            const pageIds = [{{ implode(',', $records->pluck('id')->toArray()) }}];
            this.selectedIds = [...new Set([...this.selectedIds, ...pageIds])];
        } else {
            const pageIds = [{{ implode(',', $records->pluck('id')->toArray()) }}];
            this.selectedIds = this.selectedIds.filter(id => !pageIds.includes(id));
        }
    },
    isAllSelected() {
        const pageIds = [{{ implode(',', $records->pluck('id')->toArray()) }}];
        return pageIds.length > 0 && pageIds.every(id => this.selectedIds.includes(id));
    },
    doExport(format, mode = 'selected') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('invoice-subscription.export') }}';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name=csrf-token]').content;
        form.appendChild(csrf);

        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'format';
        formatInput.value = format;
        form.appendChild(formatInput);

        // Send visible columns
        const visibleCols = this.columns
            .filter(c => c.visible && c.id !== 'status')
            .map(c => ({ id: c.id, label: c.label }));
        
        // Add status if visible
        if (this.columns.some(c => c.visible && c.id === 'status')) {
            visibleCols.push({ id: 'status', label: 'Status' });
        }

        const colsInput = document.createElement('input');
        colsInput.type = 'hidden';
        colsInput.name = 'columns';
        colsInput.value = JSON.stringify(visibleCols);
        form.appendChild(colsInput);

        if (mode === 'selected') {
            if (this.selectedIds.length === 0) {
                alert('Please select at least one record.');
                return;
            }
            this.selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = id;
                form.appendChild(input);
            });
        } else {
            // All (Filtered) - include current query params
            const params = new URLSearchParams(window.location.search);
            for (const [key, value] of params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        this.exportOpen = false;
    },
    async savePrefs() {
        try {
            await fetch('{{ route('invoice-subscription.preferences.update') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ columns: this.columns })
            });
        } catch (e) {
            console.error('Failed to save preferences', e);
        }
    },
    async resetPrefs() {
        if (!confirm('Are you sure you want to reset table layout to default?')) return;
        try {
            await fetch('{{ route('invoice-subscription.preferences.reset') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                }
            });
            window.location.reload();
        } catch (e) {
            console.error('Failed to reset preferences', e);
        }
    },
    // Resize Logic
    resizingIndex: null,
    startX: 0,
    startWidth: 0,
    startResize(e, index) {
        this.resizingIndex = index;
        this.startX = e.pageX;
        this.startWidth = parseInt(this.columns[index].width);
        document.body.classList.add('resizing');
        
        const onMouseMove = (me) => {
            if (this.resizingIndex === null) return;
            const diff = me.pageX - this.startX;
            this.columns[this.resizingIndex].width = Math.max(50, this.startWidth + diff).toString();
        };
        
        const onMouseUp = () => {
            this.resizingIndex = null;
            document.body.classList.remove('resizing');
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
            this.savePrefs();
        };
        
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    },
    initSortable() {
        const el = this.$refs.tableHeader;
        Sortable.create(el, {
            animation: 150,
            handle: '.sort-handle',
            ghostClass: 'sortable-ghost',
            filter: '.no-sort', // Ignore the checkbox row
            onEnd: (evt) => {
                // Adjust for the persistent checkbox column at index 0
                const oldIndex = evt.oldIndex - 1;
                const newIndex = evt.newIndex - 1;
                
                if (oldIndex < 0 || newIndex < 0) return;

                const newColumns = [...this.columns];
                const item = newColumns.splice(oldIndex, 1)[0];
                newColumns.splice(newIndex, 0, item);
                this.columns = newColumns;
                this.savePrefs();
                window.location.reload(); 
            }
        });

        // Menu Sortable
        const menuEl = this.$refs.columnMenu;
        Sortable.create(menuEl, {
            animation: 150,
            handle: '.menu-sort-handle',
            ghostClass: 'sortable-ghost',
            onEnd: (evt) => {
                const newColumns = [...this.columns];
                const item = newColumns.splice(evt.oldIndex, 1)[0];
                newColumns.splice(evt.newIndex, 0, item);
                this.columns = newColumns;
                this.savePrefs();
                window.location.reload();
            }
        });
    }
}" x-init="initSortable()">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-slate-700 dark:text-slate-200">{{ number_format($stats['total']) }}</p>
            <p class="text-xs text-slate-500">Total Periods in Window</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border {{ $stats['not_invoiced'] > 0 ? 'border-red-300 dark:border-red-800 bg-red-50/30' : 'border-slate-200 dark:border-slate-700' }} p-4">
            <p class="text-2xl font-bold text-red-500">{{ number_format($stats['not_invoiced']) }}</p>
            <p class="text-xs text-slate-500">Not Invoiced</p>
            @if($stats['overdue'] > 0)
                <p class="text-[10px] text-red-600 mt-1 font-medium">{{ $stats['overdue'] }} Overdue</p>
            @endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-yellow-500">{{ number_format($stats['draft']) }}</p>
            <p class="text-xs text-slate-500">Draft Status</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-orange-500">{{ number_format($stats['unpaid']) }}</p>
            <p class="text-xs text-slate-500">Posted/Unpaid</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-emerald-500">{{ number_format($stats['paid']) }}</p>
            <p class="text-xs text-slate-500">Paid Invoices</p>
        </div>
    </div>

    {{-- Filters & Actions --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-6 p-4">
        <form method="GET" action="{{ route('invoice-subscription.index', [], false) }}">
            
            {{-- Tabs for Status --}}
            <div class="flex space-x-1 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto pb-px">
                @php $tabs = ['all' => 'All Status', 'not_invoiced' => 'Not Invoiced', 'draft' => 'Draft', 'unpaid' => 'Unpaid', 'paid' => 'Paid']; @endphp
                @foreach($tabs as $val => $label)
                    <button type="submit" name="status" value="{{ $val }}" class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors {{ $statusFilter === $val ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-end gap-3 mb-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="SO #, customer, invoice..."
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Rental Status</label>
                    <select name="rental_status" class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="all">All</option>
                        <option value="Reserved" {{ request('rental_status') == 'Reserved' ? 'selected' : '' }}>Reserved</option>
                        <option value="Pickedup" {{ request('rental_status') == 'Pickedup' ? 'selected' : '' }}>Pickedup</option>
                        <option value="Returned" {{ request('rental_status') == 'Returned' ? 'selected' : '' }}>Returned</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Invoice Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Invoice Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="flex gap-2 items-end pb-[2px]">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">Filter</button>
                    <a href="{{ route('invoice-subscription.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Clear</a>
                    <button type="button" @click="syncOpen = !syncOpen" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync Odoo
                    </button>
                    
                    {{-- Export Data Menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-1 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M16 9l-4-4-4 4M12 5v13"/></svg>
                            Export
                            <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl z-[60] py-1 overflow-hidden">
                            <div class="px-3 py-1.5 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50/50 dark:bg-white/5 border-b border-slate-100 dark:border-slate-700/50 mb-1">Export Selected (<span x-text="selectedIds.length"></span>)</div>
                            <button type="button" @click="doExport('excel', 'selected'); open = false" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M15.8,20H14L12,16.6L10,20H8.2L11,15.5L8.2,11H10L12,14.4L14,11H15.8L13,15.5L15.8,20M13,9V3.5L18.5,9H13Z"/></svg>
                                Excel (.xls)
                            </button>
                            <button type="button" @click="doExport('csv', 'selected'); open = false" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                CSV File
                            </button>
                            
                            <div class="border-t border-slate-100 dark:border-slate-700/50 my-1"></div>
                            
                            <div class="px-3 py-1.5 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider bg-slate-50/50 dark:bg-white/5 border-b border-slate-100 dark:border-slate-700/50 mb-1">Export All (Filtered)</div>
                            <button type="button" @click="doExport('excel', 'all'); open = false" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M15.8,20H14L12,16.6L10,20H8.2L11,15.5L8.2,11H10L12,14.4L14,11H15.8L13,15.5L15.8,20M13,9V3.5L18.5,9H13Z"/></svg>
                                Excel (.xls)
                            </button>
                            <button type="button" @click="doExport('csv', 'all'); open = false" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                CSV File
                            </button>
                        </div>
                    </div>

                    {{-- Column Visibility Menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            Columns
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 p-2">
                            <div class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase border-b border-slate-100 dark:border-slate-700 mb-2">Toggle Columns</div>
                            <div class="space-y-1 overflow-y-auto max-h-64 scrollbar-thin" x-ref="columnMenu">
                                <template x-for="(col, index) in columns" :key="col.id">
                                    <div class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg group transition-colors">
                                        {{-- Drag handle --}}
                                        <div class="menu-sort-handle cursor-move text-slate-300 hover:text-emerald-500 transition-colors">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M7 15h2V17H7V15M15 15h2V17h-2V15M11 15h2V17h-2V15M7 11h2V13H7V11M15 11h2V13h-2V11M11 11h2V13h-2V11M7 7h2V9H7V7M15 7h2V9h-2V7M11 7h2V9h-2V7Z"/></svg>
                                        </div>
                                        <label class="flex-1 flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="col.visible" @change="savePrefs()" class="rounded border-slate-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500">
                                            <span class="text-[13px] text-slate-700 dark:text-slate-300 truncate" x-text="col.label"></span>
                                        </label>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                                <button type="button" @click="resetPrefs()" class="w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Reset to Default
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Sync Panel --}}
        <div x-show="syncOpen" x-cloak x-transition class="mt-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Sync Subscription Invoice Periods</h3>
            <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">
                This will fetch all subscription rental periods falling between 
                <strong class="font-mono">{{ $dateWindow['from'] }}</strong> and <strong class="font-mono">{{ $dateWindow['to'] }}</strong>.
                <br>
                @if($lastSync)
                    Last sync: {{ $lastSync->imported_at->diffForHumans() }} ({{ $lastSync->items_count }} items)
                @else
                    Never synced.
                @endif
            </p>
            <div class="flex flex-wrap items-end gap-3">
                <button @click="doSync()" :disabled="syncing" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <svg x-show="syncing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="syncing ? 'Syncing...' : 'Start Chunked Sync (Monthly)'"></span>
                </button>
            </div>
            
            {{-- Progress State --}}
            <div x-show="syncing || syncResults.length > 0" x-cloak class="mt-4 p-3 bg-white/50 dark:bg-slate-800/50 rounded-lg border border-blue-100 dark:border-blue-900">
                <div class="flex justify-between items-center text-xs font-semibold text-blue-800 dark:text-blue-300 mb-2">
                    <span x-text="syncCurrentStep"></span>
                    <span x-text="syncProgress + '%'"></span>
                </div>
                
                {{-- Progress Bar --}}
                <div class="w-full bg-blue-100 dark:bg-slate-700 rounded-full h-2 mb-3">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + syncProgress + '%'"></div>
                </div>

                {{-- Detail Results --}}
                <div class="space-y-1 max-h-40 overflow-y-auto custom-scrollbar pr-2">
                    <template x-for="(res, index) in syncResults.slice().reverse()" :key="index">
                        <div class="flex justify-between text-[10px] items-center py-1 border-b border-blue-50 dark:border-blue-900 last:border-0">
                            <span class="font-medium text-slate-600 dark:text-slate-400" x-text="res.label"></span>
                            <div class="flex items-center gap-2">
                                <span x-show="res.success" class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="'+' + res.count"></span>
                                <span x-show="!res.success" class="text-red-500" x-text="'Failed'"></span>
                                <svg x-show="res.success" class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto overflow-y-auto max-h-[75vh]">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 select-none">
                    <tr x-ref="tableHeader">
                        <th class="no-sort px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-50 w-10">
                            <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="isAllSelected()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        </th>
                        @foreach($tablePrefs['columns'] as $index => $col)
                            <th x-show="columns[{{ $index }}].visible" 
                                :style="{ width: columns[{{ $index }}].width + 'px' }"
                                class="relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40 group">
                                
                                <div class="flex items-center gap-1">
                                    {{-- Drag handle --}}
                                    <div class="sort-handle cursor-move opacity-0 group-hover:opacity-100 transition-opacity text-slate-400">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M7 15h2V17H7V15M15 15h2V17h-2V15M11 15h2V17h-2V15M7 11h2V13H7V11M15 11h2V13h-2V11M11 11h2V13h-2V11M7 7h2V9H7V7M15 7h2V9h-2V7M11 7h2V9h-2V7Z"/></svg>
                                    </div>

                                    @if($col['sortable'])
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => $col['id'], 'dir' => request('sort') === $col['id'] && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors truncate">
                                            {{ $col['label'] }}
                                            @if(request('sort', 'invoice_date') === $col['id'])
                                                <svg class="w-3 h-3 ml-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'asc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                            @endif
                                        </a>
                                    @else
                                        <span class="truncate">{{ $col['label'] }}</span>
                                    @endif
                                </div>

                                {{-- Resize handle --}}
                                <div class="resizer" @mousedown="startResize($event, {{ $index }})"></div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $rec)
                        @php
                            $rowClass = 'border-t border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors';
                            
                            $status = $rec->status;
                            $statusBadge = '';
                            
                            if ($status === 'not_invoiced') {
                                if ($rec->is_overdue) {
                                    $rowClass .= ' bg-red-50/50 dark:bg-red-900/10 hover:bg-red-50 dark:hover:bg-red-900/20';
                                    $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800">Not Invoiced (Overdue)</span>';
                                } else {
                                    $rowClass .= ' bg-amber-50/50 dark:bg-amber-900/10 hover:bg-amber-50 dark:hover:bg-amber-900/20';
                                    $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800">Not Invoiced (Upcoming)</span>';
                                }
                            } elseif ($status === 'draft') {
                                $rowClass .= ' bg-yellow-50/50 dark:bg-yellow-900/10 hover:bg-yellow-50 dark:hover:bg-yellow-900/20';
                                $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">Draft</span>';
                            } elseif ($status === 'paid') {
                                $rowClass .= ' bg-emerald-50/50 dark:bg-emerald-900/10 hover:bg-emerald-50 dark:hover:bg-emerald-900/20';
                                $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">Paid</span>';
                            } elseif ($status === 'unpaid') {
                                $rowClass .= ' bg-orange-50/50 dark:bg-orange-900/10 hover:bg-orange-50 dark:hover:bg-orange-900/20';
                                $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300 border border-orange-200 dark:border-orange-800">Posted / Unpaid</span>';
                            }
                        @endphp
                        
                        <tr class="{{ $rowClass }}" :class="selectedIds.includes({{ $rec->id }}) ? '!bg-emerald-50/50 dark:!bg-emerald-900/20' : ''">
                            <td class="px-3 py-3 text-left">
                                <input type="checkbox" :value="{{ $rec->id }}" x-model="selectedIds" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                            </td>
                            @foreach($tablePrefs['columns'] as $index => $col)
                                <td x-show="columns[{{ $index }}].visible" class="px-3 py-3 text-xs">
                                    @if($col['id'] === 'so_name')
                                        <div class="font-mono font-semibold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                            {{ $rec->so_name }}
                                        </div>
                                    @elseif($col['id'] === 'partner_name')
                                        {{ $rec->partner_name }}
                                    @elseif($col['id'] === 'rental_status')
                                        @if($rec->rental_status === 'Pickedup')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Pickedup</span>
                                        @elseif($rec->rental_status === 'Reserved')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">Reserved</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">{{ $rec->rental_status }}</span>
                                        @endif
                                    @elseif($col['id'] === 'rental_type')
                                        {{ $rec->rental_type }}
                                    @elseif($col['id'] === 'period_type')
                                        {{ $rec->period_type }}
                                    @elseif($col['id'] === 'product_name')
                                        <div class="truncate max-w-[200px]" title="{{ $rec->product_name }}">{{ $rec->product_name }}</div>
                                    @elseif($col['id'] === 'period_start')
                                        <div class="text-slate-500 whitespace-nowrap">
                                            {{ $rec->period_start ? $rec->period_start->format('Y-m-d') : '-' }} to {{ $rec->period_end ? $rec->period_end->format('Y-m-d') : '-' }}
                                        </div>
                                    @elseif($col['id'] === 'period_end')
                                        <span class="text-slate-500 whitespace-nowrap">{{ $rec->period_end ? $rec->period_end->format('Y-m-d') : '-' }}</span>
                                    @elseif($col['id'] === 'actual_start_rental')
                                        <span class="text-slate-500 whitespace-nowrap">{{ $rec->actual_start_rental ? \Carbon\Carbon::parse($rec->actual_start_rental)->format('Y-m-d') : '-' }}</span>
                                    @elseif($col['id'] === 'actual_end_rental')
                                        <span class="text-slate-500 whitespace-nowrap">{{ $rec->actual_end_rental ? \Carbon\Carbon::parse($rec->actual_end_rental)->format('Y-m-d') : '-' }}</span>
                                    @elseif($col['id'] === 'invoice_date')
                                        <span class="font-mono {{ $rec->is_overdue ? 'text-red-600 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300' }}">
                                            {{ $rec->invoice_date ? $rec->invoice_date->format('Y-m-d') : '-' }}
                                        </span>
                                    @elseif($col['id'] === 'invoice_name')
                                        <div class="font-mono font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                            @if(empty($rec->invoice_name))
                                                <span class="text-slate-400 font-normal">-</span>
                                            @else
                                                <span title="{{ $rec->invoice_ref }}">{{ $rec->invoice_name }}</span>
                                            @endif
                                        </div>
                                    @elseif($col['id'] === 'invoice_ref')
                                        <span class="font-mono text-slate-500">{{ $rec->invoice_ref ?: '-' }}</span>
                                    @elseif($col['id'] === 'invoice_state')
                                        <span class="capitalize">{{ $rec->invoice_state ?: '-' }}</span>
                                    @elseif($col['id'] === 'payment_state')
                                        <span class="capitalize">{{ $rec->payment_state ?: '-' }}</span>
                                    @elseif($col['id'] === 'price_unit')
                                        {{ number_format($rec->price_unit, 2) }}
                                    @elseif($col['id'] === 'rental_uom')
                                        {{ $rec->rental_uom ?: '-' }}
                                    @elseif($col['id'] === 'status')
                                        <div class="whitespace-nowrap">
                                            {!! $statusBadge !!}
                                        </div>
                                    @elseif($col['id'] === 'synced_at')
                                        <span class="text-[10px] text-slate-400">{{ $rec->synced_at ? $rec->synced_at->diffForHumans() : '-' }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="100" class="px-4 py-12 text-center">
                                <div class="text-slate-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="text-lg font-medium">No subscription periods found</p>
                                    <p class="text-sm mt-1">Use the <strong>Sync Odoo</strong> button above to import data from Odoo, or adjust filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">
            {{ $records->links() }}
        </div>
        @endif
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mt-3">
        <p class="text-xs text-slate-500">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries</p>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500">Show:</span>
            <div class="flex bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 shadow-sm">
                @foreach([10, 25, 50, 100] as $count)
                <a href="{{ request()->fullUrlWithQuery(['per_page' => $count]) }}"
                   class="px-2 py-1 text-[10px] font-bold rounded-md transition-colors {{ $perPage == $count ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                    {{ $count }}
                </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
