<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ImportLog;
use Illuminate\Support\Facades\Log;

class DbMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:maintenance {--prune-days=30 : Number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform routine database maintenance (Vacuum, Analyze, and Log Pruning)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database maintenance...');
        Log::info('Database maintenance started.');

        // 1. Prune Import Logs
        $days = (int) $this->option('prune-days');
        $this->info("Pruning import logs older than {$days} days...");
        
        $deleted = ImportLog::where('imported_at', '<', now()->subDays($days))->delete();
        
        $this->info("Deleted {$deleted} old log entries.");
        Log::info("Maintenance: Pruned {$deleted} old import logs.");

        // 2. Clear Expired Cache
        $this->info('Clearing expired cache...');
        $this->call('cache:prune-stale-tags');

        // 3. SQLite Specific Maintenance
        if (config('database.default') === 'sqlite') {
            $this->info('Running SQLite VACUUM...');
            DB::statement('VACUUM');
            
            $this->info('Running SQLite ANALYZE...');
            DB::statement('ANALYZE');
            
            $this->info('SQLite optimization complete.');
        }

        $this->info('Database maintenance completed successfully.');
        Log::info('Database maintenance completed.');
    }
}
