<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformNotice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime', 'dismissed_at' => 'datetime',
            'snoozed_until' => 'datetime', 'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('resolved_at')->whereNull('dismissed_at')
            ->where(fn ($query) => $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()));
    }
}
