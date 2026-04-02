<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice Penjualan</title>
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
            counter-reset: page;
        }
        .invoice-page:last-child {
            page-break-after: auto;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 35%;
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
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .page-number-counter::after {
            content: counter(page);
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
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 320px;
        }

        /* Lines Table */
        .lines-table {
            border-bottom: 2px solid #1e293b;
            margin-top: 15px;
        }
        .lines-table th {
            border-bottom: 1px solid #475569;
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
        .lines-table .col-desc { width: 35%; }
        .lines-table .col-unit { width: 15%; text-align: center; }
        .lines-table .col-price { width: 20%; text-align: right; }
        .lines-table .col-amount { width: 25%; text-align: right; }
        /* When unit column is hidden, give more space to description */
        .lines-table .col-desc-wide { width: 50%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Payment Terms */
        .payment-section {
            margin-top: 10px;
        }
        .payment-section td {
            font-size: 10px;
            vertical-align: top;
            padding: 2px 0;
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

        /* PPN row (for with-tax invoices) */
        .ppn-row td {
            font-weight: bold;
            padding: 4px 5px;
        }

        /* No PPN text (for without-tax invoices) */
        .no-ppn-text {
            color: #dc2626;
            font-weight: bold;
            font-size: 11px;
            text-align: right;
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
            margin-top: 40px;
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
    </style>
</head>
<body>


    @foreach($invoices as $invoice)
    @php
        $regularLines = $invoice->lines->filter(fn($l) => $l->quantity != 0 || $l->price_unit != 0)->values();
        $noteLines = $invoice->lines->filter(fn($l) => $l->quantity == 0 && $l->price_unit == 0 && !empty($l->description));
        // Always show SATUAN column for vehicle invoices (shows No Polisi)
        $showUnitColumn = true;
        // Fallback to app settings if Odoo fields are empty
        $managerName = !empty($invoice->manager_name)
            ? $invoice->manager_name
            : \App\Models\Setting::get('default_bc_manager', '');
        $spvName = !empty($invoice->spv_name)
            ? $invoice->spv_name
            : \App\Models\Setting::get('default_bc_spv', '');
    @endphp
    <div class="invoice-page" style="{{ $loop->last ? 'page-break-after: auto;' : 'page-break-after: always;' }}">
        <table class="lines-table">
            <thead>
                <tr>
                    <td colspan="{{ $showUnitColumn ? 5 : 4 }}" style="border: none; padding: 0 0 10px 0; background-color: white; position: relative;">
                        @if(($enableWatermark ?? '1') === '1' && isset($invoice->print_count) && $invoice->print_count > 0)
                            <div class="watermark">DUPLICATE - {{ $invoice->print_count }}</div>
                        @endif

                        {{-- Company Header (Repeated) --}}
                        <table class="company-header">
                            <tr>
                                <td style="width: 60%;">
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
                                    <div class="invoice-title">INVOICE</div>
                                    <div class="page-label">Hal : <span class="page-number-counter"></span></div>
                                </td>
                            </tr>
                        </table>

                        {{-- Invoice Info (Repeated) --}}
                        <table class="info-section">
                            <tr>
                                <td style="width: 55%; vertical-align: top;">
                                    <table style="width: 100%; table-layout: fixed;">
                                        <tr>
                                            <td colspan="3" style="padding-bottom: 5px;">
                                                <span style="font-size: 9px; color: #64748b;">Kepada Yth.</span><br>
                                                <span class="customer-name">{{ $invoice->partner_name }}</span>
                                                <div class="customer-address">{!! nl2br(e($invoice->partner_address ?? $invoice->partner_address_complete ?? '')) !!}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="width: 45%; vertical-align: top;">
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
                                            <td>{{ $invoice->ref ?? '' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">No. PO/Tanggal</td>
                                            <td class="info-colon">:</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td class="info-label">Kontrak/Tanggal</td>
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
                <tr style="border-top: 2px solid #1e293b;">
                    <th class="col-no">NO.</th>
                    <th class="{{ $showUnitColumn ? 'col-desc' : 'col-desc-wide' }}">KETERANGAN</th>
                    @if($showUnitColumn)
                    <th class="col-unit">SATUAN</th>
                    @endif
                    <th class="col-price">HARGA SATUAN</th>
                    <th class="col-amount">JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                @foreach($regularLines as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->description }}</td>
                    @if($showUnitColumn)
                    <td class="text-center">
                        {{ $line->license_plate ?? '' }}
                    </td>
                    @endif
                    <td class="text-right">
                        @if($line->price_unit != 0)
                            {{ number_format($line->price_unit, 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="text-right">
                        @if($line->quantity != 0 && $line->price_unit != 0)
                            {{ number_format($line->quantity * $line->price_unit, 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @endforeach
                {{-- Empty rows to fill space --}}
                @for($i = count($regularLines); $i < 5; $i++)
                <tr><td colspan="{{ $showUnitColumn ? 5 : 4 }}" style="height: 18px;"></td></tr>
                @endfor
            </tbody>
        </table>

        <div style="page-break-inside: avoid;">
            {{-- Payment Terms & Totals --}}
            <table style="width: 100%; margin-top: 10px;">
                <tr>
                    <td style="width: 55%; vertical-align: top;">
                        <div style="font-size: 10px; margin-bottom: 5px;">
                            <strong>Jatuh Tempo :</strong>
                                @if($invoice->payment_term && $invoice->invoice_date)
                                    @php
                                        // Calculate due date based on payment terms
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
                                <td style="text-align: right; width: 140px;">{{ number_format($invoice->amount_untaxed, 0, ',', '.') }}</td>
                            </tr>
                            <tr><td colspan="2" style="height: 8px;"></td></tr>
                            @if($invoice->amount_tax > 0)
                            {{-- WITH TAX: Show PPN row --}}
                            <tr class="ppn-row">
                                <td style="text-align: right;">PPN 11.00 %</td>
                                <td style="text-align: right;">{{ number_format($invoice->amount_tax, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr><td colspan="2" style="height: 8px;"></td></tr>
                            <tr class="total-row">
                                <td style="text-align: right;">Total</td>
                                <td style="text-align: right;">{{ number_format($invoice->amount_total, 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Terbilang --}}
            <div class="terbilang-section">
                <span class="terbilang-label">Terbilang :</span>
                <em>{{ ucwords(\App\Helpers\Terbilang::convert($invoice->amount_total)) }} Rupiah #</em>
            </div>

            {{-- Catatan / Notes - Lines with 0 qty are notes --}}
            @if($noteLines->isNotEmpty())
            <div class="catatan-section">
                <span class="catatan-label">CATATAN</span>
                <div class="catatan-content">@foreach($noteLines as $note){{ $note->description }}
    @endforeach</div>
            </div>
            @endif

            {{-- Signature Block --}}
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
    </div>
    @endforeach

    @if(isset($isHtml) && $isHtml)
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        }
    </script>
    <style>
        /* A4 Continuous Form Print */
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { margin: 1cm; }
            .invoice-page { page-break-after: always; }
            .watermark { opacity: 0.05 !important; }
        }
    </style>
    @endif
</body>
</html>
