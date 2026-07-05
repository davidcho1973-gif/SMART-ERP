<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Channel;
use App\Models\Employee;
use App\Models\Message;
use App\Models\User;
use App\Support\Comms;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]); // demo admin acts as employee 103
        $this->seed(WorkforceSeeder::class);
    }

    public function test_entering_comms_provisions_standing_rooms(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->assertSet('screen', 'comms');

        $this->assertSame(1, Channel::where('type', 'announcement')->count());
        $this->assertSame(Employee::query()->distinct('company_id')->count('company_id'), Channel::where('type', 'company')->count());
        $this->assertSame(5, Channel::where('type', 'team')->count()); // one per seeded crew
    }

    public function test_comms_nav_item_is_present(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->assertSee('Internal Comms');
    }

    public function test_selecting_a_channel_marks_it_read_and_sending_persists(): void
    {
        Comms::ensureRooms();
        $co = Channel::where('type', 'company')->first();

        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->call('selectChannel', $co->id)
            ->set('commsCompose', 'Morning crew, safety first today.')
            ->call('sendMessage')
            ->assertSet('commsCompose', '');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertSame($co->id, $msg->channel_id);
        $this->assertSame(103, $msg->sender_id); // demo admin
        $this->assertSame('Morning crew, safety first today.', $msg->body);
    }

    public function test_blank_message_is_not_posted(): void
    {
        Comms::ensureRooms();
        $co = Channel::where('type', 'company')->first();
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->call('selectChannel', $co->id)
            ->set('commsCompose', '   ')
            ->call('sendMessage');

        $this->assertSame($before, Message::count());
    }

    public function test_starting_a_dm_creates_a_channel_and_reuses_it(): void
    {
        $component = Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->call('startDm', 108);

        $dmId = $component->get('commsChannel');
        $ch = Channel::find($dmId);
        $this->assertSame('dm', $ch->type);
        $this->assertSame(2, $ch->members()->count());
        $this->assertTrue($ch->members()->where('employee_id', 108)->exists());
        $this->assertTrue($ch->members()->where('employee_id', 103)->exists());

        // opening the same DM again returns the same channel, not a duplicate
        $component->call('startDm', 108);
        $this->assertSame($dmId, $component->get('commsChannel'));
        $this->assertSame(1, Channel::where('type', 'dm')->count());
    }

    public function test_bell_counts_unread_and_clears_on_open(): void
    {
        Comms::ensureRooms();
        $co = Channel::where('type', 'company')->first();
        // a teammate posts while nobody has read the room
        Message::create(['channel_id' => $co->id, 'sender_id' => 106, 'body' => 'Parts delivered.']);

        $me = Employee::find(103);
        $this->assertSame(1, Comms::unreadCount($co, $me));

        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->call('openFromBell', $co->id);

        $this->assertSame(0, Comms::unreadCount($co->fresh(), $me));
    }

    public function test_only_managers_can_post_announcements(): void
    {
        Comms::ensureRooms();
        $ann = Channel::where('type', 'announcement')->first();
        $manager = Employee::find(101);
        $worker = Employee::find(106);

        $this->assertTrue(Comms::canPost($ann, $manager, true));
        $this->assertFalse(Comms::canPost($ann, $worker, false));
    }

    public function test_worker_home_board_shows_announcements_and_rooms(): void
    {
        Comms::ensureRooms();
        $ann = Channel::where('type', 'announcement')->first();
        Message::create(['channel_id' => $ann->id, 'sender_id' => 101, 'body' => 'Site safety meeting at 7am tomorrow.']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')                                 // employee 106 (Carlos)
            ->assertSee('Site safety meeting at 7am tomorrow.')      // announcement content on the home board
            ->assertSee('Electrical Crew A');                        // a room the worker belongs to (crew t1)
    }

    public function test_worker_can_open_a_room_and_post_from_home(): void
    {
        Comms::ensureRooms();
        $co = Channel::where('type', 'company')->where('company_id', 'c2')->first(); // worker 106's company
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('selectChannel', $co->id)
            ->assertSet('commsPane', 'thread')
            ->set('commsCompose', 'On my way to the gate.')
            ->call('sendMessage')
            ->assertSet('commsCompose', '');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertSame($co->id, $msg->channel_id);
        $this->assertSame(106, $msg->sender_id);   // posted as the worker
    }

    public function test_worker_cannot_broadcast_announcements(): void
    {
        // real mode: a worker account's access is 'worker' (demo grants manage to all)
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();
        Comms::ensureRooms();
        $ann = Channel::where('type', 'announcement')->first();
        $before = Message::count();

        Livewire::actingAs($worker)->test(WorkforceApp::class)
            ->call('selectChannel', $ann->id)
            ->set('commsCompose', 'worker trying to broadcast')
            ->call('sendMessage');

        $this->assertSame($before, Message::count());   // read-only for workers
    }

    public function test_badge_qr_moved_from_clock_tab_to_profile_tab(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')          // lands on the clock (home) tab
            ->assertDontSee('Mi QR')          // QR no longer on the clock tab
            ->call('setMobileTab', 'me')
            ->assertSee('Mi QR');             // now on the profile tab
    }
}
