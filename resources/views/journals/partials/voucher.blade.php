@php
    $useRepeatHeader = $useRepeatHeader ?? true;
    $isPdf           = $isPdf ?? true;
    $logoPath        = $isPdf ? public_path('images/logo.png') : asset('images/logo.png');
    $logoExists      = $isPdf ? file_exists(public_path('images/logo.png')) : true;
@endphp

@if(!$useRepeatHeader)
{{-- ====== STATIC HEADER (A4 PDF mode - no thead repetition) ====== --}}
<div class="voucher-static-header">
    <div style="overflow: hidden; margin-bottom: 12px;">
        <div style="float: left; width: 60%;">
            <div class="header-move-name">{{ $entry->move_name }}</div>
        </div>
        <div style="float: right; width: 40%; text-align: right;">
            @if($logoExists)<img src="{{ $logoPath }}" class="logo-img" alt="Logo">@endif
        </div>
        <div style="clear: both;"></div>
    </div>
    <div style="overflow: hidden; padding-bottom: 10px; border-bottom: 1px solid #cbd5e1; margin-bottom: 8px;">
        <div style="float: left; width: 50%; padding-right: 20px; font-size: 11px; color: #334155;">
            <span class="info-label">Reference</span>
            <span style="margin-left: 5px;">{{ $entry->ref }}{{ $entry->payment_reference ? ($entry->ref ? ' / ' : '').$entry->payment_reference : '' }}</span>
        </div>
        <div style="float: right; width: 50%; font-size: 11px; color: #334155;">
            <div style="margin-bottom: 3px;">
                <span class="info-label" style="display: inline-block; width: 130px;">Accounting Date</span>
                <span>{{ \Carbon\Carbon::parse($entry->date)->locale('id')->isoFormat('D MMMM YYYY') }}</span>
            </div>
            <div>
                <span class="info-label" style="display: inline-block; width: 130px;">Journal</span>
                <span>{{ $entry->journal_name }}</span>
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>
</div>
@endif

<table class="lines-table">
    <thead>
        @if($useRepeatHeader)
        {{-- ====== REPEATING HEADER INSIDE THEAD (A5 mode) - uses divs, NOT nested tables ====== --}}
        <tr>
            <td colspan="5" class="voucher-header-cell">
                <div style="overflow: hidden; margin-bottom: 12px;">
                    <div style="float: left; width: 60%;">
                        <div class="header-move-name">{{ $entry->move_name }}</div>
                    </div>
                    <div style="float: right; width: 40%; text-align: right;">
                        @if($logoExists)<img src="{{ $logoPath }}" class="logo-img" alt="Logo">@endif
                    </div>
                    <div style="clear: both;"></div>
                </div>
                <div style="overflow: hidden; padding-bottom: 8px;">
                    <div style="float: left; width: 50%; padding-right: 20px; font-size: 11px; color: #334155;">
                        <span class="info-label">Reference</span>
                        <span style="margin-left: 5px;">{{ $entry->ref }}{{ $entry->payment_reference ? ($entry->ref ? ' / ' : '').$entry->payment_reference : '' }}</span>
                    </div>
                    <div style="float: right; width: 50%; font-size: 11px; color: #334155;">
                        <div style="margin-bottom: 3px;">
                            <span class="info-label" style="display: inline-block; width: 130px;">Accounting Date</span>
                            <span>{{ \Carbon\Carbon::parse($entry->date)->locale('id')->isoFormat('D MMMM YYYY') }}</span>
                        </div>
                        <div>
                            <span class="info-label" style="display: inline-block; width: 130px;">Journal</span>
                            <span>{{ $entry->journal_name }}</span>
                        </div>
                    </div>
                    <div style="clear: both;"></div>
                </div>
            </td>
        </tr>
        @endif
        {{-- Column Headers --}}
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
                <td class="col-label">
                    {{ $line->display_name }}
                    @php $billRef = !empty($line->ref) ? $line->ref : $entry->payment_reference; @endphp
                    @if($billRef)<br><small>({{ $billRef }})</small>@endif
                </td>
                <td class="col-debit text-right">Rp&nbsp;{{ $line->debit == 0 ? '0' : number_format($line->debit, 0, ',', '.') }}</td>
                <td class="col-credit text-right">Rp&nbsp;{{ $line->credit == 0 ? '0' : number_format($line->credit, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr><td colspan="5" style="padding: 5px 0;"></td></tr>
        <tr class="totals-row">
            <td colspan="3"></td>
            <td class="text-right">Rp&nbsp;{{ number_format($totalDebit, 0, ',', '.') }}</td>
            <td class="text-right">Rp&nbsp;{{ number_format($totalCredit, 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
