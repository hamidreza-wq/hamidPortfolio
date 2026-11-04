<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSearchConsoleConnection extends Model
{
    protected $fillable = ['site_url', 'refresh_token', 'connected_at'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }
}
