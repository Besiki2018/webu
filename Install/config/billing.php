<?php

return [
    'credit_packs' => [
        [
            'key' => 'starter_25k',
            'name' => 'Starter 25K',
            'credits' => 25000,
            'price' => 5.00,
            'currency' => 'USD',
            'enabled' => true,
        ],
        [
            'key' => 'growth_75k',
            'name' => 'Growth 75K',
            'credits' => 75000,
            'price' => 12.00,
            'currency' => 'USD',
            'enabled' => true,
        ],
        [
            'key' => 'scale_150k',
            'name' => 'Scale 150K',
            'credits' => 150000,
            'price' => 22.00,
            'currency' => 'USD',
            'enabled' => true,
        ],
    ],

    'subscriptions' => [
        // Number of automated charge retries before moving to grace state.
        'max_retries' => max(0, (int) env('SUBSCRIPTION_MAX_RETRIES', 3)),

        // Delay between retry attempts (hours).
        'retry_interval_hours' => max(1, (int) env('SUBSCRIPTION_RETRY_INTERVAL_HOURS', 24)),

        // Grace period duration (days) after retries are exhausted.
        'grace_days' => max(0, (int) env('SUBSCRIPTION_GRACE_DAYS', 5)),

        // Reset tenant to default plan when subscription is suspended.
        'fallback_to_default_plan_on_suspend' => filter_var(
            env('SUBSCRIPTION_FALLBACK_TO_DEFAULT_PLAN_ON_SUSPEND', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
