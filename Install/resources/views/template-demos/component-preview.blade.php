<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Component: {{ $section['label'] ?? $section['key'] ?? 'Component' }} — Design Preview</title>
    <link rel="stylesheet" href="{{ asset('css/template-demos.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.38.0/dist/tabler-icons.min.css" crossorigin="anonymous">
    @if(!empty($siteDesignCssUrl))
        <link rel="stylesheet" href="{{ $siteDesignCssUrl }}">
    @else
        @vite(['resources/css/app.css'])
    @endif
    @if(!empty($themeTokenLayers['effective']['theme_tokens']))
        @php
            $tokens = $themeTokenLayers['effective']['theme_tokens'];
            $colorSource = $tokens['colors'] ?? [];
            $flatColors = [];
            if (isset($colorSource['modes']['light']) && is_array($colorSource['modes']['light'])) {
                $flatColors = $colorSource['modes']['light'];
            } elseif (is_array($colorSource)) {
                foreach ($colorSource as $k => $v) {
                    if ($k !== 'modes' && is_string($v)) {
                        $flatColors[$k] = $v;
                    }
                }
            }
            $radii = is_array($tokens['radii'] ?? null) ? $tokens['radii'] : [];
            $spacing = is_array($tokens['spacing'] ?? null) ? $tokens['spacing'] : [];
        @endphp
        <style>
            :root {
                @foreach($flatColors as $name => $value)
                    @if(is_string($value) && $value !== '')
                --{{ str_replace('_', '-', $name) }}: {{ $value }};
                    @endif
                @endforeach
                @if(!empty($radii['base']))
                --radius: {{ is_string($radii['base']) ? $radii['base'] : '0.375rem' }};
                @endif
                @foreach($spacing as $name => $value)
                    @if(is_string($value) && $value !== '')
                --spacing-{{ str_replace('_', '-', $name) }}: {{ $value }};
                    @endif
                @endforeach
            }
        </style>
    @endif
</head>
<body class="webu-component-preview">
    <div class="component-preview-header" style="padding:12px 20px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;font-size:14px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        @php
            $backUrl = $back_url ?? route('admin.component-library.index');
            $backLabel = $back_label ?? __('Back to Components');
        @endphp
        <a href="{{ $backUrl }}" style="color:#475569;text-decoration:none;font-weight:500;">&larr; {{ $backLabel }}</a>
        <span style="color:#94a3b8;">|</span>
        <strong>Component:</strong> {{ $section['label'] ?? $section['key'] }}
        <span style="color:#64748b;">(key: {{ $section['key'] ?? '' }})</span>
        @if(!empty($componentFolderPath))
            — რედაქტირე <code>{{ $componentFolderPath }}/</code> და განაახლე გვერდი.
        @else
            — რედაქტირე <code>resources/css/webu/components/</code> ან layout პრიმიტივი; განაახლე გვერდი.
        @endif
    </div>
    <main class="component-preview-main" style="padding:24px;">
        @php
            $templateSlug = $template['slug'] ?? 'ecommerce';
            $thumb = $template['thumbnail_url'] ?? null;
            $siteId = '';
            $draft = '';
            $locale = '';
            $selectedProductSlug = '';
            $selectedOrderId = '';
            $accountPreviewUrl = '#';
            $isEcommerceTemplate = true;
            $variantSections = $variantSections ?? [['variant_label' => null, 'section' => $section]];
        @endphp
        @foreach($variantSections as $idx => $variantEntry)
            @php
                $variantLabel = $variantEntry['variant_label'] ?? null;
                $sectionForBlock = $variantEntry['section'] ?? $section;
            @endphp
            <div class="component-preview-variant-block" style="margin-bottom:{{ $idx > 0 ? '48px' : '0' }};">
                @if($variantLabel !== null)
                    <div class="component-preview-variant-label" style="margin-bottom:12px;padding:10px 14px;background:#e0f2fe;border:1px solid #7dd3fc;border-radius:8px;font-size:14px;font-weight:600;color:#0369a1;">
                        {{ __('Variant') }}: {{ $variantLabel }}
                    </div>
                @endif
                @include('template-demos.partials.section-content', [
                    'section' => $sectionForBlock,
                    'isEcommerceTemplate' => $isEcommerceTemplate,
                    'templateSlug' => $templateSlug,
                    'siteId' => $siteId,
                    'draft' => $draft,
                    'locale' => $locale,
                    'thumb' => $thumb,
                    'accountPreviewUrl' => $accountPreviewUrl,
                    'selectedProductSlug' => $selectedProductSlug,
                    'selectedOrderId' => $selectedOrderId,
                ])
            </div>
        @endforeach
    </main>
    @include('template-demos.partials.offcanvas-menu-script')
</body>
</html>
