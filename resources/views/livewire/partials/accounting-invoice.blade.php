@php
    $I = $accounting['invoice'];
    $il = $I['labels'];
    $ith = 'font-size: 10px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 10px 9px 9px; white-space: nowrap;';
    $itd = 'padding: 11px 9px; border-top: 1px solid #F0EEE8; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap;';
@endphp

{{-- ============ M7 · 기성청구서 (integrated progress-billing statement) ============ --}}
<div style="display: flex; flex-direction: column; gap: 16px;">

    @if($I['hasAny'])
        {{-- action bar (hidden on print) --}}
        <div class="wf-noprint" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div style="font-size: 12.5px; color: #8A8880; line-height: 1.5; max-width: 62ch;">{{ $il['note'] }}</div>
            <button onclick="window.print()" style="display: inline-flex; align-items: center; gap: 7px; padding: 10px 16px; border: none; border-radius: 10px; background: #16181D; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>{{ $il['print'] }}
            </button>
        </div>

        {{-- ===== the printable sheet ===== --}}
        <div id="wf-invoice-sheet" style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 26px 28px;">

            {{-- statement header --}}
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; border-bottom: 2px solid #16181D; padding-bottom: 16px;">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px;">
                        <span style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 12px; color: #E85D2A; background: #FDF0EA; padding: 3px 9px; border-radius: 7px;">M7</span>
                        <span style="font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B;">NAHSHON MEP</span>
                    </div>
                    <div style="font-family: 'Space Grotesk'; font-size: 23px; font-weight: 700; margin-top: 9px; letter-spacing: -0.01em;">{{ $il['title'] }}</div>
                    <div style="font-size: 12.5px; color: #8A8880; margin-top: 3px;">{{ $il['subtitle'] }}</div>
                </div>
                <div style="text-align: right; font-size: 12px; color: #5A5D64;">
                    <div><span style="color: #A7A49B;">{{ $il['for'] }}:</span> <strong style="color: #16181D;">{{ $I['monthLabel'] }}</strong></div>
                    <div style="margin-top: 3px;"><span style="color: #A7A49B;">{{ $il['generated'] }}:</span> {{ now()->format('M j, Y') }}</div>
                </div>
            </div>

            {{-- KPI band --}}
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 18px;">
                <div style="border: 1px solid #EFEDE7; border-radius: 12px; padding: 13px 15px;">
                    <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $il['kpi_this'] }}</div>
                    <div style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; margin-top: 6px; color: #16181D;">{{ $I['totThisLabel'] }}</div>
                </div>
                <div style="border: 1px solid #EFEDE7; border-radius: 12px; padding: 13px 15px;">
                    <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $il['kpi_cost'] }}</div>
                    <div style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; margin-top: 6px; color: #C0641F;">{{ $I['totCostLabel'] }}</div>
                </div>
                <div style="border: 1px solid {{ $I['positive'] ? '#CFEBE0' : '#F1C9C4' }}; border-radius: 12px; padding: 13px 15px; background: {{ $I['positive'] ? '#F2FAF6' : '#FDF3F2' }};">
                    <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $il['kpi_margin'] }}</div>
                    <div style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; margin-top: 6px; color: {{ $I['positive'] ? '#1F9D6B' : '#D9483B' }};">{{ $I['totMarginLabel'] }}@if($I['marginPct'] !== null)<span style="font-size: 12px; font-weight: 600;"> · {{ $I['marginPct'] }}%</span>@endif</div>
                </div>
                <div style="border: 1px solid #EFEDE7; border-radius: 12px; padding: 13px 15px;">
                    <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $il['kpi_progress'] }}</div>
                    <div style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; margin-top: 6px; color: #3B72E0;">{{ $I['overallPct'] }}%</div>
                </div>
            </div>

            {{-- integrated table --}}
            <div style="overflow-x: auto; margin-top: 20px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 880px;">
                <thead>
                    <tr>
                        <th style="{{ $ith }} text-align: left; padding-left: 4px;"></th>
                        <th colspan="4" style="{{ $ith }} text-align: center; border-bottom: 1px solid #EFEDE7; color: #3B72E0;">{{ $il['billing_h'] }}</th>
                        <th colspan="5" style="{{ $ith }} text-align: center; border-bottom: 1px solid #EFEDE7; color: #C0641F;">{{ $il['cost_h'] }}</th>
                        <th style="{{ $ith }} text-align: right;"></th>
                    </tr>
                    <tr>
                        <th style="{{ $ith }} text-align: left; padding-left: 4px;">{{ $il['col_site'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_contract'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_pct'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_this'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_remain'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_labor'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_material'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_expense'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_equipment'] }}</th>
                        <th style="{{ $ith }} text-align: right;">{{ $il['col_cost'] }}</th>
                        <th style="{{ $ith }} text-align: right; padding-right: 4px;">{{ $il['col_margin'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($I['rows'] as $r)
                        <tr>
                            <td style="padding: 11px 9px 11px 4px; border-top: 1px solid #F0EEE8;">
                                <div style="font-weight: 700;">{{ $r['name'] }}</div>
                                <div style="font-size: 10.5px; color: #A7A49B; margin-top: 1px;">{{ $r['gc'] }}</div>
                            </td>
                            <td style="{{ $itd }} font-weight: 600;">{{ $r['hasContract'] ? $r['amountLabel'] : '—' }}</td>
                            <td style="{{ $itd }} color: #3B72E0; font-weight: 600;">{{ $r['hasContract'] ? $r['cumPct'].'%' : '—' }}</td>
                            <td style="{{ $itd }} font-weight: 700; color: #16181D;">{{ $r['thisBillLabel'] }}</td>
                            <td style="{{ $itd }} color: #8A8880;">{{ $r['hasContract'] ? $r['remainingLabel'] : '—' }}</td>
                            <td style="{{ $itd }} color: #E85D2A;">{{ $r['laborLabel'] }}</td>
                            <td style="{{ $itd }} color: #3B72E0;">{{ $r['materialLabel'] }}</td>
                            <td style="{{ $itd }} color: #C0641F;">{{ $r['expenseLabel'] }}</td>
                            <td style="{{ $itd }} color: #0EA5A0;">{{ $r['equipmentLabel'] }}</td>
                            <td style="{{ $itd }} font-weight: 700; color: #C0641F;">{{ $r['monthCostLabel'] }}</td>
                            <td style="{{ $itd }} padding-right: 4px; font-weight: 700; color: {{ $r['positive'] ? '#1F9D6B' : '#D9483B' }};">{{ $r['marginLabel'] }}@if($r['marginPct'] !== null)<div style="font-size: 10px; font-weight: 600; color: #A7A49B;">{{ $r['marginPct'] }}%</div>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td style="padding: 13px 9px 13px 4px; border-top: 2px solid #16181D; font-weight: 700;">{{ $il['total'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; font-weight: 700;">{{ $I['totContractLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; font-weight: 700; color: #3B72E0;">{{ $I['overallPct'] }}%</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; font-weight: 700;">{{ $I['totThisLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; font-weight: 700; color: #8A8880;">{{ $I['totRemainLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; color: #E85D2A;">{{ $I['totLaborLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; color: #3B72E0;">{{ $I['totMaterialLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; color: #C0641F;">{{ $I['totExpenseLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; color: #0EA5A0;">{{ $I['totEquipmentLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; font-weight: 700; color: #C0641F;">{{ $I['totCostLabel'] }}</td>
                        <td style="{{ $itd }} border-top: 2px solid #16181D; padding-right: 4px; font-weight: 700; font-size: 13.5px; color: {{ $I['positive'] ? '#1F9D6B' : '#D9483B' }};">{{ $I['totMarginLabel'] }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>

            <div style="margin-top: 16px; padding-top: 13px; border-top: 1px solid #F0EEE8; font-size: 11px; color: #A7A49B; line-height: 1.6;">{{ $il['note'] }}</div>
        </div>
    @else
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 48px 34px; text-align: center; max-width: 560px; margin: 12px auto;">
            <span style="display: inline-flex; width: 54px; height: 54px; border-radius: 15px; background: #FDF0EA; color: #E85D2A; align-items: center; justify-content: center; margin-bottom: 12px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h2"/></svg></span>
            <div style="font-family: 'Space Grotesk'; font-size: 19px; font-weight: 700;">{{ $il['title'] }}</div>
            <div style="font-size: 13.5px; color: #5A5D64; margin-top: 8px; line-height: 1.6;">{{ $il['empty'] }}</div>
        </div>
    @endif
</div>

<style>
    @media print {
        body * { visibility: hidden !important; }
        #wf-invoice-sheet, #wf-invoice-sheet * { visibility: visible !important; }
        #wf-invoice-sheet { position: absolute; left: 0; top: 0; width: 100%; border: none !important; border-radius: 0 !important; padding: 0 !important; }
        .wf-noprint { display: none !important; }
    }
</style>
