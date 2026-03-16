<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Journal Entries</title>
    <!-- Using Google Fonts for Space Mono -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap');
        
        @page {
            margin: 40px 50px;
        }
        body {
            /* Fallback to Helvetica for headers, but table will use Space Mono */
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
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
            /* Use Space Mono for the entire data table */
            font-family: 'Space Mono', monospace;
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
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
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

    @foreach($entries as $entry)
    <div class="voucher-page">
        
        <table class="header-table">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="header-move-name">{{ $entry->move_name }}</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    @php
                        $logoPath = public_path('images/logo.png');
                    @endphp
                    @if(file_exists($logoPath))
                        <img src="{{ $logoPath }}" class="logo-img" alt="Logo">
                    @endif
                </td>
            </tr>
        </table>
        
        <table class="info-table">
            <tr>
                <td style="width: 50%; padding-right: 20px;">
                    <table style="width: 100%; table-layout: auto;">
                        <tr>
                            <td class="info-label">Reference</td>
                            <td>{{ $entry->ref }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%;">
                    <table style="width: 100%; table-layout: auto;">
                        <tr>
                            <td class="info-label" style="width: 130px;">Accounting Date</td>
                            <td>{{ \Carbon\Carbon::parse($entry->date)->locale('id')->isoFormat('D MMMM YYYY') }}</td>
                        </tr>
                        <tr>
                            <td class="info-label" style="width: 130px;">Journal</td>
                            <td>{{ $entry->journal_name }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <table class="lines-table">
            <thead>
                <tr>
                    <th class="col-account">Account</th>
                    <th class="col-partner">Partner</th>
                    <th class="col-label">Label</th>
                    <th class="col-debit text-right">Debit</th>
                    <th class="col-credit text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalDebit = 0;
                    $totalCredit = 0;
                @endphp
                @foreach($entry->lines as $line)
                    @php
                        $totalDebit += $line->debit;
                        $totalCredit += $line->credit;
                    @endphp
                    <tr>
                        <td class="col-account">{{ $line->account_code }}<br>{{ $line->account_name }}</td>
                        <td class="col-partner">{{ $entry->partner_name }}</td>
                        <td class="col-label">{{ $line->display_name }}</td>
                        <td class="col-debit text-right">Rp&nbsp;{{ $line->debit == 0 ? '0' : number_format($line->debit, 0, ',', '.') }}</td>
                        <td class="col-credit text-right">Rp&nbsp;{{ $line->credit == 0 ? '0' : number_format($line->credit, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="5" style="padding: 5px 0;"></td>
                </tr>
                <tr class="totals-row">
                    <td colspan="3"></td>
                    <td class="text-right">Rp&nbsp;{{ number_format($totalDebit, 0, ',', '.') }}</td>
                    <td class="text-right">Rp&nbsp;{{ number_format($totalCredit, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        
    </div>
    @endforeach

</body>
</html>
