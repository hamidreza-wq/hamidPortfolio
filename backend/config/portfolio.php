<?php

return [
    'contact_min_seconds' => (int) env('CONTACT_MIN_SECONDS', 2),
    'contact_max_seconds' => (int) env('CONTACT_MAX_SECONDS', 7200),
    'analytics_retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
];
