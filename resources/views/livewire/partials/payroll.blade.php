{{-- ============ PAYROLL ============ --}}
<div>
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 18px; flex-wrap: wrap;">
        <div style="background: #16181D; color: #fff; border-radius: 14px; padding: 16px 22px; display: flex; align-items: center; gap: 22px;">
            <div><div style="font-size: 12px; color: rgba(255,255,255,0.55);">{{ $L['p_period'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 15px; font-weight: 600; margin-top: 2px;">{{ $pay['periodLabel'] }}</div></div>
            <div style="width: 1px; height: 32px; background: rgba(255,255,255,0.15);"></div>
            <div><div style="font-size: 12px; color: rgba(255,255,255,0.55);">{{ $L['p_total'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 22px; font-weight: 700; margin-top: 2px; color: #E85D2A;">{{ $pay['totalPayout'] }}</div></div>
        </div>
        <div style="flex: 1;"></div>
        {{-- find a worker by badge NFC tag or QR-code number → open their attendance history --}}
        <form wire:submit.prevent="findByBadge" style="display: flex; align-items: center; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px; padding: 0 12px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; height: 42px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8A8880" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7V5a2 2 0 0 1 2-2h2M15 3h2a2 2 0 0 1 2 2v2M19 15v2a2 2 0 0 1-2 2h-2M9 19H7a2 2 0 0 1-2-2v-2"/><path d="M8 12h8"/></svg>
                <input wire:model="badgeLookup" placeholder="{{ $L['p_badgePh'] }}" style="border: none; outline: none; background: transparent; font-size: 13.5px; width: 190px; color: #16181D;">
            </div>
            <button type="submit" style="padding: 11px 16px; border: none; border-radius: 11px; background: #16181D; color: #fff; font-size: 13.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 7px;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>{{ $L['p_badgeFind'] }}</button>
        </form>
        {{-- payroll register export: pick the settlement period + recipients, then download the .xlsx --}}
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 6px 9px;">
            <input type="date" wire:model.live="payStart" style="border: none; outline: none; background: #F7F6F2; border-radius: 8px; padding: 8px 10px; font-size: 12.5px; font-family: 'Space Grotesk'; color: #16181D;">
            <span style="color: #A7A49B;">–</span>
            <input type="date" wire:model.live="payEnd" style="border: none; outline: none; background: #F7F6F2; border-radius: 8px; padding: 8px 10px; font-size: 12.5px; font-family: 'Space Grotesk'; color: #16181D;">
            <select wire:model.live="payRecipient" title="{{ $L['p_exRecipient'] }}" style="border: 1px solid #E4E2DB; outline: none; background: #fff; border-radius: 8px; padding: 8px 10px; font-size: 12.5px; color: #16181D; cursor: pointer; max-width: 200px;">
                @foreach($pay['recipientOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach
            </select>
            <a href="/export/payroll?start={{ $payStart }}&end={{ $payEnd }}&recipient={{ urlencode($payRecipient) }}&lang={{ $lang }}"
               style="padding: 9px 16px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12M8 11l4 4 4-4M5 21h14"/></svg>{{ $L['p_export'] }}</a>
        </div>
    </div>
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: auto;">
        <div style="display: grid; grid-template-columns: 1.8fr 1.1fr 0.9fr 0.9fr 1.2fr 1.2fr; gap: 8px; padding: 13px 20px; background: #FAFAF8; border-bottom: 1px solid #E4E2DB; font-size: 12px; font-weight: 600; color: #8A8880; min-width: 760px;">
            <span>{{ $L['e_name'] }}</span><span>{{ $L['p_rate'] }}</span><span style="text-align: right;">{{ $L['p_reg'] }}</span><span style="text-align: right;">{{ $L['p_ot'] }}</span><span style="text-align: right;">{{ $L['p_gross'] }}</span><span style="text-align: right;">{{ $L['p_net'] }}</span>
        </div>
        @foreach($pay['rows'] as $p)
            <div wire:click="openPayDetail({{ $p['id'] }})" style="display: grid; grid-template-columns: 1.8fr 1.1fr 0.9fr 0.9fr 1.2fr 1.2fr; gap: 8px; align-items: center; padding: 12px 20px; border-bottom: 1px solid #F2F0EA; font-size: 13px; min-width: 760px; cursor: pointer;">
                <span style="display: flex; align-items: center; gap: 10px;"><span style="display: inline-flex; width: 30px; height: 30px; border-radius: 50%; background: {{ $p['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; font-family: 'Space Grotesk';">{{ $p['initials'] }}</span><span style="font-weight: 600;">{{ $p['name'] }}</span></span>
                <span style="font-family: 'Space Grotesk'; color: #5A5D64;">{{ $p['rate'] }}/hr</span>
                <span style="text-align: right; font-family: 'Space Grotesk';">{{ $p['reg'] }}</span>
                <span style="text-align: right; font-family: 'Space Grotesk'; color: #1F9D6B;">{{ $p['ot'] }}</span>
                <span style="text-align: right; font-family: 'Space Grotesk';">{{ $p['gross'] }}</span>
                <span style="text-align: right; font-family: 'Space Grotesk'; font-weight: 700;">{{ $p['net'] }}@if($p['paid']) <span style="font-size: 10px; font-weight: 700; color: #1F9D6B; background: #E7F4EE; padding: 2px 7px; border-radius: 6px; vertical-align: middle;">{{ $L['p_paid'] }}</span>@endif</span>
            </div>
        @endforeach
    </div>

    {{-- payroll punch-history drawer --}}
    @if($pay['detail'])
        @php $pd = $pay['detail']; @endphp
        <div x-data @click="$wire.closePayDetail()" style="position: fixed; inset: 0; background: rgba(22,24,29,0.4); z-index: 60; display: flex; justify-content: flex-end;">
            <div @click.stop style="width: min(460px, 92vw); height: 100%; background: #F7F6F2; overflow: auto; box-shadow: -20px 0 50px rgba(0,0,0,0.18);">
                <div style="background: #16181D; color: #fff; padding: 22px 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 13px;">
                            <span style="display: inline-flex; width: 44px; height: 44px; border-radius: 50%; background: {{ $pd['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; font-family: 'Space Grotesk';">{{ $pd['initials'] }}</span>
                            <div><div style="font-size: 17px; font-weight: 700;">{{ $pd['name'] }}</div><div style="font-size: 12.5px; color: rgba(255,255,255,0.55);">{{ $pd['teamName'] }} · {{ $pd['role'] }}</div></div>
                        </div>
                        <button wire:click="closePayDetail" style="width: 34px; height: 34px; border-radius: 50%; border: none; background: rgba(255,255,255,0.12); color: #fff; font-size: 18px; cursor: pointer;">✕</button>
                    </div>
                    <div style="display: flex; gap: 20px; margin-top: 18px;">
                        <div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['p_reg'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700;">{{ $pd['reg'] }}</div></div>
                        <div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['p_ot'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; color: #4ADE80;">{{ $pd['ot'] }}</div></div>
                        <div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['p_gross'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; color: #FF6A2B;">{{ $pd['gross'] }}</div></div>
                        <div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['p_rate'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700;">{{ $pd['rate'] }}</div></div>
                    </div>
                </div>
                <div style="padding: 22px 24px;">
                    <div style="display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 14px; font-weight: 700;">{{ $pay['pdHistory'] }}</div>
                        <div style="font-size: 11.5px; color: #8A8880;">{{ $pay['pdPeriod'] }}</div>
                    </div>
                    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
                        @foreach($pd['days'] as $day)
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #F2F0EA;">
                                <span style="width: 64px; flex-shrink: 0;"><span style="display: block; font-size: 13px; font-weight: 600;">{{ $day['d'] }}</span><span style="display: block; font-size: 10.5px; color: #A7A49B;">{{ $day['dow'] }}</span></span>
                                <span style="flex: 1; display: flex; gap: 8px; font-family: 'Space Grotesk'; font-size: 12.5px; flex-wrap: wrap; align-items: center;"><span style="color: #1F9D6B;">↓ {{ $day['inFmt'] }}</span><span style="color: #8A8880;">↑ {{ $day['outFmt'] }}</span>@foreach($day['chips'] as $c)<span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: {{ $c['bg'] }}; color: {{ $c['color'] }};">{{ $c['label'] }}</span>@endforeach</span>
                                <span style="font-family: 'Space Grotesk'; font-size: 13px; font-weight: 700; background: #F4F3EF; padding: 3px 10px; border-radius: 8px;">{{ $day['paid'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button wire:click="openVoucher" style="width: 100%; margin-top: 16px; padding: 15px; border: none; border-radius: 13px; background: #16181D; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px;"><span style="font-size: 17px;">💵</span>{{ $L['pv_process'] }} · {{ $pd['gross'] }}</button>
                    <div style="font-size: 11px; color: #A7A49B; margin-top: 12px; line-height: 1.5;">{{ $pay['ruleNote'] }}</div>
                </div>
            </div>
        </div>
    @endif

    {{-- payment voucher --}}
    @if($payVoucher && $pay['voucher'])
        @php $v = $pay['voucher']; @endphp
        <div x-data @click="$wire.closeVoucher()" style="position: fixed; inset: 0; background: rgba(22,24,29,0.55); z-index: 80; display: flex; align-items: flex-start; justify-content: center; padding: 40px 20px; overflow: auto;">
            <div @click.stop style="width: 100%; max-width: 640px;">
                <div id="pay-print" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 30px 70px rgba(0,0,0,0.3);">
                    <div style="padding: 34px 40px 26px; border-bottom: 2px solid #16181D;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 11px;"><span style="display: inline-flex; width: 40px; height: 40px; border-radius: 9px; background: #FF6A2B; color: #fff; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; font-family: 'Space Grotesk';">N</span><span style="font-size: 22px; font-weight: 800; letter-spacing: 0.5px;">{{ $pay['companyName'] }}</span></div>
                                <div style="font-size: 12px; color: #8A8880; margin-top: 8px;">Phoenix, Arizona · MEP Workforce</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 19px; font-weight: 800; letter-spacing: 0.5px;">{{ $L['pv_title'] }}</div>
                                <div style="font-size: 11.5px; color: #8A8880; margin-top: 4px;">{{ $L['pv_sub'] }}</div>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 28px 40px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 22px 30px;">
                            <div><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B;">{{ $L['pv_payer'] }}</div><div style="font-size: 15px; font-weight: 700; margin-top: 4px;">{{ $pay['companyName'] }}</div></div>
                            <div><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B;">{{ $L['pv_payee'] }}</div><div style="font-size: 15px; font-weight: 700; margin-top: 4px;">{{ $v['name'] }}</div><div style="font-size: 12px; color: #8A8880; font-family: 'Space Grotesk';">{{ $v['empId'] }} · {{ $v['teamName'] }}</div></div>
                            <div><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B;">{{ $L['pv_period'] }}</div><div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $pay['periodLabel'] }}</div></div>
                            <div><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B;">{{ $L['pv_hours'] }}</div><div style="font-size: 14px; font-weight: 600; margin-top: 4px; font-family: 'Space Grotesk';">{{ $v['reg'] }} + {{ $v['ot'] }} OT · {{ $v['rate'] }}</div></div>
                        </div>
                        <div style="margin-top: 26px; background: #F7F6F2; border: 1px solid #E4E2DB; border-radius: 12px; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between;">
                            <div><div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px; color: #8A8880;">{{ $L['pv_amount'] }}</div><div style="font-size: 12px; color: #A7A49B; margin-top: 3px;">USD</div></div>
                            <div style="font-family: 'Space Grotesk'; font-size: 34px; font-weight: 800; color: #16181D;">{{ $v['gross'] }}</div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 22px;">
                            <label><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B; margin-bottom: 6px;">{{ $L['pv_check'] }}</div><input wire:model="checkNo" placeholder="{{ $L['pv_checkPh'] }}" style="width: 100%; padding: 11px 13px; border: 1px solid #16181D; border-radius: 9px; font-size: 15px; font-family: 'Space Grotesk'; font-weight: 600; outline: none;"/></label>
                            <label><div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #A7A49B; margin-bottom: 6px;">{{ $L['pv_date'] }}</div><input wire:model="payDate" style="width: 100%; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 9px; font-size: 15px; font-family: 'Space Grotesk'; font-weight: 600; outline: none;"/></label>
                        </div>
                        <div style="margin-top: 22px; font-size: 12px; color: #5A5D64; line-height: 1.6; padding: 14px 16px; background: #FAFAF8; border-left: 3px solid #FF6A2B; border-radius: 0 8px 8px 0;">{{ $L['pv_ack'] }}</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 44px;">
                            <div style="border-top: 1.5px solid #16181D; padding-top: 8px;"><div style="font-size: 11.5px; color: #8A8880;">{{ $L['pv_recipient'] }}</div><div style="font-size: 11px; color: #C9C6BD; margin-top: 3px;">{{ $v['name'] }}</div></div>
                            <div style="border-top: 1.5px solid #16181D; padding-top: 8px;"><div style="font-size: 11.5px; color: #8A8880;">{{ $L['pv_issuer'] }}</div><div style="font-size: 11px; color: #C9C6BD; margin-top: 3px;">{{ $pay['companyName'] }}</div></div>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button wire:click="closeVoucher" style="flex: 1; padding: 14px; border: 1px solid rgba(255,255,255,0.4); border-radius: 12px; background: rgba(255,255,255,0.12); color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; backdrop-filter: blur(6px);">✕</button>
                    <button wire:click="printVoucher" style="flex: 4; padding: 14px; border: none; border-radius: 12px; background: #FF6A2B; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px;">🖨 {{ $L['pv_print'] }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
