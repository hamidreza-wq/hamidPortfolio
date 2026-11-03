<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoFinding extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
