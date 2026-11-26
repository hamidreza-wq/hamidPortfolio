<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    /** Notice categories, in the order they are shown. */
    public const CATEGORIES = [
        'security' => 'Security: failed sign-ins, two-factor changes',
        'health' => 'Site health: errors, storage, certificate, scheduler',
        'backups' => 'Backups that fail',
        'updates' => 'Software, dependency and deployment updates',
        'messages' => 'New contact messages and testimonials',
        'seo' => 'SEO problems and broken pages',
        'github' => 'GitHub repositories to review',
    ];

    protected $fillable = ['user_id', 'categories', 'digest_frequency', 'last_sent_at', 'instant_alerts'];

    protected $attributes = [
        'categories' => '["security","health","backups","updates","messages","seo","github"]',
        'digest_frequency' => 'never',
        'instant_alerts' => false,
    ];

    protected function casts(): array
    {
        return ['categories' => 'array', 'last_sent_at' => 'datetime', 'instant_alerts' => 'boolean'];
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrNew(['user_id' => $userId]);
    }
}
