<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'site_id'];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'company_id');
    }
}
