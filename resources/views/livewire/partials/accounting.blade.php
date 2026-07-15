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

        {{-- ---------- KPI row ---------- --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 15px; padding: 17px 18px;">
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $lab['kpi_labor'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; margin-top: 7px; color: #E85D2A;">{{ $A['totalLaborLabel'] }}</div>
                <div style="font-size: 11.5px; color: #A7A49B; margin-top: 5px;">{{ $lab['kpi_period'] }} · {{ $A['periodLabel'] }}</div>
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 15px; padding: 17px 18px;">
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $lab['kpi_sites'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; margin-top: 7px;">{{ $A['siteCount'] }}</div>
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 15px; padding: 17px 18px;">
                <div style="font-size: 12px; color: #8A8880; font-weight: 600;">{{ $lab['kpi_head'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; margin-top: 7px;">{{ $A['totalHead'] }}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 16px;" class="wf-acct-grid">
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
                                <th style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 8px 10px;">{{ $lab['col_site'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 8px 10px;">{{ $lab['col_gc'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 8px 10px; text-align: right;">{{ $lab['col_head'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 8px 10px; text-align: right;">{{ $lab['col_labor'] }}</th>
                                <th style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 0 8px 10px; text-align: right;">{{ $lab['col_expense'] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($A['siteRows'] as $r)
                                <tr>
                                    <td style="padding: 11px 8px; border-top: 1px solid #F0EEE8;">
                                        <div style="font-weight: 600;">{{ $r['name'] }}</div>
                                        <div style="font-size: 11px; color: #A7A49B;">{{ $r['city'] }}</div>
                                    </td>
                                    <td style="padding: 11px 8px; border-top: 1px solid #F0EEE8; color: #5A5D64;">{{ $r['gc'] }}</td>
                                    <td style="padding: 11px 8px; border-top: 1px solid #F0EEE8; text-align: right; color: #5A5D64;">{{ $r['headcount'] }}</td>
                                    <td style="padding: 11px 8px; border-top: 1px solid #F0EEE8; text-align: right; font-weight: 700;">{{ $r['laborLabel'] }}</td>
                                    <td style="padding: 11px 8px; border-top: 1px solid #F0EEE8; text-align: right; color: {{ $r['expense'] > 0 ? '#C0641F' : '#C4C1B8' }}; font-weight: {{ $r['expense'] > 0 ? '600' : '400' }};">{{ $r['expenseLabel'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="padding: 12px 8px; border-top: 2px solid #16181D; font-weight: 700;">{{ $lab['total'] }}</td>
                                <td style="padding: 12px 8px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; color: #5A5D64;">{{ $A['totalHead'] }}</td>
                                <td style="padding: 12px 8px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; font-size: 15px;">{{ $A['totalLaborLabel'] }}</td>
                                <td style="padding: 12px 8px; border-top: 2px solid #16181D; text-align: right; font-weight: 700; color: #C0641F;">{{ $A['totalExpenseLabel'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                @else
                    <div style="padding: 34px 8px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $lab['empty'] }}</div>
                @endif
            </div>

            {{-- ---------- cost composition ---------- --}}
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 18px 20px; display: flex; flex-direction: column;">
                <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 14.5px; margin-bottom: 16px;">{{ $lab['comp_title'] }}</div>
                <div style="display: flex; flex-direction: column; gap: 13px;">
                    @foreach($A['pillars'] as $p)
                        @php $pct = $A['totalLabor'] > 0 ? round($p['amount'] / $A['totalLabor'] * 100) : 0; @endphp
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                                <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: {{ $p['live'] ? '#16181D' : '#A7A49B' }};">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $p['live'] ? $p['color'] : '#DAD7CE' }};"></span>{{ $p['name'] }}
                                    @unless($p['live'])<span style="font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 20px; background: #F0EEE8; color: #A7A49B;">{{ $lab['soon'] }}</span>@endunless
                                </span>
                                <span style="font-weight: 700; color: {{ $p['live'] ? '#16181D' : '#C4C1B8' }}; font-variant-numeric: tabular-nums;">{{ $p['label'] }}</span>
                            </div>
                            <div style="height: 8px; border-radius: 5px; background: #F4F3EE; overflow: hidden;">
                                <div style="height: 100%; border-radius: 5px; width: {{ $p['live'] ? $pct : 0 }}%; background: {{ $p['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div style="margin-top: auto; padding-top: 16px; font-size: 11.5px; line-height: 1.6; color: #8A8880; border-top: 1px solid #F0EEE8; margin-top: 18px; display: flex; gap: 8px;">
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
