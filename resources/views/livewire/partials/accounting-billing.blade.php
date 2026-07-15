@php
    $B = $A['billing'];
    $bl = $B['labels'];
    $th = 'font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px;';
    $bd = 'border-top: 1px solid #F0EEE8;';
    $billSiteName = collect($B['rows'])->firstWhere('id', $billSite ?? '')['name'] ?? '';
@endphp

{{-- ============ CONTRACT · PROGRESS BILLING (M4) ============ --}}

{{-- KPI row --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 13px;">
    <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #16181D;"></span>
        <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $bl['kpi_contract'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 23px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px;">{{ $B['totContractLabel'] }}</div>
    </div>
    <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #E85D2A;"></span>
        <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $bl['kpi_thisBill'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 23px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px; color: #E85D2A;">{{ $B['totThisLabel'] }}</div>
        <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $B['monthLabel'] }}</div>
    </div>
    <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #3B72E0;"></span>
        <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $bl['kpi_cumBill'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 23px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px; color: #3B72E0;">{{ $B['totCumLabel'] }}</div>
    </div>
    <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #1F9D6B;"></span>
        <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $bl['kpi_pct'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 23px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px;">{{ $B['overallPct'] }}%</div>
        <div style="height: 5px; border-radius: 3px; background: #F4F3EE; overflow: hidden; margin-top: 8px;"><span style="display: block; height: 100%; width: {{ min(100, $B['overallPct']) }}%; border-radius: 3px; background: #1F9D6B;"></span></div>
    </div>
</div>

{{-- table --}}
<div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden; margin-top: 16px;">
    @if(count($B['rows']))
        <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; font-variant-numeric: tabular-nums; min-width: 860px;">
            <thead>
                <tr style="text-align: left;">
                    <th style="{{ $th }} padding-left: 16px;">{{ $bl['col_site'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $bl['col_contract'] }}</th>
                    <th style="{{ $th }}">{{ $bl['col_pct'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $bl['col_this'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $bl['col_cum'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $bl['col_remain'] }}</th>
                    @if($B['canManage'])<th style="{{ $th }} text-align: right; padding-right: 16px;"></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($B['rows'] as $r)
                    <tr wire:key="bill-{{ $r['id'] }}" onmouseover="this.style.background='#FAF9F5'" onmouseout="this.style.background='transparent'">
                        <td style="{{ $bd }} padding: 12px 12px 12px 16px;">
                            <div style="font-weight: 600;">{{ $r['name'] }}</div>
                            <div style="font-size: 11px; color: #A7A49B;">{{ $r['gc'] }}</div>
                        </td>
                        <td style="{{ $bd }} padding: 12px; text-align: right; font-weight: 600;">
                            @if($r['hasContract']){{ $r['amountLabel'] }}@else<span style="color: #C4C1B8; font-weight: 400;">{{ $bl['noContract'] }}</span>@endif
                        </td>
                        <td style="{{ $bd }} padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 9px;">
                                <span style="height: 6px; flex: 1; min-width: 60px; max-width: 110px; border-radius: 4px; background: #F4F3EE; overflow: hidden;"><span style="display: block; height: 100%; width: {{ min(100, $r['cumPct']) }}%; border-radius: 4px; background: {{ $r['cumPct'] >= 100 ? '#1F9D6B' : '#3B72E0' }};"></span></span>
                                <span style="font-weight: 700; color: #16181D; min-width: 42px;">{{ $r['cumPct'] }}%</span>
                            </div>
                        </td>
                        <td style="{{ $bd }} padding: 12px; text-align: right; font-weight: 700; color: {{ $r['thisBill'] > 0 ? '#C0641F' : '#C4C1B8' }};">{{ $r['thisBillLabel'] }}</td>
                        <td style="{{ $bd }} padding: 12px; text-align: right; font-weight: 600;">{{ $r['cumBillLabel'] }}</td>
                        <td style="{{ $bd }} padding: 12px; text-align: right; color: #5A5D64;">{{ $r['remainingLabel'] }}</td>
                        @if($B['canManage'])
                            <td style="{{ $bd }} padding: 12px 16px 12px 12px; text-align: right; white-space: nowrap;">
                                <button wire:click="openContract('{{ $r['id'] }}')" style="padding: 6px 11px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; color: #16181D; font-size: 12px; font-weight: 600; cursor: pointer;">{{ $bl['editContract'] }}</button>
                                <button wire:click="openProgress('{{ $r['id'] }}')" @disabled(!$r['hasContract']) style="margin-left: 6px; padding: 6px 11px; border: none; border-radius: 8px; background: {{ $r['hasContract'] ? '#16181D' : '#C4C1B8' }}; color: #fff; font-size: 12px; font-weight: 700; cursor: {{ $r['hasContract'] ? 'pointer' : 'not-allowed' }};">{{ $bl['editProgress'] }}</button>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td style="padding: 13px 12px 13px 16px; border-top: 2px solid #16181D; font-weight: 700;">{{ $bl['total'] }}</td>
                    <td style="padding: 13px 12px; border-top: 2px solid #16181D; text-align: right; font-weight: 700;">{{ $B['totContractLabel'] }}</td>
                    <td style="padding: 13px 12px; border-top: 2px solid #16181D; font-weight: 700;">{{ $B['overallPct'] }}%</td>
                    <td style="padding: 13px 12px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; color: #C0641F;">{{ $B['totThisLabel'] }}</td>
                    <td style="padding: 13px 12px; border-top: 2px solid #16181D; text-align: right; font-weight: 700;">{{ $B['totCumLabel'] }}</td>
                    <td style="padding: 13px 12px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; color: #5A5D64;">{{ $B['totRemainLabel'] }}</td>
                    @if($B['canManage'])<td style="border-top: 2px solid #16181D;"></td>@endif
                </tr>
            </tfoot>
        </table>
        </div>
    @else
        <div style="padding: 54px 16px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $bl['empty'] }}</div>
    @endif
    <div style="padding: 12px 16px; border-top: 1px solid #F0EEE8; font-size: 11.5px; color: #8A8880; display: flex; align-items: center; gap: 7px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3B72E0" stroke-width="2" style="flex-shrink: 0;"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
        {{ $bl['billFormula'] }}@unless($B['canManage']) · {{ $bl['lockedNote'] }}@endunless
    </div>
</div>

{{-- ---------- contract / progress modal ---------- --}}
@if($billModal)
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="width: 400px; max-width: 100%; background: #fff; border-radius: 18px; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <div style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700;">{{ $billModal === 'contract' ? $bl['setContract'] : $bl['setProgress'] }}</div>
                <button wire:click="closeBill" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>
            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 16px;">{{ $billSiteName }}@if($billModal === 'progress') · {{ $B['monthLabel'] }}@endif</div>

            @if($billModal === 'contract')
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $bl['amount'] }}</span>
                    <input type="number" step="0.01" min="0" wire:model="billAmount" autofocus style="width: 100%; padding: 11px 13px; border: 1.5px solid #E4E2DB; border-radius: 10px; font-size: 15px; font-weight: 600; outline: none; background: #FAFAF8;">
                </label>
            @else
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $bl['pctInput'] }}</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" step="0.1" min="0" max="100" wire:model="billPct" autofocus style="flex: 1; padding: 11px 13px; border: 1.5px solid #E4E2DB; border-radius: 10px; font-size: 15px; font-weight: 600; outline: none; background: #FAFAF8;">
                        <span style="font-size: 16px; font-weight: 700; color: #8A8880;">%</span>
                    </div>
                </label>
            @endif
            <label style="display: block; margin-top: 12px;">
                <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $bl['note'] }}</span>
                <input type="text" wire:model="billNote" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
            </label>

            <div style="display: flex; gap: 10px; margin-top: 18px;">
                <button wire:click="closeBill" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $bl['cancel'] }}</button>
                <button wire:click="{{ $billModal === 'contract' ? 'saveContract' : 'saveProgress' }}" style="flex: 1.6; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $bl['save'] }}</button>
            </div>
        </div>
    </div>
@endif
