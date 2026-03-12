<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dedicated Database Feature Flag
    |--------------------------------------------------------------------------
    |
    | Enterprise-only mode. When disabled, tenant DB bindings are ignored and
    | the shared multi-tenant database remains the single source of truth.
    |
    */
    'dedicated_db_enabled' => env('TENANCY_DEDICATED_DB_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed plans for dedicated DB provisioning
    |--------------------------------------------------------------------------
    */
    'dedicated_db_allowed_plan_slugs' => [
        'enterprise',
    ],
];

