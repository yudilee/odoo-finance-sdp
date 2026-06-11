<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncUninvoicedRentals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-uninvoiced-rentals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically synchronize uninvoiced rentals from Odoo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sync for Uninvoiced Rentals...');
        
        try {
            $odoo = new \App\Services\OdooService();
            $soIds = $odoo->getUninvoicedSoIds();
            
            if (empty($soIds)) {
                $this->info('No uninvoiced rental periods found.');
                return;
            }
            
            $chunks = array_chunk($soIds, 500);
            $syncService = new \App\Services\SyncService();
            $totalSaved = 0;
            
            foreach ($chunks as $index => $chunk) {
                $isFirstChunk = ($index === 0);
                $result = $odoo->fetchUninvoicedRentalsBySoIds($chunk);
                if (!empty($result)) {
                    $totalSaved += $syncService->saveUninvoicedRentals($result, $isFirstChunk);
                }
            }
            
            $this->info("Successfully synced {$totalSaved} uninvoiced rentals.");
            \Illuminate\Support\Facades\Log::info("Automated Uninvoiced Rentals Sync completed. Synced {$totalSaved} records.");
            
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Automated Uninvoiced Rentals Sync failed: ' . $e->getMessage());
        }
    }
}
