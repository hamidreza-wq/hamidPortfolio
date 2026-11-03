<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSection extends Model
{
    public const BUILT_IN_KEYS = ['home', 'about', 'work', 'craft', 'journey', 'contact'];

    public const CUSTOM_TYPES = ['experience', 'education', 'services', 'testimonials', 'custom'];

    protected $fillable = [
        'key', 'label', 'title', 'subtitle', 'body', 'is_visible',
        'sort_order', 'background_effect', 'spacing_top', 'spacing_bottom',
        'type', 'show_in_navigation', 'items', 'image_media_id', 'background_media_id',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'show_in_navigation' => 'boolean',
            'items' => 'array',
            'sort_order' => 'integer',
            'spacing_top' => 'integer',
            'spacing_bottom' => 'integer',
        ];
    }
}
