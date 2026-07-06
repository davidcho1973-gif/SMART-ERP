<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Channel;
use App\Models\Message;
use App\Support\Comms;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Voice daily report: dictated text → AI-formatted draft → posted to the room.
 */
class VoiceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]); // demo admin acts as employee 103
        $this->seed(WorkforceSeeder::class);
    }

    protected function fakeGemini(array $sections): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($sections)]]],
                ]],
            ]),
        ]);
    }

    public function test_english_dictation_gets_a_korean_translation_attached(): void
    {
        $this->fakeGemini([
            'lang' => 'en',
            'done' => "- Installed 3 electrical panels (Bldg B, 2F)\n- Checked material delivery",
            'issues' => '- Work-area overlap with the piping crew',
            'plan' => '- Start wiring on B 3F',
            'done_ko' => "- 전기 패널 3개 설치 (B동 2층)\n- 자재 입고 검수",
            'issues_ko' => '- 배관팀과 작업구역 겹침',
            'plan_ko' => '- B동 3층 배선 시작',
        ]);
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')                    // employee 103 (Dohyun Lee)
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->assertSet('reportOpen', true)
            ->call('generateReport', 'so today we put in three panels on building B second floor and checked the material delivery, piping crew was in our area again, tomorrow we start wiring third floor');

        $draft = $c->get('reportDraft');
        $this->assertStringContainsString('Daily work report', $draft);        // header in the spoken language
        $this->assertStringContainsString('Installed 3 electrical panels', $draft);
        // Korean translation block attached below the original
        $this->assertStringContainsString('한국어 번역', $draft);
        $this->assertStringContainsString('오늘 수행 업무', $draft);            // Korean section labels
        $this->assertStringContainsString('전기 패널 3개 설치', $draft);
        $this->assertStringContainsString('B동 3층 배선 시작', $draft);
    }

    public function test_korean_dictation_stays_korean_without_translation_block(): void
    {
        $this->fakeGemini([
            'lang' => 'ko',
            'done' => '- 전기 패널 3개 설치 완료 (B동 2층)',
            'issues' => '',
            'plan' => '- 내일 3층 배선 시작',
            'done_ko' => '', 'issues_ko' => '', 'plan_ko' => '',
        ]);
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')                    // UI is English, but speech is Korean
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->call('generateReport', '오늘 B동 2층에 전기 패널 세 개 설치 끝냈고 내일은 3층 배선 시작합니다');

        $draft = $c->get('reportDraft');
        $this->assertStringContainsString('일일업무보고', $draft);              // Korean header despite EN UI
        $this->assertStringContainsString('오늘 수행 업무', $draft);
        $this->assertStringContainsString('전기 패널 3개 설치 완료', $draft);
        $this->assertStringNotContainsString('한국어 번역', $draft);            // no duplicate translation
        $this->assertStringNotContainsString('Daily work report', $draft);
    }

    public function test_posting_the_draft_sends_it_to_the_room(): void
    {
        $room = Comms::createRoom('일일업무보고', 103, [106]);
        $before = Message::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->set('reportDraft', "📋 Daily work report\n- panels done")
            ->call('postReport')
            ->assertSet('reportOpen', false)
            ->assertSet('reportDraft', '');

        $this->assertSame($before + 1, Message::count());
        $msg = Message::latest('id')->first();
        $this->assertSame($room->id, $msg->channel_id);
        $this->assertSame(103, $msg->sender_id);
        $this->assertStringContainsString('panels done', $msg->body);
    }

    public function test_ai_unconfigured_falls_back_to_the_raw_text(): void
    {
        config(['services.gemini.key' => null]);
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->call('generateReport', 'panels installed, delivery checked')
            ->assertSet('reportDraft', 'panels installed, delivery checked');   // still postable by hand
    }

    public function test_ai_failure_falls_back_to_the_raw_text(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->call('generateReport', 'rough notes about today')
            ->assertSet('reportDraft', 'rough notes about today');
    }

    public function test_empty_dictation_does_not_generate(): void
    {
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->call('generateReport', '   ')
            ->assertSet('reportDraft', '');
    }

    public function test_report_composer_only_opens_in_group_rooms(): void
    {
        Comms::ensureRooms();
        $ann = Channel::where('type', 'announcement')->first();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $ann->id)
            ->call('openReport')
            ->assertSet('reportOpen', false);   // announcements are not a report room
    }

    public function test_spanish_worker_report_carries_korean_translation(): void
    {
        $this->fakeGemini([
            'lang' => 'es',
            'done' => '- Se jaló cable en el 2º piso',
            'issues' => '', 'plan' => '',
            'done_ko' => '- 2층 케이블 포설', 'issues_ko' => '', 'plan_ko' => '',
        ]);
        $room = Comms::createRoom('일일업무보고', 103, [106]);

        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')                  // employee 106 (Carlos, es)
            ->call('selectChannel', $room->id)
            ->call('openReport')
            ->assertSet('reportOpen', true)
            ->call('generateReport', 'hoy jalamos cable en el segundo piso');

        $draft = $c->get('reportDraft');
        $this->assertStringContainsString('Reporte diario', $draft);          // es header
        $this->assertStringContainsString('Se jaló cable', $draft);           // original Spanish
        $this->assertStringContainsString('한국어 번역', $draft);              // Korean attached
        $this->assertStringContainsString('2층 케이블 포설', $draft);

        $c->call('postReport');
        $this->assertSame(106, Message::latest('id')->first()->sender_id);
    }
}
