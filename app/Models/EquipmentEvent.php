<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A lifecycle event for a piece of equipment (checkout·checkin·maintenance·return). */
class EquipmentEvent extends Model
{
    protected $fillable = ['equipment_id', 'type', 'site_id', 'employee_id', 'at', 'meter', 'note'];

    protected $casts = ['at' => 'datetime', 'meter' => 'float'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
