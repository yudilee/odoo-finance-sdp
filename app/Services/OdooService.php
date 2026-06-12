<?php

namespace App\Services;

use App\Models\Setting;

class OdooService
{
    protected string $url;
    protected string $db;
    protected string $user;
    protected string $password;
    protected ?int $uid = null;

    public function __construct()
    {
        set_time_limit(0); // Prevent maximum execution time errors when fetching large data from Odoo
        $config = Setting::getOdooConfig();
        $this->url = rtrim($config['url'] ?? '', '/');
        $this->db = $config['db'] ?? '';
        $this->user = $config['user'] ?? '';
        $this->password = $config['password'] ?? '';
    }

    /**
     * Test connection to Odoo
     */
    public function testConnection(): array
    {
        try {
            if (empty($this->url) || empty($this->db) || empty($this->user) || empty($this->password)) {
                return ['success' => false, 'message' => 'Missing configuration. Please fill all fields.'];
            }

            $uid = $this->authenticate();

            if ($uid && is_numeric($uid)) {
                return ['success' => true, 'message' => "Connection successful! User ID: {$uid}"];
            }

            return ['success' => false, 'message' => 'Authentication failed. Check credentials.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch journal entries from Odoo using export_data
     * 
     * @param string $dateFrom  Start date (Y-m-d)
     * @param string $dateTo    End date (Y-m-d)
     * @param array  $accountCodes  Account codes to filter (e.g. ['111002', '112003'])
     */
    public function fetchJournalEntries(string $dateFrom, string $dateTo, array $accountCodes = []): array
    {
        try {
            // Build domain
            $domain = [
                ['state', '=', 'posted'],
                ['date', '>=', $dateFrom],
                ['date', '<=', $dateTo],
            ];

            // If account codes specified, we need to filter by line_ids.account_id
            // First get IDs of account.move matching date + state
            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No journal entries found for the given criteria.'];
            }

            // Export fields matching the Excel columns
            $exportFields = [
                'id',                             // 0: Odoo ID
                'name',                           // 1: Move name (e.g. KJKT/2026/00427)
                'date',                            // 2: Date
                'journal_id/display_name',         // 3: Journal name (e.g. Kas Jakarta)
                'partner_id/commercial_partner_id/display_name',         // 4: Partner (Use Commercial Partner to get Parent Company Name)
                'ref',                             // 5: Reference
                'amount_total_signed',             // 6: Total amount
                'line_ids/account_id/display_name',// 7: Account (e.g. "111002 Kas Jakarta")
                'line_ids/display_name',           // 8: Line description
                'line_ids/ref',                    // 9: Line reference
                'line_ids/debit',                  // 10: Debit
                'line_ids/credit',                 // 11: Credit
                'payment_reference',               // 12: Payment Reference (Vendor Bill)
                'line_ids/full_reconcile_id/.id',  // 13: Reconciliation ID (Numeric)
            ];

            // Chunked export_data to prevent memory issues for large moves
            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);

                if (!isset($result['datas'])) {
                    continue;
                }

                $currentEntry = null;

                foreach ($result['datas'] as $row) {
                    $odooId = $row[0] ?? '';
                    $moveName = $row[1] ?? '';
                    $accountDisplay = $row[7] ?? '';

                    // Extract account code from display name
                    $accountCode = '';
                    $accountName = '';
                    if (!empty($accountDisplay)) {
                        $parts = explode(' ', trim($accountDisplay), 2);
                        $accountCode = $parts[0] ?? '';
                        $accountName = $parts[1] ?? '';
                    }

                    // If move_name is non-empty, this is a new entry header row
                    if (!empty($moveName)) {
                        if ($currentEntry !== null) {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = [
                            'odoo_id' => $row[0] ?? '',
                            'move_name' => $moveName,
                            'date' => $row[2] ?? '',
                            'journal_name' => $row[3] ?? '',
                            'partner_name' => $row[4] ?? '',
                            'ref' => $row[5] ?? '',
                            'amount_total_signed' => (float) ($row[6] ?? 0),
                            'payment_reference' => $row[12] ?? '',
                            'lines' => [],
                        ];
                    }

                    // Add line item
                    if ($currentEntry !== null) {
                        $currentEntry['lines'][] = [
                            'account_code' => $accountCode,
                            'account_name' => $accountName,
                            'display_name' => $row[8] ?? '',
                            'ref' => $row[9] ?? '',
                            'debit' => (float) ($row[10] ?? 0),
                            'credit' => (float) ($row[11] ?? 0),
                            'reconcile_id' => $row[13] ?? null,
                        ];
                    }
                }

                // Don't forget the last entry of this chunk
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }
            }

            // Filter ENTRIES (not lines) by account codes:
            // Keep an entry if ANY of its lines match the selected account codes
            if (!empty($accountCodes)) {
                $entries = array_values(array_filter($entries, function ($entry) use ($accountCodes) {
                    foreach ($entry['lines'] as $line) {
                        if (in_array($line['account_code'], $accountCodes)) {
                            return true;
                        }
                    }
                    return false;
                }));
            }

            // Deep Sync for Vendor Bills via Reconciliation
            $deepSync = \App\Models\Setting::get('odoo_deep_sync_journal', '0') === '1';
            if ($deepSync && !empty($entries)) {
                $allReconcileIds = [];
                foreach ($entries as $entry) {
                    foreach ($entry['lines'] as $line) {
                        if (!empty($line['reconcile_id'])) {
                            $allReconcileIds[] = (int) $line['reconcile_id'];
                        }
                    }
                }
                $allReconcileIds = array_values(array_unique($allReconcileIds));

                if (!empty($allReconcileIds)) {
                    $billMap = []; // Final map from RecID to Bill Name
                    $processedRecIds = [];
                    $currentRecIds = $allReconcileIds;

                    // Keep track of which original RecID each discovered RecID belongs to
                    // mapping: discovered_rec_id => [original_rec_id, ...]
                    $recRelationships = [];
                    foreach ($allReconcileIds as $rid) {
                        $recRelationships[$rid] = [$rid];
                    }

                    for ($level = 0; $level < 2; $level++) {
                        if (empty($currentRecIds))
                            break;

                        $linkedLines = $this->execute('account.move.line', 'search_read', [
                            [['full_reconcile_id', 'in', $currentRecIds]],
                            ['full_reconcile_id', 'move_name', 'move_id']
                        ]);

                        if (!is_array($linkedLines))
                            break;

                        $nextRecIds = [];
                        $moveIdsToCheck = []; // move_id => [parent_rec_id, ...]

                        foreach ($linkedLines as $ll) {
                            $recIdFull = $ll['full_reconcile_id'] ?? null;
                            $recId = is_array($recIdFull) ? $recIdFull[0] : (int) $recIdFull;

                            $moveName = $ll['move_name'] ?? '';

                            // If it's a bill, attribute it to all its "ancestor" RecIDs
                            if (stripos($moveName, 'BILL') !== false || stripos($moveName, 'INV') !== false) {
                                $moveRef = '';
                                if (isset($ll['move_id']) && is_array($ll['move_id'])) {
                                    $moveDisplayName = $ll['move_id'][1] ?? '';
                                    if (preg_match('/\((.*)\)/', $moveDisplayName, $matches)) {
                                        $moveRef = $matches[1];
                                    }
                                }
                                $billName = !empty($moveRef) ? "{$moveName} ({$moveRef})" : $moveName;

                                foreach (($recRelationships[$recId] ?? [$recId]) as $ancestorId) {
                                    $billMap[$ancestorId] = $billName;
                                }
                            } else {
                                // If it's NOT a bill, check other lines of this move for more reconciliations
                                if (isset($ll['move_id'][0])) {
                                    $moveIdsToCheck[$ll['move_id'][0]] = $recRelationships[$recId] ?? [$recId];
                                }
                            }
                        }

                        // Check moves for other reconciliations
                        if (!empty($moveIdsToCheck)) {
                            $mIds = array_keys($moveIdsToCheck);
                            $otherLines = $this->execute('account.move.line', 'search_read', [
                                [['move_id', 'in', $mIds], ['full_reconcile_id', '!=', false]],
                                ['full_reconcile_id', 'move_id']
                            ]);

                            if (is_array($otherLines)) {
                                foreach ($otherLines as $ol) {
                                    $nextIdFull = $ol['full_reconcile_id'] ?? null;
                                    $nextId = is_array($nextIdFull) ? $nextIdFull[0] : (int) $nextIdFull;
                                    $mId = $ol['move_id'][0] ?? null;

                                    if ($nextId && !in_array($nextId, $processedRecIds) && !in_array($nextId, $currentRecIds)) {
                                        $nextRecIds[] = $nextId;
                                        // Pass the ancestors down
                                        $ancestors = $moveIdsToCheck[$mId] ?? [];
                                        foreach ($ancestors as $aid) {
                                            if (!isset($recRelationships[$nextId]))
                                                $recRelationships[$nextId] = [];
                                            if (!in_array($aid, $recRelationships[$nextId])) {
                                                $recRelationships[$nextId][] = $aid;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $processedRecIds = array_merge($processedRecIds, $currentRecIds);
                        $currentRecIds = array_values(array_unique($nextRecIds));
                    }

                    // Map bills back to entries
                    foreach ($entries as &$entry) {
                        foreach ($entry['lines'] as &$line) {
                            if (!empty($line['reconcile_id'])) {
                                $rid = (int) $line['reconcile_id'];
                                if (isset($billMap[$rid])) {
                                    $this->appendPaymentRef($entry, $billMap[$rid]);
                                }
                            }
                        }
                    }
                }
            }

            return [
                'success' => true,
                'data' => $entries,
                'count' => count($entries),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fetch failed: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Helper to append payment reference without duplicates
     */
    protected function appendPaymentRef(array &$entry, string $ref): void
    {
        if (empty($entry['payment_reference'])) {
            $entry['payment_reference'] = $ref;
        } elseif (stripos($entry['payment_reference'], $ref) === false) {
            $entry['payment_reference'] .= ', ' . $ref;
        }
    }

    /**
     * Fetch Invoice Driver entries from Odoo using export_data
     */
    public function getInvoiceDriverIds(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', '=', 'Invoice Driver'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $this->execute('account.move', 'search', [$domain]);
            return ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'ids' => []];
        }
    }

    public function fetchInvoiceDriversByIds(array $moveIds): array
    {
        try {
            if (empty($moveIds))
                return ['success' => true, 'data' => []];

            $exportFields = [
                'name',
                'partner_id/name',
                'invoice_date',
                'invoice_payment_term_id/name',
                'ref',
                'journal_id/name',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'invoice_line_ids/name',
                'invoice_line_ids/quantity',
                'invoice_line_ids/price_unit',
                'partner_bank_id',
                'bc_manager_id/name',
                'bc_spv_id/name',
                'partner_id/contact_address',
                'partner_id/contact_address_complete',
                'narration',
                'partner_id/vat',
                'contract_ref',
                'invoice_line_ids/sale_order_id/rental_contract_id/name',
                'rental_period_id/rental_order_id/rental_contract_id/name',
                'invoice_line_ids/duration_price',
                'partner_id/.id',
                'invoice_line_ids/rental_qty',
                'hrc_forminv_invoice_pic/name',
            ];

            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);
                if (!isset($result['datas']))
                    continue;

                $currentEntry = null;
                foreach ($result['datas'] as $row) {
                    $invoiceName = $row[0] ?? '';
                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null)
                            $entries[] = $currentEntry;
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[22] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Driver',
                            'amount_untaxed' => (float) ($row[6] ?? 0),
                            'amount_tax' => (float) ($row[7] ?? 0),
                            'amount_total' => (float) ($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'narration' => $row[17] ?? '',
                            'partner_npwp' => $row[18] ?? '',
                            'contract_ref' => !empty($row[19]) ? $row[19] : (!empty($row[20]) ? $row[20] : ($row[21] ?? '')),
                            'partner_id_odoo' => $row[24] ?? null,
                            'invoice_pic' => $row[26] ?? '',
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float) ($row[10] ?? 0);
                        $linePrice = (float) ($row[11] ?? 0);

                        if (empty($currentEntry['contract_ref'])) {
                            if (!empty($row[20]))
                                $currentEntry['contract_ref'] = $row[20];
                            elseif (!empty($row[21]))
                                $currentEntry['contract_ref'] = $row[21];
                        }

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'rental_qty' => (float) ($row[25] ?? 0),
                                'price_unit' => $linePrice,
                                'duration_price' => (float) ($row[23] ?? 0),
                            ];
                        }
                    }
                }
                if ($currentEntry !== null)
                    $entries[] = $currentEntry;
            }

            $this->enrichAddresses($entries);
            return ['success' => true, 'data' => $entries];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchInvoiceDrivers(string $dateFrom, string $dateTo): array
    {
        $res = $this->getInvoiceDriverIds($dateFrom, $dateTo);
        if (!$res['success'])
            return $res;
        return $this->fetchInvoiceDriversByIds($res['ids']);
    }

    /**
     * Fetch Invoice Other entries from Odoo using export_data
     * Fetches both "Invoice Other with Tax" and "Invoice Other wo Tax" journals
     */
    public function getInvoiceOtherIds(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', 'in', ['Invoice Other with Tax', 'Invoice Other wo Tax']],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $this->execute('account.move', 'search', [$domain]);
            return ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'ids' => []];
        }
    }

    public function fetchInvoiceOthersByIds(array $moveIds): array
    {
        try {
            if (empty($moveIds))
                return ['success' => true, 'data' => []];

            $exportFields = [
                'name',
                'partner_id/name',
                'invoice_date',
                'invoice_payment_term_id/name',
                'ref',
                'journal_id/name',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'invoice_line_ids/name',
                'invoice_line_ids/quantity',
                'invoice_line_ids/price_unit',
                'partner_bank_id',
                'bc_manager_id/name',
                'bc_spv_id/name',
                'partner_id/contact_address',
                'partner_id/contact_address_complete',
                'narration',
                'partner_id/vat',
                'contract_ref',
                'invoice_line_ids/sale_order_id/rental_contract_id/name',
                'rental_period_id/rental_order_id/rental_contract_id/name',
                'invoice_date_due',
                'invoice_line_ids/duration_price',
                'partner_id/.id',
                'hrc_forminv_invoice_pic/name',
            ];

            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);
                if (!isset($result['datas']))
                    continue;

                $currentEntry = null;
                foreach ($result['datas'] as $row) {
                    $invoiceName = $row[0] ?? '';
                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null)
                            $entries[] = $currentEntry;
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[22] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Other',
                            'amount_untaxed' => (float) ($row[6] ?? 0),
                            'amount_tax' => (float) ($row[7] ?? 0),
                            'amount_total' => (float) ($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'narration' => $row[17] ?? '',
                            'partner_npwp' => $row[18] ?? '',
                            'contract_ref' => !empty($row[19]) ? $row[19] : (!empty($row[20]) ? $row[20] : ($row[21] ?? '')),
                            'partner_id_odoo' => $row[24] ?? null,
                            'invoice_pic' => $row[25] ?? '',
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float) ($row[10] ?? 0);
                        $linePrice = (float) ($row[11] ?? 0);

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[20]))
                                    $currentEntry['contract_ref'] = $row[20];
                                elseif (!empty($row[21]))
                                    $currentEntry['contract_ref'] = $row[21];
                            }
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'duration_price' => (float) ($row[23] ?? 0),
                            ];
                        }
                    }
                }
                if ($currentEntry !== null)
                    $entries[] = $currentEntry;
            }

            $this->enrichAddresses($entries);
            return ['success' => true, 'data' => $entries];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchInvoiceOthers(string $dateFrom, string $dateTo): array
    {
        $res = $this->getInvoiceOtherIds($dateFrom, $dateTo);
        if (!$res['success'])
            return $res;
        return $this->fetchInvoiceOthersByIds($res['ids']);
    }

    /**
     * Fetch Invoice Vehicle (Penjualan Kendaraan) entries from Odoo using export_data
     * Journal: Invoice Penjualan Kendaraan (INVCR)
     */
    public function getInvoiceVehicleIds(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', '=', 'Invoice Used Car'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $this->execute('account.move', 'search', [$domain]);
            return ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'ids' => []];
        }
    }

    public function fetchInvoiceVehiclesByIds(array $moveIds): array
    {
        try {
            if (empty($moveIds))
                return ['success' => true, 'data' => []];

            $exportFields = [
                'name',
                'partner_id/name',
                'invoice_date',
                'invoice_payment_term_id/name',
                'ref',
                'journal_id/name',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'invoice_line_ids/name',
                'invoice_line_ids/quantity',
                'invoice_line_ids/price_unit',
                'partner_bank_id',
                'bc_manager_id/name',
                'bc_spv_id/name',
                'partner_id/contact_address',
                'partner_id/contact_address_complete',
                'invoice_line_ids/product_id/name',
                'invoice_line_ids/serial_ids/name',
                'partner_id/vat',
                'narration',
                'contract_ref',
                'invoice_line_ids/sale_order_id/rental_contract_id/name',
                'rental_period_id/rental_order_id/rental_contract_id/name',
                'invoice_date_due',
                'invoice_line_ids/duration_price',
                'partner_id/.id',
                'hrc_forminv_invoice_pic/name',
            ];

            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);
                if (!isset($result['datas']))
                    continue;

                $currentEntry = null;
                foreach ($result['datas'] as $row) {
                    $invoiceName = $row[0] ?? '';
                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null)
                            $entries[] = $currentEntry;
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[24] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? '',
                            'amount_untaxed' => (float) ($row[6] ?? 0),
                            'amount_tax' => (float) ($row[7] ?? 0),
                            'amount_total' => (float) ($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'partner_npwp' => $row[19] ?? '',
                            'narration' => $row[20] ?? '',
                            'contract_ref' => !empty($row[21]) ? $row[21] : (!empty($row[22]) ? $row[22] : ($row[23] ?? '')),
                            'partner_id_odoo' => $row[26] ?? null,
                            'invoice_pic' => $row[27] ?? '',
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float) ($row[10] ?? 0);
                        $linePrice = (float) ($row[11] ?? 0);
                        $productName = $row[17] ?? '';
                        $licensePlate = $row[18] ?? '';
                        $serialNumber = '';
                        if (preg_match('/Kend\.?\s*([A-Z0-9]+)/i', $lineDesc, $matches)) {
                            $serialNumber = $matches[1];
                        }

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[22]))
                                    $currentEntry['contract_ref'] = $row[22];
                                elseif (!empty($row[23]))
                                    $currentEntry['contract_ref'] = $row[23];
                            }
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'product_name' => $productName,
                                'license_plate' => $licensePlate,
                                'serial_number' => $serialNumber,
                                'duration_price' => (float) ($row[25] ?? 0),
                            ];
                        }
                    }
                }
                if ($currentEntry !== null)
                    $entries[] = $currentEntry;
            }

            $this->enrichAddresses($entries);
            return ['success' => true, 'data' => $entries];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchInvoiceVehicles(string $dateFrom, string $dateTo): array
    {
        $res = $this->getInvoiceVehicleIds($dateFrom, $dateTo);
        if (!$res['success'])
            return $res;
        return $this->fetchInvoiceVehiclesByIds($res['ids']);
    }

    /**
     * Fetch Subscription Invoice Periods from Odoo (model: rental.period.invoice)
     * Queries invoice periods where invoice_date is within [dateFrom, dateTo]
     * and the parent sale.order has rental_type = Subscription.
     *
     * @param string $dateFrom  Start date (Y-m-d), e.g. '2025-04-01'
     * @param string $dateTo    End date   (Y-m-d), e.g. today+15 days
     */
    public function fetchSubscriptionInvoicePeriods(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
                ['rental_order_id.rental_type', '=', 'subscription'],
            ];

            // Search IDs on the period model directly (most efficient)
            $allIds = $this->execute('rental.period.invoice', 'search', [$domain], ['order' => 'invoice_date asc']);

            if (empty($allIds)) {
                return [
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'message' => 'No subscription invoice periods found for the given date range.',
                ];
            }

            $exportFields = [
                'id',                                        // 0: Odoo external ID
                'rental_order_id/name',                      // 1: SO name (R/2026/...)
                'rental_order_id/partner_id',                // 2: Customer display name
                'rental_order_id/rental_status',             // 3: Reserved / Pickedup / Returned
                'rental_order_id/rental_type',               // 4: Subscription
                'rental_order_id/actual_start_rental',       // 5: Rental start
                'rental_order_id/actual_end_rental',         // 6: Rental end
                'rental_order_id/sale_invoice_period_id',    // 7: Monthly / Weekly
                'product_id',                                // 8: Vehicle/product name
                'invoice_date',                              // 9: Expected invoice date
                'start_rental_period_date',                  // 10: Period start
                'end_rental_period_date',                    // 11: Period end
                'invoice_id',                                // 12: Invoice display (INVRS/... + refs)
                'invoice_id/state',                          // 13: draft / posted
                'invoice_id/payment_state',                  // 14: paid / not_paid
                'invoice_id/name',                           // 15: Invoice number only
                'price_unit',                                // 16: Unit price
                'rental_uom',                                // 17: month / day
                'invoice_id/amount_total',                   // 18: Actual invoice price
                'lot_id',                                    // 19: Serial Number / License Plate
                'invoice_id/ref',                             // 20: Customer Reference
                'invoice_id/l10n_id_kode_transaksi',          // 21: Kode Transaksi
                'invoice_id/invoice_date_due',                // 22: Due Date
                'invoice_id/invoice_payments_widget',         // 23: TANGGAL BAYAR (Widget)
                'rental_order_id/partner_id/contact_address',            // 24: Address (multiline)
                'rental_order_id/partner_id/contact_address_complete',   // 25: Address (single line)
                'rental_order_id/partner_id/vat',                        // 26: NPWP
                'rental_order_id/partner_id/.id',                        // 27: Partner ID for enrichment
                'invoice_id/hrc_forminv_invoice_pic/name',               // 28: PIC Name
            ];

            $entries = [];
            $chunkSize = 500;
            $idChunks = array_chunk($allIds, $chunkSize);

            foreach ($idChunks as $chunk) {
                $result = $this->execute('rental.period.invoice', 'export_data', [$chunk, $exportFields]);

                if (!isset($result['datas'])) {
                    continue;
                }

                foreach ($result['datas'] as $row) {
                    $rawId = $row[0] ?? '';
                    $invoiceRef = $row[12] ?? '';   // full display e.g. "INVRS/2025/03192 (refs...)"
                    $invoiceName = $row[15] ?? '';   // clean name e.g. "INVRS/2025/03192"
                    $rentalStatus = $row[3] ?? null;
                    $invoiceState = $row[13] ?? null;
                    $priceUnit = (float) ($row[16] ?? 0);
                    $invoiceAmount = (float) ($row[18] ?? 0);

                    // Skip cancelled rentals
                    if ($rentalStatus === 'cancel' || $rentalStatus === 'cancelled')
                        continue;

                    // Skip if it has an invoice, but the Invoice Price is 0.
                    // If it has NO invoice (Not Invoiced), we keep it.
                    if (!empty($invoiceName) && $invoiceAmount == 0)
                        continue;

                    // Parse numeric ID from external ID string (e.g. __export__.rental_period_invoice_1903_hash)
                    $numericId = null;
                    if (preg_match('/rental_period_invoice_(\d+)_/', $rawId, $m)) {
                        $numericId = (int) $m[1];
                    }

                    $subStart = $row[5] ?? null;
                    $subEnd = $row[6] ?? null;

                    $entries[] = [
                        'period_odoo_id' => $rawId,
                        'period_numeric_id' => $numericId,
                        'so_name' => $row[1] ?? '',
                        'partner_name' => $row[2] ?? '',
                        'rental_status' => $rentalStatus,
                        'rental_type' => $row[4] ?? 'Subscription',
                        'actual_start_rental' => $subStart,
                        'actual_end_rental' => $subEnd,
                        'period_type' => $row[7] ?? '',
                        'product_name' => $row[8] ?? '',
                        'invoice_date' => $row[9] ?? null,
                        'period_start' => $row[10] ?? null,
                        'period_end' => $row[11] ?? null,
                        'invoice_ref' => $invoiceRef,
                        'invoice_name' => $invoiceName,
                        'invoice_state' => $invoiceState,
                        'payment_state' => $row[14] ?? null,
                        'price_unit' => $priceUnit,
                        'invoice_amount' => $invoiceAmount,
                        'rental_uom' => $row[17] ?? '',
                        'license_plate' => $row[19] ?? null,
                        'customer_ref' => $row[20] ?? null,
                        'transaction_code' => $row[21] ?? null,
                        'due_date' => $row[22] ?? null,
                        'payment_date' => $this->extractLatestPaymentDate($row[23] ?? null),
                        'partner_address' => $row[24] ?? '',
                        'partner_address_complete' => $row[25] ?? '',
                        'partner_npwp' => $row[26] ?? '',
                        'partner_id_odoo' => $row[27] ?? null,
                        'invoice_pic' => $row[28] ?? '',
                    ];
                }
            }

            $this->enrichAddresses($entries);

            return [
                'success' => true,
                'data' => $entries,
                'count' => count($entries),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Fetch failed: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Fetch Invoice Rental entries from Odoo using export_data
     * Fetches both "Invoice Sewa Retail" and "Invoice Sewa Subscription" journals
     */
    public function getInvoiceRentalIds(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', 'in', ['Invoice Sewa Retail', 'Invoice Sewa Subscription']],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $this->execute('account.move', 'search', [$domain]);
            return ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'ids' => []];
        }
    }

    public function fetchInvoiceRentalsByIds(array $moveIds): array
    {
        try {
            if (empty($moveIds))
                return ['success' => true, 'data' => []];

            $exportFields = [
                'name',
                'partner_id/name',
                'invoice_date',
                'invoice_payment_term_id/name',
                'ref',
                'journal_id/name',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'invoice_line_ids/sale_order_id/name',
                'invoice_line_ids/name',
                'invoice_line_ids/serial_ids/name',
                'invoice_line_ids/start_rental_period',
                'invoice_line_ids/end_rental_period',
                'invoice_line_ids/sale_order_id/rental_uom',
                'invoice_line_ids/quantity',
                'invoice_line_ids/price_unit',
                'invoice_line_ids/sale_order_id/customer_name',
                'partner_bank_id',
                'bc_manager_id/name',
                'bc_spv_id/name',
                'partner_id/contact_address',
                'partner_id/contact_address_complete',
                'narration',
                'invoice_line_ids/sale_order_id/rental_contract_id/name',
                'rental_period_id/rental_order_id/rental_contract_id/name',
                'invoice_date_due',
                'partner_id/vat',
                'invoice_line_ids/rental_qty',
                'invoice_line_ids/rental_uom',
                'invoice_line_ids/duration_price',
                'invoice_line_ids/product_id/name',
                'invoice_line_ids/sale_order_id/actual_start_rental',
                'invoice_line_ids/sale_order_id/actual_end_rental',
                'partner_id/.id',
                'contract_ref',
                'hrc_forminv_invoice_pic/name'
            ];

            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);
                if (!isset($result['datas']))
                    continue;

                $currentEntry = null;
                foreach ($result['datas'] as $row) {
                    $invoiceName = $row[0] ?? '';
                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null)
                            $entries[] = $currentEntry;
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[26] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? '',
                            'amount_untaxed' => (float) ($row[6] ?? 0),
                            'amount_tax' => (float) ($row[7] ?? 0),
                            'amount_total' => (float) ($row[8] ?? 0),
                            'partner_bank' => $row[18] ?? '',
                            'manager_name' => $row[19] ?? '',
                            'spv_name' => $row[20] ?? '',
                            'partner_address' => $row[21] ?? '',
                            'partner_address_complete' => $row[22] ?? '',
                            'partner_npwp' => $row[27] ?? '',
                            'narration' => $row[23] ?? '',
                            'contract_ref' => !empty($row[35]) ? $row[35] : ($row[24] ?? $row[25] ?? ''),
                            'partner_id_odoo' => $row[34] ?? null,
                            'invoice_pic' => $row[36] ?? '',
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $soId = $row[9] ?? '';
                        $lineDesc = $row[10] ?? '';
                        $serialNum = $row[11] ?? '';
                        $actualStart = $row[12] ?? '';
                        $actualEnd = $row[13] ?? '';
                        $uom = !empty($row[29]) ? $row[29] : ($row[14] ?? '');
                        $qty = (float) ($row[15] ?? 0);
                        $priceUnit = (float) ($row[16] ?? 0);
                        $customerName = !empty($row[17]) ? $row[17] : ($currentEntry['partner_name'] ?? '');
                        $rentalQty = (float) ($row[28] ?? 0);
                        $productName = $row[31] ?? '';

                        // Pull exact time from Rental Order only for INVRT (Retail). 
                        // For INVRS (Subscription), use the billing period ($row[12]/[13]).
                        if (str_starts_with($invoiceName, 'INVRT')) {
                            $actualStart = !empty($row[32]) ? $row[32] : ($row[12] ?? '');
                            $actualEnd = !empty($row[33]) ? $row[33] : ($row[13] ?? '');
                        }

                        // Prepend product name to description if it's not already there, so we can filter by it
                        if (!empty($productName) && !empty($lineDesc) && !str_contains(strtolower($lineDesc), strtolower($productName))) {
                            // If it's something like "Lain-Lain (inv)", we format it nicely
                            $lineDesc = $productName . "\n" . $lineDesc;
                        }

                        if (!empty($lineDesc) || $qty > 0 || $priceUnit > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[35])) {
                                    $currentEntry['contract_ref'] = $row[35];
                                } elseif (!empty($row[24])) {
                                    $currentEntry['contract_ref'] = $row[24];
                                } elseif (!empty($row[25])) {
                                    $currentEntry['contract_ref'] = $row[25];
                                }
                            }
                            $currentEntry['lines'][] = [
                                'sale_order_id' => $soId,
                                'description' => $lineDesc,
                                'serial_number' => $serialNum,
                                'actual_start' => $actualStart,
                                'actual_end' => $actualEnd,
                                'uom' => $uom,
                                'quantity' => $qty,
                                'rental_qty' => $rentalQty,
                                'price_unit' => $priceUnit,
                                'duration_price' => (float) ($row[30] ?? 0),
                                'customer_name' => $customerName,
                            ];
                        }
                    }
                }

                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }
            }

            $this->enrichAddresses($entries);

            return [
                'success' => true,
                'data' => $entries,
                'count' => count($entries),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fetch failed: ' . $e->getMessage(), 'data' => []];
        }
    }
    public function getInvoiceProformaIds(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'draft'],
                ['move_type', '=', 'out_invoice'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];
            $ids = $this->execute('account.move', 'search', [$domain]);
            return ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'ids' => []];
        }
    }

    public function fetchInvoiceProformasByIds(array $moveIds): array
    {
        try {
            if (empty($moveIds))
                return ['success' => true, 'data' => []];

            $exportFields = [
                '.id', // Odoo ID (0)
                'name', // 1
                'partner_id/name', // 2
                'invoice_date', // 3
                'invoice_payment_term_id/name', // 4
                'ref', // 5
                'journal_id/name', // 6
                'amount_untaxed', // 7
                'amount_tax', // 8
                'amount_total', // 9
                'invoice_line_ids/name', // 10
                'invoice_line_ids/quantity', // 11
                'invoice_line_ids/price_unit', // 12
                'partner_bank_id', // 13
                'bc_manager_id/name', // 14
                'bc_spv_id/name', // 15
                'partner_id/contact_address', // 16
                'partner_id/contact_address_complete', // 17
                'narration', // 18
                'partner_id/vat', // 19
                'contract_ref', // 20
                'invoice_date_due', // 21
                'invoice_line_ids/duration_price', // 22
                'partner_id/.id', // 23
                'invoice_line_ids/rental_qty', // 24
                'invoice_line_ids/sale_order_id/name', // 25
                'invoice_line_ids/serial_ids/name', // 26
                'invoice_line_ids/product_id/name', // 27
            ];

            $entries = [];
            $chunkSize = 500;
            $moveIdsChunks = array_chunk($moveIds, $chunkSize);

            foreach ($moveIdsChunks as $chunk) {
                $result = $this->execute('account.move', 'export_data', [$chunk, $exportFields]);
                if (!isset($result['datas']))
                    continue;

                $currentEntry = null;
                foreach ($result['datas'] as $row) {
                    $odooId = $row[0] ?? '';
                    if (!empty($odooId)) {
                        if ($currentEntry !== null)
                            $entries[] = $currentEntry;
                        $currentEntry = [
                            'odoo_id' => $odooId,
                            'name' => $row[1] ?? '',
                            'partner_name' => $row[2] ?? '',
                            'invoice_date' => $row[3] ?? '',
                            'payment_term' => $row[4] ?? '',
                            'ref' => $row[5] ?? '',
                            'journal_name' => $row[6] ?? '',
                            'amount_untaxed' => (float) ($row[7] ?? 0),
                            'amount_tax' => (float) ($row[8] ?? 0),
                            'amount_total' => (float) ($row[9] ?? 0),
                            'partner_bank' => $row[13] ?? '',
                            'manager_name' => $row[14] ?? '',
                            'spv_name' => $row[15] ?? '',
                            'partner_address' => $row[16] ?? '',
                            'partner_address_complete' => $row[17] ?? '',
                            'narration' => $row[18] ?? '',
                            'partner_npwp' => $row[19] ?? '',
                            'contract_ref' => $row[20] ?? '',
                            'invoice_date_due' => $row[21] ?? '',
                            'partner_id_odoo' => $row[23] ?? null,
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[10] ?? '';
                        $lineQty = (float) ($row[11] ?? 0);
                        $linePrice = (float) ($row[12] ?? 0);

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'duration_price' => (float) ($row[22] ?? 0),
                                'rental_qty' => (float) ($row[24] ?? 0),
                                'sale_order_id' => $row[25] ?? '',
                                'serial_number' => $row[26] ?? '',
                                'product_name' => $row[27] ?? '',
                            ];
                        }
                    }
                }
                if ($currentEntry !== null)
                    $entries[] = $currentEntry;
            }

            $this->enrichAddresses($entries);
            return ['success' => true, 'data' => $entries];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchInvoiceProformas(string $dateFrom, string $dateTo): array
    {
        $res = $this->getInvoiceProformaIds($dateFrom, $dateTo);
        if (!$res['success'])
            return $res;
        return $this->fetchInvoiceProformasByIds($res['ids']);
    }
    /**
     * Authenticate with Odoo and return user ID
     */
    protected function authenticate(): ?int
    {
        $commonUrl = $this->url . '/xmlrpc/2/common';

        $request = $this->xmlrpcEncode('authenticate', [
            $this->db,
            $this->user,
            $this->password,
            []
        ]);

        $response = $this->sendRequest($commonUrl, $request);
        $result = $this->xmlrpcDecode($response);

        if (is_array($result) && isset($result['faultCode'])) {
            throw new \Exception($result['faultString'] ?? 'Unknown XML-RPC error');
        }

        $this->uid = is_numeric($result) ? (int) $result : null;
        return $this->uid;
    }

    /**
     * Execute a method on an Odoo model
     */
    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        if (!$this->uid) {
            throw new \Exception('Not authenticated');
        }

        $objectUrl = $this->url . '/xmlrpc/2/object';

        $request = $this->xmlrpcEncode('execute_kw', [
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $method,
            $args,
            $kwargs
        ]);

        $response = $this->sendRequest($objectUrl, $request);
        $result = $this->xmlrpcDecode($response);

        if (is_array($result) && isset($result['faultCode'])) {
            throw new \Exception($result['faultString'] ?? 'Unknown XML-RPC error');
        }

        return $result;
    }

    // ─── XML-RPC Encoding/Decoding ───

    protected function xmlrpcEncode(string $method, array $params): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<methodCall>';
        $xml .= '<methodName>' . htmlspecialchars($method) . '</methodName>';
        $xml .= '<params>';
        foreach ($params as $param) {
            $xml .= '<param>' . $this->encodeValue($param) . '</param>';
        }
        $xml .= '</params>';
        $xml .= '</methodCall>';
        return $xml;
    }

    protected function encodeValue($value): string
    {
        if (is_null($value))
            return '<value><nil/></value>';
        if (is_bool($value))
            return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        if (is_int($value))
            return '<value><int>' . $value . '</int></value>';
        if (is_float($value))
            return '<value><double>' . $value . '</double></value>';

        if (is_string($value)) {
            return '<value><string>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string></value>';
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                $xml = '<value><struct>';
                foreach ($value as $k => $v) {
                    $xml .= '<member><name>' . htmlspecialchars($k) . '</name>' . $this->encodeValue($v) . '</member>';
                }
                $xml .= '</struct></value>';
                return $xml;
            } else {
                $xml = '<value><array><data>';
                foreach ($value as $v) {
                    $xml .= $this->encodeValue($v);
                }
                $xml .= '</data></array></value>';
                return $xml;
            }
        }

        return '<value><string>' . htmlspecialchars((string) $value) . '</string></value>';
    }

    protected function isAssoc(array $arr): bool
    {
        if (empty($arr))
            return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function xmlrpcDecode(string $xml): mixed
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \Exception('Failed to parse XML response: ' . ($errors[0]->message ?? 'Unknown error'));
        }

        if (isset($doc->fault)) {
            $fault = $this->decodeValue($doc->fault->value);
            return ['faultCode' => $fault['faultCode'] ?? 0, 'faultString' => $fault['faultString'] ?? 'Unknown fault'];
        }

        if (isset($doc->params->param->value)) {
            return $this->decodeValue($doc->params->param->value);
        }

        return null;
    }

    protected function decodeValue($valueNode): mixed
    {
        if (isset($valueNode->int) || isset($valueNode->i4))
            return (int) ($valueNode->int ?? $valueNode->i4);
        if (isset($valueNode->boolean))
            return (string) $valueNode->boolean === '1';
        if (isset($valueNode->string))
            return (string) $valueNode->string;
        if (isset($valueNode->double))
            return (float) $valueNode->double;
        if (isset($valueNode->nil))
            return null;

        if (isset($valueNode->array)) {
            $result = [];
            if (isset($valueNode->array->data->value)) {
                foreach ($valueNode->array->data->value as $val) {
                    $result[] = $this->decodeValue($val);
                }
            }
            return $result;
        }

        if (isset($valueNode->struct)) {
            $result = [];
            if (isset($valueNode->struct->member)) {
                foreach ($valueNode->struct->member as $member) {
                    $name = (string) $member->name;
                    $result[$name] = $this->decodeValue($member->value);
                }
            }
            return $result;
        }

        return (string) $valueNode;
    }

    protected function sendRequest(string $url, string $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL error: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("HTTP error {$httpCode}");
        }

        return $response;
    }

    /**
     * Extract the latest payment date from Odoo's invoice_payments_widget
     */
    private function extractLatestPaymentDate($widget): ?string
    {
        if (!$widget)
            return null;

        // Widget can be a JSON string or an already parsed array (depending on RPC behavior)
        $data = is_string($widget) ? json_decode($widget, true) : $widget;

        if (!isset($data['content']) || !is_array($data['content'])) {
            return null;
        }

        $latestDate = null;
        foreach ($data['content'] as $payment) {
            if (isset($payment['date'])) {
                if ($latestDate === null || $payment['date'] > $latestDate) {
                    $latestDate = (string) $payment['date'];
                }
            }
        }

        return $latestDate;
    }

    /**
     * Enrich entries with child invoice addresses if they exist.
     *
     * Odoo's computed `contact_address` on a child contact always prefixes the
     * parent company name and uses the parent's formatting, making it look
     * identical to the parent address.  We therefore build the address manually
     * from the child's own raw fields (name, street, street2, city, zip,
     * state_id) so the Invoice Address contact info is properly reflected.
     */
    protected function enrichAddresses(array &$entries): void
    {
        $partnerIds = [];
        foreach ($entries as $entry) {
            if (!empty($entry['partner_id_odoo'])) {
                // partner_id_odoo might be an ID or an array [id, name] depending on export behavior
                $id = is_array($entry['partner_id_odoo']) ? $entry['partner_id_odoo'][0] : $entry['partner_id_odoo'];
                if (is_numeric($id)) {
                    $partnerIds[] = (int) $id;
                }
            }
        }
        $partnerIds = array_values(array_unique($partnerIds));

        if (empty($partnerIds))
            return;

        // Fetch all child contacts of type 'invoice' for these partners
        // We fetch raw address fields so we can build the address ourselves
        $invoiceContacts = $this->execute('res.partner', 'search_read', [
            [['parent_id', 'in', $partnerIds], ['type', '=', 'invoice']],
            ['parent_id', 'name', 'street', 'street2', 'city', 'state_id', 'zip', 'country_id', 'vat']
        ]);

        if (empty($invoiceContacts))
            return;

        // Map parent_id => data (built from raw fields)
        $addressMap = [];
        foreach ($invoiceContacts as $contact) {
            $parentId = is_array($contact['parent_id']) ? $contact['parent_id'][0] : $contact['parent_id'];

            // If there are multiple invoice addresses, we take the first one found
            if (isset($addressMap[$parentId]))
                continue;

            $childName = $contact['name'] ?? '';
            $street = $contact['street'] ?? '';
            $street2 = $contact['street2'] ?? '';
            $city = $contact['city'] ?? '';
            $stateId = $contact['state_id'] ?? null;
            $stateName = is_array($stateId) ? ($stateId[1] ?? '') : '';
            // Remove country suffix like " (ID)" from state name for cleaner display
            $stateName = preg_replace('/\s*\([A-Z]{2}\)\s*$/', '', $stateName);
            $zip = $contact['zip'] ?? '';
            $countryId = $contact['country_id'] ?? null;
            $country = is_array($countryId) ? ($countryId[1] ?? '') : '';
            $vat = $contact['vat'] ?? '';

            // Build multiline address (matching Odoo's format)
            $addrLines = [];
            if (!empty($street))
                $addrLines[] = $street;
            if (!empty($street2))
                $addrLines[] = $street2;

            // City + State + Zip line
            $cityLine = '';
            if (!empty($city))
                $cityLine .= $city;
            if (!empty($stateName))
                $cityLine .= ' ' . $stateName;
            if (!empty($zip))
                $cityLine .= ' ' . $zip;
            if (!empty(trim($cityLine)))
                $addrLines[] = trim($cityLine);

            if (!empty($country))
                $addrLines[] = $country;

            $addr = implode("\n", $addrLines);

            // Build single-line "complete" address
            $completeParts = array_filter([$street, $zip . ' ' . $city, $stateName, $country]);
            $addrComplete = implode(', ', $completeParts);

            if (!empty($addr)) {
                $addressMap[$parentId] = [
                    'address' => $addr,
                    'address_complete' => $addrComplete,
                    'vat' => $vat,
                ];
            }
        }

        // Apply override
        foreach ($entries as &$entry) {
            $rawId = $entry['partner_id_odoo'] ?? null;
            $pid = is_array($rawId) ? $rawId[0] : $rawId;

            if ($pid && isset($addressMap[$pid])) {
                $entry['partner_address'] = $addressMap[$pid]['address'];
                $entry['partner_address_complete'] = $addressMap[$pid]['address_complete'];
                if (!empty($addressMap[$pid]['vat'])) {
                    $entry['partner_npwp'] = $addressMap[$pid]['vat'];
                }
            }
        }
    }

    /**
     * Step 1: Get all SO IDs that have uninvoiced periods using read_group for maximum efficiency
     */
    public function getUninvoicedSoIds($dateFrom = null, $dateTo = null): array
    {
        $domainEmpty = [['invoice_id', '=', false]];
        $domainDraft = [['invoice_id.state', '=', 'draft']];

        if ($dateFrom) {
            $domainEmpty[] = ['invoice_date', '>=', $dateFrom];
            $domainDraft[] = ['invoice_date', '>=', $dateFrom];
        }
        if ($dateTo) {
            $domainEmpty[] = ['invoice_date', '<=', $dateTo];
            $domainDraft[] = ['invoice_date', '<=', $dateTo];
        }

        try {
            $resEmpty = $this->execute('rental.period.invoice', 'read_group', [
                $domainEmpty,
                ['rental_order_id', 'invoice_date:min'],
                ['rental_order_id']
            ]);
            $resDraft = $this->execute('rental.period.invoice', 'read_group', [
                $domainDraft,
                ['rental_order_id', 'invoice_date:min'],
                ['rental_order_id']
            ]);

            $soIds = [];
            foreach (array_merge($resEmpty, $resDraft) as $group) {
                if (!empty($group['rental_order_id'][0])) {
                    $soIds[] = $group['rental_order_id'][0];
                }
            }

            return array_values(array_unique($soIds));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Step 2: Fetch full Uninvoiced Rental data for a specific chunk of SO IDs
     */
    public function fetchUninvoicedRentalsBySoIds(array $soIds): array
    {
        $domainEmpty = [
            ['invoice_id', '=', false],
            ['rental_order_id', 'in', $soIds]
        ];

        $domainDraft = [
            ['invoice_id.state', '=', 'draft'],
            ['rental_order_id', 'in', $soIds]
        ];

        // Fetch periods
        $periodIdsEmpty = $this->execute('rental.period.invoice', 'search', [$domainEmpty]);
        $periodIdsDraft = $this->execute('rental.period.invoice', 'search', [$domainDraft]);

        $periodIds = array_values(array_unique(array_merge($periodIdsEmpty, $periodIdsDraft)));
        if (empty($periodIds)) {
            return [];
        }

        $fields = [
            'rental_order_id',
            'invoice_date',
            'price_unit',
            'rental_uom',
            'lot_id',
            'product_id',
            'invoice_id',
            'start_rental_period_date',
            'end_rental_period_date',
        ];

        $periods = $this->execute('rental.period.invoice', 'read', [$periodIds, $fields]);

        // Group by rental_order_id and find the earliest invoice_date
        $grouped = [];
        foreach ($periods as $period) {
            $soId = $period['rental_order_id'][0] ?? null;
            if (!$soId)
                continue;

            if (!isset($grouped[$soId]) || $period['invoice_date'] < $grouped[$soId]['invoice_date']) {
                $grouped[$soId] = $period;
            }
        }

        $earliestPeriods = array_values($grouped);
        $soIdsToFetch = array_keys($grouped);

        // Fetch SO Data
        $soFields = [
            'name',
            'client_order_ref',
            'rental_contract_id',
            'customer_name',
            'actual_start_rental',
            'actual_end_rental',
            'payment_term_id',
            'area_id',
            'partner_invoice_id',
            'first_invoice_date',
            'rental_method',
            'partner_id',
            'sale_invoice_period_id',
            'order_line',
        ];

        $soData = $this->execute('sale.order', 'read', [$soIdsToFetch, $soFields]);
        $soMap = [];
        $partnerIdsToFetch = [];
        foreach ($soData as $so) {
            $soMap[$so['id']] = $so;
            if (!empty($so['partner_id'][0])) {
                $partnerIdsToFetch[] = $so['partner_id'][0];
            }
        }

        $productIdsToFetch = [];
        foreach ($earliestPeriods as $period) {
            if (!empty($period['product_id'][0])) {
                $productIdsToFetch[] = $period['product_id'][0];
            }
        }

        $partnerIdsToFetch = array_values(array_unique($partnerIdsToFetch));
        $productIdsToFetch = array_values(array_unique($productIdsToFetch));

        // Fetch Partner Data
        $partnerFields = [
            'partner_bank_id',
            'vat',
            'l10n_id_tku',
            'tku_number',
            'l10n_id_kode_transaksi',
            'contact_address',
            'contact_address_complete',
            'l10n_id_tax_address',
            'ref',
            'hrc_forminv_invoice_pic'
        ];
        $partnerData = [];
        if (!empty($partnerIdsToFetch)) {
            $partnerData = $this->execute('res.partner', 'read', [$partnerIdsToFetch, $partnerFields]);
        }
        $partnerMap = [];
        foreach ($partnerData as $p) {
            $partnerMap[$p['id']] = $p;
        }

        // Fetch Lot Data
        $lotIdsToFetch = [];
        foreach ($earliestPeriods as $period) {
            if (!empty($period['lot_id'][0])) {
                $lotIdsToFetch[] = $period['lot_id'][0];
            }
        }
        $lotIdsToFetch = array_values(array_unique($lotIdsToFetch));

        $lotFields = [
            'name',
            'ref',
            'vehicle_year'
        ];
        $lotData = [];
        if (!empty($lotIdsToFetch)) {
            $lotData = $this->execute('stock.lot', 'read', [$lotIdsToFetch, $lotFields]);
        }
        $lotMap = [];
        foreach ($lotData as $lot) {
            $lotMap[$lot['id']] = $lot;
        }

        // Fetch Product Data (if product_id is needed for Model)
        // No longer needed, we map directly from $period['product_id'][1]

        // Fetch Contract Data
        $contractIdsToFetch = [];
        foreach ($soData as $so) {
            if (!empty($so['rental_contract_id'][0])) {
                $contractIdsToFetch[] = $so['rental_contract_id'][0];
            }
        }
        $contractIdsToFetch = array_values(array_unique($contractIdsToFetch));

        $contractFields = ['reference'];
        $contractData = [];
        if (!empty($contractIdsToFetch)) {
            $contractData = $this->execute('rental.contract', 'read', [$contractIdsToFetch, $contractFields]);
        }
        $contractMap = [];
        foreach ($contractData as $c) {
            $contractMap[$c['id']] = $c;
        }

        // Fetch Order Line Data for Duration Price
        $orderLineIdsToFetch = [];
        foreach ($soData as $so) {
            if (!empty($so['order_line'])) {
                $orderLineIdsToFetch = array_merge($orderLineIdsToFetch, $so['order_line']);
            }
        }
        $orderLineIdsToFetch = array_values(array_unique($orderLineIdsToFetch));

        $lineData = [];
        if (!empty($orderLineIdsToFetch)) {
            $lineData = $this->execute('sale.order.line', 'read', [$orderLineIdsToFetch, ['product_id', 'duration_price', 'order_id']]);
        }
        $durationPriceMap = [];
        foreach ($lineData as $line) {
            $soId = $line['order_id'][0] ?? null;
            $productName = $line['product_id'][1] ?? null;
            if ($soId && $productName) {
                if (!isset($durationPriceMap[$soId])) {
                    $durationPriceMap[$soId] = [];
                }
                $durationPriceMap[$soId][$productName] = $line['duration_price'] ?? 0;
            }
        }

        // Fetch account.move.line data to get Duration Price directly from the Invoices
        $invoiceIdsToFetch = [];
        foreach ($earliestPeriods as $period) {
            if (!empty($period['invoice_id'][0])) {
                $invoiceIdsToFetch[] = $period['invoice_id'][0];
            }
        }
        $invoiceIdsToFetch = array_values(array_unique($invoiceIdsToFetch));

        $invoiceLineData = [];
        if (!empty($invoiceIdsToFetch)) {
            $invoiceLineData = $this->execute('account.move.line', 'search_read', [
                [['move_id', 'in', $invoiceIdsToFetch], ['display_type', '=', 'product']],
                ['move_id', 'product_id', 'duration_price']
            ]);
        }
        
        $invoiceDurationPriceMap = [];
        foreach ($invoiceLineData as $line) {
            $moveId = $line['move_id'][0] ?? null;
            $productName = $line['product_id'][1] ?? null;
            if ($moveId && $productName) {
                if (!isset($invoiceDurationPriceMap[$moveId])) {
                    $invoiceDurationPriceMap[$moveId] = [];
                }
                $invoiceDurationPriceMap[$moveId][$productName] = $line['duration_price'] ?? 0;
            }
        }

        // Assemble final output
        $results = [];
        foreach ($earliestPeriods as $period) {
            $soId = $period['rental_order_id'][0] ?? null;
            $so = $soMap[$soId] ?? [];

            $partnerId = $so['partner_id'][0] ?? null;
            $partner = $partnerMap[$partnerId] ?? [];

            $lotId = $period['lot_id'][0] ?? null;
            $lot = $lotMap[$lotId] ?? [];

            // Extract Kode Cust (Now displaying full customer name)
            $kodeCust = $so['partner_id'][1] ?? '';

            $results[] = [
                'kode_cust' => $kodeCust === false ? '' : $kodeCust,
                'nomor_so' => ($so['name'] ?? '') === false ? '' : ($so['name'] ?? ''),
                'nomor_po' => ($so['client_order_ref'] ?? '') === false ? '' : ($so['client_order_ref'] ?? ''),
                'nomor_kontrak' => ($so['rental_contract_id'][1] ?? '') === false ? '' : ($so['rental_contract_id'][1] ?? ''),
                'kontrak_ref' => ($contractMap[$so['rental_contract_id'][0] ?? null]['reference'] ?? '') === false ? '' : ($contractMap[$so['rental_contract_id'][0] ?? null]['reference'] ?? ''),
                'nama_user' => ($so['customer_name'] ?? '') === false ? '' : ($so['customer_name'] ?? ''),

                'nopol' => $lot['name'] ?? '',
                'model' => (function ($str) {
                    if (preg_match('/\[(.*?)\]/', $str, $matches)) {
                        return str_replace('-', '', $matches[1]);
                    }
                    return str_replace('-', '', $str);
                })($period['product_id'][1] ?? ''),
                'tahun_mobil' => $lot['vehicle_year'] ?? '',
                'chassis' => $lot['ref'] ?? '',

                'start' => !empty($so['actual_start_rental']) ? \Carbon\Carbon::parse($so['actual_start_rental'], 'UTC')->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '',
                'end' => !empty($so['actual_end_rental']) ? \Carbon\Carbon::parse($so['actual_end_rental'], 'UTC')->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '',
                'tanggal_periode_belum_cetak' => $period['invoice_date'] ?? '',
                'start_rental_period' => !empty($period['start_rental_period_date']) ? \Carbon\Carbon::parse($period['start_rental_period_date'], 'UTC')->format('Y-m-d') : '',
                'end_rental_period' => !empty($period['end_rental_period_date']) ? \Carbon\Carbon::parse($period['end_rental_period_date'], 'UTC')->format('Y-m-d') : '',
                'price_di_so' => $period['price_unit'] ?? 0,
                'duration_price' => $invoiceDurationPriceMap[$period['invoice_id'][0] ?? null][$period['product_id'][1] ?? null] ?? 0,
                'invoice_period' => $so['sale_invoice_period_id'][1] ?? '',
                'payment_terms' => $so['payment_term_id'][1] ?? '',
                'rental_method' => ucwords(str_replace('_', ' ', $so['rental_method'] ?? '')),
                'first_invoice_date' => $so['first_invoice_date'] ?? '',

                'area_pemakaian_unit' => $so['area_id'][1] ?? '',
                'invoice_pic' => $partner['hrc_forminv_invoice_pic'][1] ?? '',
                'recipient_bank' => $partner['partner_bank_id'][1] ?? '',
                'tax_id' => $partner['vat'] ?? '',
                'id_tku' => $partner['tku_number'] ?? '',
                'kode_transaksi' => $partner['l10n_id_kode_transaksi'] ?? '',
                'address' => $partner['contact_address'] ?? '',
                'tax_address' => $partner['l10n_id_tax_address'] ?? '',
            ];
        }

        return $results;
    }
}
