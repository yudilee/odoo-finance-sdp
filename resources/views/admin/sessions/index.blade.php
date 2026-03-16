@extends('layouts.app')

@section('title', 'Session Manager')

@section('content')
<div class="space-y-6">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-emerald-500">{{ $stats['total_sessions'] }}</p>
            <p class="text-xs text-slate-500">Total Sessions</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-cyan-500">{{ $stats['online_now'] }}</p>
            <p class="text-xs text-slate-500">Online Now</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-violet-500">{{ $stats['today_logins'] }}</p>
            <p class="text-xs text-slate-500">Today's Logins</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-2xl font-bold text-amber-500">{{ $stats['unique_users_today'] }}</p>
            <p class="text-xs text-slate-500">Unique Users Today</p>
        </div>
    </div>

    {{-- Cleanup --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Session Cleanup</h3>
        <form method="POST" action="{{ route('admin.sessions.settings') }}" class="flex flex-wrap gap-4 items-end">
            @csrf
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="session_cleanup_enabled" value="1" {{ $schedule->session_cleanup_enabled ? 'checked' : '' }} class="w-4 h-4 text-emerald-500 rounded">
                Enable auto-cleanup
            </label>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Remove sessions inactive for</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="session_cleanup_days" value="{{ $schedule->session_cleanup_days ?? 7 }}" min="1" max="365" class="w-20 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm">
                    <span class="text-sm text-slate-500">days</span>
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors">Save Settings</button>
            <form method="POST" action="{{ route('admin.sessions.cleanup') }}" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors">Run Cleanup Now</button>
            </form>
        </form>
    </div>

    {{-- Sessions Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">User</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Device</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">IP</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Last Active</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($sessions as $session)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $session->user->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-slate-500">{{ $session->user->email ?? '' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs">{{ $session->browser ?? '' }} / {{ $session->platform ?? '' }}</span>
                            <span class="block text-xs text-slate-500">{{ $session->device_type ?? '' }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $session->ip_address }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $session->last_active_at ? $session->last_active_at->diffForHumans() : '-' }}
                            @if($session->is_current) <span class="ml-1 px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 rounded text-xs">Current</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.sessions.terminate', $session) }}" onsubmit="return confirm('Terminate this session?')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 hover:text-red-700 text-xs font-medium">Terminate</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $sessions->links() }}</div>
    </div>
</div>
@endsection
