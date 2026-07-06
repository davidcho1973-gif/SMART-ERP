{{-- ============ PROJECTS / SITES & COMPANIES ============ --}}
<div>
    {{-- site geofences: location + radius used to verify on-site clock-ins --}}
    @if(!empty($projects['sites']))
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B72E0" stroke-width="2"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <span style="font-size: 15px; font-weight: 700;">{{ $L['pj_siteGeo'] }}</span>
            </div>
            <div style="font-size: 12px; color: #8A8880; margin-bottom: 14px; line-height: 1.4;">{{ $L['pj_siteGeoHint'] }}</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px;">
                @foreach($projects['sites'] as $st)
                    <div style="display: flex; align-items: center; gap: 12px; padding: 13px 15px; border: 1px solid #F0EEE8; border-radius: 12px; background: #FAFAF8;">
                        <div style="flex: 1;">
                            <div style="font-size: 14px; font-weight: 600;">{{ $st['name'] }}</div>
                            <div style="font-size: 12px; color: #8A8880; margin-top: 2px;">{{ $st['city'] }}</div>
                            @if($st['hasGeo'])
                                <div style="font-size: 11.5px; color: #1F9D6B; margin-top: 4px; font-family: 'Space Grotesk';">{{ $st['coords'] }} · {{ $L['pj_radius'] }} {{ $st['radius'] }}m</div>
                            @else
                                <div style="font-size: 11.5px; color: #C0522B; margin-top: 4px;">{{ $L['pj_noGeo'] }}</div>
                            @endif
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <button wire:click="openSiteModal('{{ $st['id'] }}')" style="padding: 8px 13px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['pj_setLocation'] }}</button>
                            @if($can['sitesDelete'] ?? true)<button wire:click="askDeleteSite('{{ $st['id'] }}')" title="{{ $L['pj_delete'] }}" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #F3D9CB; border-radius: 8px; background: #fff; cursor: pointer;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D9483B" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div style="display: flex; align-items: center; margin-bottom: 18px;">
        <div style="flex: 1;"></div>
        @if($can['companiesCreate'] ?? true)<button wire:click="openCompanyModal" style="padding: 11px 18px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_create'] }}</button>@endif
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 18px;">
        @foreach($projects['companyCards'] as $c)
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; overflow: hidden;">
                <div style="padding: 18px 20px; border-bottom: 1px solid #F0EEE8; display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                    <div>
                        <div style="font-size: 17px; font-weight: 700;">{{ $c['name'] }}</div>
                        <div style="font-size: 12px; color: #8A8880; margin-top: 2px;">{{ $c['site'] }} · {{ $L['pj_gc'] }} {{ $c['gc'] }}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <button wire:click="openEditCompany('{{ $c['id'] }}')" title="{{ $L['pj_edit'] }}" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; cursor: pointer;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5A5D64" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>
                        @if($can['companiesDelete'] ?? true)<button wire:click="askDeleteCompany('{{ $c['id'] }}')" title="{{ $L['pj_delete'] }}" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #F3D9CB; border-radius: 8px; background: #fff; cursor: pointer;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D9483B" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>@endif
                        <button wire:click="openTeamModal('{{ $c['id'] }}')" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #FAFAF8; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['pj_newTeam'] }}</button>
                    </div>
                </div>
                <div style="padding: 8px 14px 14px;">
                    @foreach($c['teams'] as $t)
                        <div style="padding: 12px; border-radius: 12px; margin-top: 8px; background: #FAFAF8; border: 1px solid #F0EEE8;">
                            <div style="display: flex; align-items: center; gap: 9px;">
                                <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $t['color'] }};"></span>
                                <span style="flex: 1; font-size: 14px; font-weight: 600;">{{ $t['name'] }}</span>
                                <span style="font-family: 'Space Grotesk'; font-size: 12px; color: #1F9D6B; font-weight: 600;">{{ $t['present'] }}/{{ $t['count'] }} {{ $L['pj_present'] }}</span>
                                <button wire:click="openEditTeam('{{ $t['id'] }}')" title="{{ $L['pj_edit'] }}" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #E4E2DB; border-radius: 7px; background: #fff; cursor: pointer;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5A5D64" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>
                                <button wire:click="askDeleteTeam('{{ $t['id'] }}')" title="{{ $L['pj_delete'] }}" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #F3D9CB; border-radius: 7px; background: #fff; cursor: pointer;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#D9483B" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
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
            <div style="font-size: 17px; font-weight: 700; margin-bottom: 18px;">{{ $projects['editingCompany'] ? $L['pj_editCompanyT'] : $L['pj_newCompanyT'] }}</div>
            <label style="display: block; margin-bottom: 14px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_companyName'] }}</span><input wire:model="newCoName" placeholder="Copper State Electric" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_site'] }}</span><input wire:model="newCoSite" placeholder="TSMC Fab 21 · Phoenix, AZ" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelCompany" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="saveCompany" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $projects['editingCompany'] ? $L['pj_saveEdit'] : $L['pj_save'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- new team modal --}}
@if($teamModal)
    <div style="position: fixed; inset: 0; z-index: 70; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $projects['editingTeam'] ? $L['pj_editTeamT'] : $L['pj_newTeamT'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 18px;">{{ $projects['teamModalCo'] }}</div>
            <label style="display: block; margin-bottom: 14px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_teamName'] }}</span><input wire:model="newTeamName" placeholder="Electrical Crew B" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_selectLead'] }}</span><select wire:model="newTeamLead" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($projects['teamLeadOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelTeam" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="saveTeam" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $projects['editingTeam'] ? $L['pj_saveEdit'] : $L['pj_save'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- delete company confirm --}}
