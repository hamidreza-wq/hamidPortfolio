<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseCandidate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'release_assets' => 'array', 'manifest' => 'array', 'inspection' => 'array',
            'released_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime',
        ];
    }
}
