<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A no-show record for one work day. kind 'unexcused' = 무단결근 (no-call-no-show),
 * 'excused' = 결근 with a reason (called in). One row per (employee, work_date).
 */
class Absence extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'kind', 'reason', 'source', 'marked_by',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'marked_by' => 'integer',
    ];

    public function isUnexcused(): bool
    {
        return $this->kind === 'unexcused';
    }
}
