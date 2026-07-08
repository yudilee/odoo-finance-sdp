<?php

namespace App\Services;

use App\Models\InvoiceDriver;
use App\Models\InvoiceOther;
use App\Models\InvoiceRental;
use App\Models\InvoiceVehicle;
use App\Models\InvoiceSubscription;
use App\Models\UninvoicedRental;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class SyncService
{
    /**
     * Save Invoice Driver entries
     */
    public function saveInvoiceDrivers(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $invoice = InvoiceDriver::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Driver',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                    'invoice_pic' => $entry['invoice_pic'] ?? null,
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'rental_qty' => $line['rental_qty'] ?? null,
                    'price_unit' => $line['price_unit'],
                    'duration_price' => $line['duration_price'] ?? 0,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Save Invoice Other entries
     */
    public function saveInvoiceOthers(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $invoice = InvoiceOther::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'contract_ref' => $entry['contract_ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Other',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                    'invoice_pic' => $entry['invoice_pic'] ?? null,
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
                    'duration_price' => $line['duration_price'] ?? 0,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Save Invoice Vehicle entries
     */
    public function saveInvoiceVehicles(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $invoice = InvoiceVehicle::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'contract_ref' => $entry['contract_ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Penjualan Kendaraan',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                    'invoice_pic' => $entry['invoice_pic'] ?? null,
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
                    'product_name' => $line['product_name'] ?? null,
                    'license_plate' => $line['license_plate'] ?? null,
                    'serial_number' => $line['serial_number'] ?? null,
                    'duration_price' => $line['duration_price'] ?? 0,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Save Invoice Rental entries
     */
    public function saveInvoiceRentals(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $invoice = InvoiceRental::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'],
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'contract_ref' => $entry['contract_ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Invoice Rental',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'manager_name' => $entry['manager_name'] ?? null,
                    'spv_name' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                    'invoice_pic' => $entry['invoice_pic'] ?? null,
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'sale_order_id' => $line['sale_order_id'] ?? null,
                    'description' => $line['description'],
                    'serial_number' => $line['serial_number'] ?? null,
                    'actual_start' => $line['actual_start'] ?? null,
                    'actual_end' => $line['actual_end'] ?? null,
                    'uom' => $line['uom'] ?? null,
                    'quantity' => $line['quantity'],
                    'rental_qty' => $line['rental_qty'] ?? null,
                    'price_unit' => $line['price_unit'],
                    'duration_price' => $line['duration_price'] ?? 0,
                    'customer_name' => $line['customer_name'] ?? null,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Save Invoice Proforma entries
     */
    public function saveInvoiceProformas(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            $invoice = \App\Models\InvoiceProforma::updateOrCreate(
                ['odoo_id' => $entry['odoo_id']],
                [
                    'name' => $entry['name'],
                    'partner_name' => $entry['partner_name'],
                    'invoice_date' => $entry['invoice_date'] ?: null,
                    'invoice_date_due' => $entry['invoice_date_due'] ?: null,
                    'payment_term' => $entry['payment_term'] ?? null,
                    'ref' => $entry['ref'] ?? null,
                    'contract_ref' => $entry['contract_ref'] ?? null,
                    'journal_name' => $entry['journal_name'] ?? 'Proforma',
                    'amount_untaxed' => $entry['amount_untaxed'],
                    'amount_tax' => $entry['amount_tax'],
                    'amount_total' => $entry['amount_total'],
                    'partner_bank' => $entry['partner_bank'] ?? null,
                    'bc_manager' => $entry['manager_name'] ?? null,
                    'bc_spv' => $entry['spv_name'] ?? null,
                    'partner_address' => $entry['partner_address'] ?? null,
                    'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                    'narration' => $entry['narration'] ?? null,
                    'partner_npwp' => $entry['partner_npwp'] ?? null,
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'sale_order_id' => $line['sale_order_id'] ?? null,
                    'description' => $line['description'],
                    'serial_number' => $line['serial_number'] ?? null,
                    'actual_start' => $line['actual_start'] ?? null,
                    'actual_end' => $line['actual_end'] ?? null,
                    'uom' => $line['uom'] ?? null,
                    'quantity' => $line['quantity'],
                    'rental_qty' => $line['rental_qty'] ?? null,
                    'price_unit' => $line['price_unit'],
                    'duration_price' => $line['duration_price'] ?? 0,
                    'customer_name' => $line['customer_name'] ?? null,
                    'product_name' => $line['product_name'] ?? null,
                    'license_plate' => $line['license_plate'] ?? null,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Save Invoice Subscription entries.
     * 
     * @param array $entries      The records to save
     * @param bool  $truncateFirst  If true, wipe the table first (Deep Re-Sync). 
     *                              If false, upsert by period_odoo_id (Fast Sync).
     */
    public function saveInvoiceSubscriptions(array $entries, bool $truncateFirst = false): int
    {
        if ($truncateFirst) {
            // Deep Re-Sync: wipe the table to ensure a clean state
            InvoiceSubscription::truncate();
        }

        $count    = 0;
        $syncedAt = now();

        foreach ($entries as $entry) {
            if (empty($entry['period_odoo_id'])) continue;

            $data = [
                'period_numeric_id'   => $entry['period_numeric_id'] ?? null,
                'so_name'             => $entry['so_name'] ?? null,
                'partner_name'        => $entry['partner_name'] ?? null,
                'rental_status'       => $entry['rental_status'] ?? null,
                'rental_type'         => $entry['rental_type'] ?? 'Subscription',
                'actual_start_rental' => $entry['actual_start_rental'] ?: null,
                'actual_end_rental'   => $entry['actual_end_rental'] ?: null,
                'period_type'         => $entry['period_type'] ?? null,
                'product_name'        => $entry['product_name'] ?? null,
                'license_plate'       => $entry['license_plate'] ?? null,
                'invoice_date'        => $entry['invoice_date'] ?: null,
                'due_date'            => $entry['due_date'] ?: null,
                'payment_date'        => $entry['payment_date'] ?: null,
                'period_start'        => $entry['period_start'] ?: null,
                'period_end'          => $entry['period_end'] ?: null,
                'price_unit'          => $entry['price_unit'] ?? 0,
                'duration_price'      => $entry['duration_price'] ?? 0,
                'invoice_amount'      => $entry['invoice_amount'] ?? 0,
                'rental_uom'          => $entry['rental_uom'] ?? null,
                'invoice_name'        => $entry['invoice_name'] ?: null,
                'invoice_ref'         => $entry['invoice_ref'] ?: null,
                'customer_ref'        => $entry['customer_ref'] ?: null,
                'transaction_code'    => $entry['transaction_code'] ?: null,
                'invoice_state'       => $entry['invoice_state'] ?: null,
                'partner_npwp'        => $entry['partner_npwp'] ?? null,
                'partner_address'     => $entry['partner_address'] ?? null,
                'partner_address_complete' => $entry['partner_address_complete'] ?? null,
                'payment_state'       => $entry['payment_state'] ?: null,
                'synced_at'           => $syncedAt,
                'invoice_pic'         => $entry['invoice_pic'] ?? null,
            ];

            InvoiceSubscription::updateOrCreate(
                ['period_odoo_id' => $entry['period_odoo_id']],
                $data
            );
            $count++;
        }
        return $count;
    }

    /**
     * Save Uninvoiced Rental entries
     * 
     * @param array $entries      The records to save
     * @param bool  $truncateFirst  If true, wipe the table first (Deep Re-Sync).
     */
    public function saveUninvoicedRentals(array $entries, bool $truncateFirst = false): int
    {
        if ($truncateFirst) {
            // Deep Re-Sync: wipe the table to ensure a clean state
            UninvoicedRental::truncate();
        }

        $count = 0;
        foreach ($entries as $entry) {
            if (($entry['status'] ?? '') === 'Cancelled') {
                UninvoicedRental::where('nomor_so', $entry['nomor_so'])->delete();
                continue;
            }

            // we update or create by nomor_so since it's grouped by SO
            UninvoicedRental::updateOrCreate(
                ['nomor_so' => $entry['nomor_so']],
                [
                    'kode_cust' => $entry['kode_cust'],
                    'status' => $entry['status'] ?? null,
                    'nomor_po' => $entry['nomor_po'],
                    'nomor_kontrak' => $entry['nomor_kontrak'],
                    'kontrak_ref' => $entry['kontrak_ref'] ?? '',
                    'nama_user' => $entry['nama_user'],
                    'nopol' => $entry['nopol'],
                    'model' => $entry['model'],
                    'tahun_mobil' => $entry['tahun_mobil'],
                    'start' => $entry['start'],
                    'end' => $entry['end'],
                    'tanggal_periode_belum_cetak' => $entry['tanggal_periode_belum_cetak'],
                    'start_rental_period' => $entry['start_rental_period'] ?? null,
                    'end_rental_period' => $entry['end_rental_period'] ?? null,
                    'price_di_so' => $entry['price_di_so'],
                    'duration_price' => $entry['duration_price'],
                    'invoice_period' => $entry['invoice_period'],
                    'payment_terms' => $entry['payment_terms'],
                    'area_pemakaian_unit' => $entry['area_pemakaian_unit'],
                    'chassis' => $entry['chassis'],
                    'invoice_pic' => $entry['invoice_pic'],
                    'first_invoice_date' => $entry['first_invoice_date'],
                    'rental_method' => $entry['rental_method'],
                    'recipient_bank' => $entry['recipient_bank'],
                    'tax_id' => $entry['tax_id'],
                    'id_tku' => $entry['id_tku'],
                    'kode_transaksi' => $entry['kode_transaksi'],
                    'address' => $entry['address'],
                    'tax_address' => $entry['tax_address'],
                ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Save Journal Entry entries
     */
    public function saveJournalEntries(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            DB::transaction(function () use ($entry, &$count) {
                $journal = JournalEntry::updateOrCreate(
                    ['odoo_id' => $entry['odoo_id']],
                    [
                        'move_name' => $entry['move_name'],
                        'date' => $entry['date'],
                        'journal_name' => $entry['journal_name'],
                        'partner_name' => $entry['partner_name'],
                        'ref' => $entry['ref'],
                        'amount_total_signed' => $entry['amount_total_signed'],
                        'payment_reference' => $entry['payment_reference'] ?? null,
                    ]
                );

                $journal->lines()->delete();

                foreach ($entry['lines'] as $line) {
                    $journal->lines()->create([
                        'account_code' => $line['account_code'],
                        'account_name' => $line['account_name'],
                        'description' => $line['display_name'],
                        'ref' => $line['ref'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'reconcile_id' => $line['reconcile_id'],
                    ]);
                }
                $count++;
            });
        }
        return $count;
    }

    /**
     * Clean up cancelled invoices from the local database for a given date range
     */
    public function cleanupCancelledInvoices(OdooService $odoo, string $dateFrom, string $dateTo): void
    {
        try {
            $domain = [
                ['state', '=', 'cancel'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $odoo->execute('account.move', 'search', [$domain]);
            if (empty($ids)) {
                return;
            }

            $records = $odoo->execute('account.move', 'read', [$ids, ['name']]);
            $names = [];
            foreach ($records as $rec) {
                if (!empty($rec['name'])) {
                    $names[] = $rec['name'];
                }
            }

            if (!empty($names)) {
                \Illuminate\Support\Facades\Log::info("Pruning cancelled invoices from local database: " . implode(', ', $names));
                InvoiceRental::whereIn('name', $names)->delete();
                InvoiceDriver::whereIn('name', $names)->delete();
                InvoiceOther::whereIn('name', $names)->delete();
                InvoiceVehicle::whereIn('name', $names)->delete();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to clean up cancelled invoices: " . $e->getMessage());
        }
    }
}
