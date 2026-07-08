<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Punch;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Identity hardening: the props that decide WHO the request acts as are
 * server-set only, and the pay-affecting lunch toggle follows the same rules
 * as every other pay change.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => false]);
        $this->seed(WorkforceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function worker(): User
    {
        return User::where('email', 'cmartinez@nahshon.io')->first();
    }

    public function test_client_cannot_tamper_with_role(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->worker())->test(WorkforceApp::class)->set('role', 'admin');
    }

    public function test_client_cannot_tamper_with_preview_identity(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->worker())->test(WorkforceApp::class)->set('previewEmpId', 101);
    }

    public function test_client_cannot_tamper_with_access_ceiling(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->worker())->test(WorkforceApp::class)->set('access', 'admin');
    }

    public function test_worker_cannot_toggle_lunch_on_a_past_day(): void
    {
        $p = Punch::create([
            'employee_id' => 106, 'work_date' => now()->subDays(3)->format('Y-m-d'),
            'in_min' => 360, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
        ]);

        Livewire::actingAs($this->worker())
            ->test(WorkforceApp::class)
            ->call('togglePunchLunch', $p->id);

        $this->assertFalse((bool) $p->refresh()->no_lunch);   // unchanged — needs a correction
    }

    public function test_worker_can_toggle_lunch_on_today(): void
    {
        $p = Punch::create([
            'employee_id' => 106, 'work_date' => now()->format('Y-m-d'),
            'in_min' => 360, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
        ]);

        Livewire::actingAs($this->worker())
            ->test(WorkforceApp::class)
            ->call('togglePunchLunch', $p->id);

        $this->assertTrue((bool) $p->refresh()->no_lunch);
    }

    public function test_demo_export_hides_bank_account_numbers(): void
    {
        config(['workforce.demo' => true]);
        $res = $this->get('/export/payroll?recipient=hourly&lang=en');
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsxsec');
        file_put_contents($tmp, $res->getContent());
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringNotContainsString('334080507882', $xml);   // account #
        $this->assertStringNotContainsString('061000052', $xml);      // routing #
        $this->assertStringContainsString('PAYMENT BANK INFORMATION', $xml); // block header kept
    }
}
