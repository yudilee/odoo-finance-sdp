<?php

namespace App\Http\Controllers;

use App\Models\InvoiceDriver;
use App\Models\InvoiceDriverLine;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceDriverController extends Controller
{
    /**
     * Display the invoice driver listing page
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceDriver::with('lines');

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

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        // Sorting
        $allowedSorts = ['name', 'invoice_date', 'partner_name', 'ref', 'amount_untaxed', 'amount_tax', 'amount_total'];
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

        // Summary stats
        $statsQuery = InvoiceDriver::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $statsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%");
            });
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

        return view('invoice-driver.index', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Sync invoice driver entries from Odoo
     */
    public function sync(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $odoo = new OdooService();

            $result = $odoo->fetchInvoiceDrivers(
                $request->input('date_from'),
                $request->input('date_to')
            );

            if (!$result['success']) {
                ImportLog::create([
                    'source' => 'odoo_invoice_driver',
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
                    'message' => 'No invoice driver entries found for the given date range.',
                    'count' => 0,
                ]);
            }

            // Save to database
            $syncService = new \App\Services\SyncService();
            $savedCount = $syncService->saveInvoiceDrivers($result['data']);

            ImportLog::create([
                'source' => 'odoo_invoice_driver',
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
                'message' => "Synced {$savedCount} invoice driver entries from Odoo",
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            ImportLog::create([
                'source' => 'odoo_invoice_driver',
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
     * Show a single invoice driver entry
     */
    public function show(InvoiceDriver $invoice)
    {
        $invoice->load('lines');

        $prev = InvoiceDriver::where(function($q) use ($invoice) {
                $q->where('invoice_date', '>', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '>', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $next = InvoiceDriver::where(function($q) use ($invoice) {
                $q->where('invoice_date', '<', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '<', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('invoice-driver.show', compact('invoice', 'prev', 'next'));
    }

    /**
     * Print a single invoice driver entry to PDF
     */
    public function printPdf(InvoiceDriver $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        // Track print count (wrap in try-catch to prevent a 500 if DB is locked/write-restricted)
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for invoice: ' . $e->getMessage());
            // Ensure print_count is at least defined to 0 for the view
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-driver.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper('a4', 'portrait');

        $filename = 'invoice_driver_' . str_replace('/', '_', $invoice->name);
        if ($invoice->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoice->print_count;
        }
        
        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print selected invoice driver entries to PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_drivers,id'
        ]);

        $invoices = InvoiceDriver::with('lines')
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
            Log::warning('Could not update print log for selected invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-driver.pdf', compact('invoices'))
                ->setPaper('a4', 'portrait');

        $filename = count($invoices) === 1 
            ? 'invoice_driver_' . str_replace('/', '_', $invoices[0]->name) 
            : 'invoice_driver_export_' . now()->format('YmdHis');

        if (count($invoices) === 1 && $invoices[0]->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoices[0]->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }
    /**
     * Print a single invoice driver entry to HTML
     */
    public function printHtml(InvoiceDriver $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-driver.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'isHtml' => true,
        ]);
    }

    /**
     * Print selected invoice driver entries to HTML
     */
    public function printSelectedHtml(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_drivers,id'
        ]);

        $invoices = InvoiceDriver::with('lines')
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
            Log::warning('Could not update print log for selected invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-driver.pdf', [
            'invoices' => $invoices,
            'isHtml' => true,
        ]);
    }

    /**
     * Print kuitansi for a single invoice driver entry to PDF (half-letter)
     */
    public function kuitansiPdf(InvoiceDriver $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->kuitansi_print_count = $log->kuitansi_print_count;
                $inv->kuitansi_pembayaran = $log->kuitansi_pembayaran;
                $log->increment('kuitansi_print_count');
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->kuitansi_print_count)) $inv->kuitansi_print_count = 0;
                $inv->kuitansi_pembayaran = null;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('partials.kuitansi', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper('a5', 'landscape'); // custom size landscape: A5

        $filename = 'kuitansi_' . str_replace('/', '_', $invoice->name) . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Print kuitansi for a single invoice driver entry via browser (half-letter forced via CSS)
     */
    public function kuitansiHtml(InvoiceDriver $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->kuitansi_print_count = $log->kuitansi_print_count;
                $inv->kuitansi_pembayaran = $log->kuitansi_pembayaran;
                $log->increment('kuitansi_print_count');
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->kuitansi_print_count)) $inv->kuitansi_print_count = 0;
                $inv->kuitansi_pembayaran = null;
            }
        }

        return view('partials.kuitansi', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'isHtml' => true,
        ]);
    }
}
