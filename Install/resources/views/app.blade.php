<!DOCTYPE html>
@php
    $siteName = \App\Models\SystemSetting::get('site_name', config('app.name'));
    $siteDescription = \App\Models\SystemSetting::get('site_description', '');
    $favicon = \App\Models\SystemSetting::get('site_favicon');
    $defaultTheme = \App\Models\SystemSetting::get('default_theme', 'system');
    $colorTheme = \App\Models\SystemSetting::get('color_theme', 'neutral');
    $locale = app()->getLocale();
    try {
        $isRtl = \App\Models\Language::where('code', $locale)->value('is_rtl') ?? false;
    } catch (\Exception $e) {
        $isRtl = false;
    }
    $htmlLang = str_replace('_', '-', $locale);
    $htmlDir = $isRtl ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $htmlLang }}" dir="{{ $htmlDir }}" data-theme="{{ $colorTheme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if($siteDescription)
        <meta name="description" content="{{ $siteDescription }}">
        @endif

        @if($favicon)
        <link rel="icon" href="{{ Storage::url($favicon) }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ Storage::url($favicon) }}">
        @else
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="/favicon.ico">
        @endif

        <!-- Theme and locale initialization (prevents FOUC) -->
        <script>
            (function() {
                // Theme
                const defaultTheme = '{{ $defaultTheme }}';
                const theme = localStorage.getItem('app-theme') || defaultTheme;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (theme === 'dark' || (theme === 'system' && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
                // RTL - check stored RTL preference
                const isRtl = localStorage.getItem('app-locale-rtl') === 'true';
                if (isRtl) {
                    document.documentElement.dir = 'rtl';
                }
            })();
        </script>

        <!-- Pass site name to JavaScript for dynamic title -->
        <script>
            window.__APP_NAME__ = @json($siteName);
        </script>

        <title inertia>{{ $siteName }}</title>

        <!-- TBC Contractica: add fonts to public/fonts/ and uncomment preload links to enable -->
        <!--
        <link rel="preload" href="{{ asset('fonts/TBCContractica-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ asset('fonts/TBCContractica-Medium.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ asset('fonts/TBCContractica-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
        -->

        <!-- Tabler Icons (https://tabler.io/icons) - used in template-demos, CMS preview, and icon_class fields -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.38.0/dist/tabler-icons.min.css" crossorigin="anonymous">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
