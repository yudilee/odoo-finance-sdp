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
                'partner_id/display_name',         // 4: Partner
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
                            'amount_total_signed' => (float)($row[6] ?? 0),
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
                            'debit' => (float)($row[10] ?? 0),
                            'credit' => (float)($row[11] ?? 0),
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
                            $allReconcileIds[] = (int)$line['reconcile_id'];
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
                        if (empty($currentRecIds)) break;
                        
                        $linkedLines = $this->execute('account.move.line', 'search_read', [
                            [['full_reconcile_id', 'in', $currentRecIds]],
                            ['full_reconcile_id', 'move_name', 'move_id']
                        ]);

                        if (!is_array($linkedLines)) break;

                        $nextRecIds = [];
                        $moveIdsToCheck = []; // move_id => [parent_rec_id, ...]

                        foreach ($linkedLines as $ll) {
                            $recIdFull = $ll['full_reconcile_id'] ?? null;
                            $recId = is_array($recIdFull) ? $recIdFull[0] : (int)$recIdFull;
                            
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
                                    $nextId = is_array($nextIdFull) ? $nextIdFull[0] : (int)$nextIdFull;
                                    $mId = $ol['move_id'][0] ?? null;
                                    
                                    if ($nextId && !in_array($nextId, $processedRecIds) && !in_array($nextId, $currentRecIds)) {
                                        $nextRecIds[] = $nextId;
                                        // Pass the ancestors down
                                        $ancestors = $moveIdsToCheck[$mId] ?? [];
                                        foreach ($ancestors as $aid) {
                                            if (!isset($recRelationships[$nextId])) $recRelationships[$nextId] = [];
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
                                $rid = (int)$line['reconcile_id'];
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
    public function fetchInvoiceDrivers(string $dateFrom, string $dateTo): array
    {
        try {
            // Search account.move where journal is "Invoice Driver" and state is posted
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', '=', 'Invoice Driver'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];

            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No invoice driver entries found.'];
            }

            $exportFields = [
                'name',                                  // 0: Invoice number (INVDV/...)
                'partner_id/name',                       // 1: Customer name
                'invoice_date',                          // 2: Invoice date
                'invoice_payment_term_id/name',          // 3: Payment terms
                'ref',                                   // 4: Customer Reference
                'journal_id/name',                       // 5: Journal name
                'amount_untaxed',                        // 6: Subtotal
                'amount_tax',                            // 7: Tax
                'amount_total',                          // 8: Total
                'invoice_line_ids/name',                 // 9: Line description
                'invoice_line_ids/quantity',              // 10: Line qty
                'invoice_line_ids/price_unit',            // 11: Line unit price
                'partner_bank_id/acc_number',            // 12: Bank account number
                'bc_manager_id/name',                    // 13: Manager name
                'bc_spv_id/name',                        // 14: Supervisor name
                'partner_id/contact_address',            // 15: Address (multiline)
                'partner_id/contact_address_complete',   // 16: Address (single line)
                'narration',                             // 17: Terms and Condition
                'partner_id/vat',                        // 18: NPWP
                'contract_ref',                          // 19: Contract Ref
                'invoice_line_ids/sale_order_id/rental_contract_id/name', // 20: Path A: Contract from Line
                'rental_period_id/rental_order_id/rental_contract_id/name', // 21: Path B: Contract from Period
                'invoice_date_due',                      // 22: Due date
                'invoice_line_ids/duration_price',       // 23: Duration Price
            ];

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
                    $invoiceName = $row[0] ?? '';

                    // If name is non-empty, this is a new entry header row
                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null) {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[22] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Driver',
                            'amount_untaxed' => (float)($row[6] ?? 0),
                            'amount_tax' => (float)($row[7] ?? 0),
                            'amount_total' => (float)($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'narration' => $row[17] ?? '',
                            'partner_npwp' => $row[18] ?? '',
                            'contract_ref' => !empty($row[19]) ? $row[19] : (!empty($row[20]) ? $row[20] : ($row[21] ?? '')),
                            'lines' => [],
                        ];
                    }

                    // Add line item
                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float)($row[10] ?? 0);
                        $linePrice = (float)($row[11] ?? 0);

                        if (empty($currentEntry['contract_ref'])) {
                            if (!empty($row[20])) {
                                $currentEntry['contract_ref'] = $row[20];
                            } elseif (!empty($row[21])) {
                                $currentEntry['contract_ref'] = $row[21];
                            }
                        }

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'duration_price' => (float)($row[23] ?? 0),
                            ];
                        }
                    }
                }

                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
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
     * Fetch Invoice Other entries from Odoo using export_data
     * Fetches both "Invoice Other with Tax" and "Invoice Other wo Tax" journals
     */
    public function fetchInvoiceOthers(string $dateFrom, string $dateTo): array
    {
        try {
            // Search account.move where journal is Invoice Other (with or without tax) and state is posted
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', 'in', ['Invoice Other with Tax', 'Invoice Other wo Tax']],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];

            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No invoice other entries found.'];
            }

            // Same export fields as Invoice Driver
            $exportFields = [
                'name',                                  // 0: Invoice number (INVOT/... or INVOW/...)
                'partner_id/name',                       // 1: Customer name
                'invoice_date',                          // 2: Invoice date
                'invoice_payment_term_id/name',          // 3: Payment terms
                'ref',                                   // 4: Customer Reference
                'journal_id/name',                       // 5: Journal name
                'amount_untaxed',                        // 6: Subtotal
                'amount_tax',                            // 7: Tax
                'amount_total',                          // 8: Total
                'invoice_line_ids/name',                 // 9: Line description
                'invoice_line_ids/quantity',              // 10: Line qty
                'invoice_line_ids/price_unit',            // 11: Line unit price
                'partner_bank_id/acc_number',            // 12: Bank account number
                'bc_manager_id/name',                    // 13: Manager name
                'bc_spv_id/name',                        // 14: Supervisor name
                'partner_id/contact_address',            // 15: Address (multiline)
                'partner_id/contact_address_complete',   // 16: Address (single line)
                'narration',                             // 17: Terms and Condition
                'partner_id/vat',                        // 18: NPWP
                'contract_ref',                          // 19: Contract Ref
                'invoice_line_ids/sale_order_id/rental_contract_id/name', // 20: Path A: Contract from Line
                'rental_period_id/rental_order_id/rental_contract_id/name', // 21: Path B: Contract from Period
                'invoice_date_due',                      // 22: Due date
                'invoice_line_ids/duration_price',       // 23: Duration Price
            ];

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
                    $invoiceName = $row[0] ?? '';

                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null) {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[22] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Other',
                            'amount_untaxed' => (float)($row[6] ?? 0),
                            'amount_tax' => (float)($row[7] ?? 0),
                            'amount_total' => (float)($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'narration' => $row[17] ?? '',
                            'partner_npwp' => $row[18] ?? '',
                            'contract_ref' => !empty($row[19]) ? $row[19] : (!empty($row[20]) ? $row[20] : ($row[21] ?? '')),
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float)($row[10] ?? 0);
                        $linePrice = (float)($row[11] ?? 0);

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[20])) {
                                    $currentEntry['contract_ref'] = $row[20];
                                } elseif (!empty($row[21])) {
                                    $currentEntry['contract_ref'] = $row[21];
                                }
                            }
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'duration_price' => (float)($row[23] ?? 0),
                            ];
                        }
                    }
                }

                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
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
     * Fetch Invoice Vehicle (Penjualan Kendaraan) entries from Odoo using export_data
     * Journal: Invoice Penjualan Kendaraan (INVCR)
     */
    public function fetchInvoiceVehicles(string $dateFrom, string $dateTo): array
    {
        try {
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', '=', 'Invoice Used Car'],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];

            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No invoice vehicle entries found.'];
            }

            $exportFields = [
                'name',                                  // 0: Invoice number (INVCR/...)
                'partner_id/name',                       // 1: Customer name
                'invoice_date',                          // 2: Invoice date
                'invoice_payment_term_id/name',          // 3: Payment terms
                'ref',                                   // 4: Customer Reference
                'journal_id/name',                       // 5: Journal name
                'amount_untaxed',                        // 6: Subtotal
                'amount_tax',                            // 7: Tax
                'amount_total',                          // 8: Total
                'invoice_line_ids/name',                 // 9: Line description
                'invoice_line_ids/quantity',              // 10: Line qty
                'invoice_line_ids/price_unit',           // 11: Line unit price
                'partner_bank_id/acc_number',            // 12: Bank account number
                'bc_manager_id/name',                    // 13: Manager name
                'bc_spv_id/name',                        // 14: Supervisor name
                'partner_id/contact_address',            // 15: Address (multiline)
                'partner_id/contact_address_complete',   // 16: Address (single line)
                'invoice_line_ids/product_id/name',      // 17: Product name (vehicle model)
                'invoice_line_ids/serial_ids/name',         // 18: Serial = No Polisi
                'partner_id/vat',                        // 19: NPWP
                'narration',                             // 20: Terms and Condition
                'contract_ref',                          // 21: Contract Ref
                'invoice_line_ids/sale_order_id/rental_contract_id/name', // 22: Path A: Contract from Line
                'rental_period_id/rental_order_id/rental_contract_id/name', // 23: Path B: Contract from Period
                'invoice_date_due',                      // 24: Due date
                'invoice_line_ids/duration_price',       // 25: Duration Price
            ];

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
                    $invoiceName = $row[0] ?? '';

                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null) {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[24] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Penjualan Kendaraan',
                            'amount_untaxed' => (float)($row[6] ?? 0),
                            'amount_tax' => (float)($row[7] ?? 0),
                            'amount_total' => (float)($row[8] ?? 0),
                            'partner_bank' => $row[12] ?? '',
                            'manager_name' => $row[13] ?? '',
                            'spv_name' => $row[14] ?? '',
                            'partner_address' => $row[15] ?? '',
                            'partner_address_complete' => $row[16] ?? '',
                            'partner_npwp' => $row[19] ?? '',
                            'narration' => $row[20] ?? '',
                            'contract_ref' => !empty($row[21]) ? $row[21] : (!empty($row[22]) ? $row[22] : ($row[23] ?? '')),
                            'lines' => [],
                        ];
                    }

                    if ($currentEntry !== null) {
                        $lineDesc = $row[9] ?? '';
                        $lineQty = (float)($row[10] ?? 0);
                        $linePrice = (float)($row[11] ?? 0);
                        $productName = $row[17] ?? '';
                        $licensePlate = $row[18] ?? '';

                        // Extract serial/VIN from description if available
                        // Description typically contains: "Penjualan Kend.MHKM5FA4JLK062083"
                        $serialNumber = '';
                        if (preg_match('/Kend\.?\s*([A-Z0-9]+)/i', $lineDesc, $matches)) {
                            $serialNumber = $matches[1];
                        }

                        if (!empty($lineDesc) || $lineQty > 0 || $linePrice > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[22])) {
                                    $currentEntry['contract_ref'] = $row[22];
                                } elseif (!empty($row[23])) {
                                    $currentEntry['contract_ref'] = $row[23];
                                }
                            }
                            $currentEntry['lines'][] = [
                                'description' => $lineDesc,
                                'quantity' => $lineQty,
                                'price_unit' => $linePrice,
                                'product_name' => $productName,
                                'license_plate' => $licensePlate,
                                'serial_number' => $serialNumber,
                                'duration_price' => (float)($row[25] ?? 0),
                            ];
                        }
                    }
                }

                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
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
                    'data'    => [],
                    'count'   => 0,
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
                'duration_price',                            // 19: Duration Price
            ];

            $entries    = [];
            $chunkSize  = 500;
            $idChunks   = array_chunk($allIds, $chunkSize);

            foreach ($idChunks as $chunk) {
                $result = $this->execute('rental.period.invoice', 'export_data', [$chunk, $exportFields]);

                if (!isset($result['datas'])) {
                    continue;
                }

                foreach ($result['datas'] as $row) {
                    $rawId       = $row[0]  ?? '';
                    $invoiceRef  = $row[12] ?? '';   // full display e.g. "INVRS/2025/03192 (refs...)"
                    $invoiceName = $row[15] ?? '';   // clean name e.g. "INVRS/2025/03192"
                    $rentalStatus = $row[3]  ?? null;
                    $invoiceState = $row[13] ?? null;
                    $priceUnit    = (float)($row[16] ?? 0);
                    $invoiceAmount = (float)($row[18] ?? 0);

                    // Skip cancelled rentals
                    if ($rentalStatus === 'cancel' || $rentalStatus === 'cancelled') continue;

                    // Skip if it has an invoice, but the Invoice Price is 0.
                    // If it has NO invoice (Not Invoiced), we keep it.
                    if (!empty($invoiceName) && $invoiceAmount == 0) continue;

                    // Parse numeric ID from external ID string (e.g. __export__.rental_period_invoice_1903_hash)
                    $numericId = null;
                    if (preg_match('/rental_period_invoice_(\d+)_/', $rawId, $m)) {
                        $numericId = (int)$m[1];
                    }

                    $subStart = $row[5] ?? null;
                    $subEnd = $row[6] ?? null;

                    $entries[] = [
                        'period_odoo_id'      => $rawId,
                        'period_numeric_id'   => $numericId,
                        'so_name'             => $row[1] ?? '',
                        'partner_name'        => $row[2] ?? '',
                        'rental_status'       => $rentalStatus,
                        'rental_type'         => $row[4] ?? 'Subscription',
                        'actual_start_rental' => $subStart,
                        'actual_end_rental'   => $subEnd,
                        'period_type'         => $row[7] ?? '',
                        'product_name'        => $row[8] ?? '',
                        'invoice_date'        => $row[9] ?? null,
                        'period_start'        => $row[10] ?? null,
                        'period_end'          => $row[11] ?? null,
                        'invoice_ref'         => $invoiceRef,
                        'invoice_name'        => $invoiceName,
                        'invoice_state'       => $invoiceState,
                        'payment_state'       => $row[14] ?? null,
                        'price_unit'          => $priceUnit,
                        'duration_price'      => (float)($row[19] ?? 0),
                        'invoice_amount'      => $invoiceAmount,
                        'rental_uom'          => $row[17] ?? '',
                    ];
                }
            }

            return [
                'success' => true,
                'data'    => $entries,
                'count'   => count($entries),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Fetch failed: ' . $e->getMessage(),
                'data'    => [],
            ];
        }
    }

    /**
     * Fetch Invoice Rental entries from Odoo using export_data
     * Fetches both "Invoice Sewa Retail" and "Invoice Sewa Subscription" journals
     */
    public function fetchInvoiceRentals(string $dateFrom, string $dateTo): array
    {
        try {
            // Search account.move where journal is Invoice Sewa... and state is posted
            $domain = [
                ['state', '=', 'posted'],
                ['journal_id.name', 'in', ['Invoice Sewa Retail', 'Invoice Sewa Subscription']],
                ['invoice_date', '>=', $dateFrom],
                ['invoice_date', '<=', $dateTo],
            ];

            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No invoice rental entries found.'];
            }

            // Exactly matching the Excel columns provided:
            $exportFields = [
                'name',                                                   // 0: Invoice number (INVRS/... or INVRT/...)
                'partner_id/name',                                        // 1: Customer name
                'invoice_date',                                           // 2: Invoice date
                'invoice_payment_term_id/name',                           // 3: Payment terms
                'ref',                                                    // 4: Reference
                'journal_id/name',                                        // 5: Journal name
                'amount_untaxed',                                         // 6: Subtotal
                'amount_tax',                                             // 7: Tax
                'amount_total',                                           // 8: Total
                'invoice_line_ids/sale_order_id/name',                       // 9: Sale order id name
                'invoice_line_ids/name',                                  // 10: Line description
                'invoice_line_ids/serial_ids/name',                       // 11: Serial number
                'invoice_line_ids/start_rental_period',                      // 12: Actual start rental
                'invoice_line_ids/end_rental_period',                        // 13: Actual end rental
                'invoice_line_ids/sale_order_id/rental_uom',                 // 14: Rental uom
                'invoice_line_ids/quantity',                               // 15: Quantity
                'invoice_line_ids/price_unit',                             // 16: Price unit
                'invoice_line_ids/sale_order_id/customer_name',              // 17: Customer Name (username)
                'partner_bank_id/acc_number',                             // 18: Bank account number
                'bc_manager_id/name',                                     // 19: Manager name
                'bc_spv_id/name',                                         // 20: Supervisor name
                'partner_id/contact_address',                             // 21: Address (multiline)
                'partner_id/contact_address_complete',                    // 22: Address (single line)
                'narration',                                              // 23: Terms and Condition
                'invoice_line_ids/sale_order_id/rental_contract_id/name', // 24: Path A: Contract from Line
                'rental_period_id/rental_order_id/rental_contract_id/name', // 25: Path B: Contract from Period
                'invoice_date_due',                                       // 26: Due date
                'partner_id/vat',                                         // 27: NPWP
                'invoice_line_ids/rental_qty',                            // 28: Rental Qty
                'invoice_line_ids/rental_uom',                            // 29: Rental UOM (from line directly)
                'invoice_line_ids/duration_price',                        // 30: Duration Price
                'invoice_line_ids/product_id/name',                       // 31: Product Name
                'invoice_line_ids/sale_order_id/actual_start_rental',     // 32: Actual Start (with time)
                'invoice_line_ids/sale_order_id/actual_end_rental',       // 33: Actual End (with time)
            ];

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
                    $invoiceName = $row[0] ?? '';

                    if (!empty($invoiceName)) {
                        if ($currentEntry !== null) {
                            $entries[] = $currentEntry;
                        }
                        $currentEntry = [
                            'name' => $invoiceName,
                            'partner_name' => $row[1] ?? '',
                            'invoice_date' => $row[2] ?? '',
                            'invoice_date_due' => $row[26] ?? '',
                            'payment_term' => $row[3] ?? '',
                            'ref' => $row[4] ?? '',
                            'journal_name' => $row[5] ?? 'Invoice Rental',
                            'amount_untaxed' => (float)($row[6] ?? 0),
                            'amount_tax' => (float)($row[7] ?? 0),
                            'amount_total' => (float)($row[8] ?? 0),
                            'partner_bank' => $row[18] ?? '',
                            'manager_name' => $row[19] ?? '',
                            'spv_name' => $row[20] ?? '',
                            'partner_address' => $row[21] ?? '',
                            'partner_address_complete' => $row[22] ?? '',
                            'narration' => $row[23] ?? '',
                            'contract_ref' => !empty($row[24]) ? $row[24] : ($row[25] ?? ''),
                            'partner_npwp' => $row[27] ?? '',
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
                        $qty = (float)($row[15] ?? 0);
                        $rentalQty = (float)($row[28] ?? 0);
                        $priceUnit = (float)($row[16] ?? 0);
                        $customerName = !empty($row[17]) ? $row[17] : ($currentEntry['partner_name'] ?? '');
                        $productName = $row[31] ?? '';

                        // Pull exact time from Rental Order if available, otherwise fallback to move line date
                        $actualStart = !empty($row[32]) ? $row[32] : ($row[12] ?? '');
                        $actualEnd = !empty($row[33]) ? $row[33] : ($row[13] ?? '');

                        // Prepend product name to description if it's not already there, so we can filter by it
                        if (!empty($productName) && !str_contains(strtolower($lineDesc), strtolower($productName))) {
                            // If it's something like "Lain-Lain (inv)", we format it nicely
                            $lineDesc = $productName . "\n" . $lineDesc;
                        }

                        if (!empty($lineDesc) || $qty > 0 || $priceUnit > 0) {
                            if (empty($currentEntry['contract_ref'])) {
                                if (!empty($row[24])) {
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
                                'duration_price' => (float)($row[30] ?? 0),
                                'customer_name' => $customerName,
                            ];
                        }
                    }
                }

                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
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

        $this->uid = is_numeric($result) ? (int)$result : null;
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
        if (is_null($value))  return '<value><nil/></value>';
        if (is_bool($value))  return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        if (is_int($value))   return '<value><int>' . $value . '</int></value>';
        if (is_float($value)) return '<value><double>' . $value . '</double></value>';
        
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
        
        return '<value><string>' . htmlspecialchars((string)$value) . '</string></value>';
    }

    protected function isAssoc(array $arr): bool
    {
        if (empty($arr)) return false;
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
            return (int)($valueNode->int ?? $valueNode->i4);
        if (isset($valueNode->boolean))
            return (string)$valueNode->boolean === '1';
        if (isset($valueNode->string))
            return (string)$valueNode->string;
        if (isset($valueNode->double))
            return (float)$valueNode->double;
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
                    $name = (string)$member->name;
                    $result[$name] = $this->decodeValue($member->value);
                }
            }
            return $result;
        }

        return (string)$valueNode;
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
}
