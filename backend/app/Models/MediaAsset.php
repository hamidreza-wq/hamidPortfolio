<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = [
        'label', 'original_name', 'disk', 'path', 'mime_type', 'size_bytes',
        'width', 'height', 'alt_text', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
