<?php

namespace App\Support;

use App\Models\AttendanceCorrection;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Support\Carbon;

/** Presentation model for the internal-communication screen and the notification bell. */
class CommsView
{
    /**
     * @param  Employee  $me  the current actor
     * @param  array  $s  component state
     * @param  string  $lang  en|es|ko
     * @param  bool  $canManage  may broadcast announcements
     */
    public static function build(Employee $me, array $s, string $lang, bool $canManage): array
    {
        $teams = Team::all()->keyBy('id');
        $teamColor = fn (?string $id) => $id && $teams->has($id) ? $teams[$id]->color : '#8A8880';

        $empCache = [];
        $emp = function (int $id) use (&$empCache) {
            return $empCache[$id] ??= Employee::find($id);
        };
        $nameOf = fn (?Employee $e) => $e ? $e->displayName($lang) : '—';
        $initOf = fn (?Employee $e) => $e ? $e->initials() : '—';
        $colorOf = fn (?Employee $e) => $e ? $teamColor($e->team_id) : '#8A8880';

        $channels = Comms::visibleChannels($me);

        // last message + unread per channel (single pass, freshest first)
        $lastByChannel = Message::query()
            ->whereIn('channel_id', $channels->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('channel_id')
            ->map(fn ($g) => $g->first());

        $row = function (Channel $ch) use ($me, $lang, $teamColor, $nameOf, $lastByChannel) {
            $last = $lastByChannel->get($ch->id);
            $partner = $ch->type === 'dm' ? Comms::dmPartner($ch, $me->id) : null;
            $name = match ($ch->type) {
                'announcement' => self::tl($lang, 'Announcements', 'Anuncios', '전체 공지'),
                'dm' => $nameOf($partner),
                'correction' => self::tl($lang, 'Attendance corrections', 'Correcciones de asistencia', '출퇴근 정정요청'),
                default => $ch->name,
            };
            $preview = $last ? mb_strimwidth((string) $last->body, 0, 40, '…') : '';
            if ($last && $last->sender_id === $me->id) {
                $preview = self::tl($lang, 'You: ', 'Tú: ', '나: ').$preview;
            }

            return [
                'id' => $ch->id,
                'type' => $ch->type,
                'name' => $name,
                'color' => $ch->type === 'group' ? '#0EA5A0' : ($partner ? $teamColor($partner->team_id) : '#16181D'),
                'initials' => $ch->type === 'dm' && $partner ? $partner->initials() : null,
                'preview' => $preview,
                'time' => $last ? self::shortTime($last->created_at) : '',
                'sortKey' => $last ? $last->id : 0,
                'unread' => Comms::unreadCount($ch, $me),
            ];
        };

        $mapped = $channels->map($row);
        $sortRooms = fn ($coll) => $coll->sortBy([['sortKey', 'desc']])->values()->all();

        $groups = [
            'announcement' => $sortRooms($mapped->where('type', 'announcement')),
            'correction' => $sortRooms($mapped->where('type', 'correction')),
            'group' => $sortRooms($mapped->where('type', 'group')),
            'dm' => $sortRooms($mapped->where('type', 'dm')),
        ];

        // ---- active channel ----
        $activeId = $s['commsChannel'] ?? null;
        $activeCh = $activeId ? $channels->firstWhere('id', $activeId) : null;
        if (! $activeCh) {
            $activeCh = $channels->firstWhere('type', 'announcement') ?? $channels->first();
        }

        $active = null;
        if ($activeCh) {
            $partner = $activeCh->type === 'dm' ? Comms::dmPartner($activeCh, $me->id) : null;
            $msgs = Message::where('channel_id', $activeCh->id)->orderBy('id')->get()
                ->map(function (Message $m) use ($me, $emp, $nameOf, $initOf, $colorOf) {
                    $sender = $emp($m->sender_id);

                    return [
                        'id' => $m->id,
                        'mine' => $m->sender_id === $me->id,
                        'senderName' => $nameOf($sender),
                        'initials' => $initOf($sender),
                        'color' => $colorOf($sender),
                        'body' => $m->body,
                        'time' => self::shortTime($m->created_at),
                    ];
                })->all();

            // approver queue: owner/HR see all · a site manager their sites · a lead their crew
            $corrections = [];
            if ($activeCh->type === 'correction') {
                $global = (bool) ($s['corrGlobal'] ?? ($me->access === 'admin'));
                $sites = $s['corrSites'] ?? null;   // site-manager scope (null = n/a)
                $cq = AttendanceCorrection::where('status', 'pending')->orderBy('id');
                if (! $global) {
                    $cq->where(function ($q) use ($me, $sites) {
                        $q->where('lead_id', $me->id);
                        if ($sites) {
                            $q->orWhereIn('employee_id', Employee::whereIn('site_id', $sites)->pluck('id'));
                        }
                    });
                }
                $fmt = fn (?int $min) => $min === null ? '—' : Shift::fmtMin($min);
                $corrections = $cq->get()->map(function (AttendanceCorrection $c) use ($me, $global, $sites, $lang, $nameOf, $emp, $fmt) {
                    $worker = $emp((int) $c->employee_id);
                    $lead = $c->lead_id ? $emp((int) $c->lead_id) : null;
                    $company = $c->company_id ? Company::find($c->company_id) : null;
                    $team = $c->team_id ? Team::find($c->team_id) : null;

                    return [
                        'id' => $c->id,
                        'worker' => $nameOf($worker),
                        'workerInitials' => $worker ? $worker->initials() : '—',
                        'dateLabel' => Carbon::parse($c->work_date)->format('M j, Y (D)'),
                        'company' => $company?->name ?? '—',
                        'team' => $team?->name ?? '—',
                        'lead' => $lead ? $lead->displayName($lang) : self::tl($lang, 'HR', 'RR. HH.', '인사팀'),
                        'isDelete' => $c->type === 'delete',
                        'origIn' => $fmt($c->orig_in_min), 'origOut' => $fmt($c->orig_out_min),
                        'reqIn' => $fmt($c->req_in_min), 'reqOut' => $fmt($c->req_out_min),
                        'reason' => $c->reason,
                        'canDecide' => Corrections::canDecide($c, $me->id, $global, $sites),
                    ];
                })->all();
            }

            $corrCount = count($corrections);
            $memberCount = in_array($activeCh->type, ['group', 'dm'], true) ? count(Comms::memberIds($activeCh)) : 0;
            $titles = [
                'announcement' => self::tl($lang, 'Announcements', 'Anuncios', '전체 공지'),
                'group' => $activeCh->name,
                'dm' => $nameOf($partner),
                'correction' => self::tl($lang, 'Attendance corrections', 'Correcciones de asistencia', '출퇴근 정정요청'),
            ];
            $subs = [
                'announcement' => self::tl($lang, 'Company-wide notices', 'Avisos para toda la empresa', '회사 전체 공지'),
                'group' => self::tl($lang, "{$memberCount} members", "{$memberCount} miembros", "멤버 {$memberCount}명"),
                'dm' => self::tl($lang, 'Direct message', 'Mensaje directo', '1:1 대화'),
                'correction' => self::tl($lang, "{$corrCount} pending", "{$corrCount} pendientes", "대기 {$corrCount}건"),
            ];

            $active = [
                'id' => $activeCh->id,
                'type' => $activeCh->type,
                'title' => $titles[$activeCh->type] ?? $activeCh->name,
                'sub' => $subs[$activeCh->type] ?? '',
                'isDm' => $activeCh->type === 'dm',
                'isGroup' => $activeCh->type === 'group',
                'isCorrection' => $activeCh->type === 'correction',
                'canPost' => Comms::canPost($activeCh, $me, $canManage),
                'readOnlyNote' => self::tl($lang, 'Only admins & managers can post here.', 'Solo admins y gerentes pueden publicar.', '관리자·매니저만 공지를 올릴 수 있어요.'),
                'messages' => $msgs,
                'corrections' => $corrections,
                'corrEmpty' => self::tl($lang, 'No pending corrections', 'Sin correcciones pendientes', '대기 중인 정정요청이 없어요'),
                'rejectingId' => $s['rejectingId'] ?? null,
                'adjustingId' => $s['adjustingId'] ?? null,
                'partnerColor' => $partner ? $teamColor($partner->team_id) : '#0EA5A0',
                'partnerInitials' => $partner ? $partner->initials() : null,
            ];
        }

        // ---- bell: channels with unread, freshest first ----
        $unreadRooms = collect(array_merge($groups['announcement'], $groups['correction'], $groups['group'], $groups['dm']))
            ->filter(fn ($r) => $r['unread'] > 0)
            ->sortBy([['sortKey', 'desc']])
            ->values();
        $bell = [
            'count' => (int) $unreadRooms->sum('unread'),
            'rooms' => (int) $unreadRooms->count(),
            'items' => $unreadRooms->take(8)->map(fn ($r) => [
                'channelId' => $r['id'],
                'name' => $r['name'],
                'preview' => $r['preview'],
                'unread' => $r['unread'],
                'time' => $r['time'],
                'color' => $r['color'],
            ])->all(),
        ];

        // ---- new-chat picker: pick one (→ DM) or several (→ group room) ----
        $pickerOpen = ! empty($s['commsNewDm']) || ! empty($s['commsInviteOpen']);
        $inviteOpen = ! empty($s['commsInviteOpen']);
        $picked = array_map('intval', $s['commsPicked'] ?? []);
        $dmCandidates = [];
        if ($pickerOpen) {
            $q = trim((string) ($s['commsDmSearch'] ?? ''));
            // when inviting, hide people already in the active room
            $already = ($inviteOpen && $active) ? Comms::memberIds(Channel::find($active['id'])) : [];
            $dmCandidates = Employee::where('emp', 'active')->where('id', '!=', $me->id)
                ->whereNotIn('id', $already)
                ->get()
                ->filter(function (Employee $e) use ($q, $lang) {
                    if ($q === '') {
                        return true;
                    }
                    $hay = mb_strtolower($e->displayName($lang).' '.$e->first.' '.$e->last.' '.$e->role.' '.$e->emp_id);

                    return str_contains($hay, mb_strtolower($q));
                })
                ->sortBy(fn ($e) => $e->displayName($lang))
                ->take(40)
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->displayName($lang),
                    'initials' => $e->initials(),
                    'color' => $teamColor($e->team_id),
                    'role' => $e->role,
                    'picked' => in_array($e->id, $picked, true),
                ])->values()->all();
        }

