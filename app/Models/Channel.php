<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = ['type', 'name', 'company_id', 'team_id', 'created_by'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function members()
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function isDm(): bool
    {
        return $this->type === 'dm';
    }

    public function isAnnouncement(): bool
    {
        return $this->type === 'announcement';
    }
}
