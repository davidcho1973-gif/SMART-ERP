<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line item within a materials batch (name · qty · unit price · amount). */
class MaterialLine extends Model
{
    protected $fillable = ['batch_id', 'name', 'unit', 'qty', 'unit_price', 'amount'];

    protected $casts = [
        'qty' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class, 'batch_id');
    }
}
