<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'subject', 'message', 'status', 'internal_note',
        'archived_at', 'ip_hash',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
