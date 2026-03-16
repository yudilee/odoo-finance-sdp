@extends('layouts.app')

@section('title', 'Journal Entries')
@section('subtitle', 'Imported journal entries from Odoo')

@section('content')
<div x-data="{ expandedEntry: null }">

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
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <form method="GET" action="{{ route('journals.index') }}" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="filter_applied" value="1">
            
            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
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
                <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg max-h-[100px] overflow-y-auto min-w-[220px]">
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
            
            <div class="flex gap-2 items-end pb-[2px]">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors">Filter</button>
                <a href="{{ route('journals.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Clear</a>
                <a href="{{ route('journals.print-all', request()->all()) }}" target="_blank" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-1 ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print All (PDF)
                </a>
                <button type="submit" form="bulkPrintForm" id="printSelectedBtn" class="px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors flex items-center gap-1 ml-2 opacity-50 cursor-not-allowed" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <form id="bulkPrintForm" method="POST" action="{{ route('journals.print-selected') }}" target="_blank">
        @csrf
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-3 w-10 text-center border-b border-slate-200 dark:border-slate-700">
                            <input type="checkbox" id="selectAllCheckbox" title="Select All" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer mt-0.5 dark:bg-slate-800 dark:border-slate-600">
                        </th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'move_name', 'dir' => request('sort') === 'move_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Name
                                @if(request('sort', 'date') === 'move_name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'date', 'dir' => request('sort', 'date') === 'date' && request('dir', 'desc') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Date
                                @if(request('sort', 'date') === 'date')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir', 'desc') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'partner_name', 'dir' => request('sort') === 'partner_name' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Partner
                                @if(request('sort') === 'partner_name')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'ref', 'dir' => request('sort') === 'ref' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-emerald-600 transition-colors">
                                Ref
                                @if(request('sort') === 'ref')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'amount_total_signed', 'dir' => request('sort') === 'amount_total_signed' && request('dir') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-end hover:text-emerald-600 transition-colors">
                                Amount Total
                                @if(request('sort') === 'amount_total_signed')
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('dir') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/></svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Account</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Line Description</th>
                        <th class="px-3 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Line Ref</th>
                        <th class="px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Debit</th>
                        <th class="px-3 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @foreach($entry->lines as $idx => $line)
                        <tr class="{{ $idx === 0 ? 'border-t-2 border-slate-300 dark:border-slate-600' : 'border-t border-slate-100 dark:border-slate-800' }} hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            @if($idx === 0)
                            <td class="px-3 py-2 text-center align-top" rowspan="{{ $entry->lines->count() }}">
                                <input type="checkbox" name="selected_ids[]" value="{{ $entry->id }}" class="entry-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer mt-1 dark:bg-slate-800 dark:border-slate-600 p-0">
                            </td>
                            {{-- Entry-level columns only on first line --}}
                            <td class="px-3 py-2 font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap align-top" rowspan="{{ $entry->lines->count() }}">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('journals.show', $entry) }}" class="hover:underline">{{ $entry->move_name }}</a>
                                    <a href="{{ route('journals.print', $entry) }}" target="_blank" title="Print PDF" class="text-slate-400 hover:text-indigo-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    </a>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap align-top" rowspan="{{ $entry->lines->count() }}">{{ \Carbon\Carbon::parse($entry->date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-xs align-top" rowspan="{{ $entry->lines->count() }}">{{ $entry->partner_name ?? '' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500 align-top" rowspan="{{ $entry->lines->count() }}">{{ $entry->ref ?? '' }}</td>
                            <td class="px-3 py-2 text-right font-mono text-xs font-semibold align-top whitespace-nowrap" rowspan="{{ $entry->lines->count() }}">
                                {{ number_format($entry->amount_total_signed, 2) }}
                            </td>
                            @endif
                            {{-- Line-level columns on every row --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-mono text-xs text-violet-600 dark:text-violet-400">{{ $line->account_code }}</span>
                                <span class="text-xs text-slate-500 ml-1">{{ $line->account_name }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $line->display_name ?: '' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $line->ref ?: '' }}</td>
                            <td class="px-3 py-2 text-right font-mono text-xs {{ $line->debit > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-300 dark:text-slate-700' }}">
                                {{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-xs {{ $line->credit > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-300 dark:text-slate-700' }}">
                                {{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}
                            </td>
                        </tr>
                        @endforeach
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center">
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

    {{-- Results count --}}
    <p class="text-xs text-slate-500 mt-3">Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAllCheckbox');
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        const printBtn = document.getElementById('printSelectedBtn');
        const countSpan = document.getElementById('selectedCount');

        function updateSelection() {
            const checkedCount = document.querySelectorAll('.entry-checkbox:checked').length;
            if (countSpan) countSpan.textContent = checkedCount;
            if (printBtn) {
                printBtn.disabled = checkedCount === 0;
                if (checkedCount === 0) {
                    printBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    printBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
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
    });
</script>
@endsection
