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
        .lines-table > tbody > tr {
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

        .voucher-header-cell {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            background-color: white !important;
            padding: 8px 0 15px 0 !important;
        }

        /* Page-break header inserted by JS */
        .page-break-header {
            page-break-before: always;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .page-break-header .header-move-name {
            font-size: 26px;
            font-weight: bold;
            color: #1a237e;
            letter-spacing: -0.5px;
        }

        @if(($paperSize ?? 'A5') === 'A4')
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
                .voucher-entry { display: none; }
            @else
                @page { size: A5 landscape; margin: 10mm; }
                /* Disable Chrome's native thead repetition - JS handles this */
                .lines-table thead {
                    display: table-row-group;
                }
                .voucher-entry {
                    box-shadow: none;
                    padding: 0;
                    margin-bottom: 0;
                }
                .voucher-page { display: none; }
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
            {{-- A5 Mode: 1 voucher per page, with JS-based header repetition --}}
            @foreach($entries as $entry)
            <div class="voucher-entry" data-move-name="{{ $entry->move_name }}">
                @include('journals.partials.voucher', ['entry' => $entry, 'isPdf' => false, 'useRepeatHeader' => false])
            </div>
            @endforeach
        @endif
    </div>

    <script>
    (function() {
        var paperSize = @json($paperSize ?? 'A5');

        if (paperSize !== 'A5') {
            // A4 mode: just print directly
            window.onload = function() {
                setTimeout(function() { window.print(); }, 500);
            };
            return;
        }

        // A5 mode: split each voucher-entry's table rows into pages
        // and insert a cloned header before each page break

        // Approximate page height in px for A5 landscape with 10mm margins
        // A5 landscape: 148mm height, minus 20mm margins = 128mm usable ≈ 484px at 96dpi
        var PAGE_HEIGHT_PX = 484;

        function buildHeaderHTML(entry) {
            var staticHeader = entry.querySelector('.voucher-static-header');
            if (staticHeader) {
                return staticHeader.outerHTML;
            }
            return '';
        }

        function insertPageBreakHeaders() {
            var entries = document.querySelectorAll('.voucher-entry');

            entries.forEach(function(entry, entryIdx) {
                var table = entry.querySelector('.lines-table');
                if (!table) return;

                var headerHTML = buildHeaderHTML(entry);
                var thRow = table.querySelector('thead tr');
                var thRowHTML = thRow ? '<table class="lines-table"><thead>' + thRow.outerHTML + '</thead></table>' : '';

                var tbody = table.querySelector('tbody');
                if (!tbody) return;

                var rows = tbody.querySelectorAll('tr');
                var currentHeight = 0;

                // Measure the static header roughly
                var staticHeader = entry.querySelector('.voucher-static-header');
                var headerHeight = staticHeader ? staticHeader.offsetHeight : 0;
                var theadHeight = table.querySelector('thead') ? table.querySelector('thead').offsetHeight : 0;
                currentHeight = headerHeight + theadHeight;

                var pageBreakPoints = [];

                rows.forEach(function(row, rowIdx) {
                    var rowHeight = row.offsetHeight || 40;
                    currentHeight += rowHeight;

                    if (currentHeight > PAGE_HEIGHT_PX && rowIdx > 0) {
                        pageBreakPoints.push(rowIdx);
                        currentHeight = headerHeight + theadHeight + rowHeight;
                    }
                });

                // Insert page break + header clone before each break point (in reverse to preserve indices)
                for (var i = pageBreakPoints.length - 1; i >= 0; i--) {
                    var breakRow = rows[pageBreakPoints[i]];
                    var pageBreakDiv = document.createElement('tr');
                    pageBreakDiv.className = 'page-break-header-row';
                    pageBreakDiv.innerHTML = '<td colspan="5" style="padding:0; border:none;">' +
                        '<div class="page-break-header">' +
                        headerHTML +
                        thRowHTML +
                        '</div></td>';
                    breakRow.parentNode.insertBefore(pageBreakDiv, breakRow);
                }

                // Add page-break-before: always to each new entry (except first)
                if (entryIdx > 0) {
                    entry.style.pageBreakBefore = 'always';
                }
            });
        }

        window.onload = function() {
            // Insert page-break headers, then print
            setTimeout(function() {
                insertPageBreakHeaders();
                setTimeout(function() { window.print(); }, 300);
            }, 500);
        };
    })();
    </script>
</body>
</html>
