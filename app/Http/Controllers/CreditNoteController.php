<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditNoteController extends Controller
{
    /**
     * Display Credit Notes listing
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'invoice_date');
        $dir = $request->input('dir', 'desc');

        $query = CreditNote::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tax_number', 'like', "%{$search}%");
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
        $allowedSorts = ['name', 'invoice_date', 'partner_name', 'ref', 'payment_date', 'amount_untaxed', 'amount_tax', 'amount_total', 'payment_state', 'state'];
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
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        $creditNotes = $query->paginate($perPage)->withQueryString();

        // Stats summary
        $statsQuery = CreditNote::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                          ->orWhere('partner_name', 'like', "%{$search}%")
                          ->orWhere('ref', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('tax_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('invoice_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where('invoice_date', '<=', $request->date_to);
            });

        $stats = $statsQuery->selectRaw("
                count(*) as total_count,
                sum(amount_untaxed) as total_untaxed,
                sum(amount_tax) as total_tax,
                sum(amount_total) as total_amount
            ")
            ->first()
            ->toArray();

        return view('credit-notes.index', compact('creditNotes', 'stats', 'sort', 'dir', 'perPage'));
    }

    /**
     * Get Odoo IDs to sync
     */
    public function getSyncIds(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $odoo = new OdooService();
            $result = $odoo->getCreditNoteIds(
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
     * Sync a batch of IDs
     */
    public function syncBatch(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        try {
            $odoo = new OdooService();
            $result = $odoo->fetchCreditNotesByIds($request->input('ids'));

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

            $syncService = new SyncService();
            $savedCount = $syncService->saveCreditNotes($result['data']);

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
     * Export Credit Notes report
     */
    public function export(Request $request)
    {
        $query = CreditNote::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tax_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        $records = $query->orderBy('invoice_date', 'desc')->get();
        $format = $request->input('format', 'csv');
        $filename = "CREDIT_NOTES_REPORT_" . now()->format('YmdHis');

        $columns = [
            'Number',
            'Customer',
            'Reference',
            'Invoice Date',
            'Due Date',
            'Paid On',
            'Description',
            'Tax Number',
            'Tax Excluded',
            'Total',
            'Payment',
            'Status'
        ];

        if (in_array($format, ['xls', 'xlsx'])) {
            $html = '<table border="1"><thead><tr>';
            foreach ($columns as $col) {
                $html .= '<th style="background-color: #f2f2f2;">' . htmlspecialchars($col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($records as $row) {
                $html .= '<tr>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string) $row->name) . '</td>';
                $html .= '<td>' . htmlspecialchars((string) $row->partner_name) . '</td>';
                $html .= '<td>' . htmlspecialchars((string) $row->ref) . '</td>';
                $html .= '<td>' . htmlspecialchars($row->invoice_date ? $row->invoice_date->format('Y-m-d') : '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row->invoice_date_due ? $row->invoice_date_due->format('Y-m-d') : '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row->payment_date ? $row->payment_date->format('Y-m-d') : '') . '</td>';
                $html .= '<td style="white-space: pre-wrap; vertical-align: top;">' . nl2br(htmlspecialchars((string) $row->description)) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string) $row->tax_number) . '</td>';
                $html .= '<td style="text-align: right;">' . ($row->amount_untaxed > 0 ? '-' : '') . number_format($row->amount_untaxed, 2, '.', '') . '</td>';
                $html .= '<td style="text-align: right;">' . ($row->amount_total > 0 ? '-' : '') . number_format($row->amount_total, 2, '.', '') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) $row->payment_state) . '</td>';
                $html .= '<td>' . htmlspecialchars((string) $row->state) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';

            return response($html, 200, [
                "Content-type" => "application/vnd.ms-excel",
                "Content-Disposition" => "attachment; filename={$filename}.xls",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ]);
        }

        // CSV Export
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $callback = function () use ($records, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($records as $row) {
                fputcsv($file, [
                    $row->name,
                    $row->partner_name,
                    $row->ref,
                    $row->invoice_date ? $row->invoice_date->format('Y-m-d') : '',
                    $row->invoice_date_due ? $row->invoice_date_due->format('Y-m-d') : '',
                    $row->payment_date ? $row->payment_date->format('Y-m-d') : '',
                    $row->description,
                    $row->tax_number,
                    $row->amount_untaxed > 0 ? '-' . number_format($row->amount_untaxed, 2, '.', '') : '0.00',
                    $row->amount_total > 0 ? '-' . number_format($row->amount_total, 2, '.', '') : '0.00',
                    $row->payment_state,
                    $row->state
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
