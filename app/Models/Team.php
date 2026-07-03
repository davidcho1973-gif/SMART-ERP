<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'company_id', 'lead', 'color'];

    protected $casts = ['lead' => 'integer'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'team_id');
    }

    public function leadEmployee()
    {
        return $this->belongsTo(Employee::class, 'lead');
    }
}
