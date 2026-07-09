<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A date-range leave request (휴가). Pending until a lead/manager decides it;
 * an approved leave covering "today" makes the worker read as 휴가중.
 */
class Leave extends Model
{
    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'reason', 'status', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Does this (approved) leave cover the given YYYY-MM-DD? */
    public function covers(string $ymd): bool
    {
        return $this->status === 'approved' && $this->start_date <= $ymd && $ymd <= $this->end_date;
    }
}
