<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Setting;
use App\Models\PrintLog;
use App\Models\InvoiceDriver;
use App\Models\InvoiceOther;
use App\Models\InvoiceRental;
use App\Models\InvoiceVehicle;
use App\Services\PrintHubService;

class InvoicePrintController extends Controller
{
    /**
     * Supported doc types → [model class, view, table, paper_size, paper_orientation]
     */
    protected static function docTypeConfig(): array
    {
        return [
            'invoice_driver' => [
                'model'       => InvoiceDriver::class,
                'view'        => 'invoice-driver.pdf',
                'table'       => 'invoice_drivers',
                'paper'       => 'a4',
                'orientation' => 'portrait',
            ],
            'invoice_other' => [
                'model'       => InvoiceOther::class,
                'view'        => 'invoice-other.pdf',
                'table'       => 'invoice_others',
                'paper'       => 'a4',
                'orientation' => 'portrait',
            ],
            'invoice_rental' => [
                'model'       => InvoiceRental::class,
                'view'        => 'invoice-rental.pdf',
                'table'       => 'invoice_rentals',
                'paper'       => 'a4',
                'orientation' => 'portrait',
            ],
            'invoice_vehicle' => [
                'model'       => InvoiceVehicle::class,
                'view'        => 'invoice-vehicle.pdf',
                'table'       => 'invoice_vehicles',
                'paper'       => 'a4',
                'orientation' => 'portrait',
            ],
        ];
    }

    /**
     * Send a single invoice to Print Hub.
     *
     * POST /invoice/print-hub
     * Body: doc_type, invoice_name, print_mode (optional), show_username (optional)
     */
    public function printSingleToHub(Request $request)
    {
        $request->validate([
            'doc_type'     => 'required|string|in:invoice_driver,invoice_other,invoice_rental,invoice_vehicle',
            'invoice_name' => 'required|string',
            'print_mode'   => 'nullable|string|in:detail,summary',
            'show_username'=> 'nullable|boolean',
        ]);

        $config    = static::docTypeConfig()[$request->doc_type];
        $modelClass = $config['model'];
        $invoice   = $modelClass::where('name', $request->invoice_name)->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $invoice->load('lines');
        $invoices    = collect([$invoice]);
        $printMode   = $request->input('print_mode', 'detail');
        $showUsername = $request->boolean('show_username', false);

        // Track print count
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log: ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $viewData = $this->buildViewData($request->doc_type, $invoices, $printMode, $showUsername);
        $pdf = Pdf::loadView($config['view'], $viewData)
            ->setPaper($config['paper'], $config['orientation']);

        $pdfBase64 = base64_encode($pdf->output());

        $dest = auth()->user()->getPrintDestination($request->doc_type);
        $queue = $dest['queue'] ?: 'invoice';

        $service = new PrintHubService();
        $result  = $service->printQueue(
            queue:     $queue,
            pdfBase64: $pdfBase64,
            agentId:   $dest['agent_id'],
            printer:   $dest['printer'],
            extra:     ['reference_id' => $request->invoice_name]
        );

        return response()->json($result);
    }

    /**
     * Send multiple selected invoices to Print Hub.
     *
     * POST /invoice/print-hub-bulk
     * Body: doc_type, selected_ids[], print_mode (optional), show_username (optional)
     */
    public function printBulkToHub(Request $request)
    {
        $request->validate([
            'doc_type'      => 'required|string|in:invoice_driver,invoice_other,invoice_rental,invoice_vehicle',
            'selected_ids'  => 'required|array|min:1',
            'selected_ids.*'=> 'integer',
            'print_mode'    => 'nullable|string|in:detail,summary',
            'show_username' => 'nullable|boolean',
        ]);

        $config    = static::docTypeConfig()[$request->doc_type];
        $modelClass = $config['model'];

        $invoices = $modelClass::with('lines')
            ->whereIn('id', $request->selected_ids)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('name', 'desc')
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No invoices found.'], 404);
        }

        $printMode    = $request->input('print_mode', 'detail');
        $showUsername = $request->boolean('show_username', false);

        // Track print count
        try {
            foreach ($invoices as $inv) {
                $log = PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->print_count = $log->print_count;
                $log->increment('print_count');
            }
        } catch (\Exception $e) {
            Log::warning('Could not update print log (bulk): ' . $e->getMessage());
            foreach ($invoices as $inv) {
                if (!isset($inv->print_count)) $inv->print_count = 0;
            }
        }

        $viewData = $this->buildViewData($request->doc_type, $invoices, $printMode, $showUsername);
        $pdf = Pdf::loadView($config['view'], $viewData)
            ->setPaper($config['paper'], $config['orientation']);

        $pdfBase64 = base64_encode($pdf->output());

        $dest = auth()->user()->getPrintDestination($request->doc_type);
        $queue = $dest['queue'] ?: 'invoice';

        $service = new PrintHubService();
        $result  = $service->printQueue(
            queue:     $queue,
            pdfBase64: $pdfBase64,
            agentId:   $dest['agent_id'],
            printer:   $dest['printer'],
            extra:     ['reference_id' => 'bulk_' . count($invoices)]
        );

        return response()->json($result);
    }

    /**
     * Build view data array for a given doc type. Adds extra params for Invoice Rental if needed.
     */
    protected function buildViewData(string $docType, $invoices, string $printMode, bool $showUsername): array
    {
        $base = [
            'invoices'        => $invoices,
            'enableWatermark' => Setting::get('enable_pdf_watermark', '1'),
        ];

        if ($docType === 'invoice_rental') {
            $base['printMode']   = $printMode;
            $base['showUsername'] = $showUsername;
            $base['defaultManager'] = Setting::get('default_bc_manager', '');
            $base['defaultSpv']     = Setting::get('default_bc_spv', '');
        }

        return $base;
    }
}
