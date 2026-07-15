<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One photo of a piece of equipment (nameplate · main · meter · condition …). */
class EquipmentPhoto extends Model
{
    public const KINDS = ['main', 'plate', 'meter', 'side', 'condition'];

    protected $fillable = ['equipment_id', 'kind', 'att_disk', 'att_path', 'att_name', 'att_mime', 'att_size', 'caption'];

    protected $casts = ['att_size' => 'integer'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->att_mime, 'image/');
    }
}
