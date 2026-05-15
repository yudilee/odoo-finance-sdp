<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use App\Models\BackupSchedule;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-db {--remark=Scheduled Backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger a database backup';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService)
    {
        $this->info('Starting database backup...');
        
        try {
            $filename = $backupService->create($this->option('remark'));
            $this->info("Backup created successfully: {$filename}");
            
            // Prune if enabled
            $schedule = BackupSchedule::first();
            if ($schedule && $schedule->prune_enabled) {
                $this->info('Pruning old backups...');
                $result = $backupService->prune(
                    $schedule->keep_daily ?? 7,
                    $schedule->keep_weekly ?? 4,
                    $schedule->keep_monthly ?? 6
                );
                $this->info("Pruning complete. Deleted {$result['deleted']} backups.");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('CLI Backup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
