<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice Rental</title>
    <style>
        @page {
            margin: 30px 40px;
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
            position: relative;
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
            width: 110px;
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
            border-bottom: 2px solid #1e293b;
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
        .lines-table .col-no { width: 6%; text-align: center; }
        .lines-table .col-desc { width: 40%; }
        .lines-table .col-unit { width: 14%; text-align: center; }
        .lines-table .col-price { width: 20%; text-align: right; }
        .lines-table .col-amount { width: 20%; text-align: right; }
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
        .catatan-section {
            margin-top: 10px;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
        }
        .catatan-label {
            font-weight: bold;
            font-size: 10px;
            text-decoration: underline;
        }
        .catatan-content {
            font-size: 9px;
            color: #334155;
            white-space: pre-line;
            margin-top: 3px;
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
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
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

        .page-number-container {
            position: fixed;
            top: 58px;
            right: 5px;
            font-size: 11px;
            color: #64748b;
            text-align: right;
            z-index: 9999;
        }
        .page-number-counter::after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="page-number-container">Hal : <span class="page-number-counter"></span></div>
    @foreach($invoices as $invoice)
    <div class="invoice-page" style="{{ $loop->last ? 'page-break-after: auto;' : 'page-break-after: always;' }}">
        @php
            // Fallback to app settings if Odoo fields are empty
            $managerName = !empty($invoice->manager_name)
                ? $invoice->manager_name
                : \App\Models\Setting::get('default_bc_manager', '');
            $spvName = !empty($invoice->spv_name)
                ? $invoice->spv_name
                : \App\Models\Setting::get('default_bc_spv', '');

            // Categorize lines
            $allLines = $invoice->lines;
            $discountLines = $allLines->filter(fn($l) => 
                str_contains(strtolower($l->description), 'discount') || 
                str_contains(strtolower($l->description), 'potongan') ||
                $l->price_unit < 0
            );
            $roundingLines = $allLines->filter(fn($l) => 
                str_contains(strtolower($l->description), 'pembulatan') || 
                str_contains(strtolower($l->description), 'rounding')
            );
            $rentalLines = $allLines->reject(fn($l) => 
                $discountLines->contains('id', $l->id) || 
                $roundingLines->contains('id', $l->id)
            )->values();

            $displayLines = collect();
            if (isset($printMode) && $printMode === 'summary' && str_starts_with($invoice->name, 'INVRS')) {
                // Summary Mode: Group all rental lines
                if ($rentalLines->isNotEmpty()) {
                    $displayLines->push((object)[
                        'description' => 'Sewa Unit Rental',
                        'quantity' => $rentalLines->sum('quantity'),
                        'uom' => $rentalLines->first()->uom ?? 'Unit',
                        'price_unit' => null, 
                        'amount' => $rentalLines->sum(fn($l) => $l->quantity * $l->price_unit),
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

            $rentalSubtotal = $rentalLines->sum(fn($l) => $l->quantity * $l->price_unit);
            $discountTotal = $discountLines->sum(fn($l) => $l->quantity * $l->price_unit ?: $l->price_unit);
            $roundingTotal = $roundingLines->sum(fn($l) => $l->quantity * $l->price_unit ?: $l->price_unit);

            $refParts = $invoice->ref ? explode(' - ', $invoice->ref) : [];
            $isSubscription = str_starts_with($invoice->name, 'INVRS');
            $invoiceTitle = $isSubscription ? 'INVOICE SEWA SUBSCRIPTION' : 'INVOICE SEWA REGULER';
        @endphp

        <table class="lines-table">
            <thead>
                <tr>
                    <td colspan="5" style="border: none; padding: 0 0 10px 0; background-color: white; position: relative;">
                        @if(\App\Models\Setting::get('enable_pdf_watermark', '1') === '1' && isset($invoice->print_count) && $invoice->print_count > 0)
                            <div class="watermark">DUPLICATE - {{ $invoice->print_count }}</div>
                        @endif

                        {{-- Company Header (Repeated) --}}
                        <table class="company-header">
                            <tr>
                                <td style="width: 60%;">
                                    @php
                                        $logoPath = public_path('images/logo.png');
                                    @endphp
                                    @if(file_exists($logoPath))
                                        <img src="{{ $logoPath }}" style="max-height: 45px; max-width: 180px; margin-bottom: 5px;" alt="Logo"><br>
                                    @endif
                                    <span class="company-name">PT. SURYA DARMA PERKASA</span><br>
                                    <span class="company-address">
                                        JL. DAAN MOGOT KM.1 NO. 99, JAKARTA BARAT<br>
                                        (021) 56977708, 5661060
                                    </span>
                                </td>
                                <td style="width: 40%;">
                                    <div class="invoice-title">{{ $invoiceTitle }}</div>
                                    <div class="page-label" style="visibility: hidden;">Hal : 1</div>
                                </td>
                            </tr>
                        </table>

                        {{-- Invoice Info (Repeated) --}}
                        <table class="info-section">
                            <tr>
                                <td style="width: 65%; vertical-align: top;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td colspan="3" style="padding-bottom: 5px;">
                                                <span style="font-size: 9px; color: #64748b;">Kepada Yth.</span><br>
                                                <span class="customer-name">{{ $invoice->partner_name }}</span>
                                                <div class="customer-address">{!! nl2br(e($invoice->partner_address ?? $invoice->partner_address_complete ?? '')) !!}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="width: 35%; vertical-align: top;">
                                    <table style="width: 100%;">
                                        <tr>
                                            <td class="info-label">Nomor</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">Tanggal</td>
                                            <td class="info-colon">:</td>
                                            <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '-' }}</td>
                                        </tr>
                                        <tr><td colspan="3" style="height: 5px;"></td></tr>
                                        <tr>
                                            <td class="info-label">Kode Pelanggan</td>
                                            <td class="info-colon">:</td>
                                            <td>
                                                @php
                                                    $kodePelanggan = count($refParts) > 0 ? trim($refParts[0]) : '';
                                                @endphp
                                                {{ $kodePelanggan }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">No. PO/Tanggal</td>
                                            <td class="info-colon">:</td>
                                            <td></td>
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
                    <th class="col-no">NO.</th>
                    <th class="col-desc">KETERANGAN</th>
                    <th class="col-unit">SATUAN</th>
                    <th class="col-price">HARGA SATUAN</th>
                    <th class="col-amount">JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                @foreach($displayLines as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $line->description }}</strong>
                        @if(!$line->is_summary)
                            @if($line->serial_number)
                                <br/><span style="color: #475569;">No. Polisi/Serial: {{ $line->serial_number }}</span>
                            @endif
                            @if($line->actual_start || $line->actual_end)
                                <br/><span style="color: #475569;">Periode: {{ $line->actual_start ? $line->actual_start->format('d/m/Y') : '-' }} s/d {{ $line->actual_end ? $line->actual_end->format('d/m/Y') : '-' }}</span>
                            @endif
                            @if(isset($showUsername) && $showUsername && $line->customer_name)
                                <br/><span style="color: #475569;">User: {{ $line->customer_name }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-center">
                        @if($line->quantity > 0)
                            {{ number_format($line->quantity, 0) }} {{ $line->uom ?? 'Unit' }}
                        @endif
                    </td>
                    <td class="text-right">
                        @if(isset($line->price_unit) && $line->price_unit > 0)
                            {{ number_format($line->price_unit, 0, ',', '.') }}
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
            </tbody>
        </table>

        <div style="page-break-inside: avoid;">
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
                                &nbsp;&nbsp;&nbsp;&nbsp;pada bank : &nbsp;&nbsp;{{ $invoice->partner_bank ?? '' }}<br>
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
                            @if($discountTotal != 0)
                            <tr>
                                <td style="text-align: right; color: #ef4444;">Discount</td>
                                <td style="text-align: right; color: #ef4444;">{{ number_format($discountTotal, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($roundingTotal != 0)
                            <tr>
                                <td style="text-align: right;">Lain - lain</td>
                                <td style="text-align: right;">{{ number_format($roundingTotal, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr><td colspan="2" style="height: 8px;"></td></tr>
                            <tr>
                                <td style="text-align: right;">PPN 11.00 %</td>
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

            @php
                $noteLines = $invoice->lines->filter(fn($l) => $l->quantity == 0 && $l->price_unit == 0 && !empty($l->description));
            @endphp
            @if($noteLines->isNotEmpty())
            <div class="catatan-section">
                <span class="catatan-label">CATATAN</span>
                <div class="catatan-content">@foreach($noteLines as $note){{ $note->description }}
@endforeach</div>
            </div>
            @endif

            <table class="signature-table" style="margin-top: 20px;">
                <tr>
                    <td style="width: 50%;">
                        <div class="signature-name" style="margin-top: 60px;">
                            {{ strtoupper($managerName) }}
                        </div>
                    </td>
                    <td style="width: 50%;">
                        <div class="signature-name" style="margin-top: 60px;">
                            {{ strtoupper($spvName) }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>@endforeach</body>
</html>
