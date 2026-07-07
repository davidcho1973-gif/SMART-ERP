<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Invite → activate onboarding: an admin creates a minimal record; the person
 * activates it themselves on first login.
 */
class InviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_admin_invites_a_worker_as_a_pending_record(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openEmpInvite')
            ->assertSet('inviteOpen', true)
            ->set('invFirst', 'Nuevo')
            ->set('invLast', 'Obrero')
            ->set('invEmail', 'nuevo@example.com')
            ->set('invRole', 'worker')
            ->set('invSite', 's1')
            ->call('saveEmpInvite')
            ->assertSet('inviteOpen', false);

        $e = Employee::where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($e);
        $this->assertNull($e->activated_at);      // invited, not yet logged in
        $this->assertTrue($e->isInvited());
        $this->assertSame('worker', $e->access);
        $this->assertSame('s1', $e->site_id);      // site set → GPS works from day one
        $this->assertSame(0.0, (float) $e->rate);  // rate left for later
        $this->assertStringStartsWith('INV-', $e->emp_id);
    }

    public function test_invite_rejects_a_missing_email_and_a_duplicate(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openEmpInvite')
            ->set('invFirst', 'No')->set('invLast', 'Email')
            ->set('invEmail', '')
            ->call('saveEmpInvite')
            ->assertSet('inviteOpen', true);   // blocked, drawer stays open
        $this->assertSame(0, Employee::where('first', 'No')->count());

        // duplicate email (Carlos = cmartinez@nahshon.io is seeded)
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openEmpInvite')
            ->set('invFirst', 'Dup')->set('invLast', 'Licate')
            ->set('invEmail', 'cmartinez@nahshon.io')
            ->call('saveEmpInvite')
            ->assertSet('inviteOpen', true);
        $this->assertSame(0, Employee::where('first', 'Dup')->count());
    }

    public function test_worker_cannot_invite(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openEmpInvite')
            ->assertSet('inviteOpen', false);   // gate refused
    }

    public function test_first_login_activates_an_invited_employee(): void
    {
        config(['workforce.demo' => false]);
        // an invited stub with a linked account that has never signed in
        $e = Employee::create([
            'emp_id' => 'INV-TEST01', 'first' => 'Pending', 'last' => 'Login',
            'access' => 'worker', 'type' => 'worker', 'pay_type' => 'hourly',
            'site_id' => 's1', 'rate' => 0, 'emp' => 'active', 'activated_at' => null,
            'email' => 'pending@nahshon.io', 'lang' => 'es',
        ]);
        $u = User::create([
            'name' => 'Pending Login', 'email' => 'pending@nahshon.io',
            'password' => bcrypt('x'), 'access' => 'worker', 'employee_id' => $e->id,
        ]);
        $this->assertNull($e->fresh()->activated_at);

        Livewire::actingAs($u)->test(WorkforceApp::class);   // mount → applyUser

        $this->assertNotNull($e->fresh()->activated_at);     // flipped to active
        $this->assertFalse($e->fresh()->isInvited());
    }
}
