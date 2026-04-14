<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSubscription;
use App\Models\ImportLog;
use App\Services\OdooService;
use App\Services\SyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceSubscriptionController extends Controller
{
    /**
     * The fixed start date for subscription invoice period lookups.
     */
    const DATE_FROM = '2025-04-01';

    /**
     * Display the subscription invoice period listing page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $defaultColumns = [
            ['id' => 'partner_name', 'label' => 'Customer', 'visible' => true, 'width' => '200', 'sortable' => true],
            ['id' => 'so_name', 'label' => 'SO Number', 'visible' => true, 'width' => '120', 'sortable' => true],
            ['id' => 'rental_status', 'label' => 'Rental Status', 'visible' => true, 'width' => '120', 'sortable' => true],
            ['id' => 'rental_type', 'label' => 'Rental Type', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'period_type', 'label' => 'Period Type', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'product_name', 'label' => 'Vehicle/Product', 'visible' => true, 'width' => '200', 'sortable' => true],
            ['id' => 'period_start', 'label' => 'Rental Period', 'visible' => true, 'width' => '150', 'sortable' => true],
            ['id' => 'period_end', 'label' => 'Period End', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'actual_start_rental', 'label' => 'Actual Start', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'actual_end_rental', 'label' => 'Actual End', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'invoice_date', 'label' => 'Exp. Invoice Date', 'visible' => true, 'width' => '150', 'sortable' => true],
            ['id' => 'invoice_name', 'label' => 'Invoice #', 'visible' => true, 'width' => '150', 'sortable' => true],
            ['id' => 'invoice_ref', 'label' => 'Invoice Ref', 'visible' => false, 'width' => '150', 'sortable' => true],
            ['id' => 'invoice_state', 'label' => 'Invoice State', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'payment_state', 'label' => 'Payment State', 'visible' => false, 'width' => '120', 'sortable' => true],
            ['id' => 'invoice_amount', 'label' => 'Invoice Price', 'visible' => true, 'width' => '120', 'sortable' => true],
            ['id' => 'price_unit', 'label' => 'Price Unit', 'visible' => false, 'width' => '100', 'sortable' => true],
            ['id' => 'rental_uom', 'label' => 'Unit of Measure', 'visible' => false, 'width' => '100', 'sortable' => true],
            ['id' => 'status', 'label' => 'Status', 'visible' => true, 'width' => '150', 'sortable' => false],
            ['id' => 'synced_at', 'label' => 'Synced At', 'visible' => false, 'width' => '150', 'sortable' => true],
        ];

        $savedPrefs = $user->getPreference('table_invoice_subscription', ['columns' => []]);
        $savedCols  = $savedPrefs['columns'] ?? [];
        
        // Merge: 
        // 1. Follow the ORDER of saved columns if they exist
        // 2. Add any NEW default columns that aren't in the saved preferences
        
        $mergedColumns = [];
        $defaultMap = collect($defaultColumns)->keyBy('id')->toArray();
        $savedIds   = [];

        foreach ($savedCols as $saved) {
            if (isset($defaultMap[$saved['id']])) {
                $def = $defaultMap[$saved['id']];
                $mergedColumns[] = array_merge($def, [
                    'visible' => $saved['visible'] ?? $def['visible'],
                    'width'   => $saved['width'] ?? $def['width'],
                ]);
                $savedIds[] = $saved['id'];
            }
        }
        
        // Add new columns from defaults that weren't in the saved set
        foreach ($defaultColumns as $def) {
            if (!in_array($def['id'], $savedIds)) {
                $mergedColumns[] = $def;
            }
        }

        $tablePrefs = ['columns' => $mergedColumns];

        $sort = $request->input('sort', 'invoice_date');
        $dir  = $request->input('dir', 'asc');

        $query = InvoiceSubscription::query()->where('invoice_amount', '>', 0);

        // ── Search ──
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // ── Status filter ──
        $statusFilter = $request->input('status', 'all');
        if ($statusFilter !== 'all') {
            // Use raw where conditions (mirrors the scope logic)
            match ($statusFilter) {
                'not_invoiced' => $query->where(function ($q) {
                    $q->whereNull('invoice_name')->orWhere('invoice_name', '');
                }),
                'draft'   => $query->whereRaw("LOWER(invoice_state) = 'draft'"),
                'paid'    => $query->whereRaw("LOWER(payment_state) = 'paid'"),
                'unpaid'  => $query->whereRaw("LOWER(invoice_state) = 'posted'")
                                   ->whereRaw("LOWER(COALESCE(payment_state,'')) != 'paid'"),
                default   => null,
            };
        }

        // ── Date range filter ──
        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        // ── Rental status filter ──
        if ($request->filled('rental_status') && $request->rental_status !== 'all') {
            $query->where('rental_status', $request->rental_status);
        }

        // ── Sorting ──
        $allowedSorts = ['invoice_date', 'so_name', 'partner_name', 'rental_status', 'invoice_name', 'period_start', 'product_name'];
        if (!in_array($sort, $allowedSorts)) $sort = 'invoice_date';
        if (!in_array($dir, ['asc', 'desc']))  $dir  = 'asc';

        $query->orderBy($sort, $dir);
        if ($sort !== 'invoice_date') {
            $query->orderBy('invoice_date', 'asc');
        }

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $records = $query->paginate($perPage)->withQueryString();

        // ── Stats (over the whole window, no status/search filters) ──
        $statsBase = InvoiceSubscription::query()->where('invoice_amount', '>', 0);
        if ($request->filled('search')) $statsBase->search($request->search);
        if ($request->filled('date_from')) $statsBase->where('invoice_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $statsBase->where('invoice_date', '<=', $request->date_to);

        $stats = [
            'total'        => (clone $statsBase)->count(),
            'not_invoiced' => (clone $statsBase)->where(function ($q) {
                $q->whereNull('invoice_name')->orWhere('invoice_name', '');
            })->count(),
            'overdue'      => (clone $statsBase)->where(function ($q) {
                $q->whereNull('invoice_name')->orWhere('invoice_name', '');
            })->where('invoice_date', '<', now()->toDateString())->count(),
            'draft'        => (clone $statsBase)->whereRaw("LOWER(invoice_state) = 'draft'")->count(),
            'paid'         => (clone $statsBase)->whereRaw("LOWER(payment_state) = 'paid'")->count(),
            'unpaid'       => (clone $statsBase)
                ->whereRaw("LOWER(invoice_state) = 'posted'")
                ->whereRaw("LOWER(COALESCE(payment_state,'')) != 'paid'")
                ->count(),
        ];

        // Last sync info
        $lastSync = ImportLog::where('source', 'odoo_subscription_periods')
            ->latest()
            ->first();

        $dateWindow = [
            'from' => self::DATE_FROM,
            'to'   => Carbon::today()->addDays(15)->format('Y-m-d'),
        ];

        return view('invoice-subscription.index', compact(
            'records', 'stats', 'sort', 'dir', 'perPage',
            'statusFilter', 'lastSync', 'dateWindow', 'tablePrefs'
        ));
    }

    /**
     * Update user preferences for the subscription table.
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'columns' => 'required|array',
        ]);

        $user = auth()->user();
        $user->setPreference('table_invoice_subscription', [
            'columns' => $request->columns,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Reset user preferences for the subscription table.
     */
    public function resetPreferences()
    {
        $user = auth()->user();
        $prefs = $user->preferences ?? [];
        unset($prefs['table_invoice_subscription']);
        $user->preferences = $prefs;
        $user->save();

        return response()->json(['success' => true]);
    }

    /**
     * Sync subscription invoice periods from Odoo.
     * Uses a fixed date window: 2025-04-01 → today + 15 days.
     */
    public function sync(Request $request, SyncService $sync)
    {
        try {
            $dateFrom = $request->input('from') ?: Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d');
            $dateTo   = $request->input('to') ?: Carbon::today()->addDays(15)->format('Y-m-d');

            // Basic validation
            try {
                Carbon::parse($dateFrom);
                Carbon::parse($dateTo);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Invalid date format provided.']);
            }

            $odoo   = new OdooService();
            $result = $odoo->fetchSubscriptionInvoicePeriods($dateFrom, $dateTo);

            if (!$result['success']) {
                ImportLog::create([
                    'source'        => 'odoo_subscription_periods',
                    'imported_at'   => now(),
                    'items_count'   => 0,
                    'status'        => 'failed',
                    'error_message' => $result['message'] ?? 'Unknown error',
                    'summary_json'  => ['date_from' => $dateFrom, 'date_to' => $dateTo],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Odoo fetch failed: ' . ($result['message'] ?? 'Unknown error'),
                ]);
            }

            if (empty($result['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => "No periods found for {$dateFrom} to {$dateTo}.",
                    'count'   => 0,
                ]);
            }

            $truncate = $request->boolean('truncate', false);
            $savedCount = $sync->saveInvoiceSubscriptions($result['data'], $truncate);

            ImportLog::create([
                'source'       => 'odoo_subscription_periods',
                'imported_at'  => now(),
                'items_count'  => $savedCount,
                'status'       => 'success',
                'summary_json' => [
                    'date_from'   => $dateFrom,
                    'date_to'     => $dateTo,
                    'total_count' => $savedCount,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced {$savedCount} periods for range [{$dateFrom} to {$dateTo}].",
                'count'   => $savedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceSubscription sync error: ' . $e->getMessage());

            ImportLog::create([
                'source'        => 'odoo_subscription_periods',
                'imported_at'   => now(),
                'items_count'   => 0,
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Export subscription data to CSV or Excel.
     */
    public function export(Request $request)
    {
        $ids = $request->input('selected_ids', []);
        $format = $request->input('format', 'csv');
        $visibleColumnsInput = $request->input('columns', '[]');
        $visibleColumns = is_string($visibleColumnsInput) 
            ? json_decode($visibleColumnsInput, true) 
            : $visibleColumnsInput;

        $query = InvoiceSubscription::query()->where('invoice_amount', '>', 0);
        
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            // Replicate filtering logic from index()
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('so_name', 'like', "%{$search}%")
                      ->orWhere('partner_name', 'like', "%{$search}%")
                      ->orWhere('invoice_name', 'like', "%{$search}%")
                      ->orWhere('product_name', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $status = $request->status;
                match ($status) {
                    'not_invoiced' => $query->where(function ($q) {
                                            $q->whereNull('invoice_name')->orWhere('invoice_name', '');
                                        }),
                    'draft'        => $query->whereRaw("LOWER(invoice_state) = 'draft'"),
                    'paid'         => $query->whereRaw("LOWER(payment_state) = 'paid'"),
                    'unpaid'       => $query->whereRaw("LOWER(invoice_state) = 'posted'")
                                           ->whereRaw("LOWER(COALESCE(payment_state,'')) != 'paid'"),
                    default        => null,
                };
            }

            if ($request->filled('date_from')) $query->where('invoice_date', '>=', $request->date_from);
            if ($request->filled('date_to'))   $query->where('invoice_date', '<=', $request->date_to);
            if ($request->filled('rental_status') && $request->rental_status !== 'all') {
                $query->where('rental_status', $request->rental_status);
            }
        }

        // Apply sorting
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');
        $allowedSorts = ['invoice_date', 'so_name', 'partner_name', 'rental_status', 'invoice_name', 'period_start', 'product_name'];
        if (!in_array($sort, $allowedSorts)) $sort = 'invoice_date';
        if (!in_array($dir, ['asc', 'desc']))  $dir  = 'desc';

        $query->orderBy($sort, $dir);
        if ($sort !== 'invoice_date') {
            $query->orderBy('invoice_date', 'asc');
        }

        $records = $query->get();

        if ($format === 'excel') {
            return $this->exportExcel($records, $visibleColumns);
        }

        return $this->exportCsv($records, $visibleColumns);
    }

    /**
     * Export to Excel (as HTML table).
     */
    private function exportExcel($records, $columns)
    {
        $filename = 'export_subscription_' . now()->format('YmdHis') . '.xls';
        
        $html = '<table border="1">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th style="background-color: #f2f2f2;">' . e($col['label']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($records as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $val = $this->getColumnValue($row, $col['id']);
                $html .= '<td>' . e($val) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        return response($html, 200, [
            "Content-type"        => "application/vnd.ms-excel",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    /**
     * Export to CSV.
     */
    private function exportCsv($records, $columns)
    {
        $filename = 'export_subscription_' . now()->format('YmdHis') . '.csv';
        
        $callback = function() use ($records, $columns) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, array_column($columns, 'label'));
            
            // Data
            foreach ($records as $row) {
                $data = [];
                foreach ($columns as $col) {
                    $data[] = $this->getColumnValue($row, $col['id']);
                }
                fputcsv($file, $data);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    /**
     * Get display value for a column.
     */
    private function getColumnValue($row, $colId)
    {
        return match($colId) {
            'status'       => $this->getStatusLabel($row),
            'period_start' => $row->period_start ? \Carbon\Carbon::parse($row->period_start)->format('d M Y') . ' - ' . ($row->period_end ? \Carbon\Carbon::parse($row->period_end)->format('d M Y') : '') : '',
            'invoice_date' => $row->invoice_date ? \Carbon\Carbon::parse($row->invoice_date)->format('d M Y') : '',
            'synced_at'    => $row->synced_at ? \Carbon\Carbon::parse($row->synced_at)->format('d M Y H:i') : '',
            'price_unit'   => number_format($row->price_unit, 2),
            'invoice_amount' => number_format($row->invoice_amount, 2),
            'actual_start_rental' => $row->actual_start_rental ? \Carbon\Carbon::parse($row->actual_start_rental)->format('d M Y') : '',
            'actual_end_rental'   => $row->actual_end_rental ? \Carbon\Carbon::parse($row->actual_end_rental)->format('d M Y') : '',
            default        => $row->{$colId} ?? ''
        };
    }

    /**
     * Determine the same status label used in the UI.
     */
    private function getStatusLabel($row) 
    {
        if (!$row->invoice_name) {
            $isOverdue = $row->invoice_date < now()->toDateString();
            return $isOverdue ? 'Not Invoiced (Overdue)' : 'Not Invoiced';
        }
        
        $state = strtolower($row->invoice_state ?? '');
        $pay   = strtolower($row->payment_state ?? '');

        if ($state === 'draft') return 'Draft';
        if ($pay === 'paid')    return 'Paid';
        if ($state === 'posted') return 'Posted/Unpaid';
        
        return $row->invoice_state ?? 'Unknown';
    }
}
