<?php

$csv = static fn (string $key, string $default): array => array_values(
    array_filter(
        array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env($key, $default))
        ),
        static fn (string $value): bool => $value !== ''
    )
);

return [
    /*
    |--------------------------------------------------------------------------
    | Operational Logging & Alerts
    |--------------------------------------------------------------------------
    |
    | Baseline ops settings used by OperationLogService + OpsAlertService.
    | Critical fail paths are emitted to structured logs and alert channels.
    |
    */
    'structured_channel' => env('OPS_STRUCTURED_LOG_CHANNEL', 'ops_structured'),
    'structured_include_stacktraces' => env('OPS_STRUCTURED_INCLUDE_STACKTRACES', false),
    'structured_formatter_max_depth' => (int) env('OPS_STRUCTURED_FORMATTER_MAX_DEPTH', 6),
    'structured_formatter_max_items' => (int) env('OPS_STRUCTURED_FORMATTER_MAX_ITEMS', 250),
    'structured_context_max_depth' => (int) env('OPS_STRUCTURED_CONTEXT_MAX_DEPTH', 5),
    'structured_context_max_items' => (int) env('OPS_STRUCTURED_CONTEXT_MAX_ITEMS', 200),
    'structured_context_max_string' => (int) env('OPS_STRUCTURED_CONTEXT_MAX_STRING', 2000),
    'alert_channel' => env('OPS_ALERT_LOG_CHANNEL', 'ops_alerts'),
    'alerts_enabled' => env('OPS_ALERTS_ENABLED', true),
    'alert_dedupe_seconds' => (int) env('OPS_ALERT_DEDUPE_SECONDS', 300),
    'alert_email_enabled' => env('OPS_ALERT_EMAIL_ENABLED', false),
    'alert_email_override' => env('OPS_ALERT_EMAIL_OVERRIDE'),
    'critical_channels' => $csv('OPS_ALERT_CRITICAL_CHANNELS', 'build,publish,payment,subscription,system'),
    'critical_events' => $csv(
        'OPS_ALERT_CRITICAL_EVENTS',
        implode(',', [
            'preview_build_failed',
            'publish_unexpected_exception',
            'webhook_exception',
            'payment_initiation_failed',
            'webhook_gateway_not_found',
            'subscription_expire_failed',
            'subscription_renewal_reminder_failed',
            'subscriptions_manage_failed',
            'stale_build_checker_failed',
            'stale_build_hard_timeout',
            'stale_build_session_missing',
        ])
    ),
];
