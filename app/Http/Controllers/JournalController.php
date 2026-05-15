<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\PrintHubService;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Get list of printers from Print Hub
     */
    public function getPrinters()
    {
        $service = new PrintHubService();
        return response()->json($service->getPrinters());
    }

    /**
     * Send a single journal entry to Print Hub
     */
    public function printViaHub(JournalEntry $entry, Request $request)
    {
        $printer = $request->input('printer') ?? auth()->user()->getPreference('default_printer');
        
        if (!$printer) {
            return response()->json(['success' => false, 'message' => 'No printer selected and no default printer set.'], 422);
        }

        $entry->load('lines');
        $entries = collect([$entry]);
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journals.pdf', compact('entries', 'paperSize', 'isPdf'))
                ->setPaper($paperSize === 'A4' ? 'a4' : 'a5', $paperSize === 'A4' ? 'portrait' : 'landscape');
        
        $pdfBase64 = base64_encode($pdf->output());
        
        $service = new PrintHubService();
        $result = $service->printPdf($printer, $pdfBase64, [
            'job_name' => 'Journal: ' . $entry->move_name,
        ]);

        if ($result['success'] && $request->has('set_default')) {
            auth()->user()->setPreference('default_printer', $printer);
        }

        return response()->json($result);
    }

    /**
     * Send multiple selected journal entries to Print Hub
     */
    public function printSelectedViaHub(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:journal_entries,id'
        ]);

        $printer = $request->input('printer') ?? auth()->user()->getPreference('default_printer');
        
        if (!$printer) {
            return response()->json(['success' => false, 'message' => 'No printer selected and no default printer set.'], 422);
        }

        $entries = JournalEntry::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('date', 'desc')
            ->orderBy('move_name', 'desc')
            ->get();
            
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journals.pdf', compact('entries', 'paperSize', 'isPdf'))
                ->setPaper($paperSize === 'A4' ? 'a4' : 'a5', $paperSize === 'A4' ? 'portrait' : 'landscape');
        
        $pdfBase64 = base64_encode($pdf->output());
        
        $service = new PrintHubService();
        $result = $service->printPdf($printer, $pdfBase64, [
            'job_name' => 'Bulk Journals (' . count($entries) . ')',
        ]);

        if ($result['success'] && $request->has('set_default')) {
            auth()->user()->setPreference('default_printer', $printer);
        }

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $flowType = $request->input('flow_type', 'credit');
        $filterApplied = $request->has('filter_applied');
        $sort = $request->input('sort', 'date');
        $dir = $request->input('dir', 'desc');
        
        // Get unique account codes for 111% and 112%
        $accountCodes = JournalLine::select('account_code', 'account_name')
            ->where(function($q) {
                $q->where('account_code', 'like', '111%')
                  ->orWhere('account_code', 'like', '112%');
            })
            ->distinct()
            ->orderBy('account_code')
            ->get();
            
        $allAccountCodes = $accountCodes->pluck('account_code')->toArray();
        $selectedAccounts = $filterApplied ? $request->input('accounts', []) : $allAccountCodes;

        // Eager load all lines so debit lines still show 
        $query = JournalEntry::with('lines');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('move_name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('journal_name', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Account filter (for entries)
        if (!empty($selectedAccounts)) {
            $query->whereHas('lines', function ($q) use ($selectedAccounts) {
                $q->whereIn('account_code', $selectedAccounts);
            });
        } else {
            // If none selected, return empty 
            $query->whereRaw('1 = 0');
        }

        // Journal filter
        if ($request->filled('journal')) {
            $query->where('journal_name', $request->journal);
        }

        // Flow filter (for entries)
        if ($flowType === 'credit' || $flowType === 'debit') {
            $query->whereHas('lines', function ($q) use ($selectedAccounts, $flowType) {
                // Strictly evaluate against the first line of the entry
                $q->whereRaw('journal_lines.id = (select min(id) from journal_lines as jl2 where jl2.journal_entry_id = journal_lines.journal_entry_id)');

                if ($flowType === 'credit') {
                    $q->where('credit', '>', 0);
                } else {
                    $q->where('debit', '>', 0);
                }
                
                if (!empty($selectedAccounts)) {
                    $q->whereIn('account_code', $selectedAccounts);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // Apply Sorting
        $allowedSorts = ['move_name', 'date', 'partner_name', 'ref', 'amount_total_signed'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'date';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $query->orderBy($sort, $dir);
        if ($sort !== 'move_name' && $sort !== 'date') {
            $query->orderBy('date', 'desc');
        }
        if ($sort !== 'move_name') {
            $query->orderBy('move_name', 'desc');
        }

        $perPage = request('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $entries = $query->paginate($perPage)->withQueryString();

        $journalNames = JournalEntry::select('journal_name')
            ->distinct()
            ->orderBy('journal_name')
            ->pluck('journal_name');

        // Summary stats based on filtered query
        $totalEntries = (clone $query)->count();
        $lineStats = JournalLine::whereIn('journal_entry_id', (clone $query)->select('id'))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $stats = [
            'total_entries' => $totalEntries,
            'total_debit'   => $lineStats->total_debit ?? 0,
            'total_credit'  => $lineStats->total_credit ?? 0,
        ];

        return view('journals.index', compact('entries', 'accountCodes', 'journalNames', 'stats', 'flowType', 'selectedAccounts', 'sort', 'dir', 'perPage'));
    }

    /**
     * Show a single journal entry with its lines
     */
    public function show(JournalEntry $entry)
    {
        $entry->load('lines');
        
        // Navigation based on default sort (date desc, id desc)
        // Previous = Newer
        $prev = JournalEntry::where(function($q) use ($entry) {
                $q->where('date', '>', $entry->date)
                  ->orWhere(function($q2) use ($entry) {
                      $q2->where('date', '=', $entry->date)
                         ->where('id', '>', $entry->id);
                  });
            })
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        // Next = Older
        $next = JournalEntry::where(function($q) use ($entry) {
                $q->where('date', '<', $entry->date)
                  ->orWhere(function($q2) use ($entry) {
                      $q2->where('date', '=', $entry->date)
                         ->where('id', '<', $entry->id);
                  });
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('journals.show', compact('entry', 'prev', 'next'));
    }

    /**
     * Print a single journal entry to PDF
     */
    public function printPdf(JournalEntry $entry)
    {
        $entry->load('lines');
        $entries = collect([$entry]);
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journals.pdf', compact('entries', 'paperSize', 'isPdf'))
                ->setPaper($paperSize === 'A4' ? 'a4' : 'a5', $paperSize === 'A4' ? 'portrait' : 'landscape');
        return $pdf->stream('journal_entry_' . str_replace('/', '_', $entry->move_name) . '.pdf');
    }

    public function printHtml(JournalEntry $entry)
    {
        $entry->load('lines');
        $entries = collect([$entry]);
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = false;

        return view('journals.print', compact('entries', 'paperSize', 'isPdf'));
    }

    /**
     * Print all currently filtered journal entries to a multi-page PDF
     */
    public function printAllPdf(Request $request)
    {
        $flowType = $request->input('flow_type', 'credit');
        $filterApplied = $request->has('filter_applied');
        
        $allAccountCodes = JournalLine::where(function($q) {
                $q->where('account_code', 'like', '111%')
                  ->orWhere('account_code', 'like', '112%');
            })
            ->select('account_code')
            ->distinct()
            ->pluck('account_code')
            ->toArray();
            
        $selectedAccounts = $filterApplied ? $request->input('accounts', []) : $allAccountCodes;

        // Replicate the filtering logic from index()
        $query = JournalEntry::with('lines');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('move_name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('journal_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        if (!empty($selectedAccounts)) {
            $query->whereHas('lines', function ($q) use ($selectedAccounts) {
                $q->whereIn('account_code', $selectedAccounts);
            });
        } else {
            $query->whereRaw('1 = 0');
        }
        if ($request->filled('journal')) {
            $query->where('journal_name', $request->journal);
        }
        if ($flowType === 'credit' || $flowType === 'debit') {
            $query->whereHas('lines', function ($q) use ($selectedAccounts, $flowType) {
                // Strictly evaluate against the first line of the entry
                $q->whereRaw('journal_lines.id = (select min(id) from journal_lines as jl2 where jl2.journal_entry_id = journal_lines.journal_entry_id)');

                if ($flowType === 'credit') {
                    $q->where('credit', '>', 0);
                } else {
                    $q->where('debit', '>', 0);
                }
                
                if (!empty($selectedAccounts)) {
                    $q->whereIn('account_code', $selectedAccounts);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // Get all matching entries (do not paginate, we need all of them for PDF)
        // We'll order them the same way as the UI.
        $entries = $query->orderBy('date', 'desc')->orderBy('move_name', 'desc')->get();
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journals.pdf', compact('entries', 'paperSize', 'isPdf'))
                ->setPaper($paperSize === 'A4' ? 'a4' : 'a5', $paperSize === 'A4' ? 'portrait' : 'landscape');
        
        return $pdf->stream('journal_entries_export_' . now()->format('YmdHis') . '.pdf');
    }

    public function printAllHtml(Request $request)
    {
        // Re-use logic to get all entries
        $flowType = $request->input('flow_type', 'credit');
        $filterApplied = $request->has('filter_applied');
        $allAccountCodes = JournalLine::where(function($q) {
                $q->where('account_code', 'like', '111%')
                  ->orWhere('account_code', 'like', '112%');
            })->select('account_code')->distinct()->pluck('account_code')->toArray();
        $selectedAccounts = $filterApplied ? $request->input('accounts', []) : $allAccountCodes;

        $query = JournalEntry::with('lines');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('move_name', 'like', "%{$search}%")->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")->orWhere('journal_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) $query->where('date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->where('date', '<=', $request->date_to);
        if (!empty($selectedAccounts)) {
            $query->whereHas('lines', function ($q) use ($selectedAccounts) { $q->whereIn('account_code', $selectedAccounts); });
        } else {
            $query->whereRaw('1 = 0');
        }
        if ($request->filled('journal')) $query->where('journal_name', $request->journal);
        if ($flowType === 'credit' || $flowType === 'debit') {
            $query->whereHas('lines', function ($q) use ($selectedAccounts, $flowType) {
                $q->whereRaw('journal_lines.id = (select min(id) from journal_lines as jl2 where jl2.journal_entry_id = journal_lines.journal_entry_id)');
                if ($flowType === 'credit') $q->where('credit', '>', 0);
                else $q->where('debit', '>', 0);
                if (!empty($selectedAccounts)) $q->whereIn('account_code', $selectedAccounts);
                else $q->whereRaw('1 = 0');
            });
        }

        $entries = $query->orderBy('date', 'desc')->orderBy('move_name', 'desc')->get();
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = false;

        return view('journals.print', compact('entries', 'paperSize', 'isPdf'));
    }

    /**
     * Print specifically selected journal entries to a multi-page PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:journal_entries,id'
        ]);

        $entries = JournalEntry::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('date', 'desc')
            ->orderBy('move_name', 'desc')
            ->get();
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = true;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journals.pdf', compact('entries', 'paperSize', 'isPdf'))
                ->setPaper($paperSize === 'A4' ? 'a4' : 'a5', $paperSize === 'A4' ? 'portrait' : 'landscape');
        return $pdf->stream('selected_journal_entries.pdf');
    }

    public function printSelectedHtml(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:journal_entries,id'
        ]);

        $entries = JournalEntry::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('date', 'desc')
            ->orderBy('move_name', 'desc')
            ->get();
        $paperSize = \App\Models\Setting::get('journal_paper_size', 'A5');
        $isPdf = false;

        return view('journals.print', compact('entries', 'paperSize', 'isPdf'));
    }
}
