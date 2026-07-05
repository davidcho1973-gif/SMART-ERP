<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Message;
use App\Support\Comms;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/** Sample internal-comms content so the channels are lively right after a fresh deploy. */
class CommsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Standing rooms: org announcement + one per company + one per crew.
        Comms::ensureRooms();

        $post = function (Channel $ch, int $senderId, string $body, int $minutesAgo): void {
            $at = now()->subMinutes($minutesAgo);
            Message::create([
                'channel_id' => $ch->id,
                'sender_id' => $senderId,
                'body' => $body,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        };

        // ---- announcements (posted by managers/admin) ----
        $ann = Channel::where('type', 'announcement')->first();
        if ($ann) {
            $post($ann, 103, '안전 교육 리마인더 — 이번 주 금요일 오전 6시 30분, 모든 크루 현장 게이트 앞 집합입니다. 안전화·헬멧 필수.', 600);
            $post($ann, 101, 'TSMC Fab 21 north gate badge readers are back online. Please tap in as usual from tomorrow.', 240);
            $post($ann, 103, '급여 정산 안내: 이번 정산기간 마감은 7월 6일입니다. 초과근무 기록 확인 부탁드립니다.', 45);
        }

        // ---- company room ----
        $co = Channel::where('type', 'company')->where('company_id', 'c2')->first();
        if ($co) {
            $post($co, 101, 'Copper State Electric team — parts delivery arrives 8 AM at the north laydown yard.', 180);
            $post($co, 116, 'Copiado. Estaré ahí para recibir el material.', 150);
            $post($co, 106, '¿Necesitan ayuda descargando? Puedo apoyar antes de subir al piso 3.', 120);
        }

        // ---- crew room ----
        $crew = Channel::where('type', 'team')->where('team_id', 't1')->first();
        if ($crew) {
            $post($crew, 101, 'Electrical Crew A — 오늘 3층 배선 마감 목표입니다. 오전에 자재 점검부터 시작하죠.', 90);
            $post($crew, 106, 'Entendido, jefe. Empiezo con el panel B.', 70);
            $post($crew, 107, 'Voy con Carlos al panel B.', 60);
        }

        // ---- a sample DM (manager ↔ worker) ----
        $dm = Comms::findOrCreateDm(101, 106);
        $post($dm, 101, 'Carlos, 오늘 오후에 잠깐 안전점검 같이 돌 수 있어요?', 30);
        $post($dm, 106, 'Sí, jefe. ¿A qué hora le viene bien?', 20);
        $post($dm, 101, '2시쯤 어때요?', 12);
    }
}
