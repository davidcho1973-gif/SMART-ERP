<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Internal communication surface: org announcements, company & crew rooms, and 1:1 DMs.
 * Shared rooms (announcement/company/team) derive their membership from the roster, so they
 * never need syncing when people move; DMs store their two participants explicitly.
 */
class Comms
{
    /** Whether an employee is an attendance-correction approver (staff role or crew lead). */
    public static function isApprover(Employee $me): bool
    {
        return in_array(Access::canonical($me->access), ['owner', 'hr_admin', 'site_manager'], true)
            || Team::where('lead', $me->id)->exists();
    }

    /** Whether an employee may see (and post in) a channel. */
    public static function canAccess(Channel $ch, Employee $me): bool
    {
        return match ($ch->type) {
            'announcement' => true,
            'correction' => self::isApprover($me),
            // rooms (group) and DMs are invite-based: membership is explicit
            'group', 'dm' => ChannelMember::where('channel_id', $ch->id)->where('employee_id', $me->id)->exists(),
            default => false,   // legacy company/team rooms are retired
        };
    }

    /** Only admins/managers may broadcast to an announcement room; the correction room is a queue. */
    public static function canPost(Channel $ch, Employee $me, bool $canManage): bool
    {
        if (! self::canAccess($ch, $me)) {
            return false;
        }
        if ($ch->type === 'correction') {
            return false; // approve/reject actions, not chat
        }

        return $ch->type === 'announcement' ? $canManage : true;
    }

    /** Make sure the one standing room exists: the org announcement channel. Idempotent. */
    public static function ensureRooms(): void
    {
        Channel::firstOrCreate(['type' => 'announcement'], ['name' => '전체 공지']);
    }

    /** Find (or open) the DM room between two employees. */
    public static function findOrCreateDm(int $aId, int $bId): Channel
    {
        [$lo, $hi] = $aId <= $bId ? [$aId, $bId] : [$bId, $aId];

        $existing = Channel::where('type', 'dm')
            ->whereHas('members', fn ($q) => $q->where('employee_id', $lo))
            ->whereHas('members', fn ($q) => $q->where('employee_id', $hi))
            ->first();
        if ($existing) {
            return $existing;
        }

        $ch = Channel::create(['type' => 'dm', 'created_by' => $aId]);
        $ch->members()->create(['employee_id' => $lo]);
        $ch->members()->create(['employee_id' => $hi]);

        return $ch;
    }

    /** Create an invite-based group room: the creator plus the invited members. */
    public static function createRoom(string $name, int $creatorId, array $memberIds): Channel
    {
        $ch = Channel::create([
            'type' => 'group',
            'name' => mb_substr(trim($name), 0, 60) ?: '새 채팅방',
            'created_by' => $creatorId,
        ]);
        self::addMembers($ch, array_merge([$creatorId], $memberIds));

        return $ch;
    }

    /** Add employees to a group room (no-op for ids already in). */
    public static function addMembers(Channel $ch, array $memberIds): void
    {
        foreach (array_values(array_unique(array_filter($memberIds))) as $id) {
            if (Employee::whereKey($id)->exists()) {
                $ch->members()->firstOrCreate(['employee_id' => $id]);
            }
        }
    }

    /** Remove an employee from a group room; delete the room once it is empty. */
    public static function leaveRoom(Channel $ch, int $empId): void
    {
        ChannelMember::where('channel_id', $ch->id)->where('employee_id', $empId)->delete();
        if ($ch->type === 'group' && $ch->members()->count() === 0) {
            Message::where('channel_id', $ch->id)->delete();
            $ch->delete();
        }
    }

    /** Employee ids in a room. */
    public static function memberIds(Channel $ch): array
    {
        return ChannelMember::where('channel_id', $ch->id)->pluck('employee_id')->all();
    }

    /** All channels an employee can see. */
    public static function visibleChannels(Employee $me): Collection
    {
        $approver = self::isApprover($me);

        return Channel::query()
            ->where(function ($q) use ($me, $approver) {
                $q->where('type', 'announcement')
                    ->orWhere(fn ($q) => $q->whereIn('type', ['group', 'dm'])
                        ->whereHas('members', fn ($m) => $m->where('employee_id', $me->id)));
                if ($approver) {
                    $q->orWhere('type', 'correction');
                }
            })
            ->get();
    }

    /** Total unread across everything the employee can see (drives the bell + ping). */
    public static function totalUnread(Employee $me): int
    {
        return self::visibleChannels($me)->sum(fn (Channel $ch) => self::unreadCount($ch, $me));
    }

    /** Unread messages in a channel for an employee (own messages never count). */
    public static function unreadCount(Channel $ch, Employee $me): int
    {
        $member = ChannelMember::where('channel_id', $ch->id)
            ->where('employee_id', $me->id)->first();
        $since = $member?->last_read_at;

        $q = Message::where('channel_id', $ch->id)->where('sender_id', '!=', $me->id);
        if ($since) {
            $q->where('created_at', '>', $since);
        }

        return $q->count();
    }

    /** Move an employee's read cursor to now for a channel. */
    public static function markRead(Channel $ch, Employee $me): void
    {
        ChannelMember::updateOrCreate(
            ['channel_id' => $ch->id, 'employee_id' => $me->id],
            ['last_read_at' => now()],
        );
    }

    /** The other participant of a DM (from the current employee's side). */
    public static function dmPartner(Channel $ch, int $meId): ?Employee
    {
        $otherId = ChannelMember::where('channel_id', $ch->id)
            ->where('employee_id', '!=', $meId)->value('employee_id');

        return $otherId ? Employee::find($otherId) : null;
    }
}
