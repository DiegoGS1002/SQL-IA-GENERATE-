<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['firebird_connection_id', 'title'];

    public function firebirdConnection(): BelongsTo
    {
        return $this->belongsTo(FirebirdConnection::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(SqlGeneration::class);
    }
}
