{{-- Print Hub Manager Modal --}}
<div x-data="printHubManager()" 
     @open-print-hub.window="open($event.detail?.id)"
     class="relative z-[100]" 
     x-show="showModal" 
     x-cloak>
    
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-700"
                 x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between bg-slate-50 dark:bg-slate-900/50">
                    <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Print via Hub
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6">
                    <template x-if="error">
                        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 rounded-lg text-xs text-red-600 dark:text-red-400 flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="error"></span>
                        </div>
                    </template>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Available Printers</label>
                            <div class="grid grid-cols-1 gap-2 max-h-[300px] overflow-y-auto pr-1">
                                <template x-if="loading">
                                    <div class="py-12 text-center">
                                        <svg class="animate-spin h-8 w-8 text-amber-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <p class="text-xs text-slate-500 mt-2">Discovering printers...</p>
                                    </div>
                                </template>

                                <template x-for="p in printers" :key="p.name + p.agent">
                                    <label class="relative flex items-center p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-all"
                                           :class="selectedPrinter === p.name ? 'ring-2 ring-amber-500 border-amber-500 bg-amber-50/30 dark:bg-amber-900/20' : ''">
                                        <input type="radio" x-model="selectedPrinter" :value="p.name" class="sr-only">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mb-0.5">
                                                <span class="font-bold text-sm text-slate-700 dark:text-slate-200" x-text="p.name"></span>
                                                <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-[10px] font-bold rounded uppercase tabular-nums">Online</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                Agent: <span x-text="p.agent"></span>
                                            </div>
                                        </div>
                                        <div x-show="selectedPrinter === p.name" class="shrink-0 text-amber-500">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </div>
                                    </label>
                                </template>

                                <template x-if="!loading && printers.length === 0 && !error">
                                    <div class="py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                                        <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No printers online</p>
                                        <p class="text-[10px] text-slate-400 mt-1 px-10">Make sure your print agents are running and connected to the hub.</p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 py-2 px-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                            <input type="checkbox" x-model="setDefault" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-4 w-4">
                            <span class="text-xs text-slate-700 dark:text-slate-300">Save as default printer for my account</span>
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex flex-col gap-2">
                    <button type="button" 
                            @click="print()" 
                            :disabled="!selectedPrinter || printing || (loading && printers.length === 0)"
                            class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-amber-600 px-4 py-3 text-sm font-bold text-white shadow-lg hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all disabled:opacity-50 disabled:grayscale">
                        <svg x-show="printing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="printing ? 'SENDING TO HUB...' : 'SEND TO PRINTER'"></span>
                    </button>
                    <p class="text-[10px] text-center text-slate-400">PDF will be generated and sent as base64 to the hub.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function printHubManager() {
        return {
            showModal: false,
            printers: [],
            loading: false,
            printing: false,
            error: '',
            selectedPrinter: @json(auth()->user()->getPreference('default_printer', '')),
            setDefault: false,
            targetEntryId: null,

            async open(entryId = null) {
                this.targetEntryId = entryId;
                this.error = '';
                this.showModal = true;
                this.fetchPrinters();
            },

            async fetchPrinters() {
                this.loading = true;
                this.printers = [];
                try {
                    const resp = await fetch('{{ route('journals.printers') }}');
                    const data = await resp.json();
                    if (data.success) {
                        this.printers = data.printers;
                        if (this.printers.length === 1 && !this.selectedPrinter) {
                            this.selectedPrinter = this.printers[0].name;
                        }
                    } else {
                        this.error = data.message;
                    }
                } catch (e) {
                    this.error = 'Failed to fetch printers.';
                } finally {
                    this.loading = false;
                }
            },

            async print() {
                if (!this.selectedPrinter) {
                    this.showModal = true;
                    this.fetchPrinters();
                    return;
                }

                this.printing = true;
                try {
                    let url = this.targetEntryId 
                        ? `/journals/${this.targetEntryId}/print-hub` 
                        : '{{ route('journals.print-hub-selected') }}';
                    
                    let body = {
                        printer: this.selectedPrinter,
                        set_default: this.setDefault
                    };

                    if (!this.targetEntryId) {
                        const checkboxes = document.querySelectorAll('.entry-checkbox:checked');
                        body.selected_ids = Array.from(checkboxes).map(cb => cb.value);
                    }

                    const resp = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(body)
                    });

                    const data = await resp.json();
                    if (data.success) {
                        alert(data.message + ' (Job ID: ' + data.job_id + ')');
                        this.showModal = false;
                    } else {
                        this.error = data.message;
                        this.showModal = true;
                    }
                } catch (e) {
                    this.error = 'Printing system error.';
                    this.showModal = true;
                } finally {
                    this.printing = false;
                }
            }
        }
    }
</script>
