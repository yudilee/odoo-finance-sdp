<?php

namespace App\Http\Controllers;

use App\Models\UninvoicedRental;
use App\Models\Setting;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class UninvoicedRentalController extends Controller
{
    /**
     * Display the uninvoiced rentals list
     */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'tanggal_periode_belum_cetak');
        $dir = $request->input('dir', 'desc');

        $query = UninvoicedRental::query()->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'Cancelled');
        });

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_so', 'like', "%{$search}%")
                  ->orWhere('nama_user', 'like', "%{$search}%")
                  ->orWhere('kode_cust', 'like', "%{$search}%")
                  ->orWhere('nopol', 'like', "%{$search}%")
                  ->orWhere('chassis', 'like', "%{$search}%")
                  ->orWhere('kontrak_ref', 'like', "%{$search}%");
            });
        }

        // Filter by Invoice Period
        if ($request->filled('invoice_period')) {
            $query->where('invoice_period', $request->invoice_period);
        }

        // Sorting
        $allowedSorts = ['kode_cust', 'nomor_so', 'status', 'nama_user', 'nopol', 'tanggal_periode_belum_cetak', 'kontrak_ref'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'tanggal_periode_belum_cetak';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }

        $query->orderBy($sort, $dir);

        $perPage = $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 25;

        $rentals = $query->paginate($perPage)->withQueryString();
        
        $autoSyncEnabled = Setting::getValue('uninvoiced_rentals_auto_sync_enabled', 'false') === 'true';

        return view('uninvoiced-rentals.index', compact('rentals', 'sort', 'dir', 'perPage', 'autoSyncEnabled'));
    }

    /**
     * Toggle Auto Sync Setting
     */
    public function toggleAutoSync(Request $request)
    {
        $enabled = $request->boolean('enabled');
        Setting::setValue('uninvoiced_rentals_auto_sync_enabled', $enabled ? 'true' : 'false');
        
        return response()->json([
            'success' => true,
            'enabled' => $enabled
        ]);
    }

    /**
     * Initialize sync by fetching all SO IDs
     */
    public function syncInit(Request $request)
    {
        try {
            $odoo = new OdooService();
            $soIds = $odoo->getUninvoicedSoIds();

            if (empty($soIds)) {
                return response()->json([
                    'success' => true,
                    'so_ids' => [],
                    'message' => 'No uninvoiced rental periods found.'
                ]);
            }

            return response()->json([
                'success' => true,
                'so_ids' => $soIds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Initialization failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Process a chunk of SO IDs
     */
    public function syncChunk(Request $request)
    {
        try {
            $soIds = $request->input('so_ids', []);
            $isFirstChunk = $request->input('is_first_chunk', false);

            if (empty($soIds)) {
                return response()->json(['success' => true, 'count' => 0]);
            }

            $odoo = new OdooService();
            $result = $odoo->fetchUninvoicedRentalsBySoIds($soIds);

            if (empty($result)) {
                return response()->json(['success' => true, 'count' => 0]);
            }

            $syncService = new SyncService();
            // Wipe table only on the first chunk
            $savedCount = $syncService->saveUninvoicedRentals($result, $isFirstChunk);

            return response()->json([
                'success' => true,
                'count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk sync failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        $query = UninvoicedRental::query()->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'Cancelled');
        });

        // Apply filters to export
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_so', 'like', "%{$search}%")
                  ->orWhere('nama_user', 'like', "%{$search}%")
                  ->orWhere('kode_cust', 'like', "%{$search}%")
                  ->orWhere('nopol', 'like', "%{$search}%")
                  ->orWhere('chassis', 'like', "%{$search}%")
                  ->orWhere('kontrak_ref', 'like', "%{$search}%");
            });
        }

        if ($request->filled('invoice_period')) {
            $query->where('invoice_period', $request->invoice_period);
        }

        $rentals = $query->orderBy('tanggal_periode_belum_cetak', 'desc')->get();
        $format = $request->input('format', 'csv');

        $columns = [
            'Kode Cust', 'Nomor SO', 'Status', 'Nomor PO', 'Nomor Kontrak', 'Kontrak Ref', 'Nama user', 'Nopol',
            'Model', 'Tahun Mobil', 'Actual Start Rent', 'Actual End Rental', 'Tanggal periode belum cetak',
            'Start Rental Period', 'End Rental Period', 'Price di SO', 'Duration Price', 'Invoice Period', 'Payment Terms', 'Area pemakaian uunit',
            'Chassis', 'Invoice PIC', 'First Invoice date', 'Rental Method',
            'Recipient Bank', 'Tax ID', 'ID TKU', 'Kode Transaksi', 'Address', 'Tax Address',
        ];

        if (in_array($format, ['xls', 'xlsx'])) {
            $ext = $format;
            $filename = "REPORT_UNINVOICED_" . now()->format('YmdHis') . '.' . $ext;
            $html = '<table border="1"><thead><tr>';
            foreach ($columns as $col) {
                $html .= '<th style="background-color: #f2f2f2;">' . htmlspecialchars($col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            
            foreach ($rentals as $rental) {
                $html .= '<tr>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->kode_cust) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->nomor_so) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->status) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->nomor_po) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->nomor_kontrak) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->kontrak_ref) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->nama_user) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->nopol) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->model) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->tahun_mobil) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->start) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->end) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->tanggal_periode_belum_cetak) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($rental->start_rental_period ? date('d/m/Y', strtotime($rental->start_rental_period)) : '')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($rental->end_rental_period ? date('d/m/Y', strtotime($rental->end_rental_period)) : '')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->price_di_so) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->duration_price) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->invoice_period) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->payment_terms) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->area_pemakaian_unit) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->chassis) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->invoice_pic) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->first_invoice_date) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->rental_method) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->recipient_bank) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->tax_id) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->id_tku) . '</td>';
                $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars((string)$rental->kode_transaksi) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->address) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)$rental->tax_address) . '</td>';
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

        // Default CSV
        $filename = "REPORT_UNINVOICED_" . now()->format('YmdHis') . '.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function() use($rentals, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($rentals as $rental) {
                fputcsv($file, [
                    $rental->kode_cust,
                    $rental->nomor_so,
                    $rental->status,
                    $rental->nomor_po,
                    $rental->nomor_kontrak,
                    $rental->kontrak_ref,
                    $rental->nama_user,
                    $rental->nopol,
                    $rental->model,
                    $rental->tahun_mobil,
                    $rental->start,
                    $rental->end,
                    $rental->tanggal_periode_belum_cetak,
                    $rental->start_rental_period ? date('d/m/Y', strtotime($rental->start_rental_period)) : '',
                    $rental->end_rental_period ? date('d/m/Y', strtotime($rental->end_rental_period)) : '',
                    $rental->price_di_so,
                    $rental->duration_price,
                    $rental->invoice_period,
                    $rental->payment_terms,
                    $rental->area_pemakaian_unit,
                    $rental->chassis,
                    $rental->invoice_pic,
                    $rental->first_invoice_date,
                    $rental->rental_method,
                    $rental->recipient_bank,
                    $rental->tax_id,
                    $rental->id_tku,
                    $rental->kode_transaksi,
                    $rental->address,
                    $rental->tax_address,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
