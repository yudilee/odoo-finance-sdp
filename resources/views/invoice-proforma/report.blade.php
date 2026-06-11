@extends('layouts.app')

@section('title', 'Proforma Report')
@section('subtitle', 'History of generated Proforma invoices')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div x-data="{
    columns: {
        proforma_number: { visible: true, width: '180px', label: 'Proforma #' },
        name: { visible: true, width: '150px', label: 'Draft Invoice #' },
        date: { visible: true, width: '110px', label: 'Date' },
        partner: { visible: true, width: '200px', label: 'Customer' },
        ref: { visible: true, width: '180px', label: 'Reference' },
        payment_term: { visible: false, width: '100px', label: 'Payment Term' },
        untaxed: { visible: false, width: '130px', label: 'Untaxed' },
        tax: { visible: false, width: '110px', label: 'Tax' },
        total: { visible: true, width: '130px', label: 'Total' },
        print_count: { visible: true, width: '100px', label: 'Print Count' },
        manager: { visible: false, width: '120px', label: 'Manager' },
        spv: { visible: false, width: '120px', label: 'SPV' }
    },
    init() {
        const saved = localStorage.getItem('proforma_report_column_settings');
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
            localStorage.setItem('proforma_report_column_settings', JSON.stringify(value));
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
        return Object.values(this.columns).filter(c => c.visible).length;
    }
}">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-emerald-500">{{ number_format($stats['total_invoices']) }}</p>
            <p class="text-xs text-slate-500">Total Generated</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-cyan-500">Rp {{ number_format($stats['total_untaxed'], 0, ',', '.') }}</p>
            <p class="text-xs text-slate-500">Total Untaxed</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-amber-500">Rp {{ number_format($stats['total_tax'], 0, ',', '.') }}</p>
            <p class="text-xs text-slate-500">Total Tax</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-violet-500">Rp {{ number_format($stats['total_amount'], 0, ',', '.') }}</p>
            <p class="text-xs text-slate-500">Total Amount</p>
        </div>
    </div>

    {{-- Filters --}}
    <div x-data="{ filtersOpen: true }" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-6 overflow-hidden">
        <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between cursor-pointer group" @click="filtersOpen = !filtersOpen">
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1 text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="font-medium">Filters & Actions</span>
                </div>
                <div class="hidden md:flex items-center gap-2 text-xs text-slate-400">
                    @if(request('date_from')) <span>From: {{ request('date_from') }}</span> @endif
                    @if(request('date_to')) <span>To: {{ request('date_to') }}</span> @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Column Settings --}}
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

        <div x-show="filtersOpen" x-cloak x-transition class="p-4 border-t border-slate-200 dark:border-slate-700">
            <form method="GET" action="{{ route('invoice-proforma.report', [], false) }}">
            <div class="flex flex-wrap items-end gap-3 mb-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Proforma #, Invoice #, customer..."
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="flex gap-2 items-end pb-[2px]">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">Filter</button>
                    <a href="{{ route('invoice-proforma.report') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Clear</a>
                </div>
            </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto overflow-y-auto max-h-[75vh]">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 select-none">
                <tr>
                    {{-- Proforma # --}}
                    <th x-show="columns.proforma_number.visible" :style="{ width: columns.proforma_number.width, minWidth: columns.proforma_number.width }" class="group relative px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'proforma_number', 'dir' => request('sort') === 'proforma_number' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                            Proforma #
                            @if(request('sort') === 'proforma_number')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('proforma_number', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Invoice # --}}
                    <th x-show="columns.name.visible" :style="{ width: columns.name.width, minWidth: columns.name.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'dir' => request('sort') === 'name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                            Draft Invoice #
                            @if(request('sort') === 'name')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('name', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Date --}}
                    <th x-show="columns.date.visible" :style="{ width: columns.date.width, minWidth: columns.date.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_date', 'dir' => request('sort', 'invoice_date') === 'invoice_date' && request('dir', 'desc') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                            Date
                            @if(request('sort', 'invoice_date') === 'invoice_date')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('date', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Customer --}}
                    <th x-show="columns.partner.visible" :style="{ width: columns.partner.width, minWidth: columns.partner.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'partner_name', 'dir' => request('sort') === 'partner_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                            Customer
                            @if(request('sort') === 'partner_name')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('partner', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Reference --}}
                    <th x-show="columns.ref.visible" :style="{ width: columns.ref.width, minWidth: columns.ref.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        Reference
                        <div @mousedown="resize('ref', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Payment Term --}}
                    <th x-show="columns.payment_term.visible" :style="{ width: columns.payment_term.width, minWidth: columns.payment_term.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        Terms
                        <div @mousedown="resize('payment_term', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Untaxed --}}
                    <th x-show="columns.untaxed.visible" :style="{ width: columns.untaxed.width, minWidth: columns.untaxed.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_untaxed', 'dir' => request('sort') === 'amount_untaxed' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end hover:text-emerald-600 transition-colors">
                            Untaxed
                            @if(request('sort') === 'amount_untaxed')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('untaxed', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Tax --}}
                    <th x-show="columns.tax.visible" :style="{ width: columns.tax.width, minWidth: columns.tax.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_tax', 'dir' => request('sort') === 'amount_tax' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end hover:text-emerald-600 transition-colors">
                            Tax
                            @if(request('sort') === 'amount_tax')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('tax', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Total --}}
                    <th x-show="columns.total.visible" :style="{ width: columns.total.width, minWidth: columns.total.width }" class="group relative px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_total', 'dir' => request('sort') === 'amount_total' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end hover:text-emerald-600 transition-colors">
                            Total
                            @if(request('sort') === 'amount_total')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('total', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Print Count --}}
                    <th x-show="columns.print_count.visible" :style="{ width: columns.print_count.width, minWidth: columns.print_count.width }" class="group relative px-3 py-3 text-center font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'print_count', 'dir' => request('sort') === 'print_count' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-center hover:text-emerald-600 transition-colors">
                            Print Count
                            @if(request('sort') === 'print_count')
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                            @endif
                        </a>
                        <div @mousedown="resize('print_count', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- Manager --}}
                    <th x-show="columns.manager.visible" :style="{ width: columns.manager.width, minWidth: columns.manager.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        Manager
                        <div @mousedown="resize('manager', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>

                    {{-- SPV --}}
                    <th x-show="columns.spv.visible" :style="{ width: columns.spv.width, minWidth: columns.spv.width }" class="group relative px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400 sticky top-0 bg-slate-50 dark:bg-slate-900 z-40">
                        SPV
                        <div @mousedown="resize('spv', $event)" class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize group-hover:bg-emerald-500/30 transition-colors"></div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr class="border-t border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                    <td x-show="columns.proforma_number.visible" class="px-4 py-2 font-mono text-xs font-semibold whitespace-nowrap">
                        <a href="javascript:void(0)" onclick="previewProforma('{{ route('invoice-proforma.print', $invoice) }}')" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 hover:underline">
                            {{ $invoice->proforma_number }}
                        </a>
                    </td>
                    <td x-show="columns.name.visible" class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">
                        <a href="{{ route('invoice-proforma.show', $invoice) }}" class="hover:underline hover:text-indigo-500">{{ $invoice->name }}</a>
                    </td>
                    <td x-show="columns.date.visible" class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">{{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '' }}</td>
                    <td x-show="columns.partner.visible" class="px-3 py-2 text-xs">{{ $invoice->partner_name }}</td>
                    <td x-show="columns.ref.visible" class="px-3 py-2 text-xs text-slate-500">{{ $invoice->ref ?? '' }}</td>
                    <td x-show="columns.payment_term.visible" class="px-3 py-2 text-xs text-slate-500">{{ $invoice->payment_term ?? '' }}</td>
                    <td x-show="columns.untaxed.visible" class="px-3 py-2 text-right font-mono text-xs whitespace-nowrap">{{ number_format($invoice->amount_untaxed, 0, ',', '.') }}</td>
                    <td x-show="columns.tax.visible" class="px-3 py-2 text-right font-mono text-xs text-amber-600 dark:text-amber-400 whitespace-nowrap">{{ number_format($invoice->amount_tax, 0, ',', '.') }}</td>
                    <td x-show="columns.total.visible" class="px-3 py-2 text-right font-mono text-xs font-semibold whitespace-nowrap">{{ number_format($invoice->amount_total, 0, ',', '.') }}</td>
                    <td x-show="columns.print_count.visible" class="px-3 py-2 text-center text-xs">
                        <span class="inline-flex items-center justify-center px-2 py-1 rounded-full {{ $invoice->print_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-semibold min-w-[2rem]">
                            {{ $invoice->print_count }}
                        </span>
                    </td>
                    <td x-show="columns.manager.visible" class="px-3 py-2 text-xs text-slate-500">{{ $invoice->manager_name ?? '' }}</td>
                    <td x-show="columns.spv.visible" class="px-3 py-2 text-xs text-slate-500">{{ $invoice->spv_name ?? '' }}</td>
                </tr>
                @empty
                <tr>
                    <td :colspan="visibleColumnCount" class="px-4 py-12 text-center">
                        <div class="text-slate-400">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="text-lg font-medium">No Generated Proformas found</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $invoices->links() }}
    </div>
    @endif
