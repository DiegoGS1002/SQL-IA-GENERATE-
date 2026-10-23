<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FirebirdConnection extends Model
{
    protected $fillable = ['label', 'host', 'port', 'database_path', 'username', 'password', 'charset', 'status', 'server_version', 'last_connected_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'last_connected_at' => 'datetime'];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SchemaSnapshot::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
