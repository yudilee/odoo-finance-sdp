@extends('layouts.app')

@section('title', 'Journal Entries')
@section('subtitle', 'Imported journal entries from Odoo')

@section('content')
<div x-data="{ 
    expandedEntry: null,
    columns: {
        name: { visible: true, width: '180px', label: 'Name' },
        date: { visible: true, width: '100px', label: 'Date' },
        partner: { visible: true, width: '150px', label: 'Partner' },
        ref: { visible: true, width: '120px', label: 'Ref' },
        total: { visible: true, width: '120px', label: 'Amount' },
        account: { visible: true, width: '180px', label: 'Account' },
        desc: { visible: true, width: '200px', label: 'Description' },
        line_ref: { visible: true, width: '140px', label: 'Line Ref' },
        debit: { visible: true, width: '100px', label: 'Debit' },
        credit: { visible: true, width: '100px', label: 'Credit' }
    },
    init() {
        const saved = localStorage.getItem('journal_column_settings');
        if (saved) {
            const parsed = JSON.parse(saved);
            Object.keys(this.columns).forEach(key => {
                if (parsed[key]) {
                    this.columns[key].visible = parsed[key].visible;
                    this.columns[key].width = parsed[key].width;
                }
            });
        }
        this.$watch('columns', value => {
            localStorage.setItem('journal_column_settings', JSON.stringify(value));
        }, { deep: true });
    },
    resize(key, event) {
        const startX = event.pageX;
        const startWidth = parseInt(this.columns[key].width);
        
        const mouseMoveHandler = (e) => {
            const diff = e.pageX - startX;
            const newWidth = Math.max(60, startWidth + diff);
            this.columns[key].width = newWidth + 'px';
        };
        
        const mouseUpHandler = () => {
            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);
            document.body.style.cursor = 'default';
            document.body.style.userSelect = 'auto';
        };
        
        document.addEventListener('mousemove', mouseMoveHandler);
        document.addEventListener('mouseup', mouseUpHandler);
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    },
    get visibleColumnCount() {
        return Object.values(this.columns).filter(c => c.visible).length + 1;
    }
}">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-emerald-500">{{ number_format($stats['total_entries']) }}</p>
            <p class="text-xs text-slate-500">Total Entries</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-cyan-500">{{ number_format($stats['total_debit'], 2) }}</p>
            <p class="text-xs text-slate-500">Total Debit</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-violet-500">{{ number_format($stats['total_credit'], 2) }}</p>
            <p class="text-xs text-slate-500">Total Credit</p>
        </div>
    </div>

    {{-- Filters --}}
    <div x-data="{ filtersOpen: true }" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-6 overflow-hidden transition-all duration-300">
        {{-- Collapsed Header --}}
        <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between cursor-pointer group" @click="filtersOpen = !filtersOpen">
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1 text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="font-medium">Filters</span>
                </div>
                <div class="hidden md:flex items-center gap-2 text-xs text-slate-400">
                    @if(request('date_from')) <span>From: {{ request('date_from') }}</span> @endif
                    @if(request('date_to')) <span>To: {{ request('date_to') }}</span> @endif
                    @if(request('flow_type') && request('flow_type') !== 'all') 
                        <span class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-700 rounded capitalize">{{ request('flow_type') }}</span> 
                    @endif
                    @if(count($selectedAccounts) < count($accountCodes))
                        <span class="px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded">{{ count($selectedAccounts) }} Accounts</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Column Settings Dropdown --}}
                <div x-data="{ open: false }" class="relative" @click.stop="">
                    <button @click="open = !open" class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <span>Columns</span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-[60] p-2">
                        <template x-for="(col, key) in columns" :key="key">
                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg cursor-pointer transition-colors">
                                <input type="checkbox" x-model="col.visible" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-3.5 h-3.5 dark:bg-slate-900 dark:border-slate-600">
                                <span class="text-xs text-slate-700 dark:text-slate-300" x-text="col.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <button class="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
        </div>

        <div x-show="filtersOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="p-4 border-t border-slate-200 dark:border-slate-700">
            <form method="GET" action="{{ route('journals.index', [], false) }}">
            <input type="hidden" name="filter_applied" value="1">

            {{-- Row 1: Search + Dates + Account --}}
            <div class="flex flex-wrap items-end gap-3 mb-3">
                {{-- Search --}}
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Move name, partner, reference..."
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                {{-- Date From --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                {{-- Date To --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                {{-- Account Filter --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Account</label>
                    <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg max-h-[100px] overflow-y-auto min-w-[200px]">
                        @foreach($accountCodes as $acc)
                            <label class="flex items-start gap-2 mb-2 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 p-1 -m-1 rounded transition-colors pr-2">
                                <input type="checkbox" name="accounts[]" value="{{ $acc->account_code }}" {{ in_array($acc->account_code, $selectedAccounts) ? 'checked' : '' }} class="mt-0.5 text-emerald-500 rounded border-slate-300 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800">
                                <span class="text-xs text-slate-700 dark:text-slate-300 font-medium leading-tight">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-mono">{{ $acc->account_code }}</span><br>
                                    <span class="text-[10px] text-slate-500 font-normal">{{ $acc->account_name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Row 2: Journal + Flow + Buttons --}}
            <div class="flex flex-wrap items-end gap-3">
                {{-- Journal Filter --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Journal</label>
                    <select name="journal" class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="">All Journals</option>
                        @foreach($journalNames as $jn)
                            <option value="{{ $jn }}" {{ request('journal') == $jn ? 'selected' : '' }}>{{ $jn }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Flow Filter --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Cash/Bank Flow</label>
                    <select name="flow_type" class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="all" {{ $flowType === 'all' ? 'selected' : '' }}>All Flows</option>
                        <option value="debit" {{ $flowType === 'debit' ? 'selected' : '' }}>Debit Only (Inflow)</option>
                        <option value="credit" {{ $flowType === 'credit' ? 'selected' : '' }}>Credit Only (Outflow)</option>
                    </select>
                </div>
                {{-- Action Buttons --}}
                <div class="flex gap-2 items-end pb-[2px]">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">Filter</button>
                    <a href="{{ route('journals.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Clear</a>
                    <a href="{{ route('journals.print-all-html', request()->all()) }}" target="_blank" title="Direct Print All" class="px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span class="hidden md:inline">Print All</span>
                    </a>
                    <a href="{{ route('journals.print-all', request()->all()) }}" target="_blank" title="Export All PDF" class="px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span class="hidden md:inline">PDF All</span>
                    </a>
                    <button type="submit" form="bulkPrintForm" formaction="{{ route('journals.print-selected-html', [], false) }}" class="printSelectedBtn px-3 py-2 bg-sky-600 text-white text-sm font-medium rounded-lg hover:bg-sky-700 transition-colors flex items-center gap-1 opacity-50 cursor-not-allowed" disabled title="Direct Print Selected">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span class="hidden md:inline">Print (<span class="selectedCount">0</span>)</span>
                    </button>
                    <button type="submit" form="bulkPrintForm" formaction="{{ route('journals.print-selected', [], false) }}" class="printSelectedBtn px-3 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors flex items-center gap-1 opacity-50 cursor-not-allowed" disabled title="Export Selected PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span class="hidden md:inline">PDF (<span class="selectedCountSm">0</span>)</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <form id="bulkPrintForm" method="POST" action="{{ route('journals.print-selected', [], false) }}" target="_blank">
        @csrf
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto overflow-y-auto max-h-[75vh]">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 select-none">
                    <tr>
                        <th class="px-3 py-3 w-10 text-center border-b border-slate-200 dark:border-slate-700 sticky top-0 left-0 bg-slate-50 dark:bg-slate-900 z-50">
                            <input type="checkbox" id="selectAllCheckbox" title="Select All" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer mt-0.5 dark:bg-slate-800 dark:border-slate-600">
                        </th>
                        
                        {{-- Name --}}
                        <th x-show="columns.name.visible" :style="{ width: columns.name.width, minWidth: columns.name.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 left-10 bg-slate-50 dark:bg-slate-900 z-50">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'move_name', 'dir' => request('sort') === 'move_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Name
                                @if(request('sort', 'date') === 'move_name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                            <div @mousedown="resize('name', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Date --}}
                        <th x-show="columns.date.visible" :style="{ width: columns.date.width, minWidth: columns.date.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'date', 'dir' => request('sort', 'date') === 'date' && request('dir', 'desc') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Date
                                @if(request('sort', 'date') === 'date')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                            <div @mousedown="resize('date', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Partner --}}
                        <th x-show="columns.partner.visible" :style="{ width: columns.partner.width, minWidth: columns.partner.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'partner_name', 'dir' => request('sort') === 'partner_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Partner
                                @if(request('sort') === 'partner_name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                            <div @mousedown="resize('partner', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Ref --}}
                        <th x-show="columns.ref.visible" :style="{ width: columns.ref.width, minWidth: columns.ref.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'ref', 'dir' => request('sort') === 'ref' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Ref
                                @if(request('sort') === 'ref')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                            <div @mousedown="resize('ref', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Amount Total --}}
                        <th x-show="columns.total.visible" :style="{ width: columns.total.width, minWidth: columns.total.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_total_signed', 'dir' => request('sort') === 'amount_total_signed' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end hover:text-emerald-600 transition-colors">
                                Amount
                                @if(request('sort') === 'amount_total_signed')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                            <div @mousedown="resize('total', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Account --}}
                        <th x-show="columns.account.visible" :style="{ width: columns.account.width, minWidth: columns.account.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            Account
                            <div @mousedown="resize('account', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Line Description --}}
                        <th x-show="columns.desc.visible" :style="{ width: columns.desc.width, minWidth: columns.desc.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            Description
                            <div @mousedown="resize('desc', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Line Ref --}}
                        <th x-show="columns.line_ref.visible" :style="{ width: columns.line_ref.width, minWidth: columns.line_ref.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            Line Ref
                            <div @mousedown="resize('line_ref', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Debit --}}
                        <th x-show="columns.debit.visible" :style="{ width: columns.debit.width, minWidth: columns.debit.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            Debit
                            <div @mousedown="resize('debit', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>

                        {{-- Credit --}}
                        <th x-show="columns.credit.visible" :style="{ width: columns.credit.width, minWidth: columns.credit.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                            Credit
                            <div @mousedown="resize('credit', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @foreach($entry->lines as $idx => $line)
                        <tr class="{{ $idx === 0 ? 'border-t-2 border-slate-300 dark:border-slate-600' : 'border-t border-slate-100 dark:border-slate-800' }} hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            @if($idx === 0)
                            <td class="px-3 py-2 text-center align-top sticky left-0 bg-white dark:bg-slate-800 z-10" rowspan="{{ $entry->lines->count() }}">
                                <input type="checkbox" name="selected_ids[]" value="{{ $entry->id }}" class="entry-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer mt-1 dark:bg-slate-800 dark:border-slate-600 p-0">
                            </td>
                            {{-- Entry-level columns only on first line --}}
                            <td x-show="columns.name.visible" :style="{ width: columns.name.width, minWidth: columns.name.width }" class="px-3 py-2 font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap align-top sticky left-10 bg-white dark:bg-slate-800 z-10" rowspan="{{ $entry->lines->count() }}">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('journals.show', $entry) }}" class="hover:underline">{{ $entry->move_name }}</a>
                                    <a href="{{ route('journals.print-html', $entry) }}" target="_blank" title="Direct Print" class="text-slate-400 hover:text-blue-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    </a>
                                    <a href="{{ route('journals.print', $entry) }}" target="_blank" title="Export PDF" class="text-slate-400 hover:text-indigo-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                </div>
                            </td>
                            <td x-show="columns.date.visible" :style="{ width: columns.date.width, minWidth: columns.date.width }" class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap align-top" rowspan="{{ $entry->lines->count() }}">{{ \Carbon\Carbon::parse($entry->date)->format('Y-m-d') }}</td>
                            <td x-show="columns.partner.visible" :style="{ width: columns.partner.width, minWidth: columns.partner.width }" class="px-3 py-2 text-xs align-top" rowspan="{{ $entry->lines->count() }}">{{ $entry->partner_name ?? '' }}</td>
                            <td x-show="columns.ref.visible" :style="{ width: columns.ref.width, minWidth: columns.ref.width }" class="px-3 py-2 text-xs text-slate-500 align-top" rowspan="{{ $entry->lines->count() }}">{{ $entry->ref ?? '' }}</td>
                            <td x-show="columns.total.visible" :style="{ width: columns.total.width, minWidth: columns.total.width }" class="px-3 py-2 text-right font-mono text-xs font-semibold align-top whitespace-nowrap" rowspan="{{ $entry->lines->count() }}">
                                {{ number_format($entry->amount_total_signed, 2) }}
                            </td>
                            @endif
                            {{-- Line-level columns on every row --}}
                            <td x-show="columns.account.visible" :style="{ width: columns.account.width, minWidth: columns.account.width }" class="px-3 py-2 align-top overflow-hidden">
                                <span class="font-mono text-xs text-violet-600 dark:text-violet-400 block">{{ $line->account_code }}</span>
                                <span class="text-[10px] text-slate-500 break-words whitespace-normal block leading-tight">{{ $line->account_name }}</span>
                            </td>
                            <td x-show="columns.desc.visible" :style="{ width: columns.desc.width, minWidth: columns.desc.width }" class="px-3 py-2 text-xs align-top break-words whitespace-normal">
                                {{ $line->display_name ?: '' }}
                            </td>
                            <td x-show="columns.line_ref.visible" :style="{ width: columns.line_ref.width, minWidth: columns.line_ref.width }" class="px-3 py-2 text-xs text-slate-500 align-top break-words whitespace-normal">
                                {{ $line->ref ?: '' }}
                            </td>
                            <td x-show="columns.debit.visible" :style="{ width: columns.debit.width, minWidth: columns.debit.width }" class="px-3 py-2 text-right font-mono text-xs align-top {{ $line->debit > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-300 dark:text-slate-700' }}">
                                {{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}
                            </td>
                            <td x-show="columns.credit.visible" :style="{ width: columns.credit.width, minWidth: columns.credit.width }" class="px-3 py-2 text-right font-mono text-xs align-top {{ $line->credit > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-300 dark:text-slate-700' }}">
                                {{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}
                            </td>
                        </tr>
                        @endforeach
                    @empty
                    <tr>
                        <td :colspan="visibleColumnCount" class="px-4 py-12 text-center">
                            <div class="text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-lg font-medium">No journal entries found</p>
                                <p class="text-sm mt-1">Import data from Odoo on the <a href="{{ route('import') }}" class="text-emerald-500 hover:text-emerald-400 underline">Import Data</a> page.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($entries->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">
            {{ $entries->links() }}
        </div>
        @endif
    </div>
    </form>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mt-3">
        <p class="text-xs text-slate-500">Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries</p>
        
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAllCheckbox');
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        const printBtns = document.querySelectorAll('.printSelectedBtn');
        const countSpan = document.querySelector('.selectedCount');
        const countSpanSm = document.querySelector('.selectedCountSm');

        function updateSelection() {
            const checkedCount = document.querySelectorAll('.entry-checkbox:checked').length;
            if (countSpan) countSpan.textContent = checkedCount;
            if (countSpanSm) countSpanSm.textContent = checkedCount;
            printBtns.forEach(printBtn => {
                printBtn.disabled = checkedCount === 0;
                if (checkedCount === 0) {
                    printBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    printBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        }

        if (selectAll && checkboxes.length > 0) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
                updateSelection();
            });

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    const someChecked = Array.from(checkboxes).some(c => c.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                    updateSelection();
                });
            });
            
            updateSelection();
        }

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // Find search input
            const searchInput = document.querySelector('input[name="search"]');
            
            // Ctrl+F / Cmd+F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                if (searchInput) {
                    e.preventDefault();
                    // Scroll to top and focus
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    searchInput.focus();
                }
            }

            // Escape to clear or blur
            if (e.key === 'Escape') {
                if (document.activeElement === searchInput) {
                    searchInput.blur();
                } else if (window.location.search) {
                    // If filters are applied, clear them
                    window.location.href = "{{ route('journals.index') }}";
                }
            }
        });
    });
</script>
@endsection
