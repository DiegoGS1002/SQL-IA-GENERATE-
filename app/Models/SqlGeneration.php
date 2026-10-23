<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SqlGeneration extends Model
{
    protected $fillable = ['conversation_id', 'chat_message_id', 'schema_fingerprint', 'sql', 'explanation', 'parameters', 'warnings', 'validation_status'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'warnings' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
