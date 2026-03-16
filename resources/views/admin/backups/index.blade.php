@extends('layouts.app')

@section('title', 'Database Backups')

@section('content')
<div class="space-y-6">
    {{-- Create Backup --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Create Backup</h3>
        <form method="POST" action="{{ route('admin.backups.create') }}" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1">Remark (optional)</label>
                <input type="text" name="remark" placeholder="e.g. Before sync" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors whitespace-nowrap">Create Backup</button>
        </form>
    </div>

    {{-- Restore from File --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Restore from File</h3>
        <form method="POST" action="{{ route('admin.backups.restore-file') }}" enctype="multipart/form-data" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <input type="file" name="backup_file" required accept=".gz,.sqlite" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 dark:file:bg-emerald-900/30 file:text-emerald-700 dark:file:text-emerald-300">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors whitespace-nowrap" onclick="return confirm('This will replace the current database. Are you sure?')">Restore</button>
        </form>
    </div>

    {{-- Backups Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Filename</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Size</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Created By</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Remark</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($backups as $backup)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs">{{ $backup->filename }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ number_format($backup->size / 1024, 1) }} KB</td>
                        <td class="px-4 py-3">{{ $backup->created_by }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $backup->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $backup->remark ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.backups.download', $backup->filename) }}" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Download</a>
                                <form method="POST" action="{{ route('admin.backups.restore', $backup->filename) }}" class="inline" onsubmit="return confirm('Replace current database with this backup?')">
                                    @csrf
                                    <button class="text-amber-500 hover:text-amber-700 text-xs font-medium">Restore</button>
                                </form>
                                <form method="POST" action="{{ route('admin.backups.destroy', $backup->filename) }}" class="inline" onsubmit="return confirm('Delete this backup?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No backups yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
