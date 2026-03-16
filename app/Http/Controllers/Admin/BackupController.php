<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $backups = $this->backupService->list();
        $schedule = BackupSchedule::first() ?? new BackupSchedule([
            'enabled' => false,
            'frequency' => 'daily',
            'time' => '00:00',
        ]);
        return view('admin.backups.index', compact('backups', 'schedule'));
    }

    public function create(Request $request)
    {
        $request->validate(['remark' => 'nullable|string|max:255']);
        
        try {
            $this->backupService->create($request->input('remark'));
            return redirect()->route('admin.backups.index')->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            return redirect()->route('admin.backups.index')->with('error', 'Backup creation failed: ' . $e->getMessage());
        }
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'enabled' => 'nullable|boolean',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'prune_enabled' => 'nullable|boolean',
            'keep_daily' => 'nullable|integer|min:0|max:365',
            'keep_weekly' => 'nullable|integer|min:0|max:52',
            'keep_monthly' => 'nullable|integer|min:0|max:24',
        ]);

        BackupSchedule::updateOrCreate(
            ['id' => 1],
            [
                'enabled' => $request->boolean('enabled'),
                'frequency' => $request->input('frequency'),
                'time' => $request->input('time'),
                'prune_enabled' => $request->boolean('prune_enabled'),
                'keep_daily' => $request->input('keep_daily', 7),
                'keep_weekly' => $request->input('keep_weekly', 4),
                'keep_monthly' => $request->input('keep_monthly', 6),
            ]
        );

        return redirect()->route('admin.backups.index')->with('success', 'Backup schedule updated successfully.');
    }

    public function restore($filename)
    {
        try {
            $this->backupService->restore($filename);
            return redirect()->route('admin.backups.index')->with('success', 'Database restored successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function restoreFromFile(Request $request)
    {
        $request->validate(['backup_file' => 'required|file|max:512000']);

        try {
            $this->backupService->restoreFromFile($request->file('backup_file'));
            return redirect()->route('admin.backups.index')->with('success', 'Database restored successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function delete($filename)
    {
        try {
            $this->backupService->delete($filename);
            return redirect()->route('admin.backups.index')->with('success', 'Backup deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }
    
    public function download($filename)
    {
        return $this->backupService->download($filename);
    }

    public function deleteBatch(Request $request)
    {
        $request->validate(['filenames' => 'required|array|min:1', 'filenames.*' => 'string']);

        try {
            $deleted = $this->backupService->deleteBatch($request->input('filenames'));
            return redirect()->route('admin.backups.index')->with('success', "Deleted {$deleted} backup(s) successfully.");
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')->with('error', 'Failed to delete backups: ' . $e->getMessage());
        }
    }

    public function prune()
    {
        $schedule = BackupSchedule::first();
        
        try {
            $result = $this->backupService->prune(
                $schedule->keep_daily ?? 7,
                $schedule->keep_weekly ?? 4,
                $schedule->keep_monthly ?? 6
            );
            return redirect()->route('admin.backups.index')
                ->with('success', "Pruning complete: Kept {$result['kept']} backups, deleted {$result['deleted']}.");
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')->with('error', 'Prune failed: ' . $e->getMessage());
        }
    }
}
