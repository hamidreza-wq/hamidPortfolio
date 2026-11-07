<?php

namespace App\Services\Platform;

use App\Models\PlatformNotice;

class Notices
{
    public function put(string $key, string $category, string $priority, string $title, string $message, string $route): void
    {
        $notice = PlatformNotice::firstOrNew(['fingerprint' => hash('sha256', $key)]);
        $changed = $notice->exists && ($notice->message !== $message || $notice->resolved_at !== null);
        $notice->fill([
            'category' => $category, 'priority' => $priority, 'title' => $title,
            'message' => $message, 'route_name' => $route,
            'last_seen_at' => now(), 'resolved_at' => null,
        ]);
        if ($changed) {
            $notice->fill(['read_at' => null, 'dismissed_at' => null, 'snoozed_until' => null]);
        }
        $notice->save();
    }

    public function resolve(string $key): void
    {
        PlatformNotice::where('fingerprint', hash('sha256', $key))
            ->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
