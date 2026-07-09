<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An owner can set a login password for an employee so they sign in with email +
 * password — no Google needed (e.g. an owner who has no Gmail).
 */
class EmployeePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_owner_sets_a_password_and_the_account_can_authenticate(): void
    {
        Employee::whereKey(106)->update(['email' => 'boss@nahshon.io', 'access' => 'owner']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')                 // owner
            ->set('selectedEmp', 106)
            ->set('empPassword', 'Secret123')
            ->call('setEmpPassword')
            ->assertSet('empPassword', '');         // input cleared after set

        $user = User::where('email', 'boss@nahshon.io')->first();
        $this->assertNotNull($user);
        $this->assertSame(106, $user->employee_id);
        $this->assertSame('owner', $user->access);
        // the whole point: email + password sign-in works, no Google
        $this->assertTrue(Auth::attempt(['email' => 'boss@nahshon.io', 'password' => 'Secret123']));
    }

    public function test_updating_an_existing_account_changes_its_password(): void
    {
        Employee::whereKey(106)->update(['email' => 'boss@nahshon.io']);
        User::create(['name' => 'Boss', 'email' => 'boss@nahshon.io', 'password' => 'oldpassword', 'access' => 'worker', 'employee_id' => 106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('selectedEmp', 106)
            ->set('empPassword', 'BrandNew99')
            ->call('setEmpPassword');

        $this->assertFalse(Auth::attempt(['email' => 'boss@nahshon.io', 'password' => 'oldpassword']));
        $this->assertTrue(Auth::attempt(['email' => 'boss@nahshon.io', 'password' => 'BrandNew99']));
    }

    public function test_non_owner_cannot_set_a_password(): void
    {
        Employee::whereKey(106)->update(['email' => 'boss@nahshon.io']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')               // site_manager, not owner
            ->set('selectedEmp', 106)
            ->set('empPassword', 'Secret123')
            ->call('setEmpPassword');

        $this->assertNull(User::where('email', 'boss@nahshon.io')->first());
    }

    public function test_short_password_is_rejected(): void
    {
        Employee::whereKey(106)->update(['email' => 'boss@nahshon.io']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('selectedEmp', 106)
            ->set('empPassword', 'short')           // < 8 chars
            ->call('setEmpPassword');

        $this->assertNull(User::where('email', 'boss@nahshon.io')->first());
    }

    public function test_password_needs_an_email_on_the_employee(): void
    {
        Employee::whereKey(106)->update(['email' => '']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('selectedEmp', 106)
            ->set('empPassword', 'Secret123')
            ->call('setEmpPassword');

        $this->assertSame(0, User::where('employee_id', 106)->count());
    }
}
