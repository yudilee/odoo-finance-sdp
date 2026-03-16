@extends('layouts.app')

@section('title', $entry->move_name)
@section('subtitle', 'Journal entry detail')

@section('content')
<div class="max-w-4xl space-y-6">
    {{-- Back Link --}}
    <a href="{{ route('journals.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-emerald-500 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to list
    </a>

    {{-- Header Card --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-slate-500">Move Name</p>
                <p class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $entry->move_name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Date</p>
                <p class="font-medium">{{ $entry->date->format('Y-m-d') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Journal</p>
                <p class="font-medium">{{ $entry->journal_name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Partner</p>
                <p class="font-medium">{{ $entry->partner_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Reference</p>
                <p class="font-medium">{{ $entry->ref ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Total Amount</p>
                <p class="font-mono font-bold text-lg {{ $entry->amount_total_signed >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ number_format($entry->amount_total_signed, 2) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Lines Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold">Line Items ({{ $entry->lines->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">#</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Account</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Description</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Reference</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Debit</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-400">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($entry->lines as $i => $line)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $i + 1 }}</td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-violet-600 dark:text-violet-400 font-medium">{{ $line->account_code }}</span>
                            <span class="text-slate-500 ml-1 text-xs">{{ $line->account_name }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $line->display_name ?: '-' }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $line->ref ?: '-' }}</td>
                        <td class="px-4 py-3 text-right font-mono {{ $line->debit > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-400' }}">
                            {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono {{ $line->credit > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-400' }}">
                            {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700">
                    <tr class="font-semibold">
                        <td class="px-4 py-3" colspan="4">Total</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($entry->lines->sum('debit'), 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-red-600 dark:text-red-400">
                            {{ number_format($entry->lines->sum('credit'), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
