<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Expense;
use App\Models\Site;
use App\Models\User;
use App\Support\Payroll;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);
        Storage::fake('s3');
        $this->seed(WorkforceSeeder::class);
    }

    private function siteId(): string
    {
        return Site::query()->value('id');
    }

    public function test_submitting_a_receipt_creates_a_pending_expense_with_the_image(): void
    {
        $site = $this->siteId();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('acctTab', 'expenses')
            ->call('openExpenseForm')
            ->set('expSite', $site)
            ->set('expVendor', 'GS Caltex')
            ->set('expAmount', '88.00')
            ->set('expCategory', 'fuel')
            ->set('expDate', '2026-07-12')
            ->set('expFile', UploadedFile::fake()->image('receipt.jpg', 600, 800))
            ->call('submitExpense')
            ->assertSet('expFormOpen', false);

        $this->assertSame(1, Expense::count());
        $x = Expense::first();
        $this->assertSame('pending', $x->status);
        $this->assertSame(88.0, $x->amount);
        $this->assertSame('fuel', $x->category);
        $this->assertSame($site, $x->site_id);
        $this->assertTrue($x->hasReceipt());
        $this->assertSame('s3', $x->att_disk);
        Storage::disk('s3')->assertExists($x->att_path);
    }

    public function test_expense_without_site_or_amount_is_not_created(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('acctTab', 'expenses')
            ->call('openExpenseForm')
            ->set('expSite', '')
            ->set('expAmount', '0')
            ->call('submitExpense');

        $this->assertSame(0, Expense::count());
    }

    public function test_approving_and_rejecting_transitions_status(): void
    {
        $site = $this->siteId();
        $a = Expense::create(['site_id' => $site, 'category' => 'meal', 'amount' => 50, 'spent_on' => '2026-07-11', 'status' => 'pending']);
        $b = Expense::create(['site_id' => $site, 'category' => 'tool', 'amount' => 30, 'spent_on' => '2026-07-11', 'status' => 'pending']);

        $c = Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('go', 'accounting')->call('acctTab', 'expenses');
        $c->call('approveExpense', $a->id);
        $c->call('askRejectExpense', $b->id)->set('expRejectNote', 'no receipt')->call('rejectExpense', $b->id);

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->decided_at);
        $this->assertSame('rejected', $b->fresh()->status);
        $this->assertSame('no receipt', $b->fresh()->reject_reason);
    }

    public function test_approved_expenses_feed_the_dashboard_expense_pillar(): void
    {
        $site = $this->siteId();
        [$start] = Payroll::currentPeriod();
        // one approved (counts) + one pending (must NOT count)
        Expense::create(['site_id' => $site, 'category' => 'fuel', 'amount' => 120, 'spent_on' => $start, 'status' => 'approved']);
        Expense::create(['site_id' => $site, 'category' => 'meal', 'amount' => 999, 'spent_on' => $start, 'status' => 'pending']);

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        $this->assertSame(120.0, $A['totalExpense']);
        $expensePillar = collect($A['pillars'])->firstWhere('key', 'expense');
        $this->assertTrue($expensePillar['live']);
        $this->assertSame(120.0, $expensePillar['amount']);
    }

    public function test_receipt_download_is_gated_to_accounting_roles(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $site = $this->siteId();
        $path = 'receipts/'.$site.'/r.jpg';
        Storage::disk('s3')->put($path, 'IMG');
        $x = Expense::create([
            'site_id' => $site, 'category' => 'fuel', 'amount' => 10, 'spent_on' => '2026-07-11', 'status' => 'approved',
            'att_disk' => 's3', 'att_path' => $path, 'att_name' => 'r.jpg', 'att_mime' => 'image/jpeg', 'att_size' => 3,
        ]);

        $owner = User::where('email', 'davidcho1973@gmail.com')->first();   // owner
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();    // worker — no accounting cap

        $this->actingAs($owner)->get('/accounting/receipt/'.$x->id)->assertOk();
        $this->actingAs($worker)->get('/accounting/receipt/'.$x->id)->assertForbidden();
    }
}
