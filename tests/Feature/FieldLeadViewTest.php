<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A 현장 팀장 (site_manager) runs the phone app whether or not they are wired as a
 * crew's lead, and the top-bar role display shows only their own granted tier.
 */
class FieldLeadViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => false]);   // exercise the real login path
        $this->seed(WorkforceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_site_manager_with_no_crew_still_gets_the_lead_mobile_ui(): void
    {
        // strip mkim (emp 101) of every crew he leads — he is now a 현장 팀장 with
        // no team of his own, yet must still see the field-lead phone UI
        Team::where('lead', 101)->update(['lead' => null]);

        Livewire::actingAs(User::where('email', 'mkim@nahshon.io')->first())
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')       // mobile, not desktop
            ->assertSet('screen', 'worker')
            ->assertSet('access', 'manager')    // ceiling → badge reads 현장 팀장
            ->assertViewHas('isFieldLead', true)      // the "우리 팀" crew tab is present
            ->assertViewHas('viewSwitchable', false); // single static badge, no persona switch
    }

    public function test_registered_crew_lead_also_gets_the_lead_mobile_ui(): void
    {
        // mkim leads Electrical Crew A in the seed — the wired-lead path
        Livewire::actingAs(User::where('email', 'mkim@nahshon.io')->first())
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')
            ->assertViewHas('isFieldLead', true);
    }

    public function test_plain_worker_sees_only_the_worker_badge(): void
    {
        Livewire::actingAs(User::where('email', 'cmartinez@nahshon.io')->first())
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')
            ->assertSet('access', 'worker')
            ->assertViewHas('isFieldLead', false)      // no crew tab
            ->assertViewHas('viewSwitchable', false);  // single 작업자 badge
    }

    public function test_office_admin_can_switch_personas(): void
    {
        Livewire::actingAs(User::where('email', 'davidcho1973@gmail.com')->first())
            ->test(WorkforceApp::class)
            ->assertSet('role', 'admin')
            ->assertSet('screen', 'dashboard')
            ->assertViewHas('viewSwitchable', true);   // 어드민 · 현장 팀장 · 작업자
    }
}
