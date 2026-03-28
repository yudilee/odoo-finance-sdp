<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Journal Entries</title>
    <style>
        @page {
            margin: 40px 50px;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            vertical-align: top;
            padding: 6px;
        }
        .voucher-page {
            position: relative;
        }
        /* Use page-break-before instead of page-break-after for dompdf multi-page entries */
        .voucher-page-break {
            page-break-before: always;
        }
        .header-move-name {
            font-size: 26px;
            font-weight: bold;
            color: #1a237e;
            letter-spacing: -0.5px;
        }
        .logo-img {
            max-height: 40px;
            max-width: 160px;
        }
        .info-label {
            font-weight: bold;
            color: #1c3254;
        }
        /* Static header (used in A4 mode) */
        .voucher-static-header {
            margin-bottom: 5px;
        }
        .lines-table {
            margin-top: 0;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
        }
        .lines-table tr {
            page-break-inside: avoid;
        }
        .lines-table th {
            text-align: left;
            border-bottom: 1px solid #1e293b;
            font-weight: bold;
            padding: 8px 5px;
            color: #000000;
            font-size: 11px;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
        }
        /* Only target the direct tbody rows, not header cell content */
        .lines-table > tbody > tr > td {
            padding: 10px 5px;
            font-size: 10px;
            word-wrap: break-word;
        }
        .col-account { width: 20%; padding-right: 10px; }
        .col-partner { width: 17%; padding-right: 10px; }
        .col-label   { width: 23%; padding-right: 10px; }
        .col-debit   { width: 20%; text-align: right; white-space: nowrap; }
        .col-credit  { width: 20%; text-align: right; white-space: nowrap; }

        .totals-row td {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            font-weight: bold;
            color: #000000;
            padding-top: 12px;
            padding-bottom: 12px;
            font-size: 11px;
            white-space: nowrap;
        }
        .lines-table > tbody > tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }

        /* The repeating header cell (thead first row td) */
        .voucher-header-cell {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            background-color: white !important;
            padding: 8px 0 15px 0 !important;
        }
    </style>
</head>
<body>

    @if(($paperSize ?? 'A5') === 'A4')
        {{-- A4 Mode: 2 vouchers per page, no thead repetition to avoid ghost headers --}}
        @foreach($entries->chunk(2) as $chunk)
        <div class="voucher-page">
            @foreach($chunk as $entry)
                <div style="height: 48%; overflow: hidden; {{ $loop->first && count($chunk) > 1 ? 'border-bottom: 1px dashed #cbd5e1; margin-bottom: 2%;' : '' }}">
                    @include('journals.partials.voucher', ['entry' => $entry, 'isPdf' => true, 'useRepeatHeader' => false])
                </div>
            @endforeach
        </div>
        @endforeach
    @else
        {{-- A5 Mode: 1 voucher per page, thead repetition enabled for sticky headers --}}
        @foreach($entries as $entry)
        <div class="voucher-page {{ !$loop->first ? 'voucher-page-break' : '' }}">
            @include('journals.partials.voucher', ['entry' => $entry, 'isPdf' => true, 'useRepeatHeader' => true])
        </div>
        @endforeach
    @endif

</body>
</html>
