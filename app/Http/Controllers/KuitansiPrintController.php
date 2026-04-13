<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PrintHubService;

class KuitansiPrintController extends Controller
{
    public function printToHub(Request $request)
    {
        $request->validate([
            'invoice_name' => 'required|string',
            'show_contract' => 'nullable|boolean',
            'use_override' => 'nullable|boolean',
        ]);

        $invoiceName = $request->input('invoice_name');
        
        // Find the invoice across all invoice types
        $invoice = \App\Models\InvoiceRental::where('name', $invoiceName)->first()
                ?? \App\Models\InvoiceOther::where('name', $invoiceName)->first()
                ?? \App\Models\InvoiceDriver::where('name', $invoiceName)->first()
                ?? \App\Models\InvoiceVehicle::where('name', $invoiceName)->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $showContract = $request->boolean('show_contract');
        $useOverride = $request->boolean('use_override');

        // Generate PDF
        $invoices = collect([$invoice]);

        try {
            foreach ($invoices as $inv) {
                $log = \App\Models\PrintLog::firstOrCreate(['invoice_name' => $inv->name]);
                $inv->kuitansi_print_count = $log->kuitansi_print_count;
                // Only inject the override data if the user checked the checkbox
                $inv->kuitansi_pembayaran = $useOverride ? $log->kuitansi_pembayaran : null;
                $log->increment('kuitansi_print_count');
            }
        } catch (\Exception $e) {
            foreach ($invoices as $inv) {
                if (!isset($inv->kuitansi_print_count)) $inv->kuitansi_print_count = 0;
                $inv->kuitansi_pembayaran = null;
            }
        }

        $enableWatermark = Setting::get('enable_pdf_watermark', '1');

        $pdf = Pdf::loadView('partials.kuitansi', compact('invoices', 'showContract', 'useOverride', 'request', 'enableWatermark'))
            ->setPaper([0, 0, 396, 684], 'landscape'); // custom size landscape: 9.5in x 5.5in
            
        $pdfBase64 = base64_encode($pdf->output());

        // Get User Preferences
        $dest = auth()->user()->getPrintDestination('kuitansi');
        
        // Ensure a queue is set (fallback to 'kuitansi')
        $queue = $dest['queue'] ?: 'kuitansi';

        $service = new PrintHubService();
        $result = $service->printQueue(
            queue: $queue,
            pdfBase64: $pdfBase64,
            agentId: $dest['agent_id'],
            printer: $dest['printer'],
            extra: ['reference_id' => $invoiceName]
        );

        return response()->json($result);
    }
}
