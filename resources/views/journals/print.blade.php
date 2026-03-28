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
            padding: 0;
            background: #f1f5f9;
        }
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
        }
        /* Screen: each entry looks like a page */
        .voucher-entry {
            padding: 20px 25px;
            background: #fff;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            margin-bottom: 20px;
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
            border-top: 1px solid #1e293b;
            border-bottom: 1px solid #475569;
            font-weight: bold;
            padding: 8px 5px;
            color: #000000;
            font-size: 11px;
        }
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

        /* Repeating header cell - used in A5 mode */
        .voucher-header-cell {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            background-color: white !important;
            padding: 8px 0 15px 0 !important;
        }

        @if(($paperSize ?? 'A5') === 'A4')
        /* A4: screen preview shows 2 per A4-like block */
        .voucher-page {
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            margin-bottom: 20px;
            background: #fff;
            padding: 15px 25px;
        }
        .voucher-half {
            padding: 10px 0;
            box-sizing: border-box;
        }
        .voucher-half:first-child {
            border-bottom: 1px dashed #94a3b8;
            margin-bottom: 10px;
        }
        @endif

        /* ========= PRINT SPECIFICS ========= */
        @media print {
            body { margin: 0; padding: 0; background: none; }
            .print-container { box-shadow: none; max-width: none; width: 100%; margin: 0; padding: 0; }

            /* Tell browser to treat thead as a repeating header group */
            .lines-table thead {
                display: table-header-group;
            }

            @if(($paperSize ?? 'A5') === 'A4')
                @page { size: A4 portrait; margin: 10mm; }
                .voucher-page {
                    box-shadow: none;
                    padding: 5mm;
                    margin-bottom: 0;
                    page-break-after: always;
                    page-break-inside: avoid;
                }
                .voucher-page:last-child { page-break-after: auto; }
                .voucher-half {
                    height: 128mm;
                    overflow: hidden;
                    padding: 5px 0;
                    box-sizing: border-box;
                }
                .voucher-half:first-child {
                    border-bottom: 1px dashed #94a3b8;
                    margin-bottom: 5px;
                }
                .voucher-entry { display: none; } /* not used in A4 */
            @else
                @page { size: A5 landscape; margin: 10mm; }
                .voucher-entry {
                    box-shadow: none;
                    padding: 0;
                    margin-bottom: 0;
                    page-break-after: always;
                }
                .voucher-entry:last-child { page-break-after: auto; }
                .voucher-page { display: none; } /* not used in A5 */
            @endif
        }
    </style>
</head>
<body>
    <div class="print-container">
        @if(($paperSize ?? 'A5') === 'A4')
            {{-- A4 Mode: 2 vouchers per page --}}
            @foreach($entries->chunk(2) as $chunk)
            <div class="voucher-page">
                @foreach($chunk as $entry)
                    <div class="voucher-half">
                        @include('journals.partials.voucher', ['entry' => $entry, 'isPdf' => false, 'useRepeatHeader' => false])
                    </div>
                @endforeach
            </div>
            @endforeach
        @else
            {{-- A5 Mode: 1 voucher per page, free-flowing with sticky header --}}
            @foreach($entries as $entry)
            <div class="voucher-entry">
                @include('journals.partials.voucher', ['entry' => $entry, 'isPdf' => false, 'useRepeatHeader' => true])
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
