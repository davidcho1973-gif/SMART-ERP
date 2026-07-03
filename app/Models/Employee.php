<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'emp_id', 'first', 'last', 'ko', 'nat', 'code',
        'team_id', 'company_id', 'site_id', 'role', 'type', 'lang', 'access',
        'rate', 'issued', 'phone', 'email', 'status', 'in_t', 'out_t', 'wh', 'emp', 'term',
    ];

    protected $casts = [
        'rate' => 'float',
        'wh' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function isManager(): bool
    {
        return $this->type === 'manager';
    }

    public function isActive(): bool
    {
        return $this->emp === 'active';
    }

    public function isTerminated(): bool
    {
        return $this->emp === 'terminated';
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
