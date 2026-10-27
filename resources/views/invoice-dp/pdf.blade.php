<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tanda Terima Uang Muka</title>
    <style>
        @page {
            margin: 25px 35px 35px 35px;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #000000;
            margin: 0;
            padding: 0;
            line-height: 1.3;
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
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 70px;
            color: rgba(255, 0, 0, 0.12);
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
            z-index: -1;
        }
        .header-table {
            width: 100%;
            margin-bottom: 5px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 10px;
            font-weight: bold;
        }
        .company-address {
            font-size: 8.5px;
            color: #000;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
            margin: 10px 0 3px 0;
            color: #000;
        }
        .title-divider {
            border-bottom: 1px solid #000;
            margin-bottom: 12px;
        }
        .info-table td {
            padding: 3px 0;
            font-size: 10px;
            vertical-align: top;
        }
        .field-label {
            font-weight: bold;
            color: #000;
            width: 150px;
        }
        .amount-section {
            width: auto !important;
            margin: 15px 0 10px 0;
        }
        .amount-section td {
            padding: 4px 0;
            font-size: 10px;
            font-weight: bold;
        }
        .terbilang-container {
            border: 1px solid #000;
            padding: 5px 8px;
            margin: 8px 0 12px 0;
            font-size: 10px;
        }
        .legal-notice {
            font-style: italic;
            font-size: 8.5px;
            color: #000;
            line-height: 1.3;
            width: 65%;
            float: left;
        }
        .signature-container {
            width: 30%;
            float: right;
            text-align: center;
        }
        .signature-title {
            font-size: 10px;
            margin-bottom: 45px;
        }
        .footer-bar {
            margin-top: 50px;
            padding-top: 4px;
            font-size: 8.5px;
            color: #000;
            clear: both;
        }
        .footer-left {
            float: left;
            font-weight: normal;
        }
        .footer-right {
            float: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @foreach($invoices as $invoice)
    <div class="invoice-page">
        @if(($enableWatermark ?? '1') === '1' && isset($invoice->print_count) && $invoice->print_count > 0)
            <div class="watermark">DUPLICATE - {{ $invoice->print_count }}</div>
        @endif

        {{-- Top Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 58%;">
                    @php
                        $isPdfRequest = request()->is('*/pdf');
                        $logoSource = $isPdfRequest ? public_path('images/logo.png') : asset('images/logo.png');
                    @endphp
                    <img src="{{ $logoSource }}" style="max-height: 22px; max-width: 105px; margin-bottom: 3px;" alt="Logo"><br>
                    <span class="company-name">PT. SURYA DARMA PERKASA</span><br>
                    <span class="company-address">
                        JL. DAAN MOGOT KM.1 NO. 99, JAKARTA BARAT<br>
                        (021) 56977708, 5661060
                    </span>
                </td>
                <td style="width: 42%; text-align: right; vertical-align: top;">
                    <table style="width: 220px; float: right; border-collapse: collapse;">
                        <tr>
                            <td style="width: 55px; text-align: left;">Nomor</td>
                            <td style="width: 10px; text-align: center;">:</td>
                            <td style="text-align: left; font-weight: bold;">{{ $invoice->name }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">Tanggal</td>
                            <td style="text-align: center;">:</td>
                            <td style="text-align: left;">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Document Title --}}
        <div class="doc-title">TANDA TERIMA UANG MUKA</div>
        <div class="title-divider"></div>

        {{-- Info Block --}}
        <table class="info-table" style="width: 100%;">
            <tr>
                <td style="width: 58%; vertical-align: top; padding: 0;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="field-label" style="width: 140px; font-weight: bold;">TELAH TERIMA DARI</td>
                            <td style="width: 10px;">:</td>
                            <td style="font-weight: bold;">{{ $invoice->partner_name }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 42%; vertical-align: top; padding: 0;">
                    <table style="width: 220px; float: right; border-collapse: collapse;">
                        <tr>
                            <td style="width: 55px; text-align: left;">NPWP</td>
                            <td style="width: 10px; text-align: center;">:</td>
                            <td style="text-align: left;">{{ $invoice->partner_npwp ?: '00.000.000.0-000.000' }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">Tanggal</td>
                            <td style="text-align: center;">:</td>
                            <td style="text-align: left;">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top: 15px;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="field-label" style="width: 140px; font-weight: bold; vertical-align: top;">UNTUK PEMBAYARAN</td>
                            <td style="width: 10px; vertical-align: top;">:</td>
                            <td style="vertical-align: top;">
                                @php
                                    $lots = $invoice->lots;
                                    $lotChunks = array_chunk($lots, 5);
                                @endphp
                                @if(count($lots) > 0)
                                    <span style="vertical-align: top; display: inline-block;">Uang Muka Nopol&nbsp;</span><span style="vertical-align: top; display: inline-block;">@foreach($lotChunks as $cIndex => $chunk){{ implode(', ', $chunk) }}@if(!$loop->last),<br>@endif @endforeach</span>
                                @else
                                    {{ $invoice->payment_description }}
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Financial Amounts Section --}}
        <table class="amount-section" style="width: auto !important; margin-top: 20px;">
            <tr>
                <td class="field-label" style="width: 140px;">JUMLAH PEMBAYARAN</td>
                <td style="width: 25px;">Rp.</td>
                <td style="text-align: right; padding-right: 25px;">{{ number_format($invoice->amount_untaxed, 0, ',', '.') }}</td>
                <td style="padding-right: 12px;">PPN</td>
                <td style="width: 25px;">Rp.</td>
                <td style="text-align: right;">{{ number_format($invoice->amount_tax, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="field-label" style="width: 140px;">TOTAL PEMBAYARAN</td>
                <td style="width: 25px;">Rp.</td>
                <td style="text-align: right; padding-right: 25px;">{{ number_format($invoice->amount_total, 0, ',', '.') }}</td>
                <td colspan="3"></td>
            </tr>
        </table>

        {{-- Terbilang Box --}}
        <div class="terbilang-container">
            <strong>Terbilang :</strong>
            <em>{{ ucwords(\App\Helpers\Terbilang::convert($invoice->amount_total)) }} Rupiah</em>
        </div>

        {{-- Legal Notice & Signature Row --}}
        <div style="margin-top: 10px;">
            <div class="legal-notice">
                Tanda Terima Uang Muka akan berlaku sebagai bukti pembayaran yang sah apabila<br>
                dana sudah efektif di rekening PT. Surya Darma Perkasa.
            </div>
            <div class="signature-container">
                <div class="signature-title">Penerima</div>
            </div>
            <div style="clear: both;"></div>
        </div>

        {{-- Footer: Document Control Code & Dynamic Timestamp --}}
        @php
            $picCode = $invoice->invoice_pic ? strtoupper(substr(trim($invoice->invoice_pic), 0, 3)) : 'SDP';
            $dateCode = date('dmy');
            $printNum = $printNumDisplay ?? str_pad(max(1, (int)$invoice->print_count), 2, '0', STR_PAD_LEFT);
            $timestampCode = "{$picCode}/{$dateCode}/{$printNum}";
        @endphp

        <div class="footer-bar">
            <div class="footer-left">
                SDP/FR/BC/02, Rev.01
            </div>
            <div class="footer-right">
                {{ $timestampCode }}
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
    @endforeach

    @if(isset($isHtml) && $isHtml)
    <script>
        window.onload = function() {
            if (window.self === window.top) {
                setTimeout(function() { window.print(); }, 500);
            }
        }
    </script>
    @endif
</body>
</html>
