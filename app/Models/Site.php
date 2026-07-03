<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'city', 'gc', 'code'];

    public function companies()
    {
        return $this->hasMany(Company::class, 'site_id');
    }
}
