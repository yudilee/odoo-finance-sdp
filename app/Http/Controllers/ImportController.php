<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Setting;
use App\Models\ImportLog;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\OdooService;

class ImportController extends Controller
{
    /**
     * Show import data page
     */
    public function index()
    {
        $odooConfig = Setting::getOdooConfig();
        
        // Get stored account filter settings
        $accountCodes = json_decode(Setting::get('account_codes', '[]'), true) ?: [
            '111002', '112003', '112012', '112041', '112049'
        ];
        
        // Account labels for display
        $availableAccounts = [
            '111002' => 'Kas Jakarta',
            '112003' => 'BCA Jakarta',
            '112012' => 'Bank Mandiri',
            '112041' => 'BCA Jakarta 2',
            '112049' => 'BCA Bengkel',
        ];
        
        return view('import', compact('odooConfig', 'accountCodes', 'availableAccounts'));
    }

    /**
     * Save Odoo configuration
     */
    public function saveOdooConfig(Request $request)
    {
        $request->validate([
            'odoo_url' => 'required|url',
            'odoo_db' => 'required|string',
            'odoo_user' => 'required|string',
            'odoo_password' => 'required|string',
        ]);

        Setting::set('odoo_url', $request->input('odoo_url'));
        Setting::set('odoo_db', $request->input('odoo_db'));
        Setting::set('odoo_user', $request->input('odoo_user'));
        Setting::set('odoo_password', $request->input('odoo_password'));

        return response()->json(['success' => true, 'message' => 'Configuration saved successfully.']);
    }

    /**
     * Test Odoo connection
     */
    public function testOdooConnection()
    {
        $odoo = new OdooService();
        $result = $odoo->testConnection();
        return response()->json($result);
    }

    /**
     * Sync journal entries from Odoo
     */
    public function syncOdoo(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'account_codes' => 'nullable|array',
            'account_codes.*' => 'string',
        ]);

        try {
            $odoo = new OdooService();
            
            $accountCodes = $request->input('account_codes', []);
            
            // Save selected account codes for next time
            Setting::set('account_codes', json_encode($accountCodes));
            
            $result = $odoo->fetchJournalEntries(
                $request->input('date_from'),
                $request->input('date_to'),
                $accountCodes
            );
            
            if (!$result['success']) {
                // Log failed import
                ImportLog::create([
                    'source' => 'odoo_manual',
                    'imported_at' => now(),
                    'items_count' => 0,
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'Unknown error',
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Odoo fetch failed: ' . ($result['message'] ?? 'Unknown error')
                ]);
            }
            
            if (empty($result['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No journal entries found for the given criteria.',
                    'count' => 0,
                ]);
            }
            
            // Save to database
            $savedCount = $this->saveJournalEntries($result['data']);
            
            // Log successful import
            ImportLog::create([
                'source' => 'odoo_manual',
                'imported_at' => now(),
                'items_count' => $savedCount,
                'status' => 'success',
                'summary_json' => [
                    'date_from' => $request->input('date_from'),
                    'date_to' => $request->input('date_to'),
                    'account_codes' => $accountCodes,
                    'entries_count' => $savedCount,
                    'total_lines' => collect($result['data'])->sum(fn($e) => count($e['lines'])),
                ],
            ]);
            
            Setting::set('odoo_last_sync', now()->toIso8601String());
            
            return response()->json([
                'success' => true,
                'message' => "Synced {$savedCount} journal entries from Odoo",
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            ImportLog::create([
                'source' => 'odoo_manual',
                'imported_at' => now(),
                'items_count' => 0,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Save journal entries to database (upsert by move_name + date)
     */
    protected function saveJournalEntries(array $entries): int
    {
        $count = 0;
        
        foreach ($entries as $entry) {
            // Upsert by move_name
            $journalEntry = JournalEntry::updateOrCreate(
                ['move_name' => $entry['move_name']],
                [
                    'date' => $entry['date'],
                    'journal_name' => $entry['journal_name'],
                    'partner_name' => $entry['partner_name'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'amount_total_signed' => $entry['amount_total_signed'],
                ]
            );
            
            // Delete existing lines and re-insert
            $journalEntry->lines()->delete();
            
            foreach ($entry['lines'] as $line) {
                $journalEntry->lines()->create([
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'],
                    'display_name' => $line['display_name'],
                    'ref' => $line['ref'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }
            
            $count++;
        }
        
        return $count;
    }

    /**
     * Get schedule configuration
     */
    public function getSchedule(): JsonResponse
    {
        return response()->json([
            'enabled' => Setting::getValue('odoo_schedule_enabled', 'false') === 'true',
            'interval' => Setting::getValue('odoo_schedule_interval', 'daily'),
            'last_sync' => Setting::getValue('odoo_last_sync', null),
        ]);
    }

    /**
     * Save schedule configuration
     */
    public function saveSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'interval' => 'required|in:hourly,every_2_hours,every_4_hours,every_6_hours,every_12_hours,daily',
        ]);

        Setting::setValue('odoo_schedule_enabled', $validated['enabled'] ? 'true' : 'false');
        Setting::setValue('odoo_schedule_interval', $validated['interval']);

        return response()->json([
            'success' => true,
            'message' => $validated['enabled'] 
                ? "Auto-sync enabled ({$validated['interval']})" 
                : 'Auto-sync disabled',
        ]);
    }

    /**
     * Get import history
     */
    public function history(): JsonResponse
    {
        $logs = ImportLog::orderBy('imported_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'source' => $log->source,
                    'source_label' => $log->source_label,
                    'filename' => $log->filename,
                    'imported_at' => $log->imported_at->toIso8601String(),
                    'items_count' => $log->items_count,
                    'status' => $log->status,
                    'status_color' => $log->status_color,
                    'error_message' => $log->error_message,
                    'summary' => $log->summary_json,
                ];
            });

        return response()->json($logs);
    }
}
