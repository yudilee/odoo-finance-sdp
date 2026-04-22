<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;
use App\Models\BackupLog;
use Illuminate\Support\Facades\Auth;

class BackupService
{
    protected $disk = 'local';
    protected $backupFolder = 'backups';

    /**
     * Create a backup of the SQLite database
     */
    public function create($remark = null)
    {
        $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sqlite.gz';
        $backupDir = Storage::disk($this->disk)->path($this->backupFolder);
        $backupPath = $backupDir . '/' . $filename;
        
        // Ensure directory exists
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $dbPath = config('database.connections.sqlite.database');
        
        if (!file_exists($dbPath)) {
            throw new \Exception('SQLite database file not found.');
        }

        // Copy and gzip the SQLite file
        $tempPath = $backupDir . '/temp_backup.sqlite';
        copy($dbPath, $tempPath);
        
        // Gzip the file
        $fp = fopen($tempPath, 'rb');
        $gz = gzopen($backupPath, 'wb9');
        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 8192));
        }
        fclose($fp);
        gzclose($gz);
        unlink($tempPath);

        $fileSize = filesize($backupPath);

        BackupLog::create([
            'filename' => $filename,
            'path' => $this->backupFolder . '/' . $filename,
            'disk' => $this->disk,
            'size' => $fileSize,
            'remark' => $remark,
            'created_by' => Auth::check() ? Auth::user()->name : 'System/Scheduler',
        ]);

        return $filename;
    }

    public function list()
    {
        return BackupLog::latest()->get();
    }

    /**
     * Restore from an existing backup file
     */
    public function restore($filename)
    {
        $path = Storage::disk($this->disk)->path($this->backupFolder . '/' . $filename);
        
        if (!file_exists($path)) {
            throw new \Exception('Backup file not found.');
        }

        $this->restoreFromPath($path);
        return true;
    }

    /**
     * Restore from uploaded file
     */
    public function restoreFromFile(UploadedFile $file)
    {
        $tempPath = Storage::disk($this->disk)->path('temp_restore_' . time() . '.sqlite.gz');
        $file->move(dirname($tempPath), basename($tempPath));

        $this->restoreFromPath($tempPath, true);
        return true;
    }

    /**
     * Common restore logic for SQLite
     */
    protected function restoreFromPath($path, $deleteAfter = false)
    {
        $dbPath = config('database.connections.sqlite.database');
        
        // Decompress gzip to temp file
        $tempPath = database_path('restore_temp.sqlite');
        
        $gz = gzopen($path, 'rb');
        $fp = fopen($tempPath, 'wb');
        while (!gzeof($gz)) {
            fwrite($fp, gzread($gz, 8192));
        }
        gzclose($gz);
        fclose($fp);

        // Replace current database
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        rename($tempPath, $dbPath);

        if ($deleteAfter && file_exists($path)) {
            unlink($path);
        }
    }

    public function delete($filename)
    {
        $path = $this->backupFolder . '/' . $filename;
        
        if (Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
        
        BackupLog::where('filename', $filename)->delete();
        return true;
    }
    
    public function download($filename)
    {
        $path = $this->backupFolder . '/' . $filename;
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->download($path);
        }
        return null;
    }

    public function deleteBatch(array $filenames): int
    {
        $deleted = 0;
        foreach ($filenames as $filename) {
            try {
                $this->delete($filename);
                $deleted++;
            } catch (\Exception $e) { }
        }
        return $deleted;
    }

    public function prune(int $keepDaily = 7, int $keepWeekly = 4, int $keepMonthly = 6): array
    {
        $backups = BackupLog::orderByDesc('created_at')->get();
        
        $keepSet = [];
        $dailyCounts = [];
        $weeklyCounts = [];
        $monthlyCounts = [];

        foreach ($backups as $backup) {
            $date = $backup->created_at;
            $dayKey = $date->format('Y-m-d');
            $weekKey = $date->format('Y-W');
            $monthKey = $date->format('Y-m');
            $keep = false;

            if (!isset($dailyCounts[$dayKey])) $dailyCounts[$dayKey] = 0;
            if ($dailyCounts[$dayKey] < 1 && count($dailyCounts) <= $keepDaily) {
                $keep = true;
                $dailyCounts[$dayKey]++;
            }

            if (!isset($weeklyCounts[$weekKey])) $weeklyCounts[$weekKey] = 0;
            if ($weeklyCounts[$weekKey] < 1 && count($weeklyCounts) <= $keepWeekly) {
                $keep = true;
                $weeklyCounts[$weekKey]++;
            }

            if (!isset($monthlyCounts[$monthKey])) $monthlyCounts[$monthKey] = 0;
            if ($monthlyCounts[$monthKey] < 1 && count($monthlyCounts) <= $keepMonthly) {
                $keep = true;
                $monthlyCounts[$monthKey]++;
            }

            if ($keep) $keepSet[$backup->filename] = true;
        }

        $deleted = [];
        foreach ($backups as $backup) {
            if (!isset($keepSet[$backup->filename])) {
                try {
                    $this->delete($backup->filename);
                    $deleted[] = $backup->filename;
                } catch (\Exception $e) { }
            }
        }

        return ['kept' => count($keepSet), 'deleted' => count($deleted)];
    }
}
