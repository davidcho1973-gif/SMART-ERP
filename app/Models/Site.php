<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'city', 'gc', 'code', 'lat', 'lng', 'radius_m', 'join_token'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius_m' => 'integer',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class, 'site_id');
    }

    /** The self-sign-up token, minting one on first use. */
    public function ensureJoinToken(): string
    {
        if (empty($this->join_token)) {
            do {
                $t = Str::lower(Str::random(10));
            } while (self::where('join_token', $t)->exists());
            $this->forceFill(['join_token' => $t])->save();
        }

        return $this->join_token;
    }
}
