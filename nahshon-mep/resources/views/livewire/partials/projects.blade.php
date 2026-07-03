{{-- ============ PROJECTS / SITES & COMPANIES ============ --}}
<div>
    <div style="display: flex; align-items: center; margin-bottom: 18px;">
        <div style="flex: 1;"></div>
        <button wire:click="openCompanyModal" style="padding: 11px 18px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_create'] }}</button>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 18px;">
        @foreach($projects['companyCards'] as $c)
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; overflow: hidden;">
                <div style="padding: 18px 20px; border-bottom: 1px solid #F0EEE8; display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                    <div>
                        <div style="font-size: 17px; font-weight: 700;">{{ $c['name'] }}</div>
                        <div style="font-size: 12px; color: #8A8880; margin-top: 2px;">{{ $c['site'] }} · {{ $L['pj_gc'] }} {{ $c['gc'] }}</div>
                    </div>
                    <button wire:click="openTeamModal('{{ $c['id'] }}')" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #FAFAF8; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['pj_newTeam'] }}</button>
                </div>
                <div style="padding: 8px 14px 14px;">
                    @foreach($c['teams'] as $t)
                        <div style="padding: 12px; border-radius: 12px; margin-top: 8px; background: #FAFAF8; border: 1px solid #F0EEE8;">
                            <div style="display: flex; align-items: center; gap: 9px;">
                                <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $t['color'] }};"></span>
                                <span style="flex: 1; font-size: 14px; font-weight: 600;">{{ $t['name'] }}</span>
                                <span style="font-family: 'Space Grotesk'; font-size: 12px; color: #1F9D6B; font-weight: 600;">{{ $t['present'] }}/{{ $t['count'] }} {{ $L['pj_present'] }}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                                <span style="font-size: 11.5px; color: #8A8880;">{{ $L['pj_lead'] }}</span>
                                <span style="display: inline-flex; width: 24px; height: 24px; border-radius: 50%; background: #3B72E0; color: #fff; align-items: center; justify-content: center; font-size: 10px; font-weight: 600;">{{ $t['leadInit'] }}</span>
                                <select wire:change="changeLead('{{ $t['id'] }}', $event.target.value)" style="flex: 1; border: 1px solid #E4E2DB; border-radius: 8px; padding: 6px 8px; font-size: 12.5px; background: #fff; color: #16181D; cursor: pointer;">
                                    @foreach($t['leadOptions'] as $o)
                                        <option value="{{ $o['id'] }}" @selected($o['id'] === $t['leadId'])>{{ $o['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- new company modal --}}
@if($companyModal)
    <div style="position: fixed; inset: 0; z-index: 70; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700; margin-bottom: 18px;">{{ $L['pj_newCompanyT'] }}</div>
            <label style="display: block; margin-bottom: 14px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_companyName'] }}</span><input wire:model="newCoName" placeholder="Copper State Electric" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_site'] }}</span><input wire:model="newCoSite" placeholder="TSMC Fab 21 · Phoenix, AZ" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelCompany" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="saveCompany" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_save'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- new team modal --}}
@if($teamModal)
    <div style="position: fixed; inset: 0; z-index: 70; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $L['pj_newTeamT'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 18px;">{{ $projects['teamModalCo'] }}</div>
            <label style="display: block; margin-bottom: 14px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_teamName'] }}</span><input wire:model="newTeamName" placeholder="Electrical Crew B" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_selectLead'] }}</span><select wire:model="newTeamLead" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($projects['teamLeadOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelTeam" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="saveTeam" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_save'] }}</button>
            </div>
        </div>
    </div>
@endif
