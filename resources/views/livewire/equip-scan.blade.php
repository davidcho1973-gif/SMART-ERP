@php $Ui = \App\Support\Ui::class; @endphp
<div style="min-height: 100vh; background: linear-gradient(180deg,#EDECE7,#E6E4DD); display: flex; align-items: flex-start; justify-content: center; padding: 24px 16px;">
    <div style="width: 420px; max-width: 100%;">
        {{-- header --}}
        <div style="display: flex; align-items: center; gap: 10px; padding: 4px 4px 18px;">
            <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 9px; background: #E85D2A; align-items: center; justify-content: center; font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; color: #fff;">N</span>
            <div style="flex: 1;"><div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 15px; line-height: 1;">NAHSHON MEP</div><div style="font-size: 11px; color: #8A8880;">{{ $t['scanBy'] }}: {{ $meLabel ?? '—' }}</div></div>
            <div style="display: flex; gap: 2px; padding: 3px; background: #EAE8E1; border-radius: 8px;">
                <button wire:click="setLang('es')" style="{{ $Ui::mLang($lang==='es') }}">ES</button>
                <button wire:click="setLang('en')" style="{{ $Ui::mLang($lang==='en') }}">EN</button>
                <button wire:click="setLang('ko')" style="{{ $Ui::mLang($lang==='ko') }}">KO</button>
            </div>
        </div>

        @if(!$equip)
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 20px; padding: 44px 28px; text-align: center;">
                <span style="display: inline-flex; width: 54px; height: 54px; border-radius: 15px; background: #FBEBE9; color: #D9483B; align-items: center; justify-content: center; margin-bottom: 12px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></span>
                <div style="font-family: 'Space Grotesk'; font-size: 19px; font-weight: 700;">{{ $t['notFound'] }}</div>
                <div style="font-size: 13.5px; color: #5A5D64; margin-top: 8px; line-height: 1.6;">{{ $t['notFoundSub'] }}</div>
            </div>
        @else
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 20px; overflow: hidden;">
                {{-- cover photo --}}
                @if($cover)
                    <img src="{{ $cover }}" alt="" style="display: block; width: 100%; height: 190px; object-fit: cover; background: #16181D;">
                @else
                    <div style="height: 128px; background: #F4F2EC; display: flex; align-items: center; justify-content: center; color: #C4C1B8;"><svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 17h13V6H3zM16 9h4l3 3v5h-7z"/><circle cx="6.5" cy="17.5" r="1.5"/><circle cx="18.5" cy="17.5" r="1.5"/></svg></div>
                @endif

                <div style="padding: 20px 22px 24px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                        <div>
                            <div style="font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; line-height: 1.15;">{{ $equip->name }}</div>
                            @if($equip->type)<div style="font-size: 13px; color: #8A8880; margin-top: 3px;">{{ $equip->type }}</div>@endif
                        </div>
                        <span style="font-size: 11px; font-weight: 700; padding: 4px 11px; border-radius: 20px; background: {{ $st['bg'] }}; color: {{ $st['color'] }}; white-space: nowrap;">{{ $st['name'] }}</span>
                    </div>

                    {{-- facts --}}
                    <div style="margin-top: 16px; border: 1px solid #EFEDE7; border-radius: 12px; padding: 4px 14px;">
                        @if($siteName)<div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F4F2EC; font-size: 13px;"><span style="color: #A7A49B;">{{ $t['site'] }}</span><span style="font-weight: 600;">{{ $siteName }}</span></div>@endif
                        @if($holderName)<div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F4F2EC; font-size: 13px;"><span style="color: #A7A49B;">{{ $t['holder'] }}</span><span style="font-weight: 600;">{{ $holderName }}</span></div>@endif
                        @if($equip->serial)<div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F4F2EC; font-size: 13px;"><span style="color: #A7A49B;">{{ $t['serial'] }}</span><span style="font-weight: 600;">{{ $equip->serial }}</span></div>@endif
                        @if($equip->asset_tag)<div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F4F2EC; font-size: 13px;"><span style="color: #A7A49B;">{{ $t['tag'] }}</span><span style="font-weight: 600;">{{ $equip->asset_tag }}</span></div>@endif
                        @if($equip->meter !== null)<div style="display: flex; justify-content: space-between; padding: 9px 0; font-size: 13px;"><span style="color: #A7A49B;">{{ $t['meter'] }}</span><span style="font-weight: 600;">{{ rtrim(rtrim(number_format((float)$equip->meter, 1), '0'), '.') }} {{ $equip->meter_unit }}</span></div>@endif
                    </div>

                    {{-- actions --}}
                    @if($canCheckout)
                        <div style="margin-top: 18px; display: flex; flex-direction: column; gap: 10px;">
                            @if($equip->status !== 'out')
                                <button wire:click="checkout" style="display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 17px; border: none; border-radius: 15px; background: linear-gradient(180deg,#4B84EE,#3B72E0); color: #fff; font-size: 16.5px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(59,114,224,.32);">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M5 12h14M13 6l6 6-6 6"/></svg>{{ $t['checkout'] }}
                                </button>
                            @else
                                <button wire:click="checkin" style="display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 17px; border: none; border-radius: 15px; background: linear-gradient(180deg,#23B27C,#1F9D6B); color: #fff; font-size: 16.5px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 20px rgba(31,157,107,.32);">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>{{ $t['checkin'] }}
                                </button>
                            @endif
                        </div>
                    @else
                        <div style="margin-top: 16px; font-size: 12.5px; color: #8A8880; background: #FAFAF8; border: 1px solid #EFEDE7; border-radius: 11px; padding: 12px 14px; line-height: 1.55;">{{ $t['noPerm'] }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- toast --}}
    @if($toast)
        <div wire:key="eqscan-toast" x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearToast() }, 2600)" x-show="show" x-transition.opacity
             style="position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%); z-index: 90; background: #16181D; color: #fff; font-size: 13.5px; font-weight: 600; padding: 12px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.28);">
            {{ $toast }}
        </div>
    @endif
</div>
