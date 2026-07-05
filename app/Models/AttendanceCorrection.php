<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'type',
        'req_in_min', 'req_out_min', 'appl_in_min', 'appl_out_min', 'orig_in_min', 'orig_out_min',
        'reason', 'company_id', 'team_id', 'lead_id',
        'status', 'decided_by', 'decided_at', 'decision_note', 'channel_id',
    ];

    protected $casts = [
        'req_in_min' => 'integer',
        'req_out_min' => 'integer',
        'appl_in_min' => 'integer',
        'appl_out_min' => 'integer',
        'orig_in_min' => 'integer',
        'orig_out_min' => 'integer',
        'lead_id' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
