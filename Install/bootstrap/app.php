<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        // health: '/up' omitted — Laravel registers it as a closure, which breaks Ziggy's route reflection.
        // We serve GET /up via HealthController::up() in web.php instead.
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\ResolveTenantContext::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\IdentifyProjectBySubdomain::class,
            \App\Http\Middleware\IdentifyProjectByCustomDomain::class,
            \App\Http\Middleware\EnsureTenantContext::class,
            \App\Http\Middleware\AuditAdminProjectOverride::class,
            \App\Http\Middleware\SecureHeaders::class,
            \App\Http\Middleware\SetLocale::class, // Must run before HandleInertiaRequests to set locale for translations
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveTenantContext::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\EnsureTenantContext::class,
            \App\Http\Middleware\SecureHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'public/sites/*/customers/register',
            'public/sites/*/customers/login',
            'public/sites/*/customers/logout',
            'public/sites/*/customers/me',
            'public/sites/*/ecommerce/carts',
            'public/sites/*/ecommerce/carts/*/items',
            'public/sites/*/ecommerce/carts/*/items/*',
            'public/sites/*/ecommerce/carts/*/checkout',
            'public/sites/*/ecommerce/orders/*/payments/start',
            'public/sites/*/booking/bookings',
            'public/sites/*/forms/*/submit',
            'public/sites/*/cms/telemetry',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'registration.enabled' => \App\Http\Middleware\CheckRegistrationEnabled::class,
            'verify.server.key' => \App\Http\Middleware\VerifyServerKey::class,
            'verify.project.token' => \App\Http\Middleware\VerifyProjectToken::class,
            'entitlement' => \App\Http\Middleware\RequireEntitlement::class,
            'site.entitlement' => \App\Http\Middleware\RequireSiteEntitlement::class,
            'subscription.enforced' => \App\Http\Middleware\RequireBillableSubscription::class,
            'subdomain.project' => \App\Http\Middleware\IdentifyProjectBySubdomain::class,
            'custom.domain' => \App\Http\Middleware\IdentifyProjectByCustomDomain::class,
            'tenant.resolve' => \App\Http\Middleware\ResolveTenantContext::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
            'tenant.route.scope' => \App\Http\Middleware\EnforceTenantProjectRouteScope::class,
            'tenant.from.website' => \App\Http\Middleware\ResolveTenantFromWebsite::class,
            'public.api.observability' => \App\Http\Middleware\CapturePublicApiObservabilityTelemetry::class,
            'admin.override.audit' => \App\Http\Middleware\AuditAdminProjectOverride::class,
            'set.locale' => \App\Http\Middleware\SetLocale::class,
            'not-installed' => \App\Http\Middleware\NotInstalled::class,
            'installed' => \App\Http\Middleware\Installed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
