<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Punch;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A crew lead runs the field from their phone: anyone who leads a team gets the
 * worker-mobile app with an extra "My Crew" tab (set the shift, adjust paid
 * time), while top office roles keep the desktop.
 */
class FieldLeadMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => false]);
        $this->seed(WorkforceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_worker_lead_lands_on_mobile_with_a_crew_tab(): void
    {
        Team::whereKey('t1')->update(['lead' => 106]);   // Carlos leads his own crew
        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')
            ->assertViewHas('isWorker', true)
            ->assertViewHas('crew', fn ($crew) => $crew !== null && count($crew['teams']) === 1)
            ->assertSee('Mi cuadrilla')          // the crew tab in the mobile nav
            ->set('mobileTab', 'crew')
            ->assertSee('Electrical Crew A');     // his crew, shown in the panel
    }

    public function test_manager_who_leads_a_team_is_forced_to_mobile(): void
    {
        Team::whereKey('t1')->update(['lead' => 101]);   // Minjun (manager) leads a crew
        $minjun = User::where('email', 'mkim@nahshon.io')->first();

        Livewire::actingAs($minjun)
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')       // manager pushed onto the phone app
            ->assertSet('access', 'worker')     // and locked there (no desktop switch)
            ->assertViewHas('isDesktopApp', false);
    }

    public function test_owner_who_leads_a_team_keeps_the_desktop(): void
    {
        // David Cho is the owner (access=admin) with no linked employee; give him
        // an employee record that leads a team and confirm he still gets desktop.
        \App\Models\Employee::whereKey(103)->update([]);
        Team::whereKey('t3')->update(['lead' => 103]);
        $owner = User::where('email', 'davidcho1973@gmail.com')->first();
        $owner->update(['employee_id' => 103]);
        // 103 canonicalizes via the user's access = admin → owner, so stays desktop
        Livewire::actingAs($owner)
            ->test(WorkforceApp::class)
            ->assertSet('role', 'admin')
            ->assertViewHas('isDesktopApp', true);
    }

    public function test_plain_worker_has_no_crew_tab(): void
    {
        // 106 leads nothing
        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->assertSet('role', 'worker')
            ->assertViewHas('crew', null);
    }

    public function test_lead_can_set_the_crew_shift_from_the_phone(): void
    {
        Team::whereKey('t1')->update(['lead' => 106]);
        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->call('openCrewShift', 't1')
            ->assertSet('crewShiftTeam', 't1')
            ->set('teamShiftIn', '05:00')
            ->set('teamShiftOut', '14:00')
            ->call('saveCrewShift')
            ->assertSet('crewShiftTeam', null);

        $t = Team::find('t1');
        $this->assertSame(300, $t->shift_in);
        $this->assertSame(840, $t->shift_out);
    }

    public function test_lead_cannot_set_shift_for_a_crew_they_do_not_lead(): void
    {
        Team::whereKey('t1')->update(['lead' => 106]);   // Carlos leads t1, not t2
        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->call('openCrewShift', 't2')       // not his crew
            ->assertSet('crewShiftTeam', null); // refused

        $this->assertNull(Team::find('t2')->shift_in);
    }

    public function test_lead_can_adjust_a_crew_members_paid_time_from_the_phone(): void
    {
        Team::whereKey('t1')->update(['lead' => 106, 'shift_in' => 300, 'shift_out' => 840]);
        // 116 is a t1 member; stayed an hour past the shift end today
        $p = Punch::create([
            'employee_id' => 116, 'work_date' => now()->format('Y-m-d'), 'team_id' => 't1',
            'in_min' => 300, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
        ]);
        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->call('openAdjust', $p->id)
            ->set('adjPaidOut', '14:30')
            ->set('adjPaidReason', '30 min OT approved')
            ->call('saveAdjust')
            ->assertSet('adjPunchId', null);

        $p->refresh();
        $this->assertSame(870, $p->adj_out_min);
        $this->assertTrue($p->isAdjusted());
    }
}
