<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'in_min', 'out_min', 'no_lunch', 'early_reason', 'source',
        'in_lat', 'in_lng', 'in_acc', 'out_lat', 'out_lng', 'out_acc', 'in_geo_ok', 'out_geo_ok',
    ];

    protected $casts = [
        'in_min' => 'integer',
        'out_min' => 'integer',
        'no_lunch' => 'boolean',
        'in_lat' => 'float',
        'in_lng' => 'float',
        'in_acc' => 'float',
        'out_lat' => 'float',
        'out_lng' => 'float',
        'out_acc' => 'float',
        'in_geo_ok' => 'boolean',
        'out_geo_ok' => 'boolean',
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
