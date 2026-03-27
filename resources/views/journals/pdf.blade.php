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
            /* Fallback to Helvetica for headers, but table will use Space Mono */
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
        .voucher-page:not(:last-child) {
            page-break-after: always;
            position: relative;
        }
        .voucher-page:last-child {
            page-break-after: auto;
        }
        .header-table {
            margin-bottom: 25px;
            margin-top: 10px;
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
        .info-table {
            margin-bottom: 25px;
            width: 100%;
            table-layout: fixed;
        }
        .info-table > tbody > tr > td {
            font-size: 11px;
            color: #334155;
            vertical-align: top;
            padding: 0;
        }
        .info-table table td {
            padding: 4px 0;
        }
        .info-label {
            font-weight: bold;
            color: #1c3254;
            width: 100px;
        }
        .lines-table {
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
        }
        .lines-table tr {
            page-break-inside: avoid;
        }
        .lines-table th {
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
            font-weight: bold;
            padding: 10px 5px;
            color: #000000;
            font-size: 11px;
            /* Keep header sans-serif for clean look if desired, or let it inherit */
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
        }
        .lines-table td {
            padding: 10px 5px;
            font-size: 10px;
            word-wrap: break-word;
        }
        
        /* Adjusted Column Widths */
        .col-account { width: 20%; padding-right: 10px; }
        .col-partner { width: 17%; padding-right: 10px; }
        .col-label { width: 23%; padding-right: 10px; }
        /* Give plenty of room for Rp and huge numbers on one line */
        .col-debit { width: 20%; text-align: right; white-space: nowrap; }
        .col-credit { width: 20%; text-align: right; white-space: nowrap; }
        
        .totals-row td {
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            font-weight: bold;
            color: #000000;
            padding-top: 12px;
            padding-bottom: 12px;
            font-size: 11px;
            white-space: nowrap; /* Prevent numbers wrapping here */
        }
        .lines-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
    </style>
</head>
<body>

    @if(($paperSize ?? 'A5') === 'A4')
        @foreach($entries->chunk(2) as $chunk)
        <div class="voucher-page">
            @foreach($chunk as $entry)
                <div style="height: 48%; overflow: hidden; {{ $loop->first && count($chunk) > 1 ? 'border-bottom: 1px dashed #cbd5e1; margin-bottom: 2%;' : '' }}">
                    @include('journals.partials.voucher', ['entry' => $entry])
                </div>
            @endforeach
        </div>
        @endforeach
    @else
        @foreach($entries as $entry)
        <div class="voucher-page">
            @include('journals.partials.voucher', ['entry' => $entry])
        </div>
        @endforeach
    @endif

</body>
</html>
