<?php

return [
    'release_public_key' => env('RELEASE_PUBLIC_KEY'),
    'schema_version' => 3,
    'seo_origin' => env('SEO_MONITOR_ORIGIN'),
    'schedule' => [
        'releases' => env('RELEASE_CHECK_CRON', '0 */6 * * *'),
        'github' => env('GITHUB_SCAN_CRON', '15 */6 * * *'),
        'seo' => env('SEO_SCAN_CRON', '30 3 * * *'),
    ],
];
