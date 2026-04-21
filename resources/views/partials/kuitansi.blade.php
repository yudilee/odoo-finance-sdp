<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Kuitansi</title>
    <style>
        @page {
            size: a5 landscape;
            margin: 8mm 14mm;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; }

        .kuitansi-page {
            position: relative;
            page-break-after: always;
            width: 100%;
        }
        .kuitansi-page:last-child { page-break-after: auto; }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 45%;
            left: 40%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 70px;
            color: rgba(255, 0, 0, 0.07);
            font-weight: bold;
            white-space: nowrap;
            z-index: -1;
        }

        /* ── HEADER ── */
        .company-name { font-size: 12px; font-weight: bold; }
        .company-addr { font-size: 9px;  color: #334155; line-height: 1.4; }
        .kuitansi-ttl { font-size: 28px; font-weight: bold; color: #000; letter-spacing: 2px; }

        .header-divider {
            border: none;
            border-top: 1.5px solid #000;
            margin: 4px 0 10px 0;
            width: 100%;
        }

        /* ── FULL-WIDTH BODY ROWS ── */
        .body-table { width: 100%; }
        .body-table td { padding: 2px 0; vertical-align: top; font-size: 10px; }
        .lbl   { font-weight: normal; white-space: nowrap; width: 130px; }
        .colon { width: 12px; }
        .val   { color: #1e293b; }

        /* ── BOTTOM TWO-COLUMN SPLIT ── */
        .bottom-left  { width: 55%; vertical-align: top; padding-right: 16px; }
        .bottom-right { width: 45%; vertical-align: bottom; padding-left: 16px; }

        /* Amount block */
        .amount-border-top {
            border-top: 2.5px solid #000;
            border-bottom: 1px solid #000;
            height: 3px;
            margin-bottom: 3px;
        }
        .amount-rp {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            padding: 2px 0;
        }
        .amount-border-bottom {
            border-top: 1px solid #000;
            border-bottom: 2.5px solid #000;
            height: 3px;
            margin-top: 3px;
        }
        .disclaimer {
            font-size: 9px;
            font-style: italic;
            color: #334155;
            margin-top: 6px;
            line-height: 1.5;
        }

        /* Signature */
        .sig-wrap { width: 100%; }
        .sig-date-row td {
            text-align: center;
            font-size: 10px;
            padding-bottom: 12px;
        }
        .sig-cell {
            text-align: center;
            padding: 0 12px;
            font-size: 10px;
            vertical-align: bottom;
        }
        .sig-name-line {
            margin-top: 50px;
            border-top: 1px solid #000;
            padding-top: 3px;
            font-weight: bold;
            font-size: 10px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
@foreach($invoices as $invoice)
<div class="kuitansi-page" style="{{ !$loop->last ? 'page-break-after: always;' : '' }}">

    @if(($enableWatermark ?? '1') === '1' && isset($invoice->kuitansi_print_count) && $invoice->kuitansi_print_count > 0)
        <div class="watermark">DUPLICATE - {{ $invoice->kuitansi_print_count }}</div>
    @endif

    @php
        $isPdf   = !isset($isHtml) || !$isHtml;
        $logoSrc = $isPdf ? public_path('images/logo.png') : asset('images/logo.png');

        // Description logic: Use manual override if exists AND use_override parameter is 1
        $useOverride = request('use_override', '0') === '1';
        if ($useOverride && !empty($invoice->kuitansi_pembayaran)) {
            $descArr = array_filter(array_map('trim', explode("\n", $invoice->kuitansi_pembayaran)));
            $descSummary = implode("\n", $descArr);
        } else {
            $lines     = $invoice->lines ?? collect();
            $descLines = $lines->filter(fn($l) => !empty($l->clean_description) && ($l->quantity ?? 0) > 0);
            $descArr   = $descLines->take(3)->pluck('clean_description')->toArray();
            if ($descLines->count() > 3) {
                $descArr[] = '...dan ' . ($descLines->count() - 3) . ' item lainnya';
            }
            $descSummary = empty($descArr)
                ? ($invoice->narration ?? '-')
                : implode(', ', $descArr);
        }

        // Address: strip partner name from top
        $addr     = $invoice->partner_address ?? $invoice->partner_address_complete ?? '';
        $addr     = preg_replace('/^' . preg_quote($invoice->partner_name, '/') . '[\r\n]*/i', '', $addr);
        $addrRows = array_filter(array_map('trim', explode("\n", trim($addr))));

        // Due date
        $dueDate = null;
        if (!empty($invoice->invoice_date_due)) {
            try { $dueDate = \Carbon\Carbon::parse($invoice->invoice_date_due); } catch (\Exception $e) {}
        }

        // Invoice date
        $invDate = $invoice->invoice_date ?? null;

        // Manager / SPV — invoice first, then settings fallback
        $managerName = !empty($invoice->manager_name)
            ? $invoice->manager_name
            : \App\Models\Setting::get('default_bc_manager', '');
        $spvName = !empty($invoice->spv_name)
            ? $invoice->spv_name
            : \App\Models\Setting::get('default_bc_spv', '');
    @endphp

    {{-- ═══════════ HEADER (full width) ═══════════ --}}
    <table style="width:100%;">
        <tr>
            <td style="width:60%; vertical-align:middle;">
                <table>
                    <tr>
                        <td style="width:58px; vertical-align:middle;">
                            <img src="{{ $logoSrc }}" style="max-height:40px; max-width:58px;" alt="Logo">
                        </td>
                        <td style="vertical-align:middle; padding-left:8px;">
                            <div class="company-name">PT. SURYA DARMA PERKASA</div>
                            <div class="company-addr">
                                JL. DAAN MOGOT KM.1 NO. 99, JAKARTA BARAT<br>
                                (021) 56977708, 5661060
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:40%; vertical-align:middle; text-align:right;">
                <div class="kuitansi-ttl">KUITANSI</div>
            </td>
        </tr>
    </table>

    <hr class="header-divider">

    {{-- ═══════════ BODY ROWS — FULL WIDTH, SINGLE COLUMN ═══════════ --}}
    <table class="body-table">
        <tr>
            <td class="lbl">NO.</td>
            <td class="colon"></td>
            <td class="val">{{ $invoice->name }}</td>
        </tr>
        <tr>
            <td class="lbl" style="padding-top:4px;">TELAH TERIMA DARI</td>
            <td class="colon" style="padding-top:4px;"></td>
            <td class="val" style="padding-top:4px;">
                <strong>{{ $invoice->partner_name }}</strong>
                @foreach(array_slice($addrRows, 0, 4) as $line)
                    <br><span style="color:#334155;">{{ $line }}</span>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="lbl" style="padding-top:5px;">UANG SEJUMLAH</td>
            <td class="colon" style="padding-top:5px;"></td>
            <td class="val" style="padding-top:5px;">
                <strong>{{ ucwords(\App\Helpers\Terbilang::convert($invoice->amount_total)) }} Rupiah</strong>
            </td>
        </tr>
        <tr>
            <td class="lbl">UNTUK PEMBAYARAN</td>
            <td class="colon"></td>
            <td class="val">{!! nl2br(e($descSummary)) !!}</td>
        </tr>
        <tr><td colspan="3" style="height:6px;"></td></tr>
        @php
            $kontrakPo = !empty($invoice->contract_ref) ? $invoice->contract_ref : (!empty($invoice->ref) ? $invoice->ref : null);
            $showContract = request('show_contract', '0') === '1';
        @endphp
        @if($kontrakPo && $showContract)
        <tr>
            <td class="lbl">NOMOR KONTRAK / PO</td>
            <td class="colon"></td>
            <td class="val">{{ $kontrakPo }}</td>
        </tr>
        @endif
        <tr>
            <td class="lbl">JATUH TEMPO</td>
            <td class="colon"></td>
            <td class="val">
                @if($dueDate)
                    {{ $dueDate->format('d/m/Y') }}
                @elseif(!empty($invoice->payment_term))
                    {{ $invoice->payment_term }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">REKENING</td>
            <td class="colon"></td>
            <td class="val">
                @if(!empty($invoice->partner_bank))
                    A/N PT. SURYA DARMA PERKASA / A/C {{ str_contains($invoice->partner_bank ?? '', '1170004641403') ? '1170004641403 - MANDIRI KCU Kyai Tapa Jakarta' : $invoice->partner_bank }}
                @else
                    -
                @endif
            </td>
        </tr>
    </table>

    {{-- ═══════════ AMOUNT & DISCLAIMER ═══════════ --}}
    <div style="margin-top: 15px; width: 60%;">
        <div class="amount-border-top"></div>
        <div class="amount-rp">Rp &nbsp; {{ number_format($invoice->amount_total, 0, ',', '.') }}</div>
        <div class="amount-border-bottom"></div>
        <div class="disclaimer">
            Kuitansi akan berlaku sebagai bukti pembayaran yang sah apabila<br>
            dana sudah efektif di rekening PT. Surya Darma Perkasa.
        </div>
    </div>

    {{-- ═══════════ SIGNATURE SECTION — PUSHED TO BOTTOM RIGHT ═══════════ --}}
    <div style="margin-top: 20px; text-align: right;">
        <table class="sig-wrap" style="width: 45%; margin-left: auto;">
            <tr class="sig-date-row">
                <td colspan="2">
                    Jakarta, {{ $invDate ? $invDate->format('d F Y') : now()->format('d F Y') }}
                </td>
            </tr>
            <tr>
                <td class="sig-cell">
                    <div class="sig-name-line">{{ $managerName ?: str_repeat('&nbsp;', 25) }}</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-name-line">{{ $spvName ?: str_repeat('&nbsp;', 25) }}</div>
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
    @media print {
        @page { size: a5 landscape; margin: 0; }
        body  { margin: 8mm 14mm; }
        .kuitansi-page { overflow: hidden; page-break-after: always; }
        .kuitansi-page:last-of-type { page-break-after: auto; }
    }
</style>
@endif
</body>
</html>
