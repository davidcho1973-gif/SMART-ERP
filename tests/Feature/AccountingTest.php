<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]); // demo admin has full caps
        $this->seed(WorkforceSeeder::class);
    }

    public function test_accounting_nav_item_is_present_for_admin(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->assertSee('Accounting');
    }

    public function test_admin_can_open_accounting_and_sees_labor_cost(): void
    {
        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->assertSet('screen', 'accounting')
            ->assertSee('Cost by site')      // dashboard tab body renders
            ->assertSee('Cost composition');
        $vm = $c->viewData('accounting');

        $this->assertNotNull($vm);
        $this->assertSame('dashboard', $vm['tab']);
        $this->assertArrayHasKey('siteRows', $vm);
        $this->assertArrayHasKey('totalLaborLabel', $vm);
        // labor pillar is live, the other three are flagged coming
        $this->assertTrue($vm['pillars'][0]['live']);
        $this->assertFalse($vm['pillars'][1]['live']);
    }

    public function test_accounting_labor_total_matches_sum_of_site_rows(): void
    {
        $vm = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        $sum = array_sum(array_column($vm['siteRows'], 'labor'));
        $this->assertEqualsWithDelta($sum, $vm['totalLabor'], 0.01);
    }

    public function test_switching_sub_tabs_works(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('acctTab', 'expenses')
            ->assertSet('acctTab', 'expenses')
            ->call('acctTab', 'bogus')          // invalid → falls back to dashboard
            ->assertSet('acctTab', 'dashboard');
    }

    public function test_manager_without_payroll_cannot_open_accounting(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $manager = User::where('email', 'mkim@nahshon.io')->first(); // site_manager, no payroll.view

        $c = Livewire::actingAs($manager)->test(WorkforceApp::class)
            ->call('go', 'accounting');

        $this->assertNotSame('accounting', $c->get('screen')); // blocked from the finance screen
        $this->assertNull($c->viewData('accounting'));         // and no finance data is built
    }
}
