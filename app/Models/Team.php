<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'company_id', 'lead', 'color', 'shift_in', 'shift_out', 'sat_in', 'sat_out'];

    protected $casts = [
        'lead' => 'integer',
        'shift_in' => 'integer', 'shift_out' => 'integer',
        'sat_in' => 'integer', 'sat_out' => 'integer',
    ];

    /** Has a real work shift been configured for this crew? */
    public function hasShift(): bool
    {
        return $this->shift_in !== null && $this->shift_out !== null;
    }

    /**
     * The scheduled [inMin, outMin] for a day, or null when unconfigured.
     * Saturday uses the Saturday shift when set, otherwise the weekday shift.
     */
    public function shiftFor(bool $saturday): ?array
    {
        if ($saturday && $this->sat_in !== null && $this->sat_out !== null) {
            return [$this->sat_in, $this->sat_out];
        }
        if (! $this->hasShift()) {
            return null;
        }

        return [$this->shift_in, $this->shift_out];
    }

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
