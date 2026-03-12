<?php

return [
    'ssl' => [
        // Max SSL provisioning attempts per custom domain before hard failure.
        'max_attempts' => (int) env('DOMAIN_SSL_MAX_ATTEMPTS', 5),

        // Base retry delay in minutes. Backoff is exponential per failed attempt.
        'retry_backoff_minutes' => (int) env('DOMAIN_SSL_RETRY_BACKOFF_MINUTES', 15),
    ],
];
