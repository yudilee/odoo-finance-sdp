<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <script>
        // Check for saved theme preference or default to dark
        const theme = localStorage.getItem('theme') || 'dark';
        if (theme === 'light') {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        } else {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Odoo Finance SDP')) - {{ config('app.name', 'Odoo Finance SDP') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen flex" 
    x-data="{ 
        sidebarOpen: window.innerWidth >= 1280,
        darkMode: document.documentElement.classList.contains('dark')
    }"
    x-init="
        $watch('darkMode', val => {
            if (val) {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
                localStorage.setItem('theme', 'light');
            }
        });
        // Sync initial state to document element
        document.documentElement.classList.toggle('dark', darkMode);
        document.documentElement.style.colorScheme = darkMode ? 'dark' : 'light';
    ">

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'w-64' : 'w-16'" class="bg-slate-800 dark:bg-slate-950 text-white flex flex-col transition-all duration-300 fixed h-full z-[60]">
        {{-- Logo --}}
        <div class="flex items-center justify-between p-4 border-b border-slate-700 h-16">
            <img x-show="sidebarOpen" x-cloak src="{{ asset('images/logo.png') }}" alt="App Logo" class="h-8 object-contain">
            <button @click="sidebarOpen = !sidebarOpen" class="p-1 rounded hover:bg-slate-700 transition-colors">
                <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            {{-- Dashboard --}}
            @if(\App\Models\Setting::get('show_dashboard', '1') === '1')
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span x-show="sidebarOpen" x-cloak>Dashboard</span>
            </a>
            @endif

            @if(auth()->check() && auth()->user()->role === 'admin')
            {{-- Import Data --}}
            <a href="{{ route('import') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('import*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span x-show="sidebarOpen" x-cloak>Import Data</span>
            </a>

            {{-- Journal Entries --}}
            <a href="{{ route('journals.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('journals*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Journal Entries</span>
            </a>
            @endif

            {{-- Invoice Driver --}}
            @if(auth()->check() && (auth()->user()->role === 'invoice' || auth()->user()->role === 'admin'))
            <a href="{{ route('invoice-driver.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('invoice-driver*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Invoice Driver</span>
            </a>
            <a href="{{ route('invoice-other.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('invoice-other*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span x-show="sidebarOpen" x-cloak>Invoice Other</span>
            </a>
            <a href="{{ route('invoice-rental.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('invoice-rental*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span x-show="sidebarOpen" x-cloak>Invoice Rental</span>
            </a>
            <a href="{{ route('invoice-vehicle.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('invoice-vehicle*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span x-show="sidebarOpen" x-cloak>Invoice Penjualan</span>
            </a>
            <a href="{{ route('invoice-subscription.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('invoice-subscription*') ? 'bg-violet-600/30 text-violet-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span x-show="sidebarOpen" x-cloak>Cek Invoice Subs</span>
            </a>
            @endif

            @if(auth()->check() && auth()->user()->role === 'admin')
            <div class="pt-4 pb-2">
                <p x-show="sidebarOpen" x-cloak class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Admin</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('admin.users*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Users</span>
            </a>
            <a href="{{ route('admin.backups.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('admin.backups*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                <span x-show="sidebarOpen" x-cloak>Backups</span>
            </a>
            <a href="{{ route('admin.sessions.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('admin.sessions*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Sessions</span>
            </a>
            <a href="{{ route('admin.print_logs.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('admin.print_logs*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Printed Invoices</span>
            </a>
            @endif

            @if(auth()->check() && auth()->user()->role === 'admin')
            <div class="pt-4 pb-2">
                <p x-show="sidebarOpen" x-cloak class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">System</p>
            </div>
            <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ request()->routeIs('settings*') ? 'bg-emerald-600/30 text-emerald-300' : 'hover:bg-slate-700 text-slate-300' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-show="sidebarOpen" x-cloak>Settings</span>
            </a>
            @endif
        </nav>

        {{-- Theme Toggle --}}
        <div class="px-6 py-3 border-t border-slate-700 bg-slate-800/50">
            <button @click="darkMode = !darkMode" class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-slate-700/50 hover:bg-slate-700 rounded-lg text-xs font-medium transition-colors">
                <template x-if="!darkMode">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464l-.707-.707a1 1 0 00-1.414 1.414l.707.707a1 1 0 001.414-1.414zm2.12 8.485l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Light Mode</span>
                    </div>
                </template>
                <template x-if="darkMode">
                    <div class="flex items-center gap-2 text-slate-300">
                        <svg class="w-4 h-4 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Dark Mode</span>
                    </div>
                </template>
            </button>
        </div>

        {{-- User --}}
        @if(auth()->check())
        <div class="p-3 border-t border-slate-700">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-8 h-8 bg-emerald-600 rounded-full flex items-center justify-center text-sm font-bold shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div x-show="sidebarOpen" x-cloak class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()->getRoleDisplayName() }}</p>
                </div>
            </div>
            <div class="space-y-1">
                <a href="{{ route('profile.preferences') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-700 text-slate-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span x-show="sidebarOpen" x-cloak>My Preferences</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-700 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Logout</span>
                    </button>
                </form>
            </div>
        </div>
        @endif
    </aside>

    {{-- Main Content --}}
    <main :class="sidebarOpen ? 'ml-64' : 'ml-16'" class="flex-1 transition-all duration-300 min-h-screen">
        {{-- Header --}}
        <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 py-4">
            <h1 class="text-2xl font-bold">@yield('title', 'Dashboard')</h1>
            @hasSection('subtitle')
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">@yield('subtitle')</p>
            @endif
        </header>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mx-6 mt-4 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300 p-4 rounded-lg">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-6 mt-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 p-4 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        {{-- Page Content --}}
        <div class="p-6">
            @yield('content')
        </div>
    </main>
</body>
</html>
