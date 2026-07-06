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

    public function test_entering_comms_provisions_only_the_announcement_room(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->assertSet('screen', 'comms');

        $this->assertSame(1, Channel::where('type', 'announcement')->count());
        // company/crew rooms are no longer auto-derived — rooms are made by invite
        $this->assertSame(0, Channel::where('type', 'company')->count());
        $this->assertSame(0, Channel::where('type', 'team')->count());
    }

    public function test_comms_nav_item_is_present(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('go', 'comms')
            ->assertSee('Internal Comms');
    }

    public function test_creating_a_room_with_several_people_makes_a_group(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')                 // acts as employee 103
            ->call('go', 'comms')
            ->call('togglePick', 106)
            ->call('togglePick', 108)
            ->set('commsRoomName', 'Night shift')
            ->call('createChat');

        $ch = Channel::where('type', 'group')->where('name', 'Night shift')->first();
        $this->assertNotNull($ch);
        $this->assertEqualsCanonicalizing([103, 106, 108], $ch->members()->pluck('employee_id')->all());
    }

    public function test_picking_one_person_with_no_name_opens_a_dm(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('togglePick', 108)
            ->call('createChat');

        $this->assertSame(0, Channel::where('type', 'group')->count());
        $ch = Channel::where('type', 'dm')->first();
        $this->assertNotNull($ch);
        $this->assertSame(2, $ch->members()->count());
    }

    public function test_invite_adds_members_and_leave_removes_them(): void
    {
        $room = Comms::createRoom('Crew chat', 103, [106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openInvite')
            ->call('togglePick', 108)
            ->call('inviteMembers');

        $this->assertEqualsCanonicalizing([103, 106, 108], $room->fresh()->members()->pluck('employee_id')->all());

        Comms::leaveRoom($room, 106);
        $this->assertFalse($room->fresh()->members()->where('employee_id', 106)->exists());
    }

    public function test_last_member_leaving_deletes_the_room(): void
    {
        $room = Comms::createRoom('Solo', 103, []);
        $this->assertSame(1, $room->members()->count());

        Comms::leaveRoom($room, 103);
        $this->assertNull(Channel::find($room->id));   // empty group is removed
    }

    public function test_selecting_a_channel_marks_it_read_and_sending_persists(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsCompose', 'Morning crew, safety first today.')
            ->call('sendMessage')
            ->assertSet('commsCompose', '');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertSame($room->id, $msg->channel_id);
        $this->assertSame(103, $msg->sender_id); // demo admin
        $this->assertSame('Morning crew, safety first today.', $msg->body);
    }

    public function test_blank_message_is_not_posted(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsCompose', '   ')
            ->call('sendMessage');

        $this->assertSame($before, Message::count());
    }

    public function test_starting_a_dm_creates_a_channel_and_reuses_it(): void
    {
        $component = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
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
        $room = Comms::createRoom('Ops', 103, [106]);
        // a teammate posts while nobody has read the room
        Message::create(['channel_id' => $room->id, 'sender_id' => 106, 'body' => 'Parts delivered.']);

        $me = Employee::find(103);
        $this->assertSame(1, Comms::unreadCount($room, $me));

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('openFromBell', $room->id);

        $this->assertSame(0, Comms::unreadCount($room->fresh(), $me));
    }

    public function test_poll_chimes_only_when_unread_grows(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);

        $c = Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('go', 'comms');
        $c->call('pollComms')->assertNotDispatched('comms-ping');   // first poll seeds the baseline

        // a teammate posts → next poll should ring
        Message::create(['channel_id' => $room->id, 'sender_id' => 106, 'body' => 'ping me']);
        $c->call('pollComms')->assertDispatched('comms-ping');

        // nothing new → silent
        $c->call('pollComms')->assertNotDispatched('comms-ping');
    }

    public function test_comms_nav_item_renders_for_admin(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        Message::create(['channel_id' => $room->id, 'sender_id' => 106, 'body' => 'unread!']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->assertSee('Internal Comms');   // nav renders; unread badge is computed from the total
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
        Comms::createRoom('Electrical Crew A', 103, [106]);   // an invited room the worker is in

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')                                 // employee 106 (Carlos)
            ->assertSee('Site safety meeting at 7am tomorrow.')      // announcement content on the home board
            ->assertSee('Electrical Crew A');                        // a room the worker was invited to
    }

    public function test_worker_can_open_a_room_and_post_from_home(): void
    {
        $room = Comms::createRoom('Crew A', 103, [106]);   // worker 106 invited
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('selectChannel', $room->id)
            ->assertSet('commsPane', 'thread')
            ->set('commsCompose', 'On my way to the gate.')
            ->call('sendMessage')
            ->assertSet('commsCompose', '');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertSame($room->id, $msg->channel_id);
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
