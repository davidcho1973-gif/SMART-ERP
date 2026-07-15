<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A site's head contract value (계약금액) — the base for progress billing. */
class Contract extends Model
{
    protected $fillable = ['site_id', 'amount', 'note'];

    protected $casts = ['amount' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