        return [
            'me' => ['id' => $me->id, 'name' => $me->displayName($lang), 'initials' => $me->initials()],
            'groups' => $groups,
            'active' => $active,
            'bell' => $bell,
            'newDm' => ! empty($s['commsNewDm']),
            'inviteOpen' => $inviteOpen,
            'pickedCount' => count($picked),
            'roomName' => $s['commsRoomName'] ?? '',
            'reportOpen' => ! empty($s['reportOpen']),
            'reportDraft' => $s['reportDraft'] ?? '',
            'speechLang' => match ($lang) {
                'ko' => 'ko-KR',
                'es' => 'es-MX',
                default => 'en-US',
            },
            'dmSearch' => $s['commsDmSearch'] ?? '',
            'dmCandidates' => $dmCandidates,
            'mobilePane' => (($s['commsPane'] ?? 'list') === 'thread') ? 'thread' : 'list',
            'labels' => [
                'title' => self::tl($lang, 'Internal Comms', 'Comunicación', '내부 소통방'),
                'sub' => self::tl($lang, 'Announcements · rooms · DM', 'Anuncios · salas · DM', '공지 · 채팅방 · DM'),
                'announcements' => self::tl($lang, 'Announcements', 'Anuncios', '공지'),
                'rooms' => self::tl($lang, 'Chat rooms', 'Salas', '채팅방'),
                'corrections' => self::tl($lang, 'Corrections', 'Correcciones', '정정요청'),
                'dms' => self::tl($lang, 'Direct messages', 'Mensajes directos', 'DM'),
                'newChat' => self::tl($lang, 'New chat', 'Nuevo chat', '새 채팅'),
                'report' => self::tl($lang, 'Daily report', 'Reporte del día', '업무보고'),
                'reportTitle' => self::tl($lang, 'Voice daily report', 'Reporte diario por voz', '음성 업무보고'),
                'reportHint' => self::tl($lang, 'Pick your language, tap the mic and just talk — English & Spanish reports get a Korean translation attached.', 'Elige tu idioma, toca el micrófono y habla — los reportes en español llevan traducción al coreano.', '언어를 고르고 마이크를 눌러 말하면 AI가 보고서로 정리합니다. 영어·스페인어 보고에는 한국어 번역이 함께 붙습니다.'),
                'micStart' => self::tl($lang, 'Start talking', 'Hablar', '말하기 시작'),
                'micStop' => self::tl($lang, 'Stop', 'Detener', '멈추기'),
                'micListening' => self::tl($lang, 'Listening…', 'Escuchando…', '듣는 중…'),
                'micUnsupported' => self::tl($lang, 'Voice input not supported on this browser — type below instead.', 'Este navegador no soporta voz — escribe abajo.', '이 브라우저는 음성 입력을 지원하지 않아요 — 아래에 입력해 주세요.'),
                'reportRawPh' => self::tl($lang, 'Your spoken words appear here — you can edit or type too…', 'Lo dictado aparece aquí — también puedes escribir…', '말한 내용이 여기에 표시됩니다 — 직접 입력·수정도 가능…'),
                'reportGen' => self::tl($lang, 'Make report', 'Generar reporte', '보고서 생성'),
                'reportGenBusy' => self::tl($lang, 'Formatting…', 'Generando…', 'AI 정리 중…'),
                'reportDraftLabel' => self::tl($lang, 'Report preview — edit freely, then post', 'Vista previa — edita y publica', '보고서 미리보기 — 수정 후 게시하세요'),
                'reportPost' => self::tl($lang, 'Post report', 'Publicar', '보고서 게시'),
                'reportRedo' => self::tl($lang, 'Redo', 'Rehacer', '다시 만들기'),
                'roomNamePh' => self::tl($lang, 'Room name (optional)', 'Nombre de sala (opcional)', '채팅방 이름 (선택)'),
                'createChat' => self::tl($lang, 'Create', 'Crear', '만들기'),
                'invite' => self::tl($lang, 'Invite', 'Invitar', '초대'),
                'inviteAdd' => self::tl($lang, 'Add to room', 'Añadir a la sala', '채팅방에 추가'),
                'leaveRoom' => self::tl($lang, 'Leave', 'Salir', '나가기'),
                'pickNobody' => self::tl($lang, 'Pick people to start a chat', 'Elige personas', '대화 상대를 선택하세요'),
                'corrCurrent' => self::tl($lang, 'Current', 'Actual', '현재'),
                'corrRequested' => self::tl($lang, 'Requested', 'Solicitado', '요청'),
                'corrReason' => self::tl($lang, 'Reason', 'Motivo', '사유'),
                'corrDelete' => self::tl($lang, 'Delete this day’s record', 'Eliminar el registro', '기록 삭제 요청'),
                'corrApprove' => self::tl($lang, 'Approve', 'Aprobar', '승인'),
                'corrAdjust' => self::tl($lang, 'Adjust', 'Ajustar', '조정'),
                'corrAdjustHint' => self::tl($lang, 'Edit the times, then approve', 'Edita las horas y aprueba', '시각을 수정한 뒤 승인'),
                'corrConfirmAdjust' => self::tl($lang, 'Adjust & approve', 'Ajustar y aprobar', '조정 후 승인'),
                'corrIn' => self::tl($lang, 'Clock-in', 'Entrada', '출근'),
                'corrOut' => self::tl($lang, 'Clock-out', 'Salida', '퇴근'),
                'corrReject' => self::tl($lang, 'Reject', 'Rechazar', '반려'),
                'corrRejectPh' => self::tl($lang, 'Reason for rejection (optional)', 'Motivo del rechazo (opcional)', '반려 사유 (선택)'),
                'corrConfirmReject' => self::tl($lang, 'Confirm reject', 'Confirmar rechazo', '반려 확정'),
                'newDm' => self::tl($lang, 'New DM', 'Nuevo DM', '새 DM'),
                'dmSearchPh' => self::tl($lang, 'Search a person…', 'Buscar persona…', '이름·역할 검색…'),
                'compose' => self::tl($lang, 'Write a message…', 'Escribe un mensaje…', '메시지 입력…'),
                'announce' => self::tl($lang, 'Post announcement…', 'Publicar anuncio…', '공지 작성…'),
                'send' => self::tl($lang, 'Send', 'Enviar', '보내기'),
                'empty' => self::tl($lang, 'No messages yet — say hello.', 'Aún no hay mensajes.', '아직 메시지가 없어요.'),
                'bellEmpty' => self::tl($lang, 'All caught up', 'Todo al día', '새 알림이 없어요'),
                'bellTitle' => self::tl($lang, 'Notifications', 'Notificaciones', '알림'),
                'openComms' => self::tl($lang, 'Open comms', 'Abrir', '소통방 열기'),
                'cancel' => self::tl($lang, 'Cancel', 'Cancelar', '취소'),
                'back' => self::tl($lang, 'Rooms', 'Salas', '목록'),
            ],
        ];
    }

    protected static function tl(string $lang, string $en, string $es, string $ko): string
    {
        return $lang === 'ko' ? $ko : ($lang === 'es' ? $es : $en);
    }

    protected static function shortTime(?Carbon $t): string
    {
        if (! $t) {
            return '';
        }
        if ($t->isToday()) {
            return $t->format('g:i A');
        }
        if ($t->isYesterday()) {
            return $t->format('M j');
        }

        return $t->format('M j');
    }
}
