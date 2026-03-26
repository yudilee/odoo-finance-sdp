@extends('layouts.app')

@section('title', $invoice->name)
@section('subtitle', 'Invoice Rental Detail')

@section('content')
<div class="max-w-5xl mx-auto">
    {{-- Navigation --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('invoice-rental.index') }}" class="flex items-center gap-2 text-sm text-slate-500 hover:text-emerald-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to list
        </a>
        <div class="flex items-center gap-2">
            @if($prev)
            <a href="{{ route('invoice-rental.show', $prev) }}" class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">← Newer</a>
            @endif
            @if($next)
            <a href="{{ route('invoice-rental.show', $next) }}" class="px-3 py-1.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">Older →</a>
            @endif
            <button type="button" onclick="printInvoice('{{ $invoice->name }}', '{{ route('invoice-rental.print', $invoice) }}')" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print PDF
            </button>
        </div>
    </div>

    {{-- Invoice Header Card --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ $invoice->name }}</h2>
                    <p class="text-sm text-slate-500 mt-1">{{ $invoice->journal_name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold">Rp&nbsp;{{ number_format($invoice->amount_total, 0, ',', '.') }}</p>
                    <p class="text-xs text-slate-500 mt-1">Total Amount</p>
                </div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Left Column --}}
            <div class="space-y-4">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Customer</p>
                    <p class="text-sm font-semibold mt-1">{{ $invoice->partner_name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Address</p>
                    <p class="text-sm mt-1 whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $invoice->partner_address ?? $invoice->partner_address_complete ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Customer Reference</p>
                    <p class="text-sm mt-1 text-slate-600 dark:text-slate-400">{{ $invoice->ref ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Bank Account</p>
                    <p class="text-sm mt-1 text-slate-600 dark:text-slate-400">{{ $invoice->partner_bank ?? '-' }}</p>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="space-y-4">
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Invoice Date</p>
                    <p class="text-sm font-semibold mt-1">{{ $invoice->invoice_date->locale('id')->isoFormat('D MMMM YYYY') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Payment Terms</p>
                    <p class="text-sm mt-1 text-slate-600 dark:text-slate-400">{{ $invoice->payment_term ?? '-' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">BC Manager</p>
                        <p class="text-sm mt-1 text-slate-600 dark:text-slate-400">{{ $invoice->manager_name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">BC SPV</p>
                        <p class="text-sm mt-1 text-slate-600 dark:text-slate-400">{{ $invoice->spv_name ?? '-' }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Untaxed</p>
                        <p class="text-sm font-semibold text-cyan-600 dark:text-cyan-400 mt-1">Rp&nbsp;{{ number_format($invoice->amount_untaxed, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tax</p>
                        <p class="text-sm font-semibold text-amber-600 dark:text-amber-400 mt-1">Rp&nbsp;{{ number_format($invoice->amount_tax, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total</p>
                        <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 mt-1">Rp&nbsp;{{ number_format($invoice->amount_total, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Invoice Lines --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold">Invoice Lines</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 w-10">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Sale Order</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Serial No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500">Rental Period</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 w-20">Qty/UOM</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 w-40">Unit Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 w-40">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->lines as $idx => $line)
                    <tr class="border-t border-slate-100 dark:border-slate-800 {{ $idx % 2 === 0 ? '' : 'bg-slate-50/50 dark:bg-slate-900/30' }}">
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $idx + 1 }}</td>
                        <td class="px-4 py-3 text-sm">{{ $line->sale_order_id ?? '-' }}<br/><span class="text-xs text-slate-400">{{ $line->customer_name }}</span></td>
                        <td class="px-4 py-3 text-sm">{{ $line->description }}</td>
                        <td class="px-4 py-3 text-sm">{{ $line->serial_number ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $line->actual_start ? $line->actual_start->format('d/m/Y') : '-' }} - 
                            {{ $line->actual_end ? $line->actual_end->format('d/m/Y') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-center font-mono text-xs">{{ $line->quantity > 0 ? number_format($line->quantity, 0) : '-' }} {{ $line->uom }}</td>
                        <td class="px-4 py-3 text-right font-mono text-xs">{{ $line->price_unit > 0 ? 'Rp ' . number_format($line->price_unit, 0, ',', '.') : '-' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-xs font-semibold">
                            @if($line->quantity > 0 && $line->price_unit > 0)
                                Rp&nbsp;{{ number_format($line->quantity * $line->price_unit, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function printInvoice(name, url) {
        if (name.startsWith('INVRS')) {
            Swal.fire({
                title: 'Pilih Jenis Cetakan',
                input: 'radio',
                inputOptions: {
                    'detail_nopol': 'Invoice with detail Nopol',
                    'detail_username': 'Invoice with detail and username',
                    'summary': 'Invoice with summary only'
                },
                inputValue: 'detail_nopol',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Print',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Anda harus memilih salah satu!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let printUrl = url;
                    if (result.value === 'detail_username') {
                        printUrl += '?print_mode=detail&show_username=1';
                    } else if (result.value === 'summary') {
                        printUrl += '?print_mode=summary&show_username=0';
                    } else {
                        printUrl += '?print_mode=detail&show_username=0';
                    }
                    window.open(printUrl, '_blank');
                }
            });
        } else {
            window.open(url, '_blank');
        }
    }
</script>
@endsection
