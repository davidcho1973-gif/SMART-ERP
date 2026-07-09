<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'emp_id', 'badge_qr', 'badge_photo', 'first', 'last', 'ko', 'nat', 'code',
        'team_id', 'company_id', 'site_id', 'role', 'type', 'pay_type', 'lang', 'access',
        'rate', 'issued', 'phone', 'email', 'status', 'in_t', 'out_t', 'wh', 'emp', 'term', 'activated_at',
        'resign_on', 'resign_reason',
        'dispatch_to', 'dispatch_from', 'dispatch_until', 'dispatch_note',
    ];

    protected $casts = [
        'rate' => 'float',
        'wh' => 'integer',
        'activated_at' => 'datetime',
    ];

    /** Currently dispatched to another state (out-of-state assignment). */
    public function isDispatched(): bool
    {
        return ! empty($this->dispatch_to);
    }

    /** Invited but not yet logged in (no first authenticated session). */
    public function isInvited(): bool
    {
        return $this->emp === 'active' && $this->activated_at === null;
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function isManager(): bool
    {
        return $this->type === 'manager';
    }

    /** Hourly or salary+hourly workers have their bi-weekly pay calculated; pure salary does not. */
    public function isHourlyPaid(): bool
    {
        return in_array($this->pay_type, ['hourly', 'both'], true);
    }

    public function isActive(): bool
    {
        return $this->emp === 'active';
    }

    public function isTerminated(): bool
    {
        return $this->emp === 'terminated';
    }

    /** Has the worker filed a resignation notice still awaiting approval? */
    public function hasPendingResignation(): bool
    {
        return $this->emp === 'active' && ! empty($this->resign_on);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'employee_id');
    }

    public function absences()
    {
        return $this->hasMany(Absence::class, 'employee_id');
    }

    /** Display name — Korean when lang=ko and a Korean name exists. */
    public function displayName(string $lang): string
    {
        return $lang === 'ko' && $this->ko ? $this->ko : ($this->first . ' ' . $this->last);
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->first, 0, 1) . mb_substr($this->last, 0, 1));
    }
}
