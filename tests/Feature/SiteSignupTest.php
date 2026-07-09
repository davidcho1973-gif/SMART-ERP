<?php

namespace Tests\Feature;

use App\Livewire\JoinForm;
use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Self-service site sign-up: a worker registers via /join/{token}, lands as a
 * PENDING record (no login/clock), and an approver activates them + creates the
 * login from the password they chose.
 */
class SiteSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    private function token(): string
    {
        return Site::find('s1')->ensureJoinToken();
    }

    public function test_public_join_creates_a_pending_employee(): void
    {
        $token = $this->token();

        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('last', 'Lee')
            ->set('phone', '(912) 555-0142')
            ->set('email', 'marcus@email.com')
            ->set('trade', 'Pipefitter')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit')
            ->assertSet('submitted', true);

        $e = Employee::where('email', 'marcus@email.com')->first();
        $this->assertNotNull($e);
        $this->assertSame('pending', $e->emp);
        $this->assertSame('s1', $e->site_id);      // anchored to the site for geofencing
        $this->assertNotEmpty($e->join_password);   // stored as a hash
        $this->assertNull(User::where('email', 'marcus@email.com')->first());   // no login yet
    }

    public function test_pending_employee_cannot_log_in_before_approval(): void
    {
        $token = $this->token();
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('last', 'Lee')
            ->set('email', 'marcus@email.com')->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit');

        // no user account exists → password login impossible
        $this->assertFalse(Auth::attempt(['email' => 'marcus@email.com', 'password' => 'Savannah1']));
    }

    public function test_invalid_token_shows_the_invalid_state(): void
    {
        Livewire::test(JoinForm::class, ['token' => 'nope'])
            ->assertSet('invalid', true);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $token = $this->token();
        Employee::create(['emp_id' => 'X1', 'first' => 'A', 'last' => 'B', 'email' => 'dupe@email.com', 'type' => 'worker', 'access' => 'worker', 'rate' => 0, 'emp' => 'active']);

        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('email', 'dupe@email.com')->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit')
            ->assertHasErrors('email')
            ->assertSet('submitted', false);
    }

    public function test_password_confirm_must_match(): void
    {
        $token = $this->token();
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('email', 'marcus@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Different9')
            ->call('submit')
            ->assertHasErrors('passwordConfirm')
            ->assertSet('submitted', false);

        $this->assertNull(Employee::where('email', 'marcus@email.com')->first());
    }

    public function test_admin_approves_a_signup_and_the_account_can_log_in(): void
    {
        $token = $this->token();
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('last', 'Lee')
            ->set('email', 'marcus@email.com')->set('trade', 'Pipefitter')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit');
        $id = Employee::where('email', 'marcus@email.com')->value('id');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $id)
            ->set('editForm.rate', '28')
            ->call('approveSignup');

        $e = Employee::find($id);
        $this->assertSame('active', $e->emp);            // activated
        $this->assertNull($e->join_password);            // secret cleared
        $this->assertEquals(28.0, (float) $e->rate);     // approver's pay applied
        // the worker can now sign in with the password they chose at sign-up
        $this->assertTrue(Auth::attempt(['email' => 'marcus@email.com', 'password' => 'Savannah1']));
    }

    public function test_admin_rejects_a_signup(): void
    {
        $token = $this->token();
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Spam')->set('email', 'spam@email.com')->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit');
        $id = Employee::where('email', 'spam@email.com')->value('id');

        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('rejectSignup', $id);

        $this->assertNull(Employee::find($id));
    }

    public function test_pending_signup_is_not_in_the_active_roster_and_shows_under_its_tab(): void
    {
        $token = $this->token();
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Marcus')->set('email', 'marcus@email.com')->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('submit');
        $id = Employee::where('email', 'marcus@email.com')->value('id');

        // not on the default (active) roster
        Livewire::test(WorkforceApp::class)->call('demo', 'admin')
            ->assertViewHas('emp', fn ($emp) => ($emp['pendingCount'] ?? 0) === 1
                && ! collect($emp['rows'])->contains('id', $id))
            // ...but present under the "가입 신청" (pending) filter
            ->call('setEmpFilter', 'pending')
            ->assertViewHas('emp', fn ($emp) => collect($emp['rows'])->contains('id', $id));
    }
}
