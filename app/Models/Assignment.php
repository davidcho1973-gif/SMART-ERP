<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = ['employee_id', 'company_id', 'team_id', 'relation'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
