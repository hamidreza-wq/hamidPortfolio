<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'categories', 'digest_frequency', 'last_sent_at'];

    protected $attributes = [
        'categories' => '["updates","github","seo"]',
        'digest_frequency' => 'never',
    ];

    protected function casts(): array
    {
        return ['categories' => 'array', 'last_sent_at' => 'datetime'];
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrNew(['user_id' => $userId]);
    }
}
