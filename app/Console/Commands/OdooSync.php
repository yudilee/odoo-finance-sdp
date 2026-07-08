<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OdooService;
use App\Services\SyncService;
use App\Models\ImportLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OdooSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-odoo {--full : Perform a full re-sync instead of quick sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize data from Odoo for all modules';

    /**
     * Execute the console command.
     */
    public function handle(OdooService $odoo, SyncService $sync)
    {
        $isFull = $this->option('full');
        
        // Quick Sync range: previous month to today + 15 days
        $dateFrom = $isFull 
            ? '2025-04-01' 
            : Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d');
        $dateTo = Carbon::today()->addDays(15)->format('Y-m-d');

        $this->info("Starting Odoo Sync (" . ($isFull ? 'FULL' : 'QUICK') . " mode)");
        $this->info("Range: {$dateFrom} to {$dateTo}");

        // 1. Subscription Periods
        $this->syncModule($odoo, $sync, 'Subscription Periods', 'odoo_subscription_periods', function($odoo, $from, $to) {
            return $odoo->fetchSubscriptionInvoicePeriods($from, $to);
        }, function($sync, $data) {
            return $sync->saveInvoiceSubscriptions($data);
        }, $dateFrom, $dateTo);

        // 2. Invoice Driver
        $this->syncModule($odoo, $sync, 'Invoice Driver', 'odoo_invoice_driver', function($odoo, $from, $to) {
            return $odoo->fetchInvoiceDrivers($from, $to);
        }, function($sync, $data) {
            return $sync->saveInvoiceDrivers($data);
        }, $dateFrom, $dateTo);

        // 3. Invoice Rental
        $this->syncModule($odoo, $sync, 'Invoice Rental', 'odoo_invoice_rental', function($odoo, $from, $to) {
            return $odoo->fetchInvoiceRentals($from, $to);
        }, function($sync, $data) {
            return $sync->saveInvoiceRentals($data);
        }, $dateFrom, $dateTo);

        // 4. Invoice Other
        $this->syncModule($odoo, $sync, 'Invoice Other', 'odoo_invoice_other', function($odoo, $from, $to) {
            return $odoo->fetchInvoiceOthers($from, $to);
        }, function($sync, $data) {
            return $sync->saveInvoiceOthers($data);
        }, $dateFrom, $dateTo);

        // 5. Invoice Vehicle (Used Car)
        $this->syncModule($odoo, $sync, 'Invoice Vehicle', 'odoo_invoice_vehicle', function($odoo, $from, $to) {
            return $odoo->fetchInvoiceVehicles($from, $to);
        }, function($sync, $data) {
            return $sync->saveInvoiceVehicles($data);
        }, $dateFrom, $dateTo);

        // 6. Journal Entries
        $this->syncModule($odoo, $sync, 'Journal Entries', 'odoo_journals', function($odoo, $from, $to) {
            return $odoo->fetchJournalEntries($from, $to);
        }, function($sync, $data) {
            return $sync->saveJournalEntries($data);
        }, $dateFrom, $dateTo);

        $this->info('Cleaning up cancelled invoices...');
        $sync->cleanupCancelledInvoices($odoo, $dateFrom, $dateTo);

        $this->info('All modules processed.');
        
        Setting::setValue('odoo_last_sync', now()->format('Y-m-d H:i:s'));
        
        return 0;
    }

    protected function syncModule($odoo, $sync, $label, $source, $fetcher, $saver, $from, $to)
    {
        $this->info("Syncing {$label}...");
        try {
            $result = $fetcher($odoo, $from, $to);
            if (!$result['success']) {
                $this->error("  FAILED: " . ($result['message'] ?? 'Unknown error'));
                $this->logSync($source, 'failed', 0, $result['message'] ?? 'Unknown error', $from, $to);
                return;
            }

            if (empty($result['data'])) {
                $this->comment("  No data found.");
                return;
            }

            $count = $saver($sync, $result['data']);
            $this->info("  SUCCESS: {$count} items synced.");
            $this->logSync($source, 'success', $count, null, $from, $to);
        } catch (\Exception $e) {
            $this->error("  ERROR: " . $e->getMessage());
            $this->logSync($source, 'failed', 0, $e->getMessage(), $from, $to);
            Log::error("Scheduled sync error ({$label}): " . $e->getMessage());
        }
    }

    protected function logSync($source, $status, $count, $error, $from, $to)
    {
        ImportLog::create([
            'source' => $source,
            'imported_at' => now(),
            'items_count' => $count,
            'status' => $status,
            'error_message' => $error,
            'summary_json' => [
                'date_from' => $from,
                'date_to' => $to,
                'is_scheduled' => true
            ]
        ]);
    }
}
