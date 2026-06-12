<?php

namespace App\Http\Controllers;

use App\Models\InvoiceProforma;
use App\Models\InvoiceProformaLine;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceProformaController extends Controller
{
    /**
     * Display the invoice proforma listing page
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceProforma::with('lines');

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
        $query->orderBy('odoo_id', 'desc');

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $invoices = $query->paginate($perPage)->withQueryString();

        // Summary stats
        $statsQuery = InvoiceProforma::query()
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

        return view('invoice-proforma.index', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Display the proforma report page (downloaded history)
     */
    public function report(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceProforma::select('invoice_proformas.*')
            ->with('lines')
            ->whereNotNull('proforma_number')
            ->addSelect(['last_print_mode' => \App\Models\PrintLog::select('print_mode')
                ->whereRaw("invoice_name = 'PROFORMA_' || invoice_proformas.odoo_id")
                ->orderBy('updated_at', 'desc')
                ->limit(1)
            ]);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('proforma_number', 'like', "%{$search}%")
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
        $allowedSorts = ['name', 'proforma_number', 'invoice_date', 'partner_name', 'ref', 'amount_untaxed', 'amount_tax', 'amount_total', 'print_count'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'invoice_date';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $query->orderBy($sort, $dir);
        if ($sort !== 'proforma_number') {
            $query->orderBy('proforma_number', 'desc');
        }
        $query->orderBy('odoo_id', 'desc');

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $invoices = $query->paginate($perPage)->withQueryString();

        // Summary stats
        $statsQuery = InvoiceProforma::query()->whereNotNull('proforma_number')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('proforma_number', 'like', "%{$search}%")
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

        return view('invoice-proforma.report', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
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
            $result = $odoo->getInvoiceProformaIds(
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
            $result = $odoo->fetchInvoiceProformasByIds($request->input('ids'));

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
            $savedCount = $syncService->saveInvoiceProformas($result['data']);

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
     * Sync invoice proforma entries from Odoo (Legacy/Single-shot)
     */
    public function sync(Request $request)
    {
        return $this->getSyncIds($request);
    }

    /**
     * Show a single invoice proforma entry
     */
    public function show(InvoiceProforma $invoice)
    {
        $invoice->load('lines');

        $prev = InvoiceProforma::where(function($q) use ($invoice) {
                $q->where('invoice_date', '>', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '>', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $next = InvoiceProforma::where(function($q) use ($invoice) {
                $q->where('invoice_date', '<', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '<', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('invoice-proforma.show', compact('invoice', 'prev', 'next'));
    }

    /**
     * Ensure Proforma number is generated
     */
    private function ensureProformaNumber(InvoiceProforma $invoice)
    {
        if (!empty($invoice->proforma_number)) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
            $fresh = InvoiceProforma::where('id', $invoice->id)->lockForUpdate()->first();
            if (!empty($fresh->proforma_number)) {
                $invoice->proforma_number = $fresh->proforma_number;
                return;
            }

            $date = $fresh->invoice_date ?: now();
            $year = $date->format('y');

            $prefix = "PRO/{$year}/PRO/";

            $lastInvoice = InvoiceProforma::where('proforma_number', 'like', "{$prefix}%")
                ->orderBy('proforma_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextSequence = 1;
            if ($lastInvoice && !empty($lastInvoice->proforma_number)) {
                $parts = explode('/', $lastInvoice->proforma_number);
                $lastSequence = (int) end($parts);
                $nextSequence = $lastSequence + 1;
            }

            $newNumber = $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

            $fresh->proforma_number = $newNumber;
            $fresh->save();

            $invoice->proforma_number = $newNumber;
        });
    }

    /**
     * Print a single invoice proforma entry to PDF
     */
    public function printPdf(Request $request, InvoiceProforma $invoice)
    {
        $printMode = $request->query('print_mode', 'detail');
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
                $this->ensureProformaNumber($inv);
                $log = PrintLog::firstOrCreate(['invoice_name' => 'PROFORMA_' . $inv->odoo_id, 'print_mode' => $cetakan]);
                $log->increment('print_count');
                $inv->print_count = $log->print_count;
                $inv->save();
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-proforma.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'defaultManager' => Setting::get('default_bc_manager', ''),
            'defaultSpv' => Setting::get('default_bc_spv', ''),
        ])->setPaper('a4', 'portrait');

        $safeName = str_replace(['/', '\\'], '', $invoice->proforma_number ?? $invoice->name);
        $safePartner = preg_replace('/[^A-Za-z0-9_\- ]/', '', $invoice->partner_name);
        $safePartner = str_replace(' ', '_', trim($safePartner));
        $filename = 'PROFORMA_' . $safePartner . '_' . $safeName;
        
        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print selected invoice proforma entries to PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_proformas,id'
        ]);

        $printMode = $request->query('print_mode', 'detail');
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoices = InvoiceProforma::with('lines')
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
                $this->ensureProformaNumber($inv);
                $log = PrintLog::firstOrCreate(['invoice_name' => 'PROFORMA_' . $inv->odoo_id, 'print_mode' => $cetakan]);
                $log->increment('print_count');
                $inv->print_count = $log->print_count;
                $inv->save();
            }
        } catch (\Exception $e) {}

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-proforma.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode
        ])->setPaper('a4', 'portrait');

        if (count($invoices) === 1) {
            $safeName = str_replace(['/', '\\'], '', $invoices[0]->proforma_number ?? $invoices[0]->name);
            $safePartner = preg_replace('/[^A-Za-z0-9_\- ]/', '', $invoices[0]->partner_name);
            $safePartner = str_replace(' ', '_', trim($safePartner));
            $filename = 'PROFORMA_' . $safePartner . '_' . $safeName;
        } else {
            $filename = 'PROFORMA_export_' . now()->format('YmdHis');
        }

        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print a single invoice proforma entry to HTML
     */
    public function printHtml(Request $request, InvoiceProforma $invoice)
    {
        $printMode = $request->query('print_mode', 'detail');
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
                $this->ensureProformaNumber($inv);
                $log = PrintLog::firstOrCreate(['invoice_name' => 'PROFORMA_' . $inv->odoo_id, 'print_mode' => $cetakan]);
                $inv->print_count = $log->print_count;
                // HTML preview does not increment the print count
            }
        } catch (\Exception $e) {}

        return view('invoice-proforma.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
            'defaultManager' => Setting::get('default_bc_manager', ''),
            'defaultSpv' => Setting::get('default_bc_spv', ''),
            'isHtml' => true,
        ]);
    }

    /**
     * Print selected invoice proforma entries to HTML
     */
    public function printSelectedHtml(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_proformas,id'
        ]);

        $printMode = $request->query('print_mode', 'detail');
        $showUsername = $request->query('show_username', '0') === '1';
        
        $invoices = InvoiceProforma::with('lines')
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
                $this->ensureProformaNumber($inv);
                $log = PrintLog::firstOrCreate(['invoice_name' => 'PROFORMA_' . $inv->odoo_id, 'print_mode' => $cetakan]);
                $inv->print_count = $log->print_count;
                // HTML preview does not increment the print count
            }
        } catch (\Exception $e) {}

        return view('invoice-proforma.pdf', [
            'invoices' => $invoices,
            'showUsername' => $showUsername,
            'printMode' => $printMode,
            'isHtml' => true,
        ]);
    }
}
