<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['channel_id', 'sender_id', 'body', 'att_disk', 'att_path', 'att_name', 'att_mime', 'att_size'];

    public function hasFile(): bool
    {
        return ! empty($this->att_path);
    }

    public function isImage(): bool
    {
        return $this->hasFile() && str_starts_with((string) $this->att_mime, 'image/');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function sender()
    {
        return $this->belongsTo(Employee::class, 'sender_id');
    }
}
