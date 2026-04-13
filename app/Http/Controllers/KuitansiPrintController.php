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
        $pdf = Pdf::loadView('partials.kuitansi', compact('invoices', 'showContract', 'useOverride', 'request'))
            ->setPaper([0, 0, 684.09, 396.00]); // 241.3mm x 139.7mm in points
            
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
