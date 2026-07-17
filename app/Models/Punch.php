<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'in_min', 'out_min', 'out_auto', 'no_lunch', 'early_reason', 'source',
        'team_id', 'company_id', 'site_id',
        'in_lat', 'in_lng', 'in_acc', 'out_lat', 'out_lng', 'out_acc', 'in_geo_ok', 'out_geo_ok',
        'adj_in_min', 'adj_out_min', 'adj_reason', 'adj_by',
        'shift_in_snap', 'shift_out_snap',
    ];

    protected $casts = [
        'in_min' => 'integer',
        'out_min' => 'integer',
        'no_lunch' => 'boolean',
        'out_auto' => 'boolean',
        'in_lat' => 'float',
        'in_lng' => 'float',
        'in_acc' => 'float',
        'out_lat' => 'float',
        'out_lng' => 'float',
        'out_acc' => 'float',
        'in_geo_ok' => 'boolean',
        'out_geo_ok' => 'boolean',
        'adj_in_min' => 'integer',
        'adj_out_min' => 'integer',
        'adj_by' => 'integer',
        'shift_in_snap' => 'integer',
        'shift_out_snap' => 'integer',
    ];

    /** Has a team lead manually adjusted this day's paid time? */
    public function isAdjusted(): bool
    {
        return $this->adj_in_min !== null || $this->adj_out_min !== null;
    }

    /**
     * Freeze the crew's shift AS OF this work day onto the punch, so a later
     * shift edit can never retroactively change already-earned paid hours.
     * Call whenever the punch's team is (re)stamped.
     */
    public function stampShiftSnap(): void
    {
        $team = $this->team_id ? Team::find($this->team_id) : null;
        $saturday = \Illuminate\Support\Carbon::parse($this->work_date)->isSaturday();
        [$this->shift_in_snap, $this->shift_out_snap] = $team?->shiftFor($saturday) ?? [null, null];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function isComplete(): bool
    {
        return $this->in_min !== null && $this->out_min !== null;
    }
}
