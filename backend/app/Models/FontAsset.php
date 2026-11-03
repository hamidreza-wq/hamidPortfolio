<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FontAsset extends Model
{
    protected $fillable = [
        'family', 'weight', 'style', 'format', 'disk', 'path',
        'license_note', 'license_confirmed',
    ];

    protected function casts(): array
    {
        return ['license_confirmed' => 'boolean'];
    }
}
