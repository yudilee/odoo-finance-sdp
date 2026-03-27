<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Print Journal Entries</title>
    <style>
        /* Base styles */
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
        }
        .print-container {
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
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
        }
        .lines-table td {
            padding: 10px 5px;
            font-size: 10px;
            word-wrap: break-word;
        }
        .col-account { width: 20%; padding-right: 10px; }
        .col-partner { width: 17%; padding-right: 10px; }
        .col-label { width: 23%; padding-right: 10px; }
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
            white-space: nowrap;
        }
        .lines-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }

        /* Print Specifics */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: none;
            }
            .print-container {
                box-shadow: none;
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            /* Determine page setup based on var */
            @if(($paperSize ?? 'A5') === 'A4')
                @page { size: A4 portrait; margin: 10mm; }
                .voucher-page:not(:last-child) {
                    page-break-after: always;
                    height: 277mm; /* A4 height approx minus margins */
                    box-sizing: border-box;
                }
                .voucher-page:last-child {
                    page-break-after: auto;
                }
                .voucher-wrapper {
                    height: 48%; /* Slightly less than 50 to avoid spilling */
                    overflow: hidden;
                    box-sizing: border-box;
                    padding: 10px 0;
                }
                .voucher-divider {
                    border-bottom: 1px dashed #cbd5e1;
                    margin-bottom: 2%;
                }
            @else
                @page { size: A5 landscape; margin: 10mm; }
                .voucher-page:not(:last-child) {
                    page-break-after: always;
                }
                .voucher-wrapper {
                    height: auto;
                    padding: 0;
                }
            @endif
        }
        
        /* Screen preview styling matches print media queries above */
        @media screen {
            @if(($paperSize ?? 'A5') === 'A4')
                .voucher-page:not(:last-child) {
                    margin-bottom: 20px;
                    page-break-after: always;
                }
                .voucher-wrapper {
                    height: 48%;
                    overflow: hidden;
                    box-sizing: border-box;
                    padding: 10mm 0;
                }
                .voucher-divider {
                    border-bottom: 1px dashed #cbd5e1;
                    margin-bottom: 2%;
                }
            @else
                .voucher-page:not(:last-child) {
                    page-break-after: always;
                    margin-bottom: 20px;
                }
            @endif
        }
    </style>
</head>
<body>
    <div class="print-container">
        @if(($paperSize ?? 'A5') === 'A4')
            @foreach($entries->chunk(2) as $chunk)
            <div class="voucher-page">
                @foreach($chunk as $entry)
                    <div class="voucher-wrapper {{ $loop->first && count($chunk) > 1 ? 'voucher-divider' : '' }}">
                        @include('journals.partials.voucher', ['entry' => $entry])
                    </div>
                @endforeach
            </div>
            @endforeach
        @else
            @foreach($entries as $entry)
            <div class="voucher-page">
                <div class="voucher-wrapper">
                    @include('journals.partials.voucher', ['entry' => $entry])
                </div>
            </div>
            @endforeach
        @endif
    </div>

    <!-- Auto trigger print dialog -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
