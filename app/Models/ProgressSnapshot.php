<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cumulative % complete for a site at the end of a month (YYYY-MM). */
class ProgressSnapshot extends Model
{
    protected $fillable = ['site_id', 'ym', 'pct', 'note'];

    protected $casts = ['pct' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
