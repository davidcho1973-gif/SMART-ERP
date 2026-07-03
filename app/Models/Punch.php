<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'in_min', 'out_min', 'no_lunch', 'early_reason', 'source',
    ];

    protected $casts = [
        'in_min' => 'integer',
        'out_min' => 'integer',
        'no_lunch' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isComplete(): bool
    {
        return $this->in_min !== null && $this->out_min !== null;
    }
}
