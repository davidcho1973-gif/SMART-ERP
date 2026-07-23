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

    public function test_small_selfie_is_stored_but_oversized_or_garbage_is_dropped(): void
    {
        $token = $this->token();

        // a normal (downscaled) selfie data URI is kept
        $ok = 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 4000));
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Ana')->set('last', 'Cruz')->set('email', 'ana@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->set('selfie', $ok)
            ->call('submit')->assertSet('submitted', true);
        $this->assertSame($ok, Employee::where('email', 'ana@email.com')->value('badge_photo'));

        // an oversized blob (a raw un-shrunk photo) is dropped, NOT crashed → still registers
        $huge = 'data:image/jpeg;base64,'.base64_encode(str_repeat('y', 400_000));
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Bob')->set('last', 'Kim')->set('email', 'bob@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->set('selfie', $huge)
            ->call('submit')->assertSet('submitted', true);
        $bob = Employee::where('email', 'bob@email.com')->first();
        $this->assertNotNull($bob);                 // sign-up succeeded (no 500)
        $this->assertNull($bob->badge_photo);        // oversized selfie was dropped

        // a non-image string is also dropped
        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Cid')->set('last', 'Ro')->set('email', 'cid@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->set('selfie', 'not-a-data-uri')
            ->call('submit')->assertSet('submitted', true);
        $this->assertNull(Employee::where('email', 'cid@email.com')->value('badge_photo'));
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

    public function test_first_onsite_signup_bootstraps_the_site_geofence(): void
    {
        $s2 = Site::find('s2');                 // seeded without a geofence
        $this->assertNull($s2->lat);
        $token = $s2->ensureJoinToken();

        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Ivan')->set('email', 'ivan@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('setGeo', 33.30539, -111.90478, 18)   // accurate GPS fix on site
            ->call('submit')->assertSet('submitted', true);

        $s2->refresh();
        $this->assertEqualsWithDelta(33.30539, (float) $s2->lat, 0.00001);
        $this->assertEqualsWithDelta(-111.90478, (float) $s2->lng, 0.00001);
        $this->assertGreaterThan(0, $s2->radius_m);      // default radius applied
    }

    public function test_signup_never_overwrites_an_existing_geofence(): void
    {
        $s1 = Site::find('s1');                 // seeded WITH a geofence
        $this->assertNotNull($s1->lat);
        [$lat0, $lng0] = [(float) $s1->lat, (float) $s1->lng];
        $token = $s1->ensureJoinToken();

        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Jo')->set('email', 'jo@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('setGeo', 10.0, 10.0, 12)              // a wildly different position
            ->call('submit')->assertSet('submitted', true);

        $s1->refresh();
        $this->assertEqualsWithDelta($lat0, (float) $s1->lat, 0.00001);   // unchanged
        $this->assertEqualsWithDelta($lng0, (float) $s1->lng, 0.00001);
    }

    public function test_coarse_gps_fix_does_not_bootstrap_the_geofence(): void
    {
        $s3 = Site::find('s3');
        $token = $s3->ensureJoinToken();

        Livewire::test(JoinForm::class, ['token' => $token])
            ->set('first', 'Kai')->set('email', 'kai@email.com')
            ->set('password', 'Savannah1')->set('passwordConfirm', 'Savannah1')
            ->call('setGeo', 33.3, -111.9, 5000)          // ~5 km accuracy (IP-based) → ignored
            ->call('submit')->assertSet('submitted', true);

        $s3->refresh();
        $this->assertNull($s3->lat);   // not set from a coarse fix
    }

    public function test_open_site_qr_mints_a_token_and_exposes_the_qr(): void
    {
        $s2 = Site::find('s2');
        $this->assertEmpty($s2->join_token);

        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'projects')
            ->call('openSiteQr', 's2')
            ->assertSet('siteQrModal', 's2');

        $this->assertNotEmpty(Site::find('s2')->join_token);   // token minted on open
        $c->assertViewHas('projects', fn ($p) => ! empty($p['siteQr']['joinQrSvg'])
            && str_contains($p['siteQr']['joinPosterUrl'], '/poster'));
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
