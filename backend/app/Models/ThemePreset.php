<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemePreset extends Model
{
    protected $fillable = [
        'name', 'color_background', 'color_foreground', 'color_primary',
        'color_accent', 'color_cyan', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
