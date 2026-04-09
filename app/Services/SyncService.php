<?php

namespace App\Services;

use App\Models\InvoiceDriver;
use App\Models\InvoiceOther;
use App\Models\InvoiceRental;
use App\Models\InvoiceVehicle;
use App\Models\InvoiceSubscription;
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
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
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
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
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
                ]
            );

            $invoice->lines()->delete();
            foreach ($entry['lines'] as $line) {
                $invoice->lines()->create([
                    'sale_order_name' => $line['sale_order_name'] ?? null,
                    'description' => $line['description'],
                    'serial_number' => $line['serial_number'] ?? null,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price_unit'],
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
                'invoice_date'        => $entry['invoice_date'] ?: null,
                'period_start'        => $entry['period_start'] ?: null,
                'period_end'          => $entry['period_end'] ?: null,
                'price_unit'          => $entry['price_unit'] ?? 0,
                'invoice_amount'      => $entry['invoice_amount'] ?? 0,
                'rental_uom'          => $entry['rental_uom'] ?? null,
                'invoice_name'        => $entry['invoice_name'] ?: null,
                'invoice_ref'         => $entry['invoice_ref'] ?: null,
                'invoice_state'       => $entry['invoice_state'] ?: null,
                'payment_state'       => $entry['payment_state'] ?: null,
                'synced_at'           => $syncedAt,
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
}
