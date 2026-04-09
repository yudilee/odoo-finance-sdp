<?php

namespace App\Http\Controllers;

use App\Models\InvoiceOther;
use App\Models\InvoiceOtherLine;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceOtherController extends Controller
{
    /**
     * Display the invoice other listing page
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceOther::with('lines');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('manager_name', 'like', "%{$search}%")
                  ->orWhere('spv_name', 'like', "%{$search}%");
            });
        }

        // Type filter (with_tax / without_tax)
        if ($request->filled('type')) {
            if ($request->type === 'with_tax') {
                $query->where('name', 'like', 'INVOT%');
            } elseif ($request->type === 'without_tax') {
                $query->where('name', 'like', 'INVOW%');
            }
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        // Sorting
        $allowedSorts = ['name', 'invoice_date', 'partner_name', 'ref', 'amount_untaxed', 'amount_tax', 'amount_total', 'journal_name'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'invoice_date';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $query->orderBy($sort, $dir);
        if ($sort !== 'name') {
            $query->orderBy('name', 'desc');
        }

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $invoices = $query->paginate($perPage)->withQueryString();

        // Summary stats (apply same filters)
        $statsQuery = InvoiceOther::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $statsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%");
            });
        }
        if ($request->filled('type')) {
            if ($request->type === 'with_tax') {
                $statsQuery->where('name', 'like', 'INVOT%');
            } elseif ($request->type === 'without_tax') {
                $statsQuery->where('name', 'like', 'INVOW%');
            }
        }
        if ($request->filled('date_from')) {
            $statsQuery->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $statsQuery->where('invoice_date', '<=', $request->date_to);
        }

        $stats = [
            'total_invoices' => $statsQuery->count(),
            'total_untaxed' => (clone $statsQuery)->sum('amount_untaxed'),
            'total_tax' => (clone $statsQuery)->sum('amount_tax'),
            'total_amount' => (clone $statsQuery)->sum('amount_total'),
        ];

        return view('invoice-other.index', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Sync invoice other entries from Odoo
     */
    public function sync(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $odoo = new OdooService();

            $result = $odoo->fetchInvoiceOthers(
                $request->input('date_from'),
                $request->input('date_to')
            );

            if (!$result['success']) {
                ImportLog::create([
                    'source' => 'odoo_invoice_other',
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
                    'message' => 'No invoice other entries found for the given date range.',
                    'count' => 0,
                ]);
            }

            // Save to database
            $savedCount = $this->saveInvoiceOthers($result['data']);

            ImportLog::create([
                'source' => 'odoo_invoice_other',
                'imported_at' => now(),
                'items_count' => $savedCount,
                'status' => 'success',
                'summary_json' => [
                    'date_from' => $request->input('date_from'),
                    'date_to' => $request->input('date_to'),
                    'entries_count' => $savedCount,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced {$savedCount} invoice other entries from Odoo",
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            ImportLog::create([
                'source' => 'odoo_invoice_other',
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
     * Save invoice other entries to database (upsert by name)
     */
    protected function saveInvoiceOthers(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            $invoice = InvoiceOther::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'invoice_date_due' => $entry['invoice_date_due'] ?? null,
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Other',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                ]
            );

            // Delete existing lines and re-insert
            $invoice->lines()->delete();

            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Show a single invoice other entry
     */
    public function show(InvoiceOther $invoice)
    {
        $invoice->load('lines');

        $prev = InvoiceOther::where(function($q) use ($invoice) {
                $q->where('invoice_date', '>', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '>', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $next = InvoiceOther::where(function($q) use ($invoice) {
                $q->where('invoice_date', '<', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '<', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('invoice-other.show', compact('invoice', 'prev', 'next'));
    }

    /**
     * Print a single invoice other entry to PDF
     */
    public function printPdf(InvoiceOther $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        // Track print count (wrap in try-catch for production safety)
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for other invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-other.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper('a4', 'portrait');

        $filename = 'invoice_other_' . str_replace('/', '_', $invoice->name);
        if ($invoice->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoice->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print selected invoice other entries to PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_others,id'
        ]);

        $invoices = InvoiceOther::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('name', 'desc')
            ->get();

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for selected other invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-other.pdf', compact('invoices'))
                ->setPaper('a4', 'portrait');

        $filename = count($invoices) === 1 
            ? 'invoice_other_' . str_replace('/', '_', $invoices[0]->name) 
            : 'invoice_other_export_' . now()->format('YmdHis');

        if (count($invoices) === 1 && $invoices[0]->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoices[0]->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }
    /**
     * Print a single invoice other entry to HTML
     */
    public function printHtml(InvoiceOther $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        // Track print count (wrap in try-catch for production safety)
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for other invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-other.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'isHtml' => true,
        ]);
    }

    /**
     * Print selected invoice other entries to HTML
     */
    public function printSelectedHtml(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_others,id'
        ]);

        $invoices = InvoiceOther::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('name', 'desc')
            ->get();

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for selected other invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-other.pdf', [
            'invoices' => $invoices,
            'isHtml' => true,
        ]);
    }

    /**
     * Print kuitansi to PDF (half-letter)
     */
    public function kuitansiPdf(InvoiceOther $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('partials.kuitansi', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper([0, 0, 396, 612], 'landscape'); // half-letter landscape: 8.5in x 5.5in

        $filename = 'kuitansi_' . str_replace('/', '_', $invoice->name) . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Print kuitansi via browser (half-letter forced via CSS)
     */
    public function kuitansiHtml(InvoiceOther $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('partials.kuitansi', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'isHtml' => true,
        ]);
    }

}
