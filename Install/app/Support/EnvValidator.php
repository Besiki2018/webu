<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Validates critical environment variables at boot.
 * Fail fast in staging/production if required vars are missing.
 * Secrets are never logged.
 */
class EnvValidator
{
    /**
     * Required in all environments (staging + production).
     *
     * @var array<string, string>
     */
    protected static array $required = [
        'APP_KEY' => 'Application key is required. Run: php artisan key:generate',
        'APP_ENV' => 'APP_ENV must be set (e.g. local, staging, production)',
    ];

    /**
     * Required when APP_ENV is staging or production.
     *
     * @var array<string, string>
     */
    protected static array $requiredProduction = [
        'APP_KEY' => 'Application key is required in non-local environments',
        'DB_CONNECTION' => 'Database connection must be configured',
        'DB_DATABASE' => 'DB_DATABASE must be set',
        'DB_USERNAME' => 'DB_USERNAME must be set',
        'CACHE_STORE' => 'CACHE_STORE must be set',
    ];

    /**
     * Keys that must never appear in logs or error messages (only "missing" hint).
     *
     * @var list<string>
     */
    protected static array $secretKeys = [
        'APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD',
        'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'REVERB_APP_KEY', 'REVERB_APP_SECRET',
        'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY',
    ];

    public static function validate(): void
    {
        $env = config('app.env', 'production');

        // Local and testing: optional validation (e.g. phpunit may not set DB_*)
        if (in_array($env, ['local', 'testing'], true)) {
            return;
        }

        $missing = [];

        foreach (array_keys(self::$required) as $key) {
            if (! self::hasNonEmpty($key)) {
                $missing[$key] = self::$required[$key];
            }
        }

        if (in_array($env, ['staging', 'production'], true)) {
            foreach (self::$requiredProduction as $key => $message) {
                if (! self::hasNonEmpty($key)) {
                    $missing[$key] = $message;
                }
            }
        }

        if ($missing !== []) {
            $hint = collect($missing)->map(function (string $msg, string $key) {
                $name = self::isSecretKey($key) ? $key.' (secret)' : $key;
                return "{$name}: {$msg}";
            })->values()->implode('; ');
            throw new \RuntimeException('Environment validation failed. '.$hint);
        }
    }

    protected static function hasNonEmpty(string $key): bool
    {
        $value = env($key);
        if ($value === null || $value === '') {
            return false;
        }
        return trim((string) $value) !== '';
    }

    protected static function isSecretKey(string $key): bool
    {
        foreach (self::$secretKeys as $secret) {
            if (Str::startsWith($key, $secret) || $key === $secret) {
                return true;
            }
        }
        return false;
    }
}
