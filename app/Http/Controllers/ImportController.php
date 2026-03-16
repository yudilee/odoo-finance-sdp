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
        
        return view('import', compact('accountCodes', 'availableAccounts'));
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
            // Empty database if configured
            if (Setting::get('empty_before_sync', '0') === '1') {
                JournalLine::query()->delete();
                JournalEntry::query()->delete();
            }

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
