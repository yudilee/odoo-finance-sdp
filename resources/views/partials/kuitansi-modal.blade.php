@php
    $printLog = \App\Models\PrintLog::firstWhere('invoice_name', $invoice->name);
    $kuitansiLines = [];
    $showContract = false;
    $useOverride = false;
    
    if ($printLog) {
        if ($printLog->kuitansi_pembayaran) {
            $kuitansiLines = explode("\n", $printLog->kuitansi_pembayaran);
        }
        $showContract = (bool) $printLog->getPreference('show_contract', false);
        $useOverride = (bool) $printLog->getPreference('use_override', false);
    }

    // Determine PDF/HTML routes based on the current module prefix
    $routePrefix = explode('.', request()->route()->getName())[0];
    $pdfRoute = $routePrefix . '.kuitansi-pdf';
    $htmlRoute = $routePrefix . '.kuitansi-html';
@endphp

<div x-data="{ 
    kuitansiModalOpen: false, 
    saveStatus: null,
    showContract: {{ $showContract ? 'true' : 'false' }},
    useOverride: {{ $useOverride ? 'true' : 'false' }},
    lines: [
        '{{ addslashes($kuitansiLines[0] ?? '') }}',
        '{{ addslashes($kuitansiLines[1] ?? '') }}',
        '{{ addslashes($kuitansiLines[2] ?? '') }}',
        '{{ addslashes($kuitansiLines[3] ?? '') }}'
    ],
    async saveKuitansi() {
        this.saveStatus = 'saving';
        try {
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('invoice_name', '{{ $invoice->name }}');
            formData.append('show_contract', this.showContract ? '1' : '0');
            formData.append('use_override', this.useOverride ? '1' : '0');
            
            // Send the 4 lines
            this.lines.forEach((line, index) => {
                formData.append('pembayaran_' + (index + 1), line);
            });

            const response = await fetch('{{ route('kuitansi.override.update') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            if (result.success) {
                this.saveStatus = 'success';
                setTimeout(() => { this.saveStatus = null; }, 3000);
            } else {
                throw new Error(result.message || 'Failed to save');
            }
        } catch (error) {
            console.error('Kuitansi Save Error:', error);
            this.saveStatus = 'error';
            setTimeout(() => { this.saveStatus = null; }, 3000);
        }
    },
    async printToHub() {
        this.saveStatus = 'saving';
        try {
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('invoice_name', '{{ $invoice->name }}');
            formData.append('show_contract', this.showContract ? '1' : '0');
            formData.append('use_override', this.useOverride ? '1' : '0');
            
            const response = await fetch('{{ route('kuitansi.print-hub') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();
            if (result.success) {
                this.saveStatus = 'success';
                setTimeout(() => { this.saveStatus = null; }, 3000);
            } else {
                throw new Error(result.message || 'Failed to print');
            }
        } catch (error) {
            console.error('Print Error:', error);
            this.saveStatus = 'error';
            setTimeout(() => { this.saveStatus = null; }, 3000);
        }
    }
}" 
class="inline-flex items-center">
    
    {{-- Trigger Button --}}
    @if(isset($isMainButton) && $isMainButton)
        <button @click="kuitansiModalOpen = true" type="button" class="text-slate-400 hover:text-emerald-600 transition-colors" title="Print Kuitansi">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
        </button>
    @else
        <button @click="kuitansiModalOpen = true" type="button" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 w-full text-left transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            <span>Print Kuitansi</span>
        </button>
    @endif

    {{-- Modal Teleport --}}
    <template x-teleport="body">
        <div x-show="kuitansiModalOpen" 
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <div @click.away="kuitansiModalOpen = false" 
                 class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50 relative">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Print Kuitansi ({{ $invoice->name }})</h3>
                    <button @click="kuitansiModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l18 18"></path></svg>
                    </button>

                    {{-- Local Toast Notifications --}}
                    <div x-show="saveStatus" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute inset-0 flex items-center justify-center z-50 pointer-events-none">
                        <div :class="{
                                'bg-emerald-500': saveStatus === 'success',
                                'bg-amber-500': saveStatus === 'saving',
                                'bg-red-500': saveStatus === 'error'
                             }" 
                             class="px-4 py-1.5 rounded-full text-white text-xs font-bold shadow-lg flex items-center gap-2 pointer-events-auto">
                            <template x-if="saveStatus === 'success'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </template>
                            <template x-if="saveStatus === 'saving'">
                                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </template>
                            <span x-text="saveStatus === 'success' ? 'Settings Saved!' : (saveStatus === 'saving' ? 'Saving...' : 'Error Saving')"></span>
                        </div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-6 space-y-6">
                    {{-- Checkboxes --}}
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-all">
                            <input type="checkbox" x-model="showContract" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Print No Contract/PO</p>
                                <p class="text-[10px] text-slate-500">Show contract reference in kuitansi body</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-all">
                            <input type="checkbox" x-model="useOverride" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Override Keterangan</p>
                                <p class="text-[10px] text-slate-500">Use custom description line(s)</p>
                            </div>
                        </label>
                    </div>

                    {{-- Custom Lines --}}
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl">
                        <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                            <span class="font-bold">Note:</span> Custom lines below will only be used if "Override Keterangan" is checked. Max 4 lines, 110 chars each.
                        </p>
                    </div>

                    <div class="space-y-4">
                        @for ($i = 1; $i <= 4; $i++)
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Row {{ $i }}</label>
                                <input type="text" 
                                       x-model="lines[{{ $i - 1 }}]"
                                       maxlength="110"
                                       placeholder="Type custom value for row {{ $i }}..."
                                       class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                            </div>
                        @endfor
                    </div>

                    <div class="flex justify-end">
                        <button @click="saveKuitansi()" 
                                type="button" 
                                class="flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-emerald-600/20 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save Custom Content
                        </button>
                    </div>
                </div>

                {{-- Footer Action Buttons --}}
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <a :href="'{{ route($pdfRoute, $invoice) }}' + (showContract ? '?show_contract=1' : '?show_contract=0') + (useOverride ? '&use_override=1' : '&use_override=0')" 
                           target="_blank"
                           class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-indigo-600/20 active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Download PDF
                        </a>
                        <button @click="printToHub()" 
                           type="button" 
                           class="flex items-center gap-2 px-5 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl hover:bg-slate-300 dark:hover:bg-slate-600 transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print to Hub
                        </button>
                    </div>
                    <button @click="kuitansiModalOpen = false" class="text-sm font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Close</button>
                </div>
            </div>
        </div>
    </template>
</div>
