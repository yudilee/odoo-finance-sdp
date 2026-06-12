<?php

namespace App\Http\Controllers;

use App\Models\InvoiceRental;
use App\Models\InvoiceRentalLine;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceRentalController extends Controller
{
    /**
     * Display the invoice rental listing page
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceRental::with('lines');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('bc_manager', 'like', "%{$search}%")
                  ->orWhere('bc_spv', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where([['invoice_date', '>=', $request->date_from]]);
        }
        if ($request->filled('date_to')) {
            $query->where([['invoice_date', '<=', $request->date_to]]);
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
        $statsQuery = InvoiceRental::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('partner_name', 'like', "%{$search}%")
                        ->orWhere('ref', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('invoice_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where('invoice_date', '<=', $request->date_to);
            });

        $stats = $statsQuery->selectRaw("
                count(*) as total_invoices,
                sum(amount_untaxed) as total_untaxed,
                sum(amount_tax) as total_tax,
                sum(amount_total) as total_amount
            ")
            ->first()
            ->toArray();

        return view('invoice-rental.index', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Get all Odoo IDs for the given date range to sync
     */
    public function getSyncIds(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $odoo = new OdooService();
            $result = $odoo->getInvoiceRentalIds(
                $request->input('date_from'),
                $request->input('date_to')
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Odoo fetch failed: ' . ($result['message'] ?? 'Unknown error')
                ]);
            }

            return response()->json([
                'success' => true,
                'ids' => $result['ids'],
                'count' => $result['count']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch IDs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Sync a specific batch of IDs
     */
    public function syncBatch(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        try {
            $odoo = new OdooService();
            $result = $odoo->fetchInvoiceRentalsByIds($request->input('ids'));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Odoo batch fetch failed: ' . ($result['message'] ?? 'Unknown error')
                ]);
            }

            if (empty($result['data'])) {
                return response()->json([
                    'success' => true,
                    'count' => 0,
                    'message' => 'No data returned for this batch.'
                ]);
            }

            $syncService = new \App\Services\SyncService();
            $savedCount = $syncService->saveInvoiceRentals($result['data']);

            return response()->json([
                'success' => true,
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch sync failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Sync invoice rental entries from Odoo (Legacy/Single-shot)
     */
    public function sync(Request $request)
    {
        // For backward compatibility or small ranges
        return $this->getSyncIds($request);
    }



    /**
     * Show a single invoice rental entry
     */
    public function show(InvoiceRental $invoice)
    {
        $invoice->load('lines');

        $prev = InvoiceRental::where(function($q) use ($invoice) {
                $q->where('invoice_date', '>', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '>', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $next = InvoiceRental::where(function($q) use ($invoice) {
                $q->where('invoice_date', '<', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '<', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('invoice-rental.show', compact('invoice', 'prev', 'next'));
    }

    /**
     * Print a single invoice rental entry to PDF
     */
    public function printPdf(Request $request, InvoiceRental $invoice)
    {
        $printMode = $request->query('print_mode', 'detail');
        \Illuminate\Support\Facades\Log::info("InvoiceRental Print PDF Mode: " . $printMode);
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        $cetakan = 'detail_nopol';
        if ($request->query('print_mode') === 'summary') {
            $cetakan = 'summary';
        } elseif ($request->query('hide_nopol') === '1') {
            $cetakan = 'without_nopol';
        } elseif ($request->query('show_username') === '1') {
            $cetakan = 'detail_username';
        }

        // Track print count
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => $cetakan]);
                
                $sessionKey = 'printed_' . md5($inv->name . '_' . $cetakan);
                $isDebounced = session()->has($sessionKey) && session()->get($sessionKey) > time() - 5;
                
                if (!$isDebounced) {
                    $previousCount = $log->print_count ?? 0;
                    $log->increment('print_count');
                    session()->put($sessionKey, time());
                    session()->put($sessionKey . '_prev', $previousCount);
                } else {
                    $previousCount = session()->get($sessionKey . '_prev', max(0, ($log->print_count ?? 1) - 1));
                }
                
                $inv->print_count = $previousCount;
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-rental.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'hideNopol' => $request->query('hide_nopol', '0') === '1',
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'defaultManager' => Setting::get('default_bc_manager', ''),
            'defaultSpv' => Setting::get('default_bc_spv', ''),
        ])->setPaper('a4', 'portrait');

        $filename = 'invoice_rental_' . str_replace('/', '_', $invoice->name);
        if ($invoice->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoice->print_count;
        }
        
        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print selected invoice rental entries to PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_rentals,id'
        ]);

        $printMode = $request->query('print_mode', 'detail');
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoices = InvoiceRental::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('name', 'desc')
            ->get();

        $cetakan = 'detail_nopol';
        if ($request->query('print_mode') === 'summary') {
            $cetakan = 'summary';
        } elseif ($request->query('hide_nopol') === '1') {
            $cetakan = 'without_nopol';
        } elseif ($request->query('show_username') === '1') {
            $cetakan = 'detail_username';
        }

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => $cetakan]);
                
                $sessionKey = 'printed_' . md5($inv->name . '_' . $cetakan);
                $isDebounced = session()->has($sessionKey) && session()->get($sessionKey) > time() - 5;
                
                if (!$isDebounced) {
                    $previousCount = $log->print_count ?? 0;
                    $log->increment('print_count');
                    session()->put($sessionKey, time());
                    session()->put($sessionKey . '_prev', $previousCount);
                } else {
                    $previousCount = session()->get($sessionKey . '_prev', max(0, ($log->print_count ?? 1) - 1));
                }
                
                $inv->print_count = $previousCount;
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for selected invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-rental.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'hideNopol' => $request->query('hide_nopol', '0') === '1'
        ])->setPaper('a4', 'portrait');

        $filename = count($invoices) === 1 
            ? 'invoice_rental_' . str_replace('/', '_', $invoices[0]->name) 
            : 'invoice_rental_export_' . now()->format('YmdHis');

        if (count($invoices) === 1 && $invoices[0]->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoices[0]->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }
    /**
     * Print a single invoice rental entry to HTML
     */
    public function printHtml(Request $request, InvoiceRental $invoice)
    {
        $printMode = $request->query('print_mode', 'detail');
        \Illuminate\Support\Facades\Log::info("InvoiceRental Print HTML Mode: " . $printMode);
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        $cetakan = 'detail_nopol';
        if ($request->query('print_mode') === 'summary') {
            $cetakan = 'summary';
        } elseif ($request->query('hide_nopol') === '1') {
            $cetakan = 'without_nopol';
        } elseif ($request->query('show_username') === '1') {
            $cetakan = 'detail_username';
        }

        // Track print count
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => $cetakan]);
                $inv->print_count = $log->print_count;
                // HTML preview does not increment the print count
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-rental.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'hideNopol' => $request->query('hide_nopol', '0') === '1',
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'defaultManager' => Setting::get('default_bc_manager', ''),
            'defaultSpv' => Setting::get('default_bc_spv', ''),
            'isHtml' => true,
        ]);
    }

    /**
     * Print selected invoice rental entries to HTML
     */
    public function printSelectedHtml(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_rentals,id'
        ]);

        $printMode = $request->query('print_mode', 'detail');
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoices = InvoiceRental::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('name', 'desc')
            ->get();

        $cetakan = 'detail_nopol';
        if ($request->query('print_mode') === 'summary') {
            $cetakan = 'summary';
        } elseif ($request->query('hide_nopol') === '1') {
            $cetakan = 'without_nopol';
        } elseif ($request->query('show_username') === '1') {
            $cetakan = 'detail_username';
        }

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => $cetakan]);
                $inv->print_count = $log->print_count;
                // HTML preview does not increment the print count
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for selected invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        return view('invoice-rental.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'hideNopol' => $request->query('hide_nopol', '0') === '1',
            'isHtml' => true,
        ]);
    }

    /**
     * Print kuitansi to PDF (half-letter)
     */
    public function kuitansiPdf(InvoiceRental $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => 'default']);
                
                $sessionKey = 'printed_kuitansi_' . md5($inv->name);
                $isDebounced = session()->has($sessionKey) && session()->get($sessionKey) > time() - 5;
                
                if (!$isDebounced) {
                    $previousCount = $log->kuitansi_print_count ?? 0;
                    $log->increment('kuitansi_print_count');
                    session()->put($sessionKey, time());
                    session()->put($sessionKey . '_prev', $previousCount);
                } else {
                    $previousCount = session()->get($sessionKey . '_prev', max(0, ($log->kuitansi_print_count ?? 1) - 1));
                }
                
                $inv->kuitansi_print_count = $previousCount;
                $inv->kuitansi_pembayaran = $log->kuitansi_pembayaran;
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
     * Print kuitansi via browser (half-letter forced via CSS)
     */
    public function kuitansiHtml(InvoiceRental $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name, 'print_mode' => 'default']);
                $inv->kuitansi_print_count = $log->kuitansi_print_count;
                $inv->kuitansi_pembayaran = $log->kuitansi_pembayaran;
                // HTML preview does not increment the count
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

    /**
     * Refresh a single invoice from Odoo (quick re-sync)
     */
    public function refreshFromOdoo(InvoiceRental $invoice)
    {
        try {
            $odoo = new OdooService();

            // Search for this invoice's Odoo ID by name
            $domain = [
                ['state', '=', 'posted'],
                ['name', '=', $invoice->name],
            ];
            $ids = $odoo->execute('account.move', 'search', [$domain]);

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found in Odoo: ' . $invoice->name
                ]);
            }

            // Fetch the full data for this single invoice
            $result = $odoo->fetchInvoiceRentalsByIds($ids);

            if (!$result['success'] || empty($result['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch invoice data from Odoo.'
                ]);
            }

            // Save/update the invoice locally
            $syncService = new \App\Services\SyncService();
            $savedCount = $syncService->saveInvoiceRentals($result['data']);

            return response()->json([
                'success' => true,
                'message' => "Refreshed {$invoice->name} successfully.",
                'count' => $savedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh failed: ' . $e->getMessage()
            ]);
        }
    }

}
