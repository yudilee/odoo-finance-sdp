{{--
    Partial: invoice-print-hub.blade.php
    Provides shared JS functions for printing invoices directly to Print Hub.
    Include via @include('partials.invoice-print-hub') on any invoice show/index page.
--}}
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
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
            html: window.buildPrintOptionsHtml(),
            showCancelButton: true,
            confirmButtonText: 'Print to Hub',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            confirmButtonColor: '#10b981',
            width: '450px',
            didOpen: () => {
                window.initPrintOptions();
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
    window.printBulkToHub = async function (docType, selectedIds, printMode, showUsername, hideNopol) {
        printMode   = printMode   || 'detail';
        showUsername = showUsername || 0;

        showHubToast(`Sending ${selectedIds.length} invoice(s) to hub…`, 'loading');
        try {
            const formData = new FormData();
            formData.append('_token',       '{{ csrf_token() }}');
            formData.append('doc_type',     docType);
            formData.append('print_mode',   printMode);
            formData.append('show_username',showUsername);
            if (hideNopol) formData.append('hide_nopol', hideNopol);
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
    window.buildPrintOptionsHtml = function(isBulk = false) {
        const text = isBulk ? 'Beberapa invoice yang dipilih adalah tipe Subscription (INVRS).' : '';
        return `
            <div class="text-left py-2">
                ${text ? `<p class="text-sm text-slate-500 mb-4">${text}</p>` : ''}
                <div class="space-y-3" id="print-options-group">
                    <label class="option-card p-4 border-2 rounded-xl cursor-pointer flex items-center gap-4 bg-slate-50 border-emerald-500 shadow-sm transition-all active-option" data-value="detail">
                        <input type="radio" name="print_type" value="detail" class="hidden" checked>
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-slate-800 text-sm">Invoice with detail</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Full line-item breakdown.</p>
                        </div>
                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        </div>
                    </label>

                    <label class="option-card p-4 border-2 rounded-xl cursor-pointer flex items-center gap-4 bg-white border-slate-100 transition-all hover:border-emerald-200" data-value="summary">
                        <input type="radio" name="print_type" value="summary" class="hidden">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-slate-800 text-sm">Invoice with summary only</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Compact one-line per item summary.</p>
                        </div>
                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-slate-200 flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 hidden"></div>
                        </div>
                    </label>
                </div>
            </div>
            <style>
                #print-options-group .option-card.active-option { 
                    border-color: #10b981 !important; 
                    background-color: #f0fdf4 !important;
                    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                }
                #print-options-group .option-card.active-option .radio-indicator { 
                    border-color: #10b981 !important; 
                }
                #print-options-group .option-card.active-option .radio-indicator div { 
                    display: block !important; 
                }
            </style>
        `;
    };

    window.initPrintOptions = function() {
        const container = document.getElementById('print-options-group');
        if (!container) return;
        window.swalSelectedValue = 'detail';
        container.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', () => {
                container.querySelectorAll('.option-card').forEach(c => {
                    c.classList.remove('border-emerald-500', 'bg-slate-50', 'bg-f0fdf4', 'shadow-sm', 'active-option');
                    c.classList.add('border-slate-100', 'bg-white');
                    c.querySelector('.radio-indicator div').classList.add('hidden');
                    c.querySelector('.radio-indicator').classList.remove('border-emerald-500');
                    c.querySelector('.radio-indicator').classList.add('border-slate-200');
                });
                card.classList.add('border-emerald-500', 'bg-f0fdf4', 'shadow-sm', 'active-option');
                card.classList.remove('border-slate-100', 'bg-white');
                card.querySelector('.radio-indicator div').classList.remove('hidden');
                card.querySelector('.radio-indicator').classList.add('border-emerald-500');
                card.querySelector('.radio-indicator').classList.remove('border-slate-200');
                window.swalSelectedValue = card.dataset.value;
            });
        });
    }

    /* ---------- preview modal (iframe) ---------- */
    window.showInvoicePreviewModal = function(htmlUrl, pdfUrl, refreshUrl) {
        Swal.fire({
            html: `
                <div style="margin:0 -20px 0 -20px;">
                    <div style="background:linear-gradient(135deg,#1e293b,#334155);padding:10px 20px;display:flex;align-items:center;justify-content:space-between;">
                        <span style="color:#94a3b8;font-size:12px;display:flex;align-items:center;gap:6px;">
                            <span style="background-color:#fef08a;width:12px;height:12px;border-radius:3px;display:inline-block;"></span> = Rate anomaly (internal only, hidden in PDF)
                        </span>
                        <div style="display:flex;align-items:center;gap:12px;">
                            ${refreshUrl ? `<button id="refreshOdooBtn" onclick="window._refreshFromOdoo(this)" data-url="${refreshUrl}" data-html-url="${htmlUrl}" style="background:#f59e0b;color:white;padding:6px 16px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Refresh from Odoo
                            </button>` : ''}
                            <a href="${pdfUrl}" target="_blank" style="background:#10b981;color:white;padding:6px 16px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Download PDF (Clean)
                            </a>
                            <button onclick="Swal.close()" style="background:transparent;border:none;color:#94a3b8;cursor:pointer;padding:4px;display:flex;align-items:center;justify-content:center;border-radius:9999px;transition:all 0.2s;" onmouseover="this.style.color='#f8fafc';this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.color='#94a3b8';this.style.background='transparent'">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <iframe id="invoicePreviewIframe" src="${htmlUrl}" style="width:100%;height:78vh;border:none;display:block;"></iframe>
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
    };

    /* ---------- Refresh from Odoo handler ---------- */
    window._refreshFromOdoo = async function(btn) {
        const url = btn.dataset.url;
        const htmlUrl = btn.dataset.htmlUrl;
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"/><path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Refreshing...`;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                btn.style.background = '#10b981';
                btn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Updated!`;
                // Reload the iframe to show updated data
                const iframe = document.getElementById('invoicePreviewIframe');
                if (iframe) {
                    iframe.src = htmlUrl + (htmlUrl.includes('?') ? '&' : '?') + '_t=' + Date.now();
                }
                setTimeout(() => {
                    btn.innerHTML = origText;
                    btn.style.background = '#f59e0b';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }, 2000);
            } else {
                btn.style.background = '#ef4444';
                btn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ${data.message || 'Failed'}`;
                setTimeout(() => {
                    btn.innerHTML = origText;
                    btn.style.background = '#f59e0b';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }, 3000);
            }
        } catch (e) {
            btn.style.background = '#ef4444';
            btn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Connection error`;
            setTimeout(() => {
                btn.innerHTML = origText;
                btn.style.background = '#f59e0b';
                btn.disabled = false;
                btn.style.opacity = '1';
            }, 3000);
        }
    };
})();
</script>
