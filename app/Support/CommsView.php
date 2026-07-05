<?php

namespace App\Support;

use App\Models\Channel;
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
                'color' => $ch->type === 'team' ? $teamColor($ch->team_id) : ($partner ? $teamColor($partner->team_id) : '#16181D'),
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
            'company' => $sortRooms($mapped->where('type', 'company')),
            'team' => $sortRooms($mapped->where('type', 'team')),
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

            $titles = [
                'announcement' => self::tl($lang, 'Announcements', 'Anuncios', '전체 공지'),
                'company' => $activeCh->name,
                'team' => $activeCh->name,
                'dm' => $nameOf($partner),
            ];
            $subs = [
                'announcement' => self::tl($lang, 'Company-wide notices', 'Avisos para toda la empresa', '회사 전체 공지'),
                'company' => self::tl($lang, 'Company room', 'Sala de empresa', '회사 채팅방'),
                'team' => self::tl($lang, 'Crew room', 'Sala de cuadrilla', '팀 채팅방'),
                'dm' => self::tl($lang, 'Direct message', 'Mensaje directo', '1:1 대화'),
            ];

            $active = [
                'id' => $activeCh->id,
                'type' => $activeCh->type,
                'title' => $titles[$activeCh->type] ?? $activeCh->name,
                'sub' => $subs[$activeCh->type] ?? '',
                'isDm' => $activeCh->type === 'dm',
                'canPost' => Comms::canPost($activeCh, $me, $canManage),
                'readOnlyNote' => self::tl($lang, 'Only admins & managers can post here.', 'Solo admins y gerentes pueden publicar.', '관리자·매니저만 공지를 올릴 수 있어요.'),
                'messages' => $msgs,
                'partnerColor' => $partner ? $teamColor($partner->team_id) : '#16181D',
                'partnerInitials' => $partner ? $partner->initials() : null,
            ];
        }

        // ---- bell: channels with unread, freshest first ----
        $unreadRooms = collect(array_merge($groups['announcement'], $groups['company'], $groups['team'], $groups['dm']))
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

        // ---- new-DM candidates (active roster except me) ----
        $dmCandidates = [];
        if (! empty($s['commsNewDm'])) {
            $q = trim((string) ($s['commsDmSearch'] ?? ''));
            $dmCandidates = Employee::where('emp', 'active')->where('id', '!=', $me->id)
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
                ])->values()->all();
        }

        return [
            'me' => ['id' => $me->id, 'name' => $me->displayName($lang), 'initials' => $me->initials()],
            'groups' => $groups,
            'active' => $active,
            'bell' => $bell,
            'newDm' => ! empty($s['commsNewDm']),
            'dmSearch' => $s['commsDmSearch'] ?? '',
            'dmCandidates' => $dmCandidates,
            'mobilePane' => (($s['commsPane'] ?? 'list') === 'thread') ? 'thread' : 'list',
            'labels' => [
                'title' => self::tl($lang, 'Internal Comms', 'Comunicación', '내부 소통방'),
                'sub' => self::tl($lang, 'Announcements · company & crew chat · DM', 'Anuncios · chat de empresa y cuadrilla · DM', '공지 · 회사·팀 채팅 · DM'),
                'announcements' => self::tl($lang, 'Announcements', 'Anuncios', '공지'),
                'companies' => self::tl($lang, 'Company rooms', 'Salas de empresa', '회사 채팅'),
                'crews' => self::tl($lang, 'Crew rooms', 'Salas de cuadrilla', '팀 채팅'),
                'dms' => self::tl($lang, 'Direct messages', 'Mensajes directos', 'DM'),
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