</div>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mt-3">
    <p class="text-xs text-slate-500">Showing {{ $invoices->firstItem() ?? 0 }}-{{ $invoices->lastItem() ?? 0 }} of {{ $invoices->total() }} invoices</p>
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
    function previewProforma(url) {
        let htmlUrl = url.replace('/pdf', '/html');
        
        // Show preview in a popup modal with iframe
        Swal.fire({
            html: `
                <div style="margin:0 -20px 0 -20px;">
                    <div style="background:linear-gradient(135deg,#1e293b,#334155);padding:10px 20px;display:flex;align-items:center;justify-content:space-between;">
                        <span style="color:#94a3b8;font-size:12px;display:flex;align-items:center;gap:6px;">
                            <span style="background-color:#fef08a;width:12px;height:12px;border-radius:3px;display:inline-block;"></span> = Rate anomaly (internal only, hidden in PDF)
                        </span>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <button onclick="Swal.close()" style="background:transparent;border:none;color:#94a3b8;cursor:pointer;padding:4px;display:flex;align-items:center;justify-content:center;border-radius:9999px;transition:all 0.2s;" onmouseover="this.style.color='#f8fafc';this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.color='#94a3b8';this.style.background='transparent'">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <iframe src="${htmlUrl}" style="width:100%;height:78vh;border:none;display:block;"></iframe>
                </div>
            `,
            width: '900px',
            padding: '0',
            showConfirmButton: false,
            showCloseButton: false,
            customClass: {
                popup: 'swal-preview-popup'
            }
        });
    }
</script>
@endsection
