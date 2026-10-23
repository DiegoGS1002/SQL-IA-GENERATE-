<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function generation(): HasOne
    {
        return $this->hasOne(SqlGeneration::class, 'chat_message_id');
    }
}
