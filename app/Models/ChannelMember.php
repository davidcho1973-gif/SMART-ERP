<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelMember extends Model
{
    protected $fillable = ['channel_id', 'employee_id', 'last_read_at'];

    protected $casts = ['last_read_at' => 'datetime'];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
