<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One materials inbound document (delivery slip · manual · opening stock) with
 * its line items. Only approved delivery/manual batches count toward cost.
 */
class MaterialBatch extends Model
{
    public const KINDS = ['delivery', 'manual', 'opening'];

    protected $fillable = [
        'site_id', 'vendor', 'spent_on', 'kind', 'status',
        'submitted_by', 'decided_by', 'decided_at', 'reject_reason', 'note',
        'att_disk', 'att_path', 'att_name', 'att_mime', 'att_size',
    ];

    protected $casts = [
        'spent_on' => 'date',
        'decided_at' => 'datetime',
        'att_size' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialLine::class, 'batch_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }

    public function hasImage(): bool
    {
        return ! empty($this->att_path);
    }

    public function isImage(): bool
    {
        return $this->hasImage() && str_starts_with((string) $this->att_mime, 'image/');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** delivery & manual carry cost; opening is quantity-only (already paid before). */
    public function isCosted(): bool
    {
        return $this->kind !== 'opening';
    }

    public function total(): float
    {
        return (float) $this->lines->sum('amount');
    }
}
