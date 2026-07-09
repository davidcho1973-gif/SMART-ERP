<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 직원 구분 (worker/manager × local/korean + THIRD PARTY) and the LOCAL/한국인
 * nationality selector: form ids map to the stored Employee.type/nat and back.
 */
class EmployeeTypeNationalityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_registration_type_maps_to_stored_type_and_defaults(): void
    {
        $c = Livewire::test(WorkforceApp::class)->call('demo', 'admin');

        $c->call('setRegType', 'third_party')
            ->assertSet('regAccess', 'worker')
            ->assertSet('regPayType', 'hourly')
            ->assertSet('regNat', 'LOCAL');

        $c->call('setRegType', 'manager_ko')
            ->assertSet('regAccess', 'manager')
            ->assertSet('regPayType', 'salary')
            ->assertSet('regLang', 'ko')
            ->assertSet('regNat', '한국인');

        $c->call('setRegType', 'manager_local')
            ->assertSet('regAccess', 'manager')
            ->assertSet('regNat', 'LOCAL');
    }

    public function test_edit_saves_manager_local_and_third_party_and_nationality(): void
    {
        $e = Employee::where('type', 'worker')->first();

        // → 관리자 (현지인)
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $e->id)
            ->set('editForm.type', 'manager_local')
            ->set('editForm.nat', 'LOCAL')
            ->call('saveEmp');
        $e->refresh();
        $this->assertSame('manager', $e->type);
        $this->assertSame('LOCAL', $e->nat);

        // → THIRD PARTY, 한국인
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $e->id)
            ->set('editForm.type', 'third_party')
            ->set('editForm.nat', '한국인')
            ->call('saveEmp');
        $e->refresh();
        $this->assertSame('third_party', $e->type);
        $this->assertSame('한국인', $e->nat);
    }

    public function test_edit_form_derives_the_right_dropdown_id_from_stored_record(): void
    {
        $e = Employee::where('type', 'worker')->first();
        $e->update(['type' => 'third_party', 'nat' => '한국인']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $e->id)
            ->assertSet('editForm.type', 'third_party')
            ->assertSet('editForm.nat', '한국인');

        // a Korean manager derives manager_ko
        $e->update(['type' => 'manager', 'nat' => '한국인', 'lang' => 'ko']);
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $e->id)
            ->assertSet('editForm.type', 'manager_ko');
    }

    public function test_five_type_options_and_nationality_options_are_offered(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'employees')
            ->assertViewHas('emp', function ($emp) {
                $typeIds = array_column($emp['typeOptions'], 'id');
                $natIds = array_column($emp['natOptions'], 'id');

                return $typeIds === ['worker_local', 'worker_ko', 'manager_ko', 'manager_local', 'third_party']
                    && in_array('LOCAL', $natIds, true) && in_array('한국인', $natIds, true);
            });
    }
}
