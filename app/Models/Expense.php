<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One receipt / site expense. Lives in the accounting module; the receipt
 * image is stored on the object-storage disk and streamed only through the
 * gated ExpenseReceiptController.
 */
class Expense extends Model
{
    public const CATEGORIES = ['fuel', 'meal', 'transport', 'tool', 'supply', 'rental', 'other'];

    protected $fillable = [
        'site_id', 'category', 'vendor', 'amount', 'spent_on', 'note', 'status',
        'submitted_by', 'decided_by', 'decided_at', 'reject_reason',
        'att_disk', 'att_path', 'att_name', 'att_mime', 'att_size',
    ];

    protected $casts = [
        'amount' => 'float',
        'spent_on' => 'date',
        'decided_at' => 'datetime',
        'att_size' => 'integer',
    ];

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

    public function hasReceipt(): bool
    {
        return ! empty($this->att_path);
    }

    public function isImage(): bool
    {
        return $this->hasReceipt() && str_starts_with((string) $this->att_mime, 'image/');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
