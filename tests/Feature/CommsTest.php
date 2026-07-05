<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Channel;
use App\Models\Employee;
use App\Models\Message;
use App\Support\Comms;
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
}
