<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'city', 'gc', 'code', 'lat', 'lng', 'radius_m'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius_m' => 'integer',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class, 'site_id');
    }
}
