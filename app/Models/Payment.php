<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'employee_id', 'period_start', 'period_end', 'check_no', 'pay_date',
        'amount', 'reg_hours', 'ot_hours',
    ];

    protected $casts = [
        'amount' => 'float',
        'reg_hours' => 'integer',
        'ot_hours' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
