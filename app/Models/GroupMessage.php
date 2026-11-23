<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMessage extends Model
{
    protected $table = 'group_messages';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class, 'reply_to_id');
    }
}
