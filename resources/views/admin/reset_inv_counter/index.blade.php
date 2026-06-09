@extends('layouts.app')

@section('title', 'Reset INV Counter')
@section('subtitle', 'Reset the PIC Watermark increment for printed invoices.')

@section('content')
<div class="max-w-2xl bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
    <form action="{{ route('admin.reset_inv_counter.reset') }}" method="POST">
        @csrf
        <div class="space-y-6">
            {{-- Invoice Name --}}
            <div>
                <label for="invoice_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Invoice Name</label>
                <input type="text" id="invoice_name" name="invoice_name" value="{{ old('invoice_name') }}" required placeholder="e.g. INVRS/2026/06345"
                    class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white transition-colors">
            </div>

            {{-- Jenis Cetakan --}}
            <div>
                <label for="print_mode" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Jenis Cetakan (Print Type)</label>
                <select id="print_mode" name="print_mode" required
                    class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white transition-colors">
                    <option value="" disabled selected>-- Select Print Type --</option>
                    <option value="detail_nopol" {{ old('print_mode') == 'detail_nopol' ? 'selected' : '' }}>Detail dengan No-Polisi (Default)</option>
                    <option value="summary" {{ old('print_mode') == 'summary' ? 'selected' : '' }}>Summary</option>
                    <option value="without_nopol" {{ old('print_mode') == 'without_nopol' ? 'selected' : '' }}>Tanpa No-Polisi</option>
                    <option value="detail_username" {{ old('print_mode') == 'detail_username' ? 'selected' : '' }}>Detail + Username</option>
                </select>
            </div>

            {{-- Submit --}}
            <div class="pt-4">
                <button type="submit" class="w-full flex justify-center items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors focus:ring-4 focus:ring-red-500/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset Counter
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
