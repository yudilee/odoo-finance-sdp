@extends('layouts.app')

@section('title', 'Application Settings')
@section('subtitle', 'Configure application behavior')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <form action="{{ route('settings.update') }}" method="POST" class="p-6 space-y-6">
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
                
                <form action="{{ route('settings.empty-database') }}" method="POST" class="mt-4" onsubmit="return confirm('Are you sure you want to empty the entire database? This action is permanent.');">
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
                <div class="space-y-4">
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
                        const resp = await fetch('{{ route("admin.settings.odoo.config") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ odoo_url: this.config.url, odoo_db: this.config.db, odoo_user: this.config.user, odoo_password: this.config.password })
                        });
                        const data = await resp.json();
                        this.configMsg = data.message;
                        this.configMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.configMsg = 'Network error'; this.configMsgType = 'error'; }
                    this.saving = false;
                    setTimeout(() => this.configMsg = '', 3000);
                },

                async testConnection() {
                    this.testing = true;
                    this.configMsg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.odoo.test") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                        });
                        const data = await resp.json();
                        this.configMsg = data.message;
                        this.configMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.configMsg = 'Network error'; this.configMsgType = 'error'; }
                    this.testing = false;
                },

                async saveSchedule() {
                    this.scheduleSaving = true;
                    this.scheduleMsg = '';
                    try {
                        const resp = await fetch('{{ route("admin.settings.odoo.schedule.save") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ enabled: this.schedule.enabled, interval: this.schedule.interval })
                        });
                        const data = await resp.json();
                        this.scheduleMsg = data.message;
                        this.scheduleMsgType = data.success ? 'success' : 'error';
                    } catch (e) { this.scheduleMsg = 'Network error'; this.scheduleMsgType = 'error'; }
                    this.scheduleSaving = false;
                    setTimeout(() => this.scheduleMsg = '', 3000);
                }
            }
        }
        </script>
        @endif
    </div>
</div>
@endsection
