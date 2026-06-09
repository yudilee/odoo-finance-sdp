@extends('layouts.app')

@section('title', 'Printed Invoices Tracking')
@section('subtitle', 'Monitor and reset print counters for generated PDF invoices.')

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden" x-data="{ selectedIds: [] }">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700 space-y-4">
        <form method="GET" action="{{ route('admin.print_logs.index') }}" class="flex flex-col sm:flex-row gap-4" id="filter-form">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Invoice No..." 
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500">
            </div>
            
            <div class="flex items-center gap-2">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Search
                </button>
                <a href="{{ route('admin.print_logs.index') }}" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    Clear
                </a>
            </div>
        </form>

        {{-- Bulk Actions --}}
        <div class="flex items-center gap-4 pt-2 border-t border-slate-100 dark:border-slate-700" x-show="selectedIds.length > 0" x-transition x-cloak>
            <span class="text-sm text-slate-500"><span x-text="selectedIds.length"></span> invoices selected</span>
            <form method="POST" action="{{ route('admin.print_logs.reset_bulk') }}" onsubmit="return confirm('Are you sure you want to reset the selected invoice counters back to zero?')">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="selected_ids[]" :value="id">
                </template>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Bulk Reset Counters
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-medium border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-4 py-3 w-10 text-center">
                        <input type="checkbox" @change="
                            if($event.target.checked) {
                                selectedIds = {{ json_encode($logs->pluck('id')) }};
                            } else {
                                selectedIds = [];
                            }
                        " class="rounded border-slate-300 dark:border-slate-600 text-emerald-600 bg-transparent focus:ring-emerald-500">
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_name', 'dir' => $sort === 'invoice_name' && $dir === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-2 hover:text-emerald-600 transition-colors">
                            Invoice No
                            @if($sort === 'invoice_name')
                                <svg class="w-4 h-4 {{ $dir === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <span class="text-slate-500 font-medium">Print Mode</span>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'print_count', 'dir' => $sort === 'print_count' && $dir === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-2 hover:text-emerald-600 transition-colors">
                            Total Prints
                            @if($sort === 'print_count')
                                <svg class="w-4 h-4 {{ $dir === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'dir' => $sort === 'updated_at' && $dir === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-2 hover:text-emerald-600 transition-colors">
                            Last Printed
                            @if($sort === 'updated_at')
                                <svg class="w-4 h-4 {{ $dir === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" :value="{{ $log->id }}" x-model="selectedIds" class="rounded border-slate-300 dark:border-slate-600 text-emerald-600 bg-transparent focus:ring-emerald-500">
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $log->invoice_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ str_replace('_', ' ', Str::title($log->print_mode)) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            {{ $log->print_count }} Times
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm">{{ $log->updated_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <form method="POST" action="{{ route('admin.print_logs.reset', $log) }}" class="inline" onsubmit="return confirm('Reset this invoice\'s print counter to zero? This removes the watermark.')">
                            @csrf
                            <button type="submit" class="text-emerald-600 hover:text-emerald-700 transition-colors" title="Reset Counter">
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                        No partially or extensively reprinted invoices found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
