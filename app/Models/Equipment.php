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

    /** Rental rate normalised to a per-day figure (day/week/month → day), or 0. */
    public function perDayRate(): float
    {
        if (! $this->isRented() || ! $this->rental_rate) {
            return 0.0;
        }

        return match ($this->rate_unit) {
            'week' => $this->rental_rate / 7,
            'month' => $this->rental_rate / 30,
            default => (float) $this->rental_rate,
        };
    }

    /**
     * Rent accrued for this rented unit within a month window — the overlap of
     * [rental_start, rental_end?] with the month, billed per day. In the current
     * month we stop at $asOf (today) so we never accrue future days.
     */
    public function rentAccrual(Carbon $monthStart, Carbon $monthEnd, ?Carbon $asOf = null): float
    {
        if (! $this->isRented() || ! $this->rental_rate || ! $this->rental_start || $this->status === 'disposed') {
            return 0.0;
        }
        $start = $this->rental_start->greaterThan($monthStart) ? $this->rental_start->copy() : $monthStart->copy();
        $end = $monthEnd->copy();
        if ($this->rental_end && $this->rental_end->lessThan($end)) {
            $end = $this->rental_end->copy();
        }
        if ($asOf && $asOf->lessThan($end)) {
            $end = $asOf->copy();
        }
        if ($end->lessThan($start)) {
            return 0.0;
        }
        $days = $start->diffInDays($end) + 1;   // inclusive of both endpoints

        return round($this->perDayRate() * $days, 2);
    }

    /**
     * A rented unit that is on the books and still inside its rental window but
     * NOT deployed to a site is idle — it keeps accruing rent for nothing. The
     * projected saving of returning it now is the per-day rate over the days
     * left until its expected return (capped so an open-ended rental is bounded).
     */
    public function isIdleRental(): bool
    {
        if (! $this->isRented() || ! $this->rental_rate || $this->status === 'disposed' || $this->status === 'returned') {
            return false;
        }
        if ($this->status === 'out') {
            return false;   // deployed → in use
        }
        $due = $this->daysToReturn();

        return $due === null || $due > 0;   // still within (or open-ended) rental period
    }

    /** Projected saving from returning an idle rental now (per-day × days left, capped 30). */
    public function idleSaving(int $capDays = 30): float
    {
        if (! $this->isIdleRental()) {
            return 0.0;
        }
        $due = $this->daysToReturn();
        $days = $due === null ? $capDays : min($capDays, max(1, $due));

        return round($this->perDayRate() * $days, 2);
    }
}
