<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSubscription;
use App\Models\ImportLog;
use App\Services\OdooService;
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
            ['id' => 'so_name', 'label' => 'SO Number', 'visible' => true, 'width' => '120', 'sortable' => true],
            ['id' => 'partner_name', 'label' => 'Customer', 'visible' => true, 'width' => '200', 'sortable' => true],
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

        $query = InvoiceSubscription::query();

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
        $statsBase = InvoiceSubscription::query();
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
    public function sync(Request $request)
    {
        try {
            $dateFrom = self::DATE_FROM;
            $dateTo   = Carbon::today()->addDays(15)->format('Y-m-d');

            $odoo   = new OdooService();
            $result = $odoo->fetchSubscriptionInvoicePeriods($dateFrom, $dateTo);

            if (!$result['success']) {
                ImportLog::create([
                    'source'        => 'odoo_subscription_periods',
                    'imported_at'   => now(),
                    'items_count'   => 0,
                    'status'        => 'failed',
                    'error_message' => $result['message'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Odoo fetch failed: ' . ($result['message'] ?? 'Unknown error'),
                ]);
            }

            if (empty($result['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No subscription invoice periods found.',
                    'count'   => 0,
                ]);
            }

            $savedCount = $this->saveRecords($result['data']);

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
                'message' => "Synced {$savedCount} subscription invoice periods from Odoo.",
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
     * Upsert records into the local database.
     */
    protected function saveRecords(array $entries): int
    {
        $count   = 0;
        $syncedAt = now();

        foreach ($entries as $entry) {
            if (empty($entry['period_odoo_id'])) continue;

            InvoiceSubscription::updateOrCreate(
                ['period_odoo_id' => $entry['period_odoo_id']],
                [
                    'period_numeric_id'   => $entry['period_numeric_id'] ?? null,
                    'so_name'             => $entry['so_name'] ?? null,
                    'partner_name'        => $entry['partner_name'] ?? null,
                    'rental_status'       => $entry['rental_status'] ?? null,
                    'rental_type'         => $entry['rental_type'] ?? 'Subscription',
                    'actual_start_rental' => $entry['actual_start_rental'] ?: null,
                    'actual_end_rental'   => $entry['actual_end_rental'] ?: null,
                    'period_type'         => $entry['period_type'] ?? null,
                    'product_name'        => $entry['product_name'] ?? null,
                    'invoice_date'        => $entry['invoice_date'] ?: null,
                    'period_start'        => $entry['period_start'] ?: null,
                    'period_end'          => $entry['period_end'] ?: null,
                    'price_unit'          => $entry['price_unit'] ?? 0,
                    'rental_uom'          => $entry['rental_uom'] ?? null,
                    'invoice_name'        => $entry['invoice_name'] ?: null,
                    'invoice_ref'         => $entry['invoice_ref'] ?: null,
                    'invoice_state'       => $entry['invoice_state'] ?: null,
                    'payment_state'       => $entry['payment_state'] ?: null,
                    'synced_at'           => $syncedAt,
                ]
            );

            $count++;
        }

        return $count;
    }
}
