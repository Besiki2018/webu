<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription management - daily
Schedule::command('subscriptions:manage')->daily();

// Build credits reset - runs on the 1st of each month at midnight
Schedule::command('credits:reset')->monthlyOn(1, '00:00');

// GDPR data retention commands
Schedule::command('accounts:process-deletions')->daily();
Schedule::command('data:prune-exports')->daily();
Schedule::command('data:prune-audit-logs')->weekly();

// Project cleanup
Schedule::command('projects:purge-trash')->daily();

// Builder workspace cleanup
Schedule::command('builder:clean-workspaces')->daily();

// Stale build session checker - every 5 minutes
Schedule::command('builder:check-stale-sessions')->everyFiveMinutes();

// Booking reminders - every 10 minutes
Schedule::command('bookings:send-reminders')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Cron log cleanup
Schedule::command('cron:prune')->daily();

// Capacity guardrails cleanup (artifact/media retention + orphan cleanup + storage sync)
Schedule::command('capacity:cleanup-artifacts')
    ->dailyAt('01:15')
    ->withoutOverlapping();

// Internal AI content refresh - 3x daily at period boundaries
Schedule::command('internal-ai:refresh-content')->dailyAt('05:00');
Schedule::command('internal-ai:refresh-content')->dailyAt('12:00');
Schedule::command('internal-ai:refresh-content')->dailyAt('17:00');

// Custom domain SSL provisioning - every minute (only when custom domains are enabled)
Schedule::command('domain:provision-ssl')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => SystemSetting::get('domain_enable_custom_domains', false));

// Backup readiness verification - daily
Schedule::command('backup:create-artifact')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('backup:readiness-check')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Staging stability gate - daily (release readiness report)
Schedule::command('staging:stability-gate')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Pilot readiness gate - daily (pilot tenant quality signal)
Schedule::command('pilot:readiness-report')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Production go-live preflight - daily
Schedule::command('release:go-live-check')
    ->dailyAt('04:30')
    ->withoutOverlapping();

// Post-launch review snapshot - daily
Schedule::command('release:post-launch-review')
    ->dailyAt('05:00')
    ->withoutOverlapping();

// Demo mode cleanup - runs every 3 hours (only in demo mode)
// Note: This job is intentionally NOT in AdminCronjobController::getJobs()
// so it doesn't appear in the admin panel
// Uses ->when() instead of if() so it evaluates at runtime, not at config cache time
Schedule::command('demo:cleanup')->everyThreeHours()->when(fn () => config('app.demo'));

// AI Auto Bug Fixer - every 5 minutes: intake logs, dedup, auto-fix high/critical when enabled
Schedule::command('bugfixer:process-logs')->everyFiveMinutes()->withoutOverlapping();
