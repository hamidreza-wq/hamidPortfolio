<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSetting extends Model
{
    protected $fillable = [
        'name', 'professional_title', 'github_url', 'email', 'linkedin_url',
        'site_title', 'site_description', 'canonical_url', 'og_image',
        'color_background', 'color_foreground', 'color_primary', 'color_accent',
        'color_cyan', 'font_body', 'font_heading', 'max_width',
        'section_spacing', 'border_radius', 'animations_enabled',
        'particles_enabled', 'maintenance_mode', 'footer_text',
    ];

    protected function casts(): array
    {
        return [
            'animations_enabled' => 'boolean',
            'particles_enabled' => 'boolean',
            'maintenance_mode' => 'boolean',
            'max_width' => 'integer',
            'section_spacing' => 'integer',
            'border_radius' => 'integer',
        ];
    }
}
