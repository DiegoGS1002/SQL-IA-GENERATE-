<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaSnapshot extends Model
{
    protected $fillable = ['firebird_connection_id', 'fingerprint', 'catalog'];

    protected function casts(): array
    {
        return ['catalog' => 'array'];
    }

    public function firebirdConnection(): BelongsTo
    {
        return $this->belongsTo(FirebirdConnection::class);
    }
}
