<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_identifier',
        'receiver_identifier',
        'group_id',
        'reply_to_id',
        'body',
        'attachment_url',
        'is_read',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }
}