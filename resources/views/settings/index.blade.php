@extends('layouts.app')

@section('title', 'Application Settings')
@section('subtitle', 'Configure application behavior')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            
            <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-slate-200 dark:border-slate-700 pb-2">General Settings</h3>
                
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="font-medium">Show Dashboard</p>
                        <p class="text-xs text-slate-500 mt-1">When enabled, the dashboard will be shown as the landing page. If disabled, you'll be redirected to the Import page.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="show_dashboard" value="1" {{ $settings['show_dashboard'] === '1' ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 dark:peer-focus:ring-emerald-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-amber-600 dark:text-amber-500">Empty before Sync</p>
                        <p class="text-xs text-slate-500 mt-1">If enabled, the local database will be cleared automatically every time you fetch new data from Odoo.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="empty_before_sync" value="1" {{ $settings['empty_before_sync'] === '1' ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 dark:peer-focus:ring-amber-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-amber-500"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-blue-600 dark:text-blue-500">Enable PDF Watermark</p>
                        <p class="text-xs text-slate-500 mt-1">If enabled, a "DUPLICATE" watermark will be shown on printed PDFs that have been printed more than once. Tracking remains active regardless.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="enable_pdf_watermark" value="1" {{ $settings['enable_pdf_watermark'] === '1' ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-violet-600 dark:text-violet-500">Enable Deep Sync (Vendor Bill)</p>
                        <p class="text-xs text-slate-500 mt-1">If enabled, the system will perform extra lookups in Odoo to find the Vendor Bill number for payment journals. This makes synchronization slower but more complete.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="odoo_deep_sync_journal" value="1" {{ $settings['odoo_deep_sync_journal'] === '1' ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-violet-300 dark:peer-focus:ring-violet-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-violet-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="font-medium text-indigo-600 dark:text-indigo-500">Journal Print Paper Size</p>
                        <p class="text-xs text-slate-500 mt-1">Select the default paper size for printing Journal Entries. A4 will print 2 entries per page.</p>
                    </div>
                    <div>
                        <select name="journal_paper_size" class="px-8 py-1.5 pr-10 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[right_8px_center] bg-no-repeat cursor-pointer">
                            <option value="A5" {{ $settings['journal_paper_size'] === 'A5' ? 'selected' : '' }}>A5 (1-up)</option>
                            <option value="A4" {{ $settings['journal_paper_size'] === 'A4' ? 'selected' : '' }}>A4 (2-up)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-slate-200 dark:border-slate-700 pb-2">Invoice Defaults</h3>
                <p class="text-xs text-slate-500">These values are used as fallback when Odoo does not provide them (e.g. BC Manager / SPV not set in Odoo).</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Default BC Manager</label>
                        <input type="text" name="default_bc_manager"
                            value="{{ $settings['default_bc_manager'] }}"
                            placeholder="e.g. LISA IBRAHIM"
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Default BC SPV</label>
                        <input type="text" name="default_bc_spv"
                            value="{{ $settings['default_bc_spv'] }}"
                            placeholder="e.g. PURNIASIH"
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition uppercase">
                    </div>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-between">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                    Save Changes
                </button>
            </div>
        </form>

        <div class="px-6 pb-6 pt-2 border-t border-slate-200 dark:border-slate-700 space-y-4">
            <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Database Management</h3>
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-900/30">
                <p class="text-sm text-red-800 dark:text-red-300 font-medium">Clear All Local Data</p>
                <p class="text-xs text-red-600 dark:text-red-400/80 mt-1">This will permanently delete all local journal entries and lines. This action cannot be undone.</p>
                
                <form action="{{ route('admin.settings.empty-database') }}" method="POST" class="mt-4" onsubmit="return confirm('Are you sure you want to empty the entire database? This action is permanent.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition-colors">
                        Empty Database Now
                    </button>
                </form>
            </div>
        </div>

        @if(auth()->user()->role === 'admin')
        {{-- ═══ ODOO CONFIGURATION (ADMIN ONLY) ═══ --}}
        <div class="px-6 py-6 border-t border-slate-200 dark:border-slate-700 space-y-6" x-data="odooConfigManager()">
            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Odoo Integration</h3>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {{-- Connection Card --}}
                <div class="space-y-4">
                    <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Odoo Configuration</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Server URL</label>
                            <input type="url" x-model="config.url" placeholder="https://your-odoo.com"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Database</label>
                            <input type="text" x-model="config.db" placeholder="odoo_db"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Username / Email</label>
                            <input type="text" x-model="config.user" placeholder="admin@example.com"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Password / API Key</label>
                            <input type="password" x-model="config.password" placeholder="••••••••"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-emerald-500 focus:border-emerald-500 transition">
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button @click="saveConfig()" :disabled="saving" class="flex-1 py-2 bg-slate-600 text-white text-xs font-bold rounded hover:bg-slate-700 transition-colors disabled:opacity-50">
                                <span x-text="saving ? 'SAVING...' : 'SAVE CONFIG'"></span>
                            </button>
                            <button @click="testConnection()" :disabled="testing" class="flex-1 py-2 bg-emerald-600 text-white text-xs font-bold rounded hover:bg-emerald-700 transition-colors disabled:opacity-50">
                                <span x-text="testing ? 'TESTING...' : 'TEST CONNECTION'"></span>
                            </button>
                        </div>

                        <div x-show="configMsg" x-cloak x-transition class="p-3 rounded-lg text-xs" :class="configMsgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'">
                            <span x-text="configMsg"></span>
                        </div>
                    </div>
                </div>

                {{-- Schedule Card --}}
                <div class="space-y-4" id="schedule">
                    <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Auto-Sync Schedule</h4>
                    <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Enable Auto-Sync</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="schedule.enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-violet-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Interval</label>
                            <select x-model="schedule.interval" :disabled="!schedule.enabled" class="w-full px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-violet-500 focus:border-violet-500 transition disabled:opacity-50">
                                <option value="hourly">Every Hour</option>
                                <option value="every_2_hours">Every 2 Hours</option>
                                <option value="every_4_hours">Every 4 Hours</option>
                                <option value="every_6_hours">Every 6 Hours</option>
                                <option value="every_12_hours">Every 12 Hours</option>
                                <option value="daily">Daily</option>
                            </select>
                        </div>

                        <button @click="saveSchedule()" :disabled="scheduleSaving" class="w-full py-2 bg-violet-600 text-white text-xs font-bold rounded hover:bg-violet-700 transition-colors disabled:opacity-50">
                            <span x-text="scheduleSaving ? 'SAVING...' : 'SAVE SCHEDULE'"></span>
                        </button>
                        
                        <div x-show="scheduleMsg" x-cloak x-transition class="text-xs text-center" :class="scheduleMsgType === 'success' ? 'text-emerald-500' : 'text-red-500'" x-text="scheduleMsg"></div>
                        
                        <div x-show="schedule.lastSync" class="text-[10px] text-slate-500 text-center uppercase tracking-tight">
                            Last sync: <span x-text="schedule.lastSync"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function odooConfigManager() {
            return {
                config: {
                    url: @json($odooConfig['url']),
                    db: @json($odooConfig['db']),
                    user: @json($odooConfig['user']),
                    password: @json($odooConfig['password']),
                },
                schedule: @json($schedule),
                saving: false,
                testing: false,
                configMsg: '',
                configMsgType: '',
                scheduleSaving: false,
                scheduleMsg: '',
                scheduleMsgType: '',

                async saveConfig() {
                    this.saving = true;
                    this.configMsg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.odoo.config", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            },
                            body: JSON.stringify({ odoo_url: this.config.url, odoo_db: this.config.db, odoo_user: this.config.user, odoo_password: this.config.password })
                        });
                        const data = await resp.json();
                        this.configMsg = data.message || 'Validation error or invalid config.';
                        this.configMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.configMsg = 'Request failed. Ensure URL includes http:// or https://'; this.configMsgType = 'error'; }
                    this.saving = false;
                    setTimeout(() => this.configMsg = '', 4000);
                },

                async testConnection() {
                    this.testing = true;
                    this.configMsg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.odoo.test", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            }
                        });
                        const data = await resp.json();
                        this.configMsg = data.message || 'Connection failed';
                        this.configMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.configMsg = 'Server Error determining connection.'; this.configMsgType = 'error'; }
                    this.testing = false;
                },

                async saveSchedule() {
                    this.scheduleSaving = true;
                    this.scheduleMsg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.odoo.schedule.save", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            },
                            body: JSON.stringify({ enabled: this.schedule.enabled, interval: this.schedule.interval })
                        });
                        const data = await resp.json();
                        this.scheduleMsg = data.message || 'Validation error.';
                        this.scheduleMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.scheduleMsg = 'Schedule save failed.'; this.scheduleMsgType = 'error'; }
                    this.scheduleSaving = false;
                    setTimeout(() => this.scheduleMsg = '', 3000);
                }
            }
        }

        function printHubManager() {
            return {
                config: {
                    url: @json($printHubConfig['url']),
                    api_key: @json($printHubConfig['api_key']),
                    timeout: @json($printHubConfig['timeout']),
                    default_profile: @json($printHubConfig['default_profile'] ?? ''),
                },
                saving: false,
                testing: false,
                syncing: false,
                msg: '',
                msgType: '',
                syncOutput: '',

                async save() {
                    this.saving = true;
                    this.msg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.print_hub.config", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            },
                            body: JSON.stringify({ 
                                print_hub_url: this.config.url, 
                                print_hub_api_key: this.config.api_key,
                                print_hub_timeout: this.config.timeout,
                                print_hub_default_profile: this.config.default_profile
                            })
                        });
                        const data = await resp.json();
                        this.msg = data.message;
                        this.msgType = data.success ? 'success' : 'error';
                    } catch (e) { this.msg = 'Request failed.'; this.msgType = 'error'; }
                    this.saving = false;
                    setTimeout(() => this.msg = '', 4000);
                },

                async test() {
                    this.testing = true;
                    this.msg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.print_hub.test", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            },
                            body: JSON.stringify({
                                print_hub_url: this.config.url,
                                print_hub_api_key: this.config.api_key
                            })
                        });
                        const data = await resp.json();
                        this.msg = data.message;
                        this.msgType = data.success ? 'success' : 'error';
                    } catch (e) { this.msg = 'Connection failed.'; this.msgType = 'error'; }
                    this.testing = false;
                },

                async syncSchemas() {
                    if (!confirm('This will push all document schemas (Journals, Invoices) to Print Hub. Continue?')) return;
                    this.syncing = true;
                    this.msg = '';
                    this.syncOutput = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.print_hub.sync_schemas", [], false) }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json', 
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                            }
                        });
                        const data = await resp.json();
                        this.msg = data.message;
                        this.msgType = data.success ? 'success' : 'error';
                        if (data.output) this.syncOutput = data.output;
                    } catch (e) { 
                        this.msg = 'Sync request failed.'; 
                        this.msgType = 'error'; 
                    }
                    this.syncing = false;
                }
            }
        }
        </script>

        {{-- ═══ PRINT HUB CONFIGURATION (ADMIN ONLY) ═══ --}}
        <div class="px-6 py-6 border-t border-slate-200 dark:border-slate-700 space-y-6" x-data="printHubManager()">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Print Hub Integration</h3>
                <span class="px-2.5 py-0.5 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 text-[10px] font-bold rounded-full uppercase tracking-wider">Production SDK</span>
            </div>
            
            <div class="p-5 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Hub Endpoint URL</label>
                            <input type="url" x-model="config.url" placeholder="https://print-hub.yourdomain.com"
                                class="w-full px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">API Key / Token</label>
                            <input type="password" x-model="config.api_key" placeholder="Enter Hub API Key"
                                class="w-full px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Default Print Profile</label>
                            <input type="text" x-model="config.default_profile" placeholder="e.g. journal_half_letter"
                                class="w-full px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 transition">
                            <p class="mt-1 text-[10px] text-slate-400">Profiles define paper size, orientation and margins on the Hub.</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Request Timeout (Seconds)</label>
                            <div class="flex items-center gap-3">
                                <input type="range" min="5" max="60" step="5" x-model="config.timeout" class="flex-1 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-600">
                                <span class="text-sm font-mono w-8 text-center" x-text="config.timeout"></span>
                            </div>
                        </div>

                        <div class="pt-2 grid grid-cols-2 gap-3">
                            <button @click="save()" :disabled="saving" class="flex items-center justify-center gap-2 py-2.5 bg-slate-800 dark:bg-slate-700 text-white text-xs font-bold rounded-lg hover:bg-slate-900 dark:hover:bg-slate-600 transition-all shadow-sm disabled:opacity-50">
                                <svg x-show="saving" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="saving ? 'SAVING...' : 'SAVE CONFIG'"></span>
                            </button>
                            <button @click="test()" :disabled="testing" class="flex items-center justify-center gap-2 py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition-all shadow-sm disabled:opacity-50">
                                <svg x-show="testing" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="testing ? 'TESTING...' : 'TEST CONNECTION'"></span>
                            </button>
                            <button @click="syncSchemas()" :disabled="syncing" class="col-span-2 flex items-center justify-center gap-2 py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-all shadow-sm disabled:opacity-50">
                                <svg x-show="syncing" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="syncing ? 'SYNCING SCHEMAS...' : '🔄 SYNC DATA SCHEMAS TO HUB'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="msg" x-cloak x-transition class="mt-4 p-3 rounded-lg text-xs flex items-center gap-2" :class="msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'">
                    <svg x-show="msgType === 'success'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="msgType === 'error'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="msg"></span>
                </div>

                <div x-show="syncOutput" x-cloak x-transition class="mt-2 p-3 bg-slate-900 rounded-lg text-[10px] font-mono text-slate-300 overflow-x-auto whitespace-pre">
                    <span x-text="syncOutput"></span>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
