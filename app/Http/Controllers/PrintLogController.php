<?php

namespace App\Http\Controllers;

use App\Models\PrintLog;
use Illuminate\Http\Request;

class PrintLogController extends Controller
{
    /**
     * Update the Kuitansi override text mapped to a specific invoice.
     */
    public function updateKuitansi(Request $request)
    {
        $request->validate([
            'invoice_name' => 'required|string',
            'pembayaran_1' => 'nullable|string|max:110',
            'pembayaran_2' => 'nullable|string|max:110',
            'pembayaran_3' => 'nullable|string|max:110',
            'pembayaran_4' => 'nullable|string|max:110',
            'show_contract' => 'nullable|boolean',
            'use_override' => 'nullable|boolean',
        ]);

        $lines = [];
        for ($i = 1; $i <= 4; $i++) {
            $val = trim($request->input("pembayaran_{$i}"));
            if (!empty($val)) {
                $lines[] = $val;
            }
        }

        $pembayaranText = empty($lines) ? null : implode("\n", $lines);

        $printLog = PrintLog::firstOrCreate(
            ['invoice_name' => $request->invoice_name, 'print_mode' => 'default']
        );

        $printLog->kuitansi_pembayaran = $pembayaranText;
        
        // Save preferences
        $prefs = $printLog->preferences ?? [];
        $prefs['show_contract'] = $request->boolean('show_contract');
        $prefs['use_override'] = $request->boolean('use_override');
        $printLog->preferences = $prefs;

        $printLog->save();

        return response()->json([
            'success' => true,
            'message' => 'Kuitansi override text saved successfully.'
        ]);
    }
}
