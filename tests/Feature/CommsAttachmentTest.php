<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Message;
use App\Models\User;
use App\Support\Attach;
use App\Support\Comms;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommsAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);          // demo admin acts as employee 103
        config(['filesystems.disks.s3.bucket' => 'test-bucket']); // turns file sharing on
        Storage::fake('s3');
        $this->seed(WorkforceSeeder::class);
    }

    public function test_file_sharing_is_off_without_an_object_storage_bucket(): void
    {
        config(['filesystems.disks.s3.bucket' => null]);
        $this->assertFalse(Attach::enabled());

        config(['filesystems.disks.s3.bucket' => 'test-bucket']);
        $this->assertTrue(Attach::enabled());
    }

    public function test_sending_an_image_stores_it_and_records_metadata(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsFile', UploadedFile::fake()->image('site.jpg', 400, 300))
            ->set('commsCompose', 'Here is the wall')
            ->call('sendMessage')
            ->assertSet('commsFile', null)
            ->assertSet('commsCompose', '');

        $msg = Message::latest('id')->first();
        $this->assertSame($room->id, $msg->channel_id);
        $this->assertSame('Here is the wall', $msg->body);
        $this->assertTrue($msg->hasFile());
        $this->assertTrue($msg->isImage());
        $this->assertSame('site.jpg', $msg->att_name);
        $this->assertSame('s3', $msg->att_disk);
        Storage::disk('s3')->assertExists($msg->att_path);
    }

    public function test_sending_a_file_with_no_text_is_allowed(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsFile', UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf'))
            ->call('sendMessage');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertTrue($msg->hasFile());
        $this->assertFalse($msg->isImage());
        $this->assertSame('plan.pdf', $msg->att_name);
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsFile', UploadedFile::fake()->image('huge.jpg')->size(11 * 1024)) // 11 MB > 10 MB
            ->call('sendMessage');

        $this->assertSame($before, Message::count()); // nothing posted
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $room = Comms::createRoom('Ops', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->set('commsFile', UploadedFile::fake()->create('malware.exe', 20))
            ->call('sendMessage');

        $this->assertSame($before, Message::count());
    }

    public function test_a_renamed_script_is_blocked_even_with_an_allowed_extension(): void
    {
        // a real HTML/script payload renamed .pdf must be caught by the content sniff.
        // (a real UploadedFile is needed — fakes report the extension's mime, not the bytes)
        $tmp = tempnam(sys_get_temp_dir(), 'att').'.pdf';
        file_put_contents($tmp, '<html><script>alert(1)</script></html>');
        $file = new UploadedFile($tmp, 'report.pdf', null, null, true);
        $this->assertSame('danger', Attach::reject($file));
        @unlink($tmp);
    }

    public function test_office_documents_are_accepted(): void
    {
        foreach (['xlsx', 'docx', 'pptx'] as $ext) {
            $file = UploadedFile::fake()->create("book.$ext", 100);
            $this->assertNull(Attach::reject($file), "$ext should be accepted");
        }
    }

    public function test_a_channel_member_can_download_the_attachment(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $room = Comms::createRoom('Ops', 103, [106]);
        $path = 'comms/'.$room->id.'/'.'file.pdf';
        Storage::disk('s3')->put($path, 'PDF-BYTES');
        $msg = Message::create([
            'channel_id' => $room->id, 'sender_id' => 103, 'body' => '',
            'att_disk' => 's3', 'att_path' => $path, 'att_name' => 'plan.pdf',
            'att_mime' => 'application/pdf', 'att_size' => 9,
        ]);

        $member = User::where('email', 'cmartinez@nahshon.io')->first(); // employee 106 = member
        $this->actingAs($member)->get('/comms/file/'.$msg->id)->assertOk();
    }

    public function test_a_non_member_is_forbidden_from_downloading(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $room = Comms::createRoom('Ops', 103, [106]); // members: 103, 106 (not 101)
        $path = 'comms/'.$room->id.'/'.'file.pdf';
        Storage::disk('s3')->put($path, 'PDF-BYTES');
        $msg = Message::create([
            'channel_id' => $room->id, 'sender_id' => 103, 'body' => '',
            'att_disk' => 's3', 'att_path' => $path, 'att_name' => 'plan.pdf',
            'att_mime' => 'application/pdf', 'att_size' => 9,
        ]);

        $outsider = User::where('email', 'mkim@nahshon.io')->first(); // employee 101, not in the room
        $this->actingAs($outsider)->get('/comms/file/'.$msg->id)->assertForbidden();
    }

    public function test_downloading_a_message_without_a_file_is_404(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $room = Comms::createRoom('Ops', 103, [106]);
        $msg = Message::create(['channel_id' => $room->id, 'sender_id' => 103, 'body' => 'text only']);

        $member = User::where('email', 'cmartinez@nahshon.io')->first();
        $this->actingAs($member)->get('/comms/file/'.$msg->id)->assertNotFound();
    }
}
