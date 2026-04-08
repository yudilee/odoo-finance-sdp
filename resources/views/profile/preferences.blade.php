@extends('layouts.app')

@section('title', 'My Preferences')
@section('subtitle', 'Personalize your application experience')

@section('content')
<div class="max-w-xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <form action="{{ route('profile.preferences.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            
            {{-- Print Hub Preferences --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h3 class="text-lg font-semibold">Print Hub Preferences</h3>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Default Printer</label>
                    <p class="text-xs text-slate-500 mb-3">When a default printer is set, "Fast Print" will be enabled and it will skip the printer selection modal.</p>
                    
                    <div class="grid grid-cols-1 gap-2">
                        <label class="relative flex items-center p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-all">
                            <input type="radio" name="default_printer" value="" {{ empty($user->getPreference('default_printer')) ? 'checked' : '' }} class="sr-only peer">
                            <div class="flex-1">
                                <span class="font-bold text-sm text-slate-700 dark:text-slate-200">None (Always Ask)</span>
                            </div>
                            <div class="peer-checked:block hidden text-amber-500">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                        </label>

                        @forelse($printers as $p)
                        <label class="relative flex items-center p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-all">
                            <input type="radio" name="default_printer" value="{{ $p['name'] }}" {{ $user->getPreference('default_printer') === $p['name'] ? 'checked' : '' }} class="sr-only peer">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $p['name'] }}</span>
                                    <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 text-[10px] font-bold rounded uppercase">Online</span>
                                </div>
                                <div class="text-[10px] text-slate-500">Agent: {{ $p['agent'] }}</div>
                            </div>
                            <div class="peer-checked:block hidden text-amber-500">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                        </label>
                        @empty
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-center">
                            <p class="text-sm text-slate-500 italic">No printers currently online.</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                    Save Preferences
                </button>
            </div>
        </form>
    </div>

    {{-- Security Section --}}
    <div class="mt-8 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <form action="{{ route('profile.password.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Change Password</h3>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Current Password</label>
                        <input type="password" name="current_password" required
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        @error('current_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">New Password</label>
                        <input type="password" name="new_password" required
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                        @error('new_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" required
                            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-slate-800 dark:bg-slate-700 text-white text-sm font-bold rounded-lg hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors shadow-sm">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    input[type="radio"]:checked + div + div {
        display: block !important;
    }
    input[type="radio"]:checked ~ .flex-1 .text-slate-700 {
        color: #f59e0b !important;
    }
</style>
@endsection
