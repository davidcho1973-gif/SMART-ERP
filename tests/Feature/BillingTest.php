<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Contract;
use App\Models\ProgressSnapshot;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    private function siteId(): string
    {
        return Site::query()->value('id');
    }

    public function test_billing_tab_is_live_and_renders(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'billing')
            ->assertSet('acctTab', 'billing')
            ->assertSee('Contract'); // en column header
    }

    public function test_owner_can_set_a_contract_amount(): void
    {
        $site = $this->siteId();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'billing')
            ->call('openContract', $site)
            ->set('billAmount', '1850000')
            ->call('saveContract')
            ->assertSet('billModal', null);

        $this->assertEqualsWithDelta(1850000.0, Contract::where('site_id', $site)->value('amount'), 0.01);
    }

    public function test_progress_billing_math_this_month_and_cumulative(): void
    {
        $site = $this->siteId();
        Contract::create(['site_id' => $site, 'amount' => 1000000]);
        $thisYm = now()->format('Y-m');
        $prevYm = now()->subMonthNoOverflow()->format('Y-m');
        // last month cumulative 40%, this month cumulative 60%
        ProgressSnapshot::create(['site_id' => $site, 'ym' => $prevYm, 'pct' => 40]);
        ProgressSnapshot::create(['site_id' => $site, 'ym' => $thisYm, 'pct' => 60]);

        $B = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting')['billing'];

        $row = collect($B['rows'])->firstWhere('id', $site);
        $this->assertSame(60.0, $row['cumPct']);
        // this month = 1,000,000 × (60% − 40%) = 200,000
        $this->assertEqualsWithDelta(200000.0, $row['thisBill'], 0.01);
        // cumulative = 1,000,000 × 60% = 600,000 ; remaining = 400,000
        $this->assertEqualsWithDelta(600000.0, $row['cumBill'], 0.01);
        $this->assertEqualsWithDelta(400000.0, $row['remaining'], 0.01);
    }

    public function test_saving_progress_writes_a_snapshot_for_the_month(): void
    {
        $site = $this->siteId();
        Contract::create(['site_id' => $site, 'amount' => 500000]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'billing')
            ->call('openProgress', $site)
            ->set('billPct', '35')
            ->call('saveProgress')
            ->assertSet('billModal', null);

        $snap = ProgressSnapshot::where('site_id', $site)->where('ym', now()->format('Y-m'))->first();
        $this->assertNotNull($snap);
        $this->assertSame(35.0, $snap->pct);
    }

    public function test_progress_is_clamped_to_0_100(): void
    {
        $site = $this->siteId();
        Contract::create(['site_id' => $site, 'amount' => 500000]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('openProgress', $site)
            ->set('billPct', '150')
            ->call('saveProgress');

        $this->assertSame(100.0, ProgressSnapshot::where('site_id', $site)->value('pct'));
    }

    public function test_manager_cannot_manage_contracts(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $site = $this->siteId();
        $manager = User::where('email', 'mkim@nahshon.io')->first(); // site_manager, no contracts.manage

        Livewire::actingAs($manager)->test(WorkforceApp::class)
            ->call('openContract', $site)
            ->set('billAmount', '9999')
            ->call('saveContract');

        $this->assertSame(0, Contract::count()); // blocked
    }
}
