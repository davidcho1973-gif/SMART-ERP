<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A piece of equipment — owned (구매) or rented (랜트). */
class Equipment extends Model
{
    protected $table = 'equipment';

    public const STATUSES = ['available', 'out', 'maintenance', 'returned', 'disposed'];

    protected $fillable = [
        'name', 'type', 'acquisition', 'serial', 'asset_tag', 'qr_token', 'status',
        'site_id', 'holder_id', 'meter', 'meter_unit', 'condition',
        'purchase_date', 'purchase_cost', 'useful_life_months', 'salvage_value',
        'vendor', 'rental_rate', 'rate_unit', 'rental_start', 'rental_end', 'deposit',
        'note', 'created_by',
    ];

    protected $casts = [
        'meter' => 'float', 'purchase_cost' => 'float', 'salvage_value' => 'float',
        'rental_rate' => 'float', 'deposit' => 'float', 'useful_life_months' => 'integer',
        'purchase_date' => 'date', 'rental_start' => 'date', 'rental_end' => 'date',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(EquipmentPhoto::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EquipmentEvent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'holder_id');
    }

    public function isRented(): bool
    {
        return $this->acquisition === 'rented';
    }

    /** Days until the expected rental return (negative = overdue), or null. */
    public function daysToReturn(): ?int
    {
        if (! $this->isRented() || ! $this->rental_end) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->rental_end, false);
    }

    /** Straight-line book value for an owned asset as of today, or null. */
    public function bookValue(): ?float
    {
        if ($this->isRented() || ! $this->purchase_cost || ! $this->purchase_date || ! $this->useful_life_months) {
            return null;
        }
        $salvage = (float) ($this->salvage_value ?? 0);
        $months = (int) Carbon::parse($this->purchase_date)->diffInMonths(Carbon::today());
        $monthly = ($this->purchase_cost - $salvage) / max(1, $this->useful_life_months);
        $depreciated = min($this->purchase_cost - $salvage, $monthly * $months);

        return round(max($salvage, $this->purchase_cost - $depreciated), 2);
    }

    /** Approx monthly straight-line depreciation for an owned asset, or null. */
    public function monthlyDepreciation(): ?float
    {
        if ($this->isRented() || ! $this->purchase_cost || ! $this->useful_life_months) {
            return null;
        }

        return round(($this->purchase_cost - (float) ($this->salvage_value ?? 0)) / max(1, $this->useful_life_months), 2);
    }
}
