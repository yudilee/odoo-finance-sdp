<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice Rental</title>
    <style>
        @page {
            margin: 30px 40px 60px 40px;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-page {
            page-break-after: always;
        }
        .invoice-page:last-child {
            page-break-after: auto;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(255, 0, 0, 0.15);
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
            z-index: -1;
        }

        /* Company Header */
        .company-header {
            margin-bottom: 5px;
        }
        .company-header td {
            vertical-align: top;
        }
        .company-name {
            font-size: 11px;
            font-weight: bold;
        }
        .company-address {
            font-size: 9px;
            color: #334155;
        }
        .invoice-title {
            font-size: 22px;
            font-weight: bold;
            text-align: right;
            color: #1a237e;
        }
        .page-label {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }

        /* Invoice Info */
        .info-section {
            margin-bottom: 15px;
            margin-top: 10px;
        }
        .info-section td {
            vertical-align: top;
            padding: 2px 0;
            font-size: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #1c3254;
            width: 75px;
        }
        .info-colon {
            width: 10px;
            text-align: center;
        }
        .customer-name {
            font-weight: bold;
            font-size: 11px;
        }
        .customer-address {
            font-size: 9px;
            color: #475569;
            padding-top: 3px;
        }

        /* Lines Table */
        .lines-table {
            border: none;
            margin-top: 0;
        }
        .table-header-row th {
            border-top: 2px solid #1e293b;
            border-bottom: 1px solid #475569;
        }
        .lines-table th {
            text-align: left;
            padding: 6px 5px;
            font-weight: bold;
            font-size: 10px;
            color: #1e293b;
            background-color: #f8fafc;
        }
        .lines-table td {
            padding: 5px;
            font-size: 10px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .lines-table .col-no { width: 5%; text-align: center; }
        .lines-table .col-desc { width: 55%; }
        .lines-table .col-unit { width: 10%; text-align: center; }
        .lines-table .col-price { width: 15%; text-align: right; }
        .lines-table .col-amount { width: 15%; text-align: right; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Separator */
        .dashed-line {
            border: none;
            border-top: 1px dashed #94a3b8;
            margin: 10px 0;
        }

        /* Totals */
        .totals-table {
            margin-top: 8px;
        }
        .totals-table td {
            font-size: 10px;
            padding: 3px 5px;
        }
        .total-row {
            border-top: 1px solid #1e293b;
            border-bottom: 2px solid #1e293b;
        }
        .total-row td {
            font-weight: bold;
            font-size: 11px;
            padding: 6px 5px;
        }

        /* Terbilang */
        .terbilang-section {
            border-top: 1px solid #94a3b8;
            margin-top: 10px;
            padding-top: 6px;
            font-size: 9px;
            font-style: italic;
        }
        .terbilang-label {
            font-weight: bold;
            font-style: normal;
        }

        /* Notes / Catatan */
        .catatan-container {
            margin-top: 10px;
            width: 55%;
        }
        .catatan-box {
            border: 1px solid #1e293b;
            padding: 8px;
            min-height: 40px;
        }
        .catatan-label {
            font-weight: bold;
            font-size: 9px;
            text-decoration: underline;
            display: block;
            margin-bottom: 4px;
        }
        .catatan-content {
            font-size: 10px;
            color: #1e293b;
            font-family: inherit;
        }

        /* Signatures */
        .signature-table {
            margin-top: 30px;
        }
        .signature-table td {
            text-align: center;
            vertical-align: bottom;
            font-size: 10px;
            padding: 5px 10px;
            width: 33.33%;
        }
        .signature-name {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: none;
            margin-top: 30px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: -40px;
            height: 30px;
            left: 0;
            right: 0;
            font-size: 11px;
            color: #1e293b;
            border-top: 2px solid #000;
            padding-top: 5px;
            text-align: center;
        }

        /* Ketentuan */
        .ketentuan-section {
            margin-top: 10px;
            font-size: 9px;
        }
        .ketentuan-title {
            font-weight: bold;
            font-size: 10px;
            text-decoration: underline;
            margin-bottom: 3px;
        }
        .ketentuan-content {
            color: #334155;
        }

        .page-label {
            text-align: right;
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="footer">SDP/FR/BC/02, Rev.01</div>
    @foreach($invoices as $invoice)
    <div class="invoice-page" style="{{ $loop->last ? 'page-break-after: auto;' : 'page-break-after: always;' }}">
        <script type="text/php">
            $GLOBALS['invoice_starts']['{{ $invoice->name }}'] = $pdf->get_page_number();
            $GLOBALS['invoice_pics']['{{ $invoice->name }}'] = '{{ $invoice->invoice_pic ? strtoupper(substr(trim($invoice->invoice_pic), 0, 3)) : "" }}';
            $GLOBALS['invoice_prints']['{{ $invoice->name }}'] = '{{ str_pad(($invoice->print_count ?? 0) + 1, 2, "0", STR_PAD_LEFT) }}';
        </script>
        @php
            // Fallback to app settings if Odoo fields are empty
            $managerName = !empty($invoice->manager_name)
                ? $invoice->manager_name
                : ($defaultManager ?? '');
            $spvName = !empty($invoice->spv_name)
                ? $invoice->spv_name
                : ($defaultSpv ?? '');

            // Categorize lines
            $allLines = $invoice->lines;

            // Extract pure note lines first (qty=0 and price=0)
            $noteLines = $allLines->filter(fn($l) => 
                $l->quantity == 0 && $l->price_unit == 0 && !empty($l->description)
            );

            $discountLines = $allLines->filter(fn($l) => 
                !$noteLines->contains('id', $l->id) && (
                    str_contains(strtolower($l->description), 'discount') || 
                    str_contains(strtolower($l->description), 'potongan') ||
                    $l->price_unit < 0
                )
            );
            $roundingLines = $allLines->filter(fn($l) => 
                !$noteLines->contains('id', $l->id) &&
                !$discountLines->contains('id', $l->id) && (
                    str_contains(strtolower($l->description), 'pembulatan') || 
                    str_contains(strtolower($l->description), 'rounding') ||
                    str_contains(strtolower($l->description), '(inv)') ||
                    str_contains(strtolower($l->description), 'driver') ||
                    str_contains(strtolower($l->description), 'lain-lain')
                )
            );
            $pphLines = $allLines->filter(fn($l) => 
                !$noteLines->contains('id', $l->id) && (
                    str_contains(strtolower($l->description), 'pph 2%') || 
                    str_contains(strtolower($l->description), 'pph 2 %')
                )
            );
            
            // Extract DPP Lainnya for specific customers
            $dppLainnyaText = null;
            $dppLainnyaAmount = null;
            $partnerNameLower = strtolower($invoice->partner_name ?? '');
            $isSpecialCustomer = str_contains($partnerNameLower, 'pln indonesia power ubp cilegon') || 
                                 str_contains($partnerNameLower, 'control systems arena paranusa') ||
                                 str_contains($partnerNameLower, 'control systems arena para nusa');
            
            if (isset($printMode) && $printMode === 'summary' && $isSpecialCustomer) {
                $dppLine = $noteLines->first(fn($l) => str_starts_with(strtolower(trim($l->description)), 'dpp lainnya'));
                if ($dppLine) {
                    $parts = explode(':', $dppLine->description);
                    if (count($parts) > 1) {
                        $dppLainnyaText = trim($parts[0]);
                        $dppLainnyaAmount = trim($parts[1]);
                    } else {
                        // Fallback if there is no colon
                        $dppLainnyaText = 'DPP Lainnya';
                        $dppLainnyaAmount = trim(str_ireplace('dpp lainnya', '', strtolower($dppLine->description)));
                    }
                    // Remove it from noteLines so it doesn't appear in the main description or notes
                    $noteLines = $noteLines->reject(fn($l) => $l->id === $dppLine->id);
                }
            }

            $rentalLines = $allLines->reject(fn($l) => 
                $discountLines->contains('id', $l->id) || 
                $roundingLines->contains('id', $l->id) ||
                $pphLines->contains('id', $l->id) ||
                $noteLines->contains('id', $l->id) ||
                (isset($dppLine) && $l->id === $dppLine->id)
            )->sortBy('actual_start')->values();

            $displayLines = collect();
            if (isset($printMode) && $printMode === 'summary') {
                // Summary Mode: Group all rental lines
                if ($rentalLines->isNotEmpty()) {
                    $totalQty = $rentalLines->sum('quantity');
                    
                    // Earliest start and Latest end
                    $earliestStart = $rentalLines->filter(fn($l) => !empty($l->actual_start))->min('actual_start');
                    $latestEnd = $rentalLines->filter(fn($l) => !empty($l->actual_end))->max('actual_end');
                    
                    $periodStr = '';
                    if ($earliestStart && $latestEnd) {
                        $periodStr = ' Periode ' . $earliestStart->format('d/m/Y') . ' - ' . $latestEnd->format('d/m/Y');
                    }
                    
                    // Use note lines (qty=0, price=0 description lines from Odoo) as the summary description
                    if ($noteLines->isNotEmpty()) {
                        $summaryDesc = $noteLines->pluck('description')->map(fn($d) => trim($d))->filter()->implode("\n");
                    } elseif (!empty($invoice->narration)) {
                        $summaryDesc = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $invoice->narration));
                    } else {
                        // Fallback to generic description
                        $summaryDesc = 'Sewa ' . number_format($totalQty, 0) . ' Unit Kendaraan' . $periodStr;
                    }
                    $summaryAmount = $invoice->amount_untaxed; // Use the actual untaxed amount from Odoo
                    
                    $displayLines->push((object)[
                        'description' => $summaryDesc,
                        'quantity' => $totalQty,
                        'uom' => !empty($rentalLines->first()->uom) ? $rentalLines->first()->uom : (!empty($rentalLines->first()->rental_qty) ? 'month' : 'Unit'),
                        'price_unit' => null, 
                        'amount' => $summaryAmount,
                        'is_summary' => true
                    ]);
                }
            } else {
                // Detail Mode
                foreach($rentalLines as $l) {
                    $l->amount = $l->quantity * $l->price_unit;
                    $l->serial_number = $l->serial_number;
                    $l->actual_start = $l->actual_start;
                    $l->actual_end = $l->actual_end;
                    $l->customer_name = $l->customer_name;
                    $l->is_summary = false;
                    $displayLines->push($l);
                }
            }

            if (str_starts_with($invoice->name, 'R') && $displayLines->isEmpty()) {
                $originalInvoice = '';
                if (preg_match('/(INV[A-Z]+\/\d{4}\/\d{5})/', $invoice->ref ?? '', $m)) {
                    $originalInvoice = $m[1];
                } else {
                    $originalInvoice = $invoice->ref ?? '';
                }

                $displayLines->push((object)[
                    'clean_description' => $originalInvoice,
                    'description' => $originalInvoice,
                    'serial_number' => '-',
                    'customer_name' => '',
                    'is_summary' => false,
                    'quantity' => 1,
                    'uom' => '',
                    'price_unit' => $invoice->amount_untaxed,
                    'amount' => $invoice->amount_untaxed,
                    'is_rinvrs_line' => true,
                    'actual_start' => null,
                    'actual_end' => null,
                ]);
            }

            $rentalSubtotal = $invoice->amount_untaxed;
            $discountTotal = $discountLines->sum(fn($l) => $l->quantity * $l->price_unit ?: $l->price_unit);
            
            // Group rounding lines by display label
            $roundingGroups = $roundingLines->groupBy(function($l) {
                $desc = strtolower($l->description);
                if (str_contains($desc, 'driver')) return 'Biaya Driver';
                if (str_contains($desc, 'discount')) return 'Discount';
                return 'Lain - lain';
            })->map(function($group) {
                return $group->sum(fn($l) => $l->quantity * $l->price_unit ?: $l->price_unit);
            });

            if (!isset($printMode) || $printMode !== 'summary') {
                $rentalSubtotal = $rentalSubtotal - $discountTotal - $roundingLines->sum(fn($l) => $l->quantity * $l->price_unit ?: $l->price_unit);
            }

            $refParts = $invoice->ref ? explode(' - ', $invoice->ref) : [];
            $isSubscription = str_starts_with($invoice->name, 'INVRS') || str_starts_with($invoice->name, 'INVRT'); // kept for other logic if needed
        @endphp

        <table class="lines-table">
            <thead>
                <tr>
                    <td colspan="5" style="border: none; padding: 0 0 10px 0; background-color: white; position: relative;">
                        @if(($enableWatermark ?? '1') === '1' && isset($invoice->print_count) && $invoice->print_count > 0)
                            <div class="watermark">DUPLICATE - {{ $invoice->print_count }}</div>
                        @endif

                        {{-- Company Header (Repeated) --}}
                        <table class="company-header" style="border-spacing: 0;">
                            <tr>
                                <td style="width: 60%; padding: 0;">
                                    @php
                                        // For PDF we need absolute path, for Browser we need URL
                                        $isPdf = request()->is('*/pdf');
                                        $logoSource = $isPdf ? public_path('images/logo.png') : asset('images/logo.png');
                                    @endphp
                                    <img src="{{ $logoSource }}" style="max-height: 45px; max-width: 180px; margin-bottom: 5px;" alt="Logo"><br>
                                    <span class="company-name">PT. SURYA DARMA PERKASA</span><br>
                                    <span class="company-address">
                                        JL. DAAN MOGOT KM.1 NO. 99, JAKARTA BARAT<br>
                                        (021) 56977708, 5661060
                                    </span>
                                </td>
                                <td style="width: 40%;">
                                    <div class="invoice-title">{{ str_starts_with($invoice->name, 'R') ? 'CREDIT NOTES' : 'PROFORMA INVOICE' }}</div>
                                    <div class="page-label" style="visibility: hidden;">
                                        Hal : 1
                                    </div>
                                </td>
                            </tr>
                        </table>

                        {{-- Invoice Info (Repeated) --}}
                        <table class="info-section">
                            <tr>
                                <td style="width: 55%; vertical-align: top; padding: 0;">
                                    <table style="width: 100%; border-spacing: 0;">
                                        <tr>
                                            <td colspan="3" style="padding-bottom: 5px; padding-left: 0;">
                                                <span style="font-size: 9px; color: #64748b;">Kepada Yth.</span><br>
                                                <span class="customer-name">{{ $invoice->partner_name }}</span>
                                                    @php
                                                        $address = $invoice->partner_address ?? $invoice->partner_address_complete ?? '';
                                                        $address = preg_replace('/^' . preg_quote($invoice->partner_name, '/') . '[\r\n]*/i', '', $address);
                                                        $address = preg_replace('/,?\s*Indonesia\s*$/i', '', trim($address));
                                                    @endphp
                                                <div class="customer-address">{!! nl2br(e(trim($address))) !!}</div>
                                                @if($invoice->partner_npwp)
                                                <div style="font-size: 9px; margin-top: 3px; font-weight: bold; padding-top: 5px;">NPWP : {{ $invoice->partner_npwp }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="width: 45%; vertical-align: top;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td class="info-label">Nomor</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->proforma_number ?? $invoice->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">Tanggal</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                                        </tr>
                                        <tr><td colspan="3" style="height: 5px;"></td></tr>
                                        <tr>
                                            <td class="info-label">No Kontrak</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->contract_ref ?? '' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">No. PO</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ str_starts_with($invoice->name, 'R') ? '' : ($invoice->ref ?? '') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">Payment Terms</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->payment_term ?? '' }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="table-header-row">
                    @if(isset($printMode) && $printMode === 'summary')
                        <th class="col-no">NO.</th>
                        <th class="col-desc">KETERANGAN</th>
                        <th class="col-unit"></th>
                        <th class="col-price"></th>
                        <th class="col-amount">JUMLAH</th>
                    @else
                        <th class="col-no">NO.</th>
                        <th class="col-desc">KETERANGAN</th>
                        <th class="col-unit">SATUAN</th>
                        <th class="col-price">HARGA SATUAN</th>
                        <th class="col-amount">JUMLAH</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @if(isset($printMode) && $printMode === 'detail' && !str_starts_with($invoice->name, 'R'))
                <tr>
                    <td></td>
                    <td style="padding-bottom: 2px;">
                        Pembayaran sewa Kendaraan
                        <table style="width: 100%; margin-top: 4px; border-collapse: collapse;">
                            <tr>
                                <th style="width: 35%; text-align: left; text-decoration: underline; font-weight: bold; padding: 0;">Type</th>
                                <th style="width: 25%; text-align: center; text-decoration: underline; font-weight: bold; padding: 0;">No-Polisi</th>
                                <th style="width: 40%; text-align: center; text-decoration: underline; font-weight: bold; padding: 0;">Periode-Tagihan-Sewa</th>
                            </tr>
                        </table>
                    </td>
                    <td colspan="3"></td>
                </tr>
                @endif
                @foreach($displayLines as $idx => $line)
                <tr>
                    @if(isset($printMode) && $printMode === 'summary')
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{!! nl2br(e($line->description)) !!}</strong>
                        </td>
                    @else
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>
                            @php
                                // Extract product code from description (text inside first [...])
                                preg_match('/\[([^\]]+)\]/', $line->description ?? '', $codeMatch);
                                $productCode = $codeMatch[1] ?? '';

                                $periodeStart = !empty($line->actual_start) ? \Carbon\Carbon::parse($line->actual_start) : null;
                                $periodeEnd   = !empty($line->actual_end) ? \Carbon\Carbon::parse($line->actual_end) : null;

                                $isINVRT = str_starts_with($invoice->name, 'INVRT');
                                
                                $startStr = '-';
                                if ($periodeStart) {
                                    if ($isINVRT && $periodeStart->format('H:i') !== '00:00') {
                                        $startStr = $periodeStart->format('d/m h:i A');
                                    } else {
                                        $startStr = $periodeStart->format('d/m/Y');
                                    }
                                }

                                $endStr = '-';
                                if ($periodeEnd) {
                                    if ($isINVRT && $periodeEnd->format('H:i') !== '00:00') {
                                        $endStr = $periodeEnd->format('d/m h:i A');
                                    } else {
                                        $endStr = $periodeEnd->format('d/m/Y');
                                    }
                                }

                                $periodeStr = ($periodeStart || $periodeEnd) 
                                    ? $startStr . ' s/d ' . $endStr
                                    : '-';
                            @endphp

                            @if(!$line->is_summary)
                                @if(str_starts_with($invoice->name, 'R'))
                                    @php
                                        $originalInvoice = '';
                                        if (preg_match('/(INV[A-Z]+\/\d{4}\/\d{5})/', $invoice->ref ?? '', $m)) {
                                            $originalInvoice = $m[1];
                                        } else {
                                            $originalInvoice = $invoice->ref ?? '';
                                        }
                                    @endphp
                                    <span>{{ $originalInvoice }}</span>
                                @elseif(strtolower(trim($line->clean_description)) === 'lain-lain')
                                    <span>{{ $line->clean_description }}</span>
                                @elseif(isset($printMode) && $printMode === 'detail')
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="width: 35%; text-align: left; padding: 0; vertical-align: top;">{{ $productCode }}</td>
                                            <td style="width: 25%; text-align: center; padding: 0; vertical-align: top;">{{ $line->serial_number ?? '-' }}</td>
                                            <td style="width: 40%; text-align: center; padding: 0; vertical-align: top; white-space: nowrap;">{{ $periodeStr }}</td>
                                        </tr>
                                    </table>
                                @else
                                    @php
                                        $inlinePeriode = ($periodeStart || $periodeEnd) 
                                            ? ' Periode: ' . ($periodeStart ? $periodeStart->format('d/m/Y') : '-') . ' s/d ' . ($periodeEnd ? $periodeEnd->format('d/m/Y') : '-')
                                            : '';
                                    @endphp
                                    <span>{{ $productCode }} {{ $line->serial_number ?? '-' }}{{ $inlinePeriode }}</span>
                                @endif
                                @if(isset($showUsername) && $showUsername && $line->customer_name && strtolower(trim($line->clean_description)) !== 'lain-lain')
                                    <div style="color: #475569; font-style: italic; margin-top: 2px;">{{ $line->customer_name }}</div>
                                @endif
                            @else
                                <span>{!! nl2br(e($line->clean_description)) !!}</span>
                            @endif
                        </td>
                    @endif
                    <td class="text-center">
                        @if(!isset($printMode) || $printMode !== 'summary')
                            @if(strtolower(trim($line->clean_description)) !== 'lain-lain' && !isset($line->is_rinvrs_line))
                                @php
                                    $displayQty = $line->rental_qty ?? $line->quantity ?? 0;
                                    $uomMap = [
                                        'hour' => 'Jam',
                                        'hours' => 'Jam',
                                        'day' => 'Hari',
                                        'days' => 'Hari',
                                        'month' => 'Bln',
                                        'months' => 'Bln',
                                        'year' => 'Tahun',
                                        'years' => 'Tahun',
                                        'unit' => 'Unit',
                                        'units' => 'Unit'
                                    ];
                                    $uomStr = !empty($line->uom) ? $line->uom : (!empty($line->rental_qty) || !empty($line->actual_start) || !empty($line->actual_end) ? 'month' : 'Unit');
                                    $uomIndo = $uomMap[strtolower(trim($uomStr))] ?? $uomStr;
                                @endphp
                                @if($displayQty != 0 && !request('without_satuan'))
                                    {{ ($displayQty == (int)$displayQty) ? number_format($displayQty, 0, '.', ',') : rtrim(rtrim(number_format($displayQty, 4, '.', ','), '0'), '.') }} {{ $uomIndo }}
                                @endif
                            @endif
                        @endif
                    </td>
                    <td class="text-right">
                        @if(!isset($printMode) || $printMode !== 'summary')
                            @if(strtolower(trim($line->clean_description)) !== 'lain-lain' && !isset($line->is_rinvrs_line))
                                @php
                                    $displayQty = $line->rental_qty ?? $line->quantity ?? 0;
                                    $daysInclusive = ($periodeStart && $periodeEnd) ? $periodeStart->diffInDays($periodeEnd) + 1 : 0;
                                    $isExactlyOneMonth = ($daysInclusive >= 28 && $daysInclusive <= 31);
                                    
                                    $odooDurationPrice = $line->duration_price ?? 0;
                                    $hasWarning = false;
                                    
                                    if ($displayQty == 1 && $isExactlyOneMonth) {
                                        // STANDARD 1-MONTH: Safe to use actual billed price_unit
                                        $unitPrice = $line->price_unit ?? 0;
                                        // Warn if contract price drifted in Odoo
                                        if ($odooDurationPrice > 0 && abs($unitPrice - $odooDurationPrice) >= 1) {
                                            $hasWarning = true;
                                        }
                                    } else {
                                        // PRORATED / MULTI-MONTH: Force duration_price to show base monthly rate
                                        $unitPrice = $odooDurationPrice > 0 ? $odooDurationPrice : ($line->price_unit ?? 0);
                                        $calcQty = $displayQty > 0 ? $displayQty : 1;
                                        // Warn if the math (Qty * Harga Satuan) does not cleanly equal Jumlah
                                        if (abs(($unitPrice * $calcQty) - $line->amount) >= 1) {
                                            $hasWarning = true;
                                        }
                                    }
                                @endphp
                                @if(isset($unitPrice) && $unitPrice != 0)
                                    @if($hasWarning && isset($isHtml) && $isHtml)
                                        <span title="Rate anomaly or uneven proration" style="background-color: #fef08a; color: #854d0e; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-right: -6px;">
                                            {{ number_format($unitPrice, 0, ',', '.') }}
                                        </span>
                                    @else
                                        {{ number_format($unitPrice, 0, ',', '.') }}
                                    @endif
                                @endif
                            @endif
                        @endif
                    </td>
                    <td class="text-right">
                        @if($line->amount != 0)
                            {{ number_format($line->amount, 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @endforeach
                {{-- Empty rows to fill space --}}
                @for($i = count($displayLines); $i < 5; $i++)
                <tr><td colspan="5" style="height: 18px;"></td></tr>
                @endfor
                <tr>
                    <td colspan="5" style="border-bottom: 2px solid #1e293b; padding: 0; height: 0; line-height: 0;"></td>
                </tr>
                <tr style="border: none;">
                    <td colspan="5" style="border: none; padding: 0;">
                        <div style="page-break-inside: avoid; width: 100%;">
                            <table style="width: 100%; margin-top: 10px;">
                <tr>
                    <td style="width: 55%; vertical-align: top;">
                        <div style="font-size: 10px; margin-bottom: 5px;">
                            <strong>Jatuh Tempo :</strong>
                                @if($invoice->payment_term && $invoice->invoice_date)
                                    @php
                                        $days = 0;
                                        if (preg_match('/(\d+)\s*Days?/i', $invoice->payment_term, $m)) {
                                            $days = (int)$m[1];
                                        }
                                        $dueDate = $invoice->invoice_date->copy()->addDays($days);
                                    @endphp
                                    {{ $dueDate->format('d/m/Y') }}
                                @endif
                        </div>
                        <div class="ketentuan-section">
                            <div class="ketentuan-title">KETENTUAN</div>
                            <div class="ketentuan-content">
                                1. Pembayaran dengan Cek/Giro/Transfer harap diatas namakan<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;PT. SURYA DARMA PERKASA<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;pada bank : &nbsp;&nbsp;{{ str_contains($invoice->partner_bank ?? '', '1170004641403') ? '1170004641403 - MANDIRI KCU Kyai Tapa Jakarta' : ($invoice->partner_bank ?? '') }}<br>
                                2. Pembayaran dianggap lunas bila sudah diterima di rekening<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;PT. SURYA DARMA PERKASA<br>
                                3. Bukti & perincian pembayaran harap di fax/di email ke : collection@hartonorentcar.com
                            </div>
                        </div>
                    </td>
                    <td style="width: 45%; vertical-align: top;">
                        <table class="totals-table" style="width: 100%;">
                            <tr>
                                <td style="text-align: right; font-weight: bold;">Jumlah</td>
                                <td style="text-align: right; width: 140px;">{{ number_format($rentalSubtotal, 0, ',', '.') }}</td>
                            </tr>
                            @if(isset($printMode) && $printMode === 'summary' && $dppLainnyaText && $dppLainnyaAmount)
                            <tr>
                                <td style="text-align: right; color: #1e293b;">{{ $dppLainnyaText }}</td>
                                <td style="text-align: right;">{{ $dppLainnyaAmount }}</td>
                            </tr>
                            @endif
                            @if(!isset($printMode) || $printMode !== 'summary')
                                @if($discountTotal != 0)
                                <tr>
                                    <td style="text-align: right; color: #ef4444;">Discount</td>
                                    <td style="text-align: right; color: #ef4444;">{{ number_format($discountTotal, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if(isset($roundingGroups) && $roundingGroups->isNotEmpty())
                                    @foreach($roundingGroups as $label => $amount)
                                        @if($amount != 0)
                                        <tr>
                                            <td style="text-align: right;">{{ $label }}</td>
                                            <td style="text-align: right;">{{ number_format($amount, 0, ',', '.') }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                @endif

                                @if(isset($printMode) && $printMode === 'detail' && ($discountTotal != 0 || $roundingLines->isNotEmpty()))
                                <tr>
                                    <td style="text-align: right; font-weight: bold;">Subtotal</td>
                                    <td style="text-align: right; font-weight: bold; border-top: 1px solid #000;">{{ number_format($invoice->amount_untaxed, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                            @endif
                            <tr><td colspan="2" style="height: 8px;"></td></tr>
                            <tr>
                                <td style="text-align: right;">PPN</td>
                                <td style="text-align: right;">{{ number_format($invoice->amount_tax, 0, ',', '.') }}</td>
                            </tr>
                            <tr><td colspan="2" style="height: 8px;"></td></tr>
                            <tr class="total-row">
                                <td style="text-align: right;">Total</td>
                                <td style="text-align: right;">{{ number_format($invoice->amount_total, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="terbilang-section">
                <span class="terbilang-label">Terbilang :</span>
                <em>{{ ucwords(\App\Helpers\Terbilang::convert($invoice->amount_total)) }} Rupiah #</em>
            </div>

            <div class="catatan-container">
                <div class="catatan-box">
                    <span class="catatan-label">CATATAN</span>
                    <div class="catatan-content">
                        @php
                            $catatanContent = [];
                            $isSummary = isset($printMode) && $printMode === 'summary';

                            if (str_starts_with($invoice->name, 'R') && !empty($invoice->ref)) {
                                $catatanContent[] = nl2br(e(trim($invoice->ref)));
                            }
                            
                            // Process Narration
                            if (!$isSummary && !empty($invoice->narration)) {
                                $narration = trim($invoice->narration);
                                if (str_contains($narration, '##')) {
                                    $narration = trim(explode('##', $narration)[0]);
                                }
                                if (!empty($showUsername) && !empty($invoice->partner_name) && stripos($narration, trim($invoice->partner_name)) !== false) {
                                    $narration = trim(str_ireplace(trim($invoice->partner_name), '', $narration));
                                }
                                if (!empty($narration)) {
                                    $cleanNarration = strip_tags(str_replace(['<br>', '<br/>', '<br />', '<div>', '<p>', '</p>', '</div>'], ["\n", "\n", "\n", "\n", "\n", "\n", "\n"], $narration));
                                    $catatanContent[] = nl2br(e(trim($cleanNarration)));
                                }
                            }
                            
                            // Process PPH Lines
                            if (isset($pphLines) && $pphLines->isNotEmpty()) {
                                foreach($pphLines as $pphL) {
                                    $pphText = preg_replace('/^.*?(PPH\s*2\s*%)/i', '$1', $pphL->description);
                                    $catatanContent[] = e($pphText);
                                }
                            }
                            
                            // Process Note Lines
                            if (!$isSummary && isset($noteLines) && $noteLines->isNotEmpty()) {
                                foreach($noteLines as $noteL) {
                                    $noteText = trim($noteL->clean_description);
                                    if (str_contains($noteText, '##')) {
                                        $noteText = trim(explode('##', $noteText)[0]);
                                    }
                                    if (!empty($showUsername) && !empty($invoice->partner_name) && stripos($noteText, trim($invoice->partner_name)) !== false) {
                                        $noteText = trim(str_ireplace(trim($invoice->partner_name), '', $noteText));
                                    }
                                    if (!empty($noteText)) {
                                        $cleanNoteText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '<div>', '<p>', '</p>', '</div>'], ["\n", "\n", "\n", "\n", "\n", "\n", "\n"], $noteText));
                                        $catatanContent[] = nl2br(e(trim($cleanNoteText)));
                                    }
                                }
                            }
                        @endphp
                        {!! implode('<br/>', $catatanContent) !!}
                    </div>
                </div>
            </div>

            <table class="signature-table" style="margin-top: 20px;">
                <tr>
                    <td style="width: 60%;"></td>
                    <td style="width: 20%; text-align: center;">
                        <div class="signature-name" style="margin-top: 60px;">
                            {{ strtoupper($managerName) }}
                        </div>
                    </td>
                    <td style="width: 20%; text-align: center;">
                        <div class="signature-name" style="margin-top: 60px;">
                            {{ strtoupper($spvName) }}
                        </div>
                    </td>
                </tr>
            </table>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="border: none; padding: 0; height: 50px;"></td>
                </tr>
            </tfoot>
        </table>
    </div>@endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $starts = $GLOBALS["invoice_starts"] ?? [];
                asort($starts);
                
                @if(!isset($printMode) || $printMode !== "summary")
                $invoiceStartPage = 1;
                foreach ($starts as $name => $startPage) {
                    if ($PAGE_NUM >= $startPage) {
                        $invoiceStartPage = $startPage;
                    }
                }
                $localPageNum = $PAGE_NUM - $invoiceStartPage + 1;
                $font = $fontMetrics->get_font("helvetica", "normal");
                $text = "Hal : " . $localPageNum;
                $pdf->text(524, 47, $text, $font, 9, array(0.39, 0.45, 0.55));
                @endif
                
                $currentInvoiceName = null;
                foreach ($starts as $name => $startPage) {
                    if ($PAGE_NUM >= $startPage) {
                        $currentInvoiceName = $name;
                    }
                }
                if ($currentInvoiceName) {
                    $pic = $GLOBALS["invoice_pics"][$currentInvoiceName] ?? "";
                    if (!empty($pic)) {
                        $print = $GLOBALS["invoice_prints"][$currentInvoiceName] ?? "01";
                        $date = date("dmy");
                        $watermarkText = $pic . "/" . $date . "/" . $print;
                        
                        $font = $fontMetrics->get_font("helvetica", "normal");
                        $size = 8.25;
                        $color = array(0.118, 0.161, 0.231); // #1e293b
                        $textWidth = $fontMetrics->getTextWidth($watermarkText, $font, $size);
                        
                        // Right side, matching right margin
                        $x = 595.28 - 30 - $textWidth;
                        
                        // Vertically align with footer
                        $y = 804;
                        
                        $pdf->text($x, $y, $watermarkText, $font, $size, $color);
                    }
                }
            ');
        }
    </script>

    @if(isset($isHtml) && $isHtml)
    <script>
        window.onload = function() {
            // Don't auto-print if loaded inside an iframe (preview popup)
            if (window.self === window.top) {
                setTimeout(function() { window.print(); }, 500);
            }
        }
    </script>
    <style>
        /* A4 Continuous Form Print */
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { margin: 1cm; }
            .invoice-box { border: none !important; box-shadow: none !important; }
            .watermark { opacity: 0.05 !important; }
        }
    </style>
    @endif
</body>
</html>
