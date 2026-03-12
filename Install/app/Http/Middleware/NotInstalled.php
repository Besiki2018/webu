<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Installed;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotInstalled
{
    /**
     * Handle an incoming request.
     *
     * Only allow access to installer routes when the application is NOT installed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always allow access to the completed page (to show success message)
        if ($request->routeIs('install.completed')) {
            return $next($request);
        }

        if ($this->isInstalled()) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * Check if the application is installed (same logic as Installed middleware).
     */
    private function isInstalled(): bool
    {
        if (file_exists(Installed::installedMarkerPath())) {
            return true;
        }

        try {
            return SystemSetting::get('installation_completed', false) === true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
