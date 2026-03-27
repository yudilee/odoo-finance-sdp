<?php

namespace App\Http\Controllers;

use App\Models\InvoiceVehicle;
use App\Models\InvoiceVehicleLine;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceVehicleController extends Controller
{
    /**
     * Display the invoice vehicle listing page
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = InvoiceVehicle::with('lines');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('manager_name', 'like', "%{$search}%")
                  ->orWhere('spv_name', 'like', "%{$search}%")
                  ->orWhereHas('lines', function ($lq) use ($search) {
                      $lq->where('serial_number', 'like', "%{$search}%")
                         ->orWhere('license_plate', 'like', "%{$search}%")
                         ->orWhere('product_name', 'like', "%{$search}%");
                  });
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

        // Summary stats (apply same filters)
        $statsQuery = InvoiceVehicle::query();
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

        return view('invoice-vehicle.index', compact('invoices', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Sync invoice vehicle entries from Odoo
     */
    public function sync(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $odoo = new OdooService();

            $result = $odoo->fetchInvoiceVehicles(
                $request->input('date_from'),
                $request->input('date_to')
            );

            if (!$result['success']) {
                ImportLog::create([
                    'source' => 'odoo_invoice_vehicle',
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
                    'message' => 'No invoice vehicle entries found for the given date range.',
                    'count' => 0,
                ]);
            }

            // Save to database
            $savedCount = $this->saveInvoiceVehicles($result['data']);

            ImportLog::create([
                'source' => 'odoo_invoice_vehicle',
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
                'message' => "Synced {$savedCount} invoice vehicle entries from Odoo",
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            ImportLog::create([
                'source' => 'odoo_invoice_vehicle',
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
     * Save invoice vehicle entries to database (upsert by name)
     */
    protected function saveInvoiceVehicles(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            $invoice = InvoiceVehicle::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Penjualan Kendaraan',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                ]
            );

            // Delete existing lines and re-insert
            $invoice->lines()->delete();

            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'serial_number' => $line['serial_number'] ?? null,
                    'license_plate' => $line['license_plate'] ?? null,
                    'product_name' => $line['product_name'] ?? null,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
                ]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * Show a single invoice vehicle entry
     */
    public function show(InvoiceVehicle $invoice)
    {
        $invoice->load('lines');

        $prev = InvoiceVehicle::where(function($q) use ($invoice) {
                $q->where('invoice_date', '>', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '>', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $next = InvoiceVehicle::where(function($q) use ($invoice) {
                $q->where('invoice_date', '<', $invoice->invoice_date)
                  ->orWhere(function($q2) use ($invoice) {
                      $q2->where('invoice_date', '=', $invoice->invoice_date)
                         ->where('id', '<', $invoice->id);
                  });
            })
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return view('invoice-vehicle.show', compact('invoice', 'prev', 'next'));
    }

    /**
     * Print a single invoice vehicle entry to PDF
     */
    public function printPdf(InvoiceVehicle $invoice)
    {
        $invoice->load('lines');
        $invoices = collect([$invoice]);

        // Track print count
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log for vehicle invoice: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-vehicle.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper('a4', 'portrait');

        $filename = 'invoice_vehicle_' . str_replace('/', '_', $invoice->name);
        if ($invoice->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoice->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Print selected invoice vehicle entries to PDF
     */
    public function printSelectedPdf(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:invoice_vehicles,id'
        ]);

        $invoices = InvoiceVehicle::with('lines')
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
            Log::warning('Could not update print log for selected vehicle invoices: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoice-vehicle.pdf', [
            'invoices' => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ])->setPaper('a4', 'portrait');

        $filename = count($invoices) === 1 
            ? 'invoice_vehicle_' . str_replace('/', '_', $invoices[0]->name) 
            : 'invoice_vehicle_export_' . now()->format('YmdHis');

        if (count($invoices) === 1 && $invoices[0]->print_count > 0) {
            $filename .= '_DUPLICATE_' . $invoices[0]->print_count;
        }

        return $pdf->stream($filename . '.pdf');
    }
}
