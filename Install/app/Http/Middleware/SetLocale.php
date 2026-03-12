<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Installer runs before DB is configured; use config default only.
        if ($request->routeIs('install*')) {
            app()->setLocale(config('app.locale', 'en'));

            return $next($request);
        }

        try {
            $locale = $this->resolveLocale($request);

            // Validate locale exists and is active
            if (! Language::isValidCode($locale)) {
                $locale = $this->resolveDefaultLocale();
            }

            app()->setLocale($locale);
        } catch (\Exception $e) {
            // Database not available (fresh install), use default
            app()->setLocale(config('app.locale', 'ka'));
        }

        return $next($request);
    }

    /**
     * Resolve the locale from user, session, or system default.
     */
    protected function resolveLocale(Request $request): string
    {
        // Priority 1: Authenticated user's preference
        if ($request->user() && $request->user()->locale) {
            return $request->user()->locale;
        }

        // Priority 2: Session (for guests)
        if ($request->session()->has('locale')) {
            return $request->session()->get('locale');
        }

        // Priority 3: System default
        return $this->resolveDefaultLocale();
    }

    protected function resolveDefaultLocale(): string
    {
        $default = SystemSetting::get('default_locale', config('app.locale', 'ka'));

        if (is_string($default) && Language::isValidCode($default)) {
            return $default;
        }

        $languageDefault = Language::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('code');

        if (is_string($languageDefault) && $languageDefault !== '') {
            return $languageDefault;
        }

        return config('app.locale', 'ka');
    }
}
