<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
