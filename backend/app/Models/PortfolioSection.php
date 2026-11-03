<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSection extends Model
{
    protected $fillable = [
        'key', 'label', 'title', 'subtitle', 'body', 'is_visible',
        'sort_order', 'background_effect', 'spacing_top', 'spacing_bottom',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'spacing_top' => 'integer',
            'spacing_bottom' => 'integer',
        ];
    }
}
