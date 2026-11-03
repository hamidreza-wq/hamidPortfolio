<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FontAsset extends Model
{
    protected $fillable = [
        'family', 'weight', 'style', 'format', 'disk', 'path',
        'license_note', 'license_confirmed',
        'source_url', 'is_active', 'original_name', 'size_bytes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['license_confirmed' => 'boolean', 'is_active' => 'boolean', 'size_bytes' => 'integer'];
    }

    public function cssFamily(): string
    {
        return 'PortfolioFont'.$this->id;
    }
}
