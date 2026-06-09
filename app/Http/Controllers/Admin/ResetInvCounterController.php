<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrintLog;

class ResetInvCounterController extends Controller
{
    public function index()
    {
        return view('admin.reset_inv_counter.index');
    }

    public function reset(Request $request)
    {
        $request->validate([
            'invoice_name' => 'required|string',
            'print_mode' => 'required|string|in:detail_nopol,summary,without_nopol,detail_username',
        ]);

        $log = PrintLog::where('invoice_name', $request->invoice_name)
                       ->where('print_mode', $request->print_mode)
                       ->first();

        if ($log) {
            $log->print_count = 0;
            $log->save();
            return redirect()->back()->with('success', "Print increment for {$request->invoice_name} has been successfully reset!");
        }

        // If the log doesn't exist, it means it hasn't been printed yet.
        // We could create it with print_count 0, but usually it's better to just tell the user.
        return redirect()->back()->with('error', "No print record found for {$request->invoice_name} with the selected print type. It might not have been printed yet.");
    }
}
