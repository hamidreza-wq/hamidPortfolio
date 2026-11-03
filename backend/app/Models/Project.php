<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'category_label', 'repository_url',
        'live_url', 'description', 'details', 'technologies', 'features',
        'visual', 'visual_label', 'status', 'is_featured', 'is_published',
        'sort_order', 'seo_title', 'seo_description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'technologies' => 'array',
            'features' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
