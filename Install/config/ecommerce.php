<?php

return [
    'rs_sync' => [
        'default_connector' => env('ECOMMERCE_RS_SYNC_CONNECTOR', 'rs-v2-skeleton'),
        'queue' => env('ECOMMERCE_RS_SYNC_QUEUE', 'default'),
        'max_attempts' => (int) env('ECOMMERCE_RS_SYNC_MAX_ATTEMPTS', 5),
        'backoff_seconds' => [60, 180, 600, 1800, 3600],
    ],
];
