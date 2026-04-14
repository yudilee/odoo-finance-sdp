{{--
    Partial: invoice-print-hub.blade.php
    Provides shared JS functions for printing invoices directly to Print Hub.
    Include via @include('partials.invoice-print-hub') on any invoice show/index page.
--}}
<div id="invoice-hub-toast" class="fixed top-4 right-4 z-[200] pointer-events-none" style="display:none">
    <div id="invoice-hub-toast-inner"
         class="flex items-center gap-2 px-4 py-2.5 rounded-full text-white text-xs font-bold shadow-2xl transition-all duration-300"
         style="min-width:180px">
        <svg id="invoice-hub-toast-icon-spin" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <svg id="invoice-hub-toast-icon-ok" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg id="invoice-hub-toast-icon-err" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span id="invoice-hub-toast-msg"></span>
    </div>
</div>

<script>
(function () {
    /* ---------- toast helper ---------- */
    function showHubToast(msg, state) {
        // state: 'loading' | 'success' | 'error'
        const toast  = document.getElementById('invoice-hub-toast');
        const inner  = document.getElementById('invoice-hub-toast-inner');
        const msgEl  = document.getElementById('invoice-hub-toast-msg');
        const spin   = document.getElementById('invoice-hub-toast-icon-spin');
        const ok     = document.getElementById('invoice-hub-toast-icon-ok');
        const err    = document.getElementById('invoice-hub-toast-icon-err');

        msgEl.textContent = msg;
        spin.classList.add('hidden');
        ok.classList.add('hidden');
        err.classList.add('hidden');
        inner.className = inner.className.replace(/bg-\S+/g, '');

        if (state === 'loading') {
            inner.classList.add('bg-amber-500');
            spin.classList.remove('hidden');
        } else if (state === 'success') {
            inner.classList.add('bg-emerald-500');
            ok.classList.remove('hidden');
        } else {
            inner.classList.add('bg-red-500');
            err.classList.remove('hidden');
        }

        toast.style.display = '';
        toast.style.opacity = '1';
        if (state !== 'loading') {
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => { toast.style.display = 'none'; }, 400);
            }, 3500);
        }
    }

    /* ---------- single invoice ---------- */
    window.printInvoiceToHub = async function (invoiceName, docType, printMode, showUsername) {
        printMode   = printMode   || 'detail';
        showUsername = showUsername || 0;

        showHubToast('Sending to hub…', 'loading');
        try {
            const formData = new FormData();
            formData.append('_token',       '{{ csrf_token() }}');
            formData.append('invoice_name', invoiceName);
            formData.append('doc_type',     docType);
            formData.append('print_mode',   printMode);
            formData.append('show_username',showUsername);

            const res    = await fetch('{{ route('invoice.print-hub') }}', {
                method: 'POST', body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const result = await res.json();
            if (result.success) {
                showHubToast('Sent to print hub ✓', 'success');
            } else {
                showHubToast('Error: ' + (result.message || 'Unknown'), 'error');
            }
        } catch (e) {
            showHubToast('Connection error', 'error');
        }
    };

    /* ---------- INVRS single: show print-mode modal first ---------- */
    window.printRentalInvoiceToHub = function (invoiceName, docType) {
        if (!invoiceName.startsWith('INVRS')) {
            return window.printInvoiceToHub(invoiceName, docType);
        }

        Swal.fire({
            title: 'Pilih Jenis Cetakan',
            html: window.getPrintOptionsHtml ? getPrintOptionsHtml('Pilih Jenis Cetakan') : buildPrintOptionsHtml(),
            showCancelButton: true,
            confirmButtonText: 'Print to Hub',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            confirmButtonColor: '#10b981',
            width: '450px',
            didOpen: () => {
                if (window.initSwalEvents) initSwalEvents();
                else initPrintOptions();
            },
            preConfirm: () => window.swalSelectedValue || 'detail_nopol'
        }).then(result => {
            if (!result.isConfirmed) return;
            const val = result.value;
            const printMode    = val === 'summary' ? 'summary' : 'detail';
            const showUsername = val === 'detail_username' ? 1 : 0;
            window.printInvoiceToHub(invoiceName, docType, printMode, showUsername);
        });
    };

    /* ---------- bulk send ---------- */
    window.printBulkToHub = async function (docType, selectedIds, printMode, showUsername) {
        printMode   = printMode   || 'detail';
        showUsername = showUsername || 0;

        showHubToast(`Sending ${selectedIds.length} invoice(s) to hub…`, 'loading');
        try {
            const formData = new FormData();
            formData.append('_token',       '{{ csrf_token() }}');
            formData.append('doc_type',     docType);
            formData.append('print_mode',   printMode);
            formData.append('show_username',showUsername);
            selectedIds.forEach(id => formData.append('selected_ids[]', id));

            const res    = await fetch('{{ route('invoice.print-hub-bulk') }}', {
                method: 'POST', body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const result = await res.json();
            if (result.success) {
                showHubToast(`${selectedIds.length} invoice(s) sent ✓`, 'success');
            } else {
                showHubToast('Error: ' + (result.message || 'Unknown'), 'error');
            }
        } catch (e) {
            showHubToast('Connection error', 'error');
        }
    };

    /* ---------- fallback print-options builder (for pages that don't load Swal) ---------- */
    function buildPrintOptionsHtml() {
        return `<div class="text-left py-2">
            <div class="space-y-3" id="print-options-group">
                <label class="option-card p-4 border-2 rounded-xl cursor-pointer flex items-center gap-4 bg-slate-50 border-emerald-500 shadow-sm" data-value="detail_nopol">
                    <input type="radio" name="print_type" value="detail_nopol" class="hidden" checked>
                    <div class="flex-1"><h4 class="font-bold text-sm">Invoice with detail Nopol</h4><p class="text-[11px] text-slate-500">Standard detailing license plates.</p></div>
                    <div class="radio-indicator w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div></div>
                </label>
                <label class="option-card p-4 border-2 rounded-xl cursor-pointer flex items-center gap-4 bg-white border-slate-100" data-value="detail_username">
                    <input type="radio" name="print_type" value="detail_username" class="hidden">
                    <div class="flex-1"><h4 class="font-bold text-sm">Invoice with detail and username</h4><p class="text-[11px] text-slate-500">Includes driver/operator names.</p></div>
                    <div class="radio-indicator w-5 h-5 rounded-full border-2 border-slate-200 flex items-center justify-center"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500 hidden"></div></div>
                </label>
                <label class="option-card p-4 border-2 rounded-xl cursor-pointer flex items-center gap-4 bg-white border-slate-100" data-value="summary">
                    <input type="radio" name="print_type" value="summary" class="hidden">
                    <div class="flex-1"><h4 class="font-bold text-sm">Invoice with summary only</h4><p class="text-[11px] text-slate-500">Compact one-line per item summary.</p></div>
                    <div class="radio-indicator w-5 h-5 rounded-full border-2 border-slate-200 flex items-center justify-center"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500 hidden"></div></div>
                </label>
            </div></div>`;
    }

    function initPrintOptions() {
        const container = document.getElementById('print-options-group');
        if (!container) return;
        window.swalSelectedValue = 'detail_nopol';
        container.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', () => {
                container.querySelectorAll('.option-card').forEach(c => {
                    c.classList.remove('border-emerald-500', 'bg-slate-50', 'shadow-sm');
                    c.classList.add('border-slate-100', 'bg-white');
                    c.querySelector('.radio-indicator div').classList.add('hidden');
                    c.querySelector('.radio-indicator').classList.remove('border-emerald-500');
                    c.querySelector('.radio-indicator').classList.add('border-slate-200');
                });
                card.classList.add('border-emerald-500', 'bg-slate-50', 'shadow-sm');
                card.classList.remove('border-slate-100', 'bg-white');
                card.querySelector('.radio-indicator div').classList.remove('hidden');
                card.querySelector('.radio-indicator').classList.add('border-emerald-500');
                card.querySelector('.radio-indicator').classList.remove('border-slate-200');
                window.swalSelectedValue = card.dataset.value;
            });
        });
    }
})();
</script>