@if($projects['delCompanyName'])
    <div style="position: fixed; inset: 0; z-index: 75; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $L['pj_delCompanyT'] }}</div>
            <div style="font-size: 13.5px; color: #16181D; margin-top: 6px; font-weight: 600;">{{ $projects['delCompanyName'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-top: 8px; line-height: 1.5;">{{ $L['pj_delCompanyMsg'] }}</div>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelDeleteCompany" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="confirmDeleteCompany" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #D9483B; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_confirmDelete'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- site geofence (location + radius) modal --}}
@if($projects['siteModal'])
    <div style="position: fixed; inset: 0; z-index: 72; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 420px; max-width: 100%; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $L['pj_siteEditT'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 16px;">{{ $projects['siteModal']['name'] }}</div>

            {{-- name + city --}}
            <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                <label style="flex: 2;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_siteName'] }}</span><input wire:model="siteName" placeholder="TSMC Fab 21" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                <label style="flex: 1;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_siteCity'] }}</span><input wire:model="siteCity" placeholder="Phoenix, AZ" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
            </div>
            <div style="height: 1px; background: #F0EEE8; margin: 14px 0 16px;"></div>

            {{-- use my current position ("현재 위치로 설정") --}}
            <button type="button" x-data
                @click="
                    if (!navigator.geolocation) return;
                    $el.disabled = true;
                    navigator.geolocation.getCurrentPosition(
                        p => { $wire.setSiteCurrentLocation(p.coords.latitude, p.coords.longitude); $el.disabled = false; },
                        () => { $el.disabled = false; },
                        { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                    );
                "
                style="width: 100%; padding: 12px; border: none; border-radius: 11px; background: #16181D; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 14px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>{{ $L['pj_useCurrent'] }}
            </button>

            {{-- or: look up an address (geocoded to lat/lng) --}}
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <span style="flex: 1; height: 1px; background: #EDEBE4;"></span>
                <span style="font-size: 11px; color: #A7A49B; text-transform: uppercase; letter-spacing: 0.04em;">{{ $L['pj_or'] }}</span>
                <span style="flex: 1; height: 1px; background: #EDEBE4;"></span>
            </div>
            <label style="display: block; margin-bottom: 16px;">
                <span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_address'] }}</span>
                <div style="display: flex; gap: 8px; margin-top: 5px;">
                    <input wire:model="siteAddress" wire:keydown.enter.prevent="geocodeSiteAddress" placeholder="{{ $L['pj_addressPh'] }}" style="flex: 1; min-width: 0; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/>
                    <button wire:click="geocodeSiteAddress" wire:loading.attr="disabled" wire:target="geocodeSiteAddress" style="padding: 11px 16px; border: 1px solid #E4E2DB; border-radius: 10px; background: #FAFAF8; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                        <span wire:loading.remove wire:target="geocodeSiteAddress">{{ $L['pj_lookup'] }}</span>
                        <span wire:loading wire:target="geocodeSiteAddress">…</span>
                    </button>
                </div>
            </label>

            <div style="display: flex; gap: 10px;">
                <label style="flex: 1;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_lat'] }}</span><input wire:model="siteLat" placeholder="33.78380" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                <label style="flex: 1;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_lng'] }}</span><input wire:model="siteLng" placeholder="-112.15000" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
            </div>
            <label style="display: block; margin-top: 14px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['pj_radiusM'] }}</span><input wire:model="siteRadius" type="number" min="10" placeholder="150" style="width: 100%; margin-top: 5px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>

            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelSiteModal" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="saveSiteGeo" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_saveEdit'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- delete site confirm --}}
@if($projects['delSiteName'])
    <div style="position: fixed; inset: 0; z-index: 75; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $L['pj_delSiteT'] }}</div>
            <div style="font-size: 13.5px; color: #16181D; margin-top: 6px; font-weight: 600;">{{ $projects['delSiteName'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-top: 8px; line-height: 1.5;">
                {{ $L['pj_delSiteMsg'] }}
                @if($projects['delSiteCompanies'] > 0)
                    <br><span style="color: #C0522B; font-weight: 600;">{{ $projects['delSiteCompanies'] }} {{ $L['pj_delSiteCos'] }}</span>
                @endif
            </div>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelDeleteSite" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="confirmDeleteSite" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #D9483B; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_confirmDelete'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- delete crew confirm --}}
@if($projects['delTeamName'])
    <div style="position: fixed; inset: 0; z-index: 75; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 26px;">
            <div style="font-size: 17px; font-weight: 700;">{{ $L['pj_delTeamT'] }}</div>
            <div style="font-size: 13.5px; color: #16181D; margin-top: 6px; font-weight: 600;">{{ $projects['delTeamName'] }}</div>
            <div style="font-size: 12.5px; color: #8A8880; margin-top: 8px; line-height: 1.5;">{{ $L['pj_delTeamMsg'] }}</div>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelDeleteTeam" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                <button wire:click="confirmDeleteTeam" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #D9483B; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_confirmDelete'] }}</button>
            </div>
        </div>
    </div>
@endif
