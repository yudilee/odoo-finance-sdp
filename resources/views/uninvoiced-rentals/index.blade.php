@extends('layouts.app')

@section('title', 'Uninvoiced Rentals')
@section('subtitle', 'View and export rental periods and sales orders that have not yet been invoiced.')

@section('content')
<div class="flex flex-col gap-6" x-data="uninvoicedRentals()">
    
    {{-- Tabs Navigation --}}
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
        <a href="{{ route('uninvoiced-rentals.index', ['tab' => 'long_term']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'long_term' ? 'bg-violet-600 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Long Term / Subscription</span>
            <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $activeTab === 'long_term' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' }}">
                {{ number_format($counts['long_term']) }}
            </span>
        </a>

        <a href="{{ route('uninvoiced-rentals.index', ['tab' => 'short_term']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'short_term' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span>Short Term</span>
            <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $activeTab === 'short_term' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' }}">
                {{ number_format($counts['short_term']) }}
            </span>
        </a>

        <a href="{{ route('uninvoiced-rentals.index', ['tab' => 'sold_out']) }}" 
           class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'sold_out' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            <span>Sold Out</span>
            <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $activeTab === 'sold_out' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' }}">
                {{ number_format($counts['sold_out']) }}
            </span>
        </a>
    </div>
    
    {{-- Top Bar: Search & Actions --}}
    <div class="flex flex-col lg:flex-row justify-start items-start lg:items-center gap-4 bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex-wrap">
        
        <form method="GET" action="{{ route('uninvoiced-rentals.index') }}" class="flex items-center gap-2 w-full sm:w-auto flex-wrap sm:flex-nowrap">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="relative w-full sm:w-80">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="search" value="{{ request('search') }}" 
                       class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                       placeholder="Search SO, Customer, Nopol...">
            </div>

            @if($activeTab === 'long_term')
            <div class="relative w-full sm:w-40">
                <select name="invoice_period" class="block w-full pl-3 pr-8 py-2 border border-slate-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm appearance-none cursor-pointer">
                    <option value="">All Periods</option>
                    <option value="Monthly" {{ request('invoice_period') == 'Monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="Quarterly" {{ request('invoice_period') == 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                    <option value="Semester" {{ request('invoice_period') == 'Semester' ? 'selected' : '' }}>Semester</option>
                    <option value="Yearly" {{ request('invoice_period') == 'Yearly' ? 'selected' : '' }}>Yearly</option>
                    <option value="Bimonthly" {{ request('invoice_period') == 'Bimonthly' ? 'selected' : '' }}>Bimonthly</option>
                    <option value="Four-monthly" {{ request('invoice_period') == 'Four-monthly' ? 'selected' : '' }}>Four-monthly</option>
                    <option value="Seven-monthly" {{ request('invoice_period') == 'Seven-monthly' ? 'selected' : '' }}>Seven-monthly</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
            </div>
            @endif

            <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-sm font-medium transition-colors border border-slate-300 dark:border-slate-600">
                Filter
            </button>
            @if(request()->hasAny(['search', 'invoice_period']) && (request('search') || request('invoice_period')))
                <a href="{{ route('uninvoiced-rentals.index', ['tab' => $activeTab]) }}" class="px-4 py-2 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium">Clear</a>
            @endif
        </form>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            {{-- Export Button --}}
            <form method="POST" action="{{ route('uninvoiced-rentals.export') }}" class="flex items-center w-full sm:w-auto shadow-sm rounded-lg overflow-hidden">
                @csrf
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="invoice_period" value="{{ request('invoice_period') }}">
                <div class="relative flex items-center bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors border-r border-slate-700/50">
                    <select name="format" class="appearance-none bg-transparent text-white text-sm font-medium pl-4 pr-8 py-2 outline-none cursor-pointer">
                        <option value="xls" class="text-slate-900" selected>Excel (XLS)</option>
                        <option value="csv" class="text-slate-900">CSV</option>
                    </select>
                    <div class="pointer-events-none absolute right-0 flex items-center px-2 text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
                <button type="submit" class="bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white px-4 py-2 text-sm font-medium transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </form>

            {{-- Auto Sync Toggle (All Tabs) --}}
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-100 dark:bg-slate-700/50 rounded-lg border border-slate-200 dark:border-slate-700">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Auto Sync</span>
                <button type="button" 
                        @click="autoSyncEnabled = !autoSyncEnabled; toggleAutoSync()" 
                        :class="autoSyncEnabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" 
                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                    <span aria-hidden="true" 
                          :class="autoSyncEnabled ? 'translate-x-4' : 'translate-x-0'" 
                          class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
            </div>

            {{-- Fast Sync Button --}}
            <button @click="syncDataFast" :disabled="isSyncing" 
                    class="w-full sm:w-auto px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <svg x-show="!isSyncing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <svg x-show="isSyncing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span x-text="isSyncing ? 'Syncing...' : 'Fast Sync'"></span>
            </button>

            {{-- Full Sync Button --}}
            <button @click="syncData" :disabled="isSyncing" 
                    class="w-full sm:w-auto px-4 py-2 {{ $activeTab === 'sold_out' ? 'bg-rose-600 hover:bg-rose-700' : ($activeTab === 'short_term' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-emerald-600 hover:bg-emerald-700') }} text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <svg x-show="!isSyncing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg x-show="isSyncing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span x-text="isSyncing ? 'Syncing...' : syncButtonLabel"></span>
            </button>
        </div>
    </div>

    {{-- Progress Bar (Visible only when syncing) --}}
    <div x-show="isSyncing" x-cloak class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm" x-transition>
        <div class="flex justify-between text-sm mb-1">
            <span class="font-medium text-slate-700 dark:text-slate-300" x-text="syncStatusText">Initializing...</span>
            <span class="font-medium text-emerald-600 dark:text-emerald-400" x-text="syncPercentage + '%'">0%</span>
        </div>
        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
            <div class="bg-emerald-600 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + syncPercentage + '%'"></div>
        </div>
    </div>

    {{-- Main Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left whitespace-nowrap">
                <thead class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 uppercase border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 font-medium">Kode Cust</th>
                        <th class="px-4 py-3 font-medium">Nomor SO</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Nomor PO</th>
                        <th class="px-4 py-3 font-medium">Nomor Kontrak</th>
                        <th class="px-4 py-3 font-medium">Kontrak Ref</th>
                        <th class="px-4 py-3 font-medium">Nama user</th>
                        <th class="px-4 py-3 font-medium">Area pemakaian unit</th>
                        <th class="px-4 py-3 font-medium">Nopol</th>
                        <th class="px-4 py-3 font-medium">Chassis</th>
                        <th class="px-4 py-3 font-medium">Model</th>
                        <th class="px-4 py-3 font-medium">Tahun Mobil</th>
                        <th class="px-4 py-3 font-medium">Actual Start Rent</th>
                        <th class="px-4 py-3 font-medium">Actual End Rental</th>
                        <th class="px-4 py-3 font-medium">Tanggal periode belum cetak</th>
                        <th class="px-4 py-3 font-medium">Start Rental Period</th>
                        <th class="px-4 py-3 font-medium">End Rental Period</th>
                        <th class="px-4 py-3 font-medium">Price di SO</th>
                        <th class="px-4 py-3 font-medium">Duration Price</th>
                        <th class="px-4 py-3 font-medium">Invoice Period</th>
                        <th class="px-4 py-3 font-medium">Invoice PIC</th>
                        <th class="px-4 py-3 font-medium">First Invoice date</th>
                        <th class="px-4 py-3 font-medium">Rental Method</th>
                        <th class="px-4 py-3 font-medium">Payment Terms</th>
                        <th class="px-4 py-3 font-medium">Recipient Bank</th>
                        <th class="px-4 py-3 font-medium">Tax ID</th>
                        <th class="px-4 py-3 font-medium">ID TKU</th>
                        <th class="px-4 py-3 font-medium">Kode Transaksi</th>
                        <th class="px-4 py-3 font-medium">Address</th>
                        <th class="px-4 py-3 font-medium">Tax Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($rentals as $rental)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-4 py-3">{{ $rental->kode_cust }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $rental->nomor_so }}</td>
                        <td class="px-4 py-3">
                            @if($rental->status == 'Pickedup' || $rental->status == 'Picked Up')
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">Pickedup</span>
                            @elseif($rental->status == 'Reserved')
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Reserved</span>
                            @elseif($rental->status == 'Quotation')
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Quotation</span>
                            @elseif($rental->status == 'Returned')
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">Returned (Unbilled)</span>
                            @elseif($rental->status == 'Sold Out')
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">Sold Out</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">{{ $rental->status ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $rental->nomor_po }}</td>
                        <td class="px-4 py-3">{{ $rental->nomor_kontrak }}</td>
                        <td class="px-4 py-3">{{ $rental->kontrak_ref }}</td>
                        <td class="px-4 py-3">{{ $rental->nama_user }}</td>
                        <td class="px-4 py-3">{{ $rental->area_pemakaian_unit }}</td>
                        <td class="px-4 py-3">{{ $rental->nopol }}</td>
                        <td class="px-4 py-3">{{ $rental->chassis }}</td>
                        <td class="px-4 py-3">{{ $rental->model }}</td>
                        <td class="px-4 py-3">{{ $rental->tahun_mobil }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">{{ $rental::formatDate($rental->start) }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">{{ $rental::formatDate($rental->end) }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">{{ $rental::formatDate($rental->tanggal_periode_belum_cetak) }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">{{ $rental::formatDate($rental->start_rental_period) }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">{{ $rental::formatDate($rental->end_rental_period) }}</td>
                        <td class="px-4 py-3 text-nowrap whitespace-nowrap">Rp {{ number_format($rental->price_di_so, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">Rp {{ number_format($rental->duration_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $rental->invoice_period }}</td>
                        <td class="px-4 py-3">{{ $rental->invoice_pic }}</td>
                        <td class="px-4 py-3">{{ $rental::formatDate($rental->first_invoice_date) }}</td>
                        <td class="px-4 py-3">{{ $rental->rental_method }}</td>
                        <td class="px-4 py-3">{{ $rental->payment_terms }}</td>
                        <td class="px-4 py-3">{{ $rental->recipient_bank }}</td>
                        <td class="px-4 py-3">{{ $rental->tax_id }}</td>
                        <td class="px-4 py-3">{{ $rental->id_tku }}</td>
                        <td class="px-4 py-3">{{ $rental->kode_transaksi }}</td>
                        <td class="px-4 py-3">{{ $rental->address }}</td>
                        <td class="px-4 py-3">{{ $rental->tax_address }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="30" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                <p class="text-base font-medium text-slate-900 dark:text-white">No records found for {{ $activeTab === 'short_term' ? 'Short Term' : ($activeTab === 'sold_out' ? 'Sold Out' : 'Long Term') }}</p>
                                <p class="text-sm mt-1">Try adjusting your filters or click {{ $activeTab === 'sold_out' ? 'Sync Sold Out' : ($activeTab === 'short_term' ? 'Sync Short Term' : 'Sync Odoo') }} to fetch data.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($rentals->hasPages())
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
            {{ $rentals->links() }}
        </div>
        @endif
    </div>

</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('uninvoicedRentals', () => ({
            activeTab: '{{ $activeTab }}',
            isSyncing: false,
            syncStatusText: 'Initializing...',
            syncPercentage: 0,
            autoSyncEnabled: {{ $autoSyncEnabled ? 'true' : 'false' }},

            get syncButtonLabel() {
                if (this.activeTab === 'short_term') return 'Sync Short Term';
                if (this.activeTab === 'sold_out') return 'Sync Sold Out';
                return 'Sync Odoo';
            },

            get tabLabel() {
                if (this.activeTab === 'short_term') return 'Short Term orders';
                if (this.activeTab === 'sold_out') return 'Sold Out orders';
                return 'Long Term rental periods';
            },

            get routes() {
                if (this.activeTab === 'short_term') {
                    return {
                        init: '{{ route('uninvoiced-rentals.short-term.sync-init') }}',
                        initFast: '{{ route('uninvoiced-rentals.short-term.sync-init-fast') }}',
                        chunk: '{{ route('uninvoiced-rentals.short-term.sync-chunk') }}'
                    };
                }
                if (this.activeTab === 'sold_out') {
                    return {
                        init: '{{ route('uninvoiced-rentals.sold-out.sync-init') }}',
                        initFast: '{{ route('uninvoiced-rentals.sold-out.sync-init-fast') }}',
                        chunk: '{{ route('uninvoiced-rentals.sold-out.sync-chunk') }}'
                    };
                }
                return {
                    init: '{{ route('uninvoiced-rentals.sync-init') }}',
                    initFast: '{{ route('uninvoiced-rentals.sync-init-fast') }}',
                    chunk: '{{ route('uninvoiced-rentals.sync-chunk') }}'
                };
            },

            async toggleAutoSync() {
                try {
                    const res = await fetch('{{ route('uninvoiced-rentals.auto-sync.toggle') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ enabled: this.autoSyncEnabled })
                    });
                    const data = await res.json();
                    if(!data.success) throw new Error();
                } catch(e) {
                    this.autoSyncEnabled = !this.autoSyncEnabled;
                    alert('Failed to update auto-sync setting.');
                }
            },

            async syncDataFast() {
                if(this.isSyncing) return;
                this.isSyncing = true;
                this.syncStatusText = `Fetching recently modified ${this.tabLabel} from Odoo...`;
                this.syncPercentage = 0;
                
                try {
                    const initRes = await fetch(this.routes.initFast, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const initData = await initRes.json();
                    
                    if(!initData.success) {
                        throw new Error(initData.message || 'Failed to initialize fast sync.');
                    }
                    
                    const soIds = initData.so_ids || [];
                    if (soIds.length === 0) {
                        alert(`No recent ${this.tabLabel} found.`);
                        this.isSyncing = false;
                        return;
                    }
                    
                    const chunkSize = 500;
                    const chunks = [];
                    for (let i = 0; i < soIds.length; i += chunkSize) {
                        chunks.push(soIds.slice(i, i + chunkSize));
                    }
                    
                    let totalSaved = 0;
                    
                    for (let i = 0; i < chunks.length; i++) {
                        this.syncStatusText = `Fast Syncing chunk ${i + 1} of ${chunks.length} (${chunks[i].length} Sales Orders)...`;
                        
                        const chunkRes = await fetch(this.routes.chunk, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                so_ids: chunks[i],
                                is_first_chunk: false
                            })
                        });
                        
                        const chunkData = await chunkRes.json();
                        if(!chunkData.success) {
                            throw new Error(chunkData.message || `Failed to fast sync chunk ${i + 1}`);
                        }
                        
                        totalSaved += chunkData.count;
                        this.syncPercentage = Math.round(((i + 1) / chunks.length) * 100);
                    }
                    
                    this.syncStatusText = 'Fast Sync complete!';
                    this.syncPercentage = 100;
                    
                    alert(`Success! Fast Synced ${totalSaved} recent ${this.tabLabel}.`);
                    window.location.reload();
                    
                } catch(error) {
                    alert(`Error: ${error.message}`);
                    console.error(error);
                    this.isSyncing = false;
                }
            },

            async syncData() {
                if(this.isSyncing) return;
                this.isSyncing = true;
                this.syncStatusText = `Fetching available ${this.tabLabel} from Odoo...`;
                this.syncPercentage = 0;
                
                try {
                    const initRes = await fetch(this.routes.init, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    const initData = await initRes.json();
                    
                    if(!initData.success) {
                        throw new Error(initData.message || 'Failed to initialize sync.');
                    }
                    
                    const soIds = initData.so_ids || [];
                    if (soIds.length === 0) {
                        alert(`No uninvoiced ${this.tabLabel} found.`);
                        this.isSyncing = false;
                        return;
                    }
                    
                    const chunkSize = 500;
                    const chunks = [];
                    for (let i = 0; i < soIds.length; i += chunkSize) {
                        chunks.push(soIds.slice(i, i + chunkSize));
                    }
                    
                    let totalSaved = 0;
                    
                    for (let i = 0; i < chunks.length; i++) {
                        this.syncStatusText = `Syncing chunk ${i + 1} of ${chunks.length} (${chunks[i].length} Sales Orders)...`;
                        
                        const chunkRes = await fetch(this.routes.chunk, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                so_ids: chunks[i],
                                is_first_chunk: (i === 0)
                            })
                        });
                        
                        const chunkData = await chunkRes.json();
                        if(!chunkData.success) {
                            throw new Error(chunkData.message || `Failed to sync chunk ${i + 1}`);
                        }
                        
                        totalSaved += chunkData.count;
                        this.syncPercentage = Math.round(((i + 1) / chunks.length) * 100);
                    }
                    
                    this.syncStatusText = 'Sync complete!';
                    this.syncPercentage = 100;
                    
                    alert(`Success! Synced ${totalSaved} uninvoiced ${this.tabLabel}.`);
                    window.location.reload();
                    
                } catch(error) {
                    alert(`Error: ${error.message}`);
                    console.error(error);
                    this.isSyncing = false;
                }
            }
        }));
    });
</script>
@endsection
