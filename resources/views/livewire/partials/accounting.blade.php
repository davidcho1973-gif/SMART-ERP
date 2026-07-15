@php
    $A = $accounting;
@endphp

@if(!$A)
    <div style="padding: 60px 20px; text-align: center; color: #A7A49B; font-size: 14px;">—</div>
@else
@php
    $lab = $A['labels'];
    $tab = $A['tab'];
    $tabs = [
        'dashboard' => $lab['tab_dashboard'],
        'expenses'  => $lab['tab_expenses'],
        'materials' => $lab['tab_materials'],
        'billing'   => $lab['tab_billing'],
        'invoice'   => $lab['tab_invoice'],
    ];
    $liveTabs = ['dashboard', 'expenses'];   // dashboard + expenses·receipts are live
@endphp

{{-- ============ ACCOUNTING ============ --}}
<div style="display: flex; flex-direction: column; gap: 20px;">

    {{-- sub-tab bar --}}
    <div style="display: flex; gap: 4px; background: #fff; border: 1px solid #E4E2DB; border-radius: 13px; padding: 5px; overflow-x: auto;">
        @foreach($tabs as $key => $label)
            @php $on = $tab === $key; $isLive = in_array($key, $liveTabs, true); @endphp
            <button wire:click="setAcctTab('{{ $key }}')"
                style="display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; padding: 9px 15px; border: none; border-radius: 9px; font-size: 13px; font-weight: {{ $on ? '700' : '600' }}; cursor: pointer; background: {{ $on ? '#16181D' : 'transparent' }}; color: {{ $on ? '#fff' : '#5A5D64' }};"
                @if(!$on) onmouseover="this.style.background='#F4F3EE'" onmouseout="this.style.background='transparent'" @endif>
                {{ $label }}
                @unless($isLive)
                    <span style="font-size: 9.5px; font-weight: 700; letter-spacing: .03em; padding: 2px 6px; border-radius: 20px; background: {{ $on ? 'rgba(255,255,255,0.16)' : '#F0EEE8' }}; color: {{ $on ? '#fff' : '#A7A49B' }};">{{ $lab['soon'] }}</span>
                @endunless
            </button>
        @endforeach
    </div>

    @if($tab === 'dashboard')
        {{-- ---------- month navigator ---------- --}}
        <div style="display: flex; align-items: center; gap: 10px;">
            <button wire:click="acctMonthShift(-1)" title="{{ $lab['prevMonth'] }}" style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #16181D; cursor: pointer;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div style="min-width: 130px; text-align: center; font-family: 'Space Grotesk'; font-weight: 700; font-size: 16px;">{{ $A['periodLabel'] }}</div>
            <button wire:click="acctMonthShift(1)" @disabled($A['isThisMonth']) title="{{ $lab['nextMonth'] }}" style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: {{ $A['isThisMonth'] ? '#C4C1B8' : '#16181D' }}; cursor: {{ $A['isThisMonth'] ? 'not-allowed' : 'pointer' }};">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            @unless($A['isThisMonth'])
                <button wire:click="acctMonthShift(0)" style="padding: 8px 13px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #E85D2A; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $lab['thisMonth'] }}</button>
            @endunless
        </div>

        @php
            $totCost = $A['totalLabor'] + $A['totalExpense'];
            $laborPct = $totCost > 0 ? round($A['totalLabor'] / $totCost * 100) : 0;
            $expPct = $totCost > 0 ? (100 - $laborPct) : 0;
            $money = fn ($n) => \App\Support\Money::usd($n);
            $maxTot = 0.0;
            foreach ($A['siteRows'] as $rr) { $maxTot = max($maxTot, $rr['labor'] + $rr['expense']); }
        @endphp

        {{-- ---------- KPI row (color rail + hierarchy) ---------- --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 13px;">
            <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
                <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #16181D;"></span>
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $lab['comp_title'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px;">{{ $money($totCost) }}</div>
                <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $A['periodLabel'] }}</div>
            </div>
            <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
                <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #E85D2A;"></span>
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;"><span style="color: #E85D2A;">●</span> {{ $A['pillars'][0]['name'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px; color: #E85D2A;">{{ $A['totalLaborLabel'] }}</div>
                <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $laborPct }}%</div>
            </div>
            <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
                <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #C98A1E;"></span>
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;"><span style="color: #C98A1E;">●</span> {{ $A['pillars'][1]['name'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px; color: #C98A1E;">{{ $A['totalExpenseLabel'] }}</div>
                <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $expPct }}%</div>
            </div>
            <div style="position: relative; overflow: hidden; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px 17px;">
                <span style="position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #1F9D6B;"></span>
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $lab['kpi_head'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; letter-spacing: -0.02em; margin-top: 9px;">{{ $A['totalHead'] }}<span style="font-size: 15px; color: #A7A49B; font-weight: 600;"> · {{ $A['siteCount'] }}</span></div>
                <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $lab['kpi_sites'] }}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.55fr 1fr; gap: 16px; margin-top: 16px;" class="wf-acct-grid">
            {{-- ---------- cost by site ---------- --}}
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 18px 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                    <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 14.5px;">{{ $lab['sites_title'] }}</div>
                    <div style="font-size: 12px; color: #A7A49B;">{{ $A['periodLabel'] }}</div>
                </div>
                @if(count($A['siteRows']))
                    <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; font-variant-numeric: tabular-nums;">
                        <thead>
                            <tr style="text-align: left;">
                                <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 10px 11px;">{{ $lab['col_site'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 10px 11px; text-align: right;">{{ $lab['col_labor'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 10px 11px; text-align: right;">{{ $lab['col_expense'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 10px 11px; text-align: right;">{{ $lab['total'] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($A['siteRows'] as $r)
                                @php $rowTot = $r['labor'] + $r['expense']; $share = $maxTot > 0 ? round($rowTot / $maxTot * 100) : 0; @endphp
                                <tr>
                                    <td style="padding: 12px 10px; border-top: 1px solid #F0EEE8;">
                                        <div style="font-weight: 600;">{{ $r['name'] }}</div>
                                        <div style="font-size: 11px; color: #A7A49B; margin-top: 1px; display: inline-flex; align-items: center; gap: 4px;">{{ $r['gc'] }} · <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>{{ $r['headcount'] }}</div>
                                        <div style="height: 5px; width: 120px; max-width: 100%; border-radius: 3px; background: #F4F3EE; overflow: hidden; margin-top: 7px;"><span style="display: block; height: 100%; width: {{ $share }}%; border-radius: 3px; background: #E85D2A;"></span></div>
                                    </td>
                                    <td style="padding: 12px 10px; border-top: 1px solid #F0EEE8; text-align: right; font-weight: 600;">{{ $r['laborLabel'] }}</td>
                                    <td style="padding: 12px 10px; border-top: 1px solid #F0EEE8; text-align: right; color: {{ $r['expense'] > 0 ? '#C0641F' : '#C4C1B8' }};">{{ $r['expenseLabel'] }}</td>
                                    <td style="padding: 12px 10px; border-top: 1px solid #F0EEE8; text-align: right; font-weight: 700;">{{ $money($rowTot) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="padding: 13px 10px; border-top: 2px solid #16181D; font-weight: 700;">{{ $lab['total'] }}</td>
                                <td style="padding: 13px 10px; border-top: 2px solid #16181D; text-align: right; font-weight: 700;">{{ $A['totalLaborLabel'] }}</td>
                                <td style="padding: 13px 10px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; color: #C0641F;">{{ $A['totalExpenseLabel'] }}</td>
                                <td style="padding: 13px 10px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; font-size: 15px;">{{ $money($totCost) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                @else
                    <div style="padding: 40px 8px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $lab['empty'] }}</div>
                @endif
            </div>

            {{-- ---------- cost composition (donut) ---------- --}}
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 18px 20px; display: flex; flex-direction: column;">
                <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 14.5px; margin-bottom: 16px;">{{ $lab['comp_title'] }}</div>
                <div style="display: flex; justify-content: center; margin-bottom: 18px;">
                    <div style="position: relative; width: 168px; height: 168px; border-radius: 50%; background: {{ $totCost > 0 ? 'conic-gradient(#E85D2A 0 '.$laborPct.'%, #C98A1E '.$laborPct.'% 100%)' : '#EDEBE4' }};">
                        <div style="position: absolute; inset: 25px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                            <span style="font-size: 10.5px; color: #A7A49B; font-weight: 600;">{{ $A['periodLabel'] }}</span>
                            <span style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; letter-spacing: -0.02em; margin-top: 2px;">{{ $money($totCost) }}</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @foreach($A['pillars'] as $p)
                        @php $pct = $totCost > 0 ? round($p['amount'] / $totCost * 100) : 0; @endphp
                        <div style="display: flex; align-items: center; gap: 9px; font-size: 12.5px;">
                            <span style="width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; background: {{ $p['live'] ? $p['color'] : '#DAD7CE' }};"></span>
                            <span style="font-weight: 600; color: {{ $p['live'] ? '#16181D' : '#A7A49B' }};">{{ $p['name'] }}</span>
                            @unless($p['live'])<span style="font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 20px; background: #F0EEE8; color: #A7A49B;">{{ $lab['soon'] }}</span>@endunless
                            <span style="margin-left: auto; font-weight: 700; font-variant-numeric: tabular-nums; color: {{ $p['live'] ? '#16181D' : '#C4C1B8' }};">{{ $p['live'] ? $p['label'] : '—' }}</span>
                            @if($p['live'])<span style="width: 36px; text-align: right; color: #A7A49B; font-weight: 600; font-variant-numeric: tabular-nums;">{{ $pct }}%</span>@else<span style="width: 36px;"></span>@endif
                        </div>
                    @endforeach
                </div>
                <div style="margin-top: 18px; padding-top: 14px; font-size: 11.5px; line-height: 1.6; color: #8A8880; border-top: 1px solid #F0EEE8; display: flex; gap: 8px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                    <span>{{ $lab['live_note'] }}</span>
                </div>
            </div>
        </div>

    @elseif($tab === 'expenses')
        @include('livewire.partials.accounting-expenses')

    @else
        {{-- ---------- module placeholder (harmonious "coming soon") ---------- --}}
        @php
            $soonKey = 'soon_'.$tab;
            $soonDesc = $lab[$soonKey] ?? '';
            $stepMap = ['expenses' => 'M2', 'materials' => 'M3', 'billing' => 'M4·M5', 'invoice' => 'M7'];
        @endphp
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 48px 34px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 14px; max-width: 560px; margin: 12px auto;">
            <span style="display: inline-flex; width: 56px; height: 56px; border-radius: 16px; background: #FDF0EA; color: #E85D2A; align-items: center; justify-content: center;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
            </span>
            <div style="display: inline-flex; align-items: center; gap: 8px;">
                <span style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 12px; color: #E85D2A; background: #FDF0EA; padding: 3px 9px; border-radius: 7px;">{{ $stepMap[$tab] ?? '' }}</span>
                <span style="font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $lab['soon'] }}</span>
            </div>
            <div style="font-family: 'Space Grotesk'; font-size: 19px; font-weight: 700;">{{ $tabs[$tab] }}</div>
            <div style="font-size: 13.5px; line-height: 1.65; color: #5A5D64; max-width: 44ch;">{{ $soonDesc }}</div>
        </div>
    @endif

</div>

<style>
    @media (max-width: 900px) { .wf-acct-grid { grid-template-columns: 1fr !important; } }
</style>
@endif
