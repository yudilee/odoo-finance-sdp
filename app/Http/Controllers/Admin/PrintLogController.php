<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintLog;
use Illuminate\Http\Request;

class PrintLogController extends Controller
{
    /**
     * Display a listing of printed invoices.
     */
    public function index(Request $request)
    {
        // Safety check: If the table is missing (can happen on initial production deploy if migration record is faked)
        if (!\Illuminate\Support\Facades\Schema::hasTable('print_logs')) {
            $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
            $sort = 'updated_at';
            $dir = 'desc';
            $perPage = 25;
            session()->now('warning', 'The print tracking database table is missing. Please contact support or run migrations.');
            return view('admin.print_logs.index', compact('logs', 'sort', 'dir', 'perPage'));
        }

        $query = PrintLog::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('invoice_name', 'like', "%{$search}%");
        }

        // Only show invoices that have been printed
        $query->where('print_count', '>', 0);

        // Sorting
        $sort = $request->input('sort', 'updated_at');
        $dir = $request->input('dir', 'desc');

        $allowedSorts = ['invoice_name', 'print_count', 'updated_at'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'updated_at';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $query->orderBy($sort, $dir);

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $logs = $query->paginate($perPage)->withQueryString();

        return view('admin.print_logs.index', compact('logs', 'sort', 'dir', 'perPage'));
    }

    /**
     * Reset the print count for a single invoice.
     */
    public function reset(PrintLog $printLog)
    {
        $printLog->update(['print_count' => 0]);

        return redirect()->back()->with('success', "Print counter for {$printLog->invoice_name} reset to 0.");
    }

    /**
     * Bulk reset selected invoices.
     */
    public function resetBulk(Request $request)
    {
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'integer|exists:print_logs,id'
        ]);

        PrintLog::whereIn('id', $request->selected_ids)
            ->update(['print_count' => 0]);

        return redirect()->back()->with('success', count($request->selected_ids) . " print counters reset successfully.");
    }
}
