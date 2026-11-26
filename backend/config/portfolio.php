<?php

return [
    'contact_recipient' => env('CONTACT_RECIPIENT'),
    'contact_min_seconds' => (int) env('CONTACT_MIN_SECONDS', 2),
    'contact_max_seconds' => (int) env('CONTACT_MAX_SECONDS', 7200),
    'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
    'analytics_retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
    'backups' => [
        // Newest backups kept; older ones are removed after a successful backup.
        'keep' => max(2, (int) env('BACKUP_KEEP', 14)),
        'schedule' => env('BACKUP_SCHEDULE', '15 3 * * *'),
        'min_free_bytes' => 200 * 1024 * 1024,
    ],
];
