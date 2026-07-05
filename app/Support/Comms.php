<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Company;
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
    /** Company ids an employee belongs to (primary company + any involvement assignments). */
    public static function companyIdsFor(Employee $me): array
    {
        $ids = Assignment::where('employee_id', $me->id)->pluck('company_id')->all();
        if ($me->company_id) {
            $ids[] = $me->company_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Crew ids an employee belongs to (primary crew + any involvement assignments). */
    public static function teamIdsFor(Employee $me): array
    {
        $ids = Assignment::where('employee_id', $me->id)->pluck('team_id')->all();
        if ($me->team_id) {
            $ids[] = $me->team_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Whether an employee may see (and post in) a channel. */
    public static function canAccess(Channel $ch, Employee $me): bool
    {
        return match ($ch->type) {
            'announcement' => true,
            'company' => in_array($ch->company_id, self::companyIdsFor($me), true),
            'team' => in_array($ch->team_id, self::teamIdsFor($me), true),
            'dm' => ChannelMember::where('channel_id', $ch->id)->where('employee_id', $me->id)->exists(),
            default => false,
        };
    }

    /** Only admins/managers may broadcast to an announcement room. */
    public static function canPost(Channel $ch, Employee $me, bool $canManage): bool
    {
        if (! self::canAccess($ch, $me)) {
            return false;
        }

        return $ch->type === 'announcement' ? $canManage : true;
    }

    /**
     * Make sure the standing rooms exist: one org announcement channel, one room per
     * company, and one room per crew. Idempotent — safe to call on every screen open.
     */
    public static function ensureRooms(): void
    {
        Channel::firstOrCreate(['type' => 'announcement'], ['name' => '전체 공지']);

        foreach (Company::all() as $c) {
            Channel::firstOrCreate(
                ['type' => 'company', 'company_id' => $c->id],
                ['name' => $c->name],
            );
        }
        foreach (Team::all() as $t) {
            Channel::firstOrCreate(
                ['type' => 'team', 'team_id' => $t->id],
                ['name' => $t->name],
            );
        }
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

    /** All channels an employee can see, freshest activity first. */
    public static function visibleChannels(Employee $me): Collection
    {
        $companyIds = self::companyIdsFor($me);
        $teamIds = self::teamIdsFor($me);

        return Channel::query()
            ->where(function ($q) use ($me, $companyIds, $teamIds) {
                $q->where('type', 'announcement')
                    ->orWhere(fn ($q) => $q->where('type', 'company')->whereIn('company_id', $companyIds ?: ['__none__']))
                    ->orWhere(fn ($q) => $q->where('type', 'team')->whereIn('team_id', $teamIds ?: ['__none__']))
                    ->orWhere(fn ($q) => $q->where('type', 'dm')
                        ->whereHas('members', fn ($m) => $m->where('employee_id', $me->id)));
            })
            ->get();
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
