<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template['name'] ?? 'Template Demo' }} - Demo</title>
    <link rel="stylesheet" href="{{ asset('css/template-demos.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.38.0/dist/tabler-icons.min.css" crossorigin="anonymous">
    @if(!empty($siteDesignCssUrl))
        {{-- Project has its own baked design (detached from default webu); use it so edits in builder don't depend on shared components --}}
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
@php
    $templateSlug = $template['slug'] ?? '';
    $activeSlug = $activePage['slug'] ?? '';
    $thumb = $template['thumbnail_url'] ?? null;
    $siteId = trim((string) request()->query('site', ''));
    $selectedProductSlug = trim((string) request()->query('product_slug', request()->query('product', '')));
    $selectedOrderId = trim((string) request()->query('order_id', request()->query('id', '')));
    $draft = trim((string) request()->query('draft', ''));
    $locale = trim((string) request()->query('locale', ''));
    $templateCategory = strtolower((string) ($template['category'] ?? 'default'));
    $isEcommerceTemplate = $templateSlug === 'ecommerce';
    $runtimeAppConfigPayload = is_array($runtimeAppConfig ?? null) ? $runtimeAppConfig : null;
    $runtimeEcommerceEnabled = (bool) ($runtimeAppConfigPayload['ecommerce']['enabled'] ?? false);
    $runtimeEcommerceScriptPayload = is_string($runtimeEcommerceScript ?? null) ? $runtimeEcommerceScript : null;
    $activeSlugClass = trim((string) preg_replace('/[^a-z0-9\\-]+/', '-', strtolower((string) $activeSlug)), '-');
    $activeSlugClass = $activeSlugClass !== '' ? $activeSlugClass : 'home';
    $sharedDemoQuery = array_filter([
        'site' => $siteId !== '' ? $siteId : null,
        'draft' => $draft !== '' ? $draft : null,
        'locale' => $locale !== '' ? $locale : null,
    ], static fn ($value) => $value !== null);
    $cartPreviewUrl = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'cart'] + $sharedDemoQuery + ['slug' => 'cart']);
    $shopPreviewUrl = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'shop'] + $sharedDemoQuery + ['slug' => 'shop']);
    $checkoutPreviewUrl = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'checkout'] + $sharedDemoQuery + ['slug' => 'checkout']);
    $paymentsPreviewUrl = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'payments'] + $sharedDemoQuery + ['slug' => 'payments']);
    $accountPreviewUrl = route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => 'account'] + $sharedDemoQuery + ['slug' => 'account']);
    $headerSource = [];
    $homePagePayload = collect($pages)->firstWhere('slug', 'home');
    $homeSections = is_array($homePagePayload['sections'] ?? null) ? $homePagePayload['sections'] : [];
    foreach ($homeSections as $homeSectionPayload) {
        if ((string) ($homeSectionPayload['key'] ?? '') !== 'webu_general_heading_01') {
            continue;
        }
        $headerSource = is_array($homeSectionPayload['data'] ?? null) ? $homeSectionPayload['data'] : [];
        break;
    }
    if ($headerSource === []) {
        $activeSections = is_array($activePage['sections'] ?? null) ? $activePage['sections'] : [];
        foreach ($activeSections as $activeSectionPayload) {
            if ((string) ($activeSectionPayload['key'] ?? '') !== 'webu_general_heading_01') {
                continue;
            }
            $headerSource = is_array($activeSectionPayload['data'] ?? null) ? $activeSectionPayload['data'] : [];
            break;
        }
    }
    $headerTopStripTextRaw = trim((string) ($headerSource['top_strip_text'] ?? 'Autumn Collection. A New Season. A New Perspective. Buy Now!'));
    $headerPhone = trim((string) ($headerSource['contact_phone'] ?? '+1800 354 4321'));
    $headerEmail = trim((string) ($headerSource['contact_email'] ?? 'info@fashionshop.com'));
    $headerBrand = trim((string) ($headerSource['brand_text'] ?? 'ORIMA.'));
    $headerTopStripCtaLabel = trim((string) ($headerSource['top_strip_cta_label'] ?? 'Buy Now!'));
    if (preg_match('/^(.*?)(buy\\s+now!?)\\s*$/i', $headerTopStripTextRaw, $topStripMatches) === 1) {
        $headerTopStripText = trim((string) ($topStripMatches[1] ?? ''));
        $matchedLabel = trim((string) ($topStripMatches[2] ?? ''));
        if ($matchedLabel !== '') {
            $headerTopStripCtaLabel = ucwords(strtolower($matchedLabel));
        }
    } else {
        $headerTopStripText = $headerTopStripTextRaw;
    }
    $headerTopStripText = $headerTopStripText !== '' ? rtrim($headerTopStripText) : 'Autumn Collection. A New Season. A New Perspective.';
    if (! preg_match('/[.!?]$/', $headerTopStripText)) {
        $headerTopStripText .= '.';
    }
    $headerTopStripCtaTarget = trim((string) ($headerSource['top_strip_cta_url'] ?? '/shop'));
    $headerTopStripCtaPath = trim($headerTopStripCtaTarget, '/');
    $headerTopStripCtaQuery = array_filter([
        'site' => $siteId !== '' ? $siteId : null,
        'draft' => $draft !== '' ? $draft : null,
        'locale' => $locale !== '' ? $locale : null,
        'slug' => $headerTopStripCtaPath !== '' ? $headerTopStripCtaPath : 'shop',
    ], static fn ($value) => $value !== null);
    $headerTopStripCtaHref = (str_starts_with($headerTopStripCtaTarget, 'http://') || str_starts_with($headerTopStripCtaTarget, 'https://'))
        ? $headerTopStripCtaTarget
        : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $headerTopStripCtaPath !== '' ? $headerTopStripCtaPath : 'shop'] + $headerTopStripCtaQuery);

    $resolvedHeaderSection = isset($layoutHeader) && is_array($layoutHeader) ? $layoutHeader : null;
    $resolvedFooterSection = isset($layoutFooter) && is_array($layoutFooter) ? $layoutFooter : null;
    if ($resolvedHeaderSection === null || $resolvedFooterSection === null) {
        foreach ($pages as $p) {
            foreach (is_array($p['sections'] ?? null) ? $p['sections'] : [] as $sec) {
                $k = strtolower(trim((string) ($sec['key'] ?? '')));
                if ($k === 'webu_header_01' && $resolvedHeaderSection === null) {
                    $resolvedHeaderSection = $sec;
                }
                if ($k === 'webu_footer_01' && $resolvedFooterSection === null) {
                    $resolvedFooterSection = $sec;
                }
            }
        }
    }
    if ($resolvedHeaderSection === null) {
        $resolvedHeaderSection = [
            'key' => 'webu_header_01',
            'label' => 'Header',
            'component' => 'header',
            'data' => [
                'logo_text' => $headerBrand !== '' ? $headerBrand : ($template['name'] ?? 'Store'),
                'logo_url' => '/',
                'menu_items' => is_array($headerMenuItems ?? null) && count($headerMenuItems ?? []) > 0 ? $headerMenuItems : [['label' => 'მთავარი', 'url' => '/'], ['label' => 'მაღაზია', 'url' => '/shop'], ['label' => 'კონტაქტი', 'url' => '/contact']],
                'cta_label' => __('დაწყება'),
                'cta_url' => '/contact',
                'layout_variant' => 'header-1',
            ],
        ];
    }
    if ($resolvedFooterSection === null) {
        $resolvedFooterSection = [
            'key' => 'webu_footer_01',
            'label' => 'Footer',
            'component' => 'footer',
            'data' => [
                'logo_text' => $headerBrand !== '' ? $headerBrand : ($template['name'] ?? 'Store'),
                'logo_url' => '/',
                'menus' => ['ლინკები' => [['label' => 'კონფიდენციალობა', 'url' => '/privacy'], ['label' => 'კონტაქტი', 'url' => '/contact']], 'მაღაზია' => [['label' => 'კატალოგი', 'url' => '/shop'], ['label' => 'კალათა', 'url' => '/cart']]],
                'contact_address' => 'თბილისი, საქართველო',
                'copyright' => '© ' . date('Y') . ' ' . ($template['name'] ?? 'Store'),
                'layout_variant' => 'footer-1',
            ],
        ];
    }
    $activePageSummary = match ($activeSlug) {
        'home' => 'გამორჩეული შეთავაზებები და ბრენდის მთავრი showcase.',
        'shop' => 'კატალოგი ძიებით, კატეგორიებით და ფილტრირებადი პროდუქტებით.',
        'product' => 'პროდუქტის დეტალი, გალერეა, ვარიაციები და კალათაში დამატება.',
        'cart' => 'კალათა, კუპონი და შეკვეთის შუალედური შეჯამება.',
        'checkout' => 'მისამართი, მიწოდება, გადახდის მეთოდი და შეკვეთის დასრულება.',
        'payments' => 'ხელმისაწვდომი გადახდის პროვაიდერები და გადახდის რეჟიმები.',
        'login' => 'რეგისტრაცია/ავტორიზაცია და მომხმარებლის სესიის მართვა.',
        'account' => 'მომხმარებლის პროფილი, უსაფრთხოება და პირადი მონაცემები.',
        'orders' => 'ყველა შეკვეთების სია და სტატუსების მონიტორინგი.',
        'order' => 'კონკრეტული შეკვეთის სრული დეტალები.',
        'delivery-returns' => 'მიწოდებისა და დაბრუნების წესები.',
        'contact' => 'საკონტაქტო ფორმა, მისამართი და რუკა.',
        default => 'Builder კომპონენტებით აგებული გვერდის live დემო ხედვა.',
    };

    $palette = match ($templateCategory) {
        'ecommerce' => ['bg' => '#ffffff', 'panel' => '#ffffff', 'primary' => '#111111', 'primary_soft' => '#f5f5f5'],
        'business' => ['bg' => '#f5f7ff', 'panel' => '#ffffff', 'primary' => '#1e40af', 'primary_soft' => '#e0e7ff'],
        'booking' => ['bg' => '#f0fdfa', 'panel' => '#ffffff', 'primary' => '#0f766e', 'primary_soft' => '#ccfbf1'],
        default => ['bg' => '#faf9f7', 'panel' => '#ffffff', 'primary' => '#1a1a1a', 'primary_soft' => '#f5f4f2'],
    };

    $isBuilderPreview = request()->query('builder') === '1';
@endphp
<body class="{{ $isEcommerceTemplate ? 'storefront' : '' }} page-{{ $activeSlugClass }}{{ $isBuilderPreview ? ' builder-preview' : '' }}" style="--bg: {{ $palette['bg'] }}; --panel: {{ $palette['panel'] }}; --primary: {{ $palette['primary'] }}; --primary-soft: {{ $palette['primary_soft'] }};">
<div class="container-fluid">
    {{-- Header: same component as in Component Library (webu_header_01 / header variants); wrapper gets advanced spacing from section data --}}
    @php
        $headerData = is_array($resolvedHeaderSection['data'] ?? null) ? $resolvedHeaderSection['data'] : [];
        $headerResponsive = is_array($headerData['responsive'] ?? null) ? $headerData['responsive'] : [];
        $headerDesktop = is_array($headerResponsive['desktop'] ?? null) ? $headerResponsive['desktop'] : [];
        $headerAdvanced = is_array($headerData['advanced'] ?? null) ? $headerData['advanced'] : [];
        $hPadT = trim((string) ($headerDesktop['padding_top'] ?? $headerAdvanced['padding_top'] ?? ''));
        $hPadR = trim((string) ($headerDesktop['padding_right'] ?? $headerAdvanced['padding_right'] ?? ''));
        $hPadB = trim((string) ($headerDesktop['padding_bottom'] ?? $headerAdvanced['padding_bottom'] ?? ''));
        $hPadL = trim((string) ($headerDesktop['padding_left'] ?? $headerAdvanced['padding_left'] ?? ''));
        $hMarT = trim((string) ($headerDesktop['margin_top'] ?? $headerAdvanced['margin_top'] ?? ''));
        $hMarR = trim((string) ($headerDesktop['margin_right'] ?? $headerAdvanced['margin_right'] ?? ''));
        $hMarB = trim((string) ($headerDesktop['margin_bottom'] ?? $headerAdvanced['margin_bottom'] ?? ''));
        $hMarL = trim((string) ($headerDesktop['margin_left'] ?? $headerAdvanced['margin_left'] ?? ''));
        $hZIdx = isset($headerAdvanced['z_index']) && $headerAdvanced['z_index'] !== null && $headerAdvanced['z_index'] !== '' ? (int) $headerAdvanced['z_index'] : null;
        $hCustomClass = trim((string) ($headerAdvanced['custom_class'] ?? ''));
        $headerWrapperParts = array_filter([
            $hPadT !== '' ? 'padding-top:' . e($hPadT) : null,
            $hPadR !== '' ? 'padding-right:' . e($hPadR) : null,
            $hPadB !== '' ? 'padding-bottom:' . e($hPadB) : null,
            $hPadL !== '' ? 'padding-left:' . e($hPadL) : null,
            $hMarT !== '' ? 'margin-top:' . e($hMarT) : null,
            $hMarR !== '' ? 'margin-right:' . e($hMarR) : null,
            $hMarB !== '' ? 'margin-bottom:' . e($hMarB) : null,
            $hMarL !== '' ? 'margin-left:' . e($hMarL) : null,
            $hZIdx !== null ? 'z-index:' . (int) $hZIdx : null,
        ]);
        $headerWrapperStyle = count($headerWrapperParts) > 0 ? implode('; ', $headerWrapperParts) : '';
    @endphp
    <div @if($headerWrapperStyle !== '') style="{{ $headerWrapperStyle }}" @endif @if($hCustomClass !== '') class="{{ $hCustomClass }}" @endif>
        @include('template-demos.partials.section-content', [
            'section' => $resolvedHeaderSection,
            'isEcommerceTemplate' => true,
            'templateSlug' => $templateSlug,
            'siteId' => $siteId,
            'draft' => $draft,
            'locale' => $locale,
            'thumb' => $thumb,
            'accountPreviewUrl' => $accountPreviewUrl,
            'selectedProductSlug' => $selectedProductSlug,
            'selectedOrderId' => $selectedOrderId,
            'sharedDemoQuery' => $sharedDemoQuery,
        ])
    </div>

    <main class="page">
        @if($isEcommerceTemplate)
            <div class="storefront-banner">
                <h3>Canonical Ecommerce Demo</h3>
                <p>Each block below is rendered from your builder section keys (webu_ecom_*) and stays editable from CMS.</p>
            </div>
            <div class="page-intro">
                <h2>{{ $activePage['title'] ?? ucfirst($activeSlug ?: 'home') }}</h2>
                <p>{{ $activePageSummary }}</p>
            </div>
        @endif

        @php
            $sectionsList = $activePage['sections'] ?? [];
            $sectionsCount = count($sectionsList);
            $isProductPage = $isEcommerceTemplate && $activeSlug === 'product';
            $productSingleSectionKeys = ['webu_ecom_product_gallery_01', 'webu_ecom_product_detail_01', 'webu_ecom_add_to_cart_button_01'];
            $productSingleHideHeadKeys = ['webu_ecom_product_gallery_01', 'webu_ecom_product_detail_01', 'webu_ecom_add_to_cart_button_01', 'webu_ecom_product_tabs_01'];
        @endphp
        @foreach($sectionsList as $section)
            @php
                $component = $section['component'] ?? 'generic';
                $data = is_array($section['data'] ?? null) ? $section['data'] : [];
                $label = $section['label'] ?? ucfirst($section['key'] ?? 'Section');
                $normalizedKey = strtolower(trim((string) ($section['key'] ?? '')));
                $renderGeneric = ! $isEcommerceTemplate;
                $products = is_array($data['products'] ?? null) ? $data['products'] : [];
                $categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
                $faqItems = is_array($data['items'] ?? null) ? $data['items'] : [];
                $prevKey = $loop->index > 0 ? strtolower(trim((string) ($sectionsList[$loop->index - 1]['key'] ?? ''))) : '';
                $nextKey = $loop->index < $sectionsCount - 1 ? strtolower(trim((string) ($sectionsList[$loop->index + 1]['key'] ?? ''))) : '';
                $isCardSection = $normalizedKey === 'webu_general_card_01';
                $openCardsRow = $isCardSection && ($loop->index === 0 || $prevKey !== 'webu_general_card_01');
                $closeCardsRow = $isCardSection && ($loop->index === $sectionsCount - 1 || $nextKey !== 'webu_general_card_01');
                $isProductSingleSection = $isProductPage && in_array($normalizedKey, $productSingleSectionKeys, true);
                $openProductSingle = $isProductSingleSection && $normalizedKey === 'webu_ecom_product_gallery_01';
                $closeProductSingle = $isProductSingleSection && $normalizedKey === 'webu_ecom_add_to_cart_button_01';
                $hideHeadOnProductPage = $isProductPage && in_array($normalizedKey, $productSingleHideHeadKeys, true);
            @endphp
            @if($openCardsRow)<div class="cards-row">@endif
            @if($openProductSingle)<div class="product-single-layout">@endif
            @php
                $variantInfo = $section['variant'] ?? [];
                $variantLayout = is_array($variantInfo) ? ($variantInfo['layout'] ?? '') : '';
                $variantStyle = is_array($variantInfo) ? ($variantInfo['style'] ?? '') : '';
                $variantLayoutClass = $variantLayout !== '' ? ' section--layout-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($variantLayout)) : '';
                $variantStyleClass = $variantStyle !== '' ? ' section--style-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($variantStyle)) : '';
                $heroVariantClass = ($normalizedKey === 'webu_general_heading_01' && ($variantLayout !== '' || $variantStyle !== '')) ? ' section--hero' . $variantLayoutClass . $variantStyleClass : '';
                $responsive = is_array($data['responsive'] ?? null) ? $data['responsive'] : [];
                $desktop = is_array($responsive['desktop'] ?? null) ? $responsive['desktop'] : [];
                $advanced = is_array($data['advanced'] ?? null) ? $data['advanced'] : [];
                $padT = trim((string) ($desktop['padding_top'] ?? $advanced['padding_top'] ?? ''));
                $padR = trim((string) ($desktop['padding_right'] ?? $advanced['padding_right'] ?? ''));
                $padB = trim((string) ($desktop['padding_bottom'] ?? $advanced['padding_bottom'] ?? ''));
                $padL = trim((string) ($desktop['padding_left'] ?? $advanced['padding_left'] ?? ''));
                $marT = trim((string) ($desktop['margin_top'] ?? $advanced['margin_top'] ?? ''));
                $marR = trim((string) ($desktop['margin_right'] ?? $advanced['margin_right'] ?? ''));
                $marB = trim((string) ($desktop['margin_bottom'] ?? $advanced['margin_bottom'] ?? ''));
                $marL = trim((string) ($desktop['margin_left'] ?? $advanced['margin_left'] ?? ''));
                $zIdx = isset($advanced['z_index']) && $advanced['z_index'] !== null && $advanced['z_index'] !== '' ? (int) $advanced['z_index'] : null;
                $customClass = trim((string) ($advanced['custom_class'] ?? ''));
                $wrapperStyleParts = array_filter([
                    $padT !== '' ? 'padding-top:' . e($padT) : null,
                    $padR !== '' ? 'padding-right:' . e($padR) : null,
                    $padB !== '' ? 'padding-bottom:' . e($padB) : null,
                    $padL !== '' ? 'padding-left:' . e($padL) : null,
                    $marT !== '' ? 'margin-top:' . e($marT) : null,
                    $marR !== '' ? 'margin-right:' . e($marR) : null,
                    $marB !== '' ? 'margin-bottom:' . e($marB) : null,
                    $marL !== '' ? 'margin-left:' . e($marL) : null,
                    $zIdx !== null ? 'z-index:' . (int) $zIdx : null,
                ]);
                $wrapperStyle = count($wrapperStyleParts) > 0 ? implode('; ', $wrapperStyleParts) : '';
                $tablet = is_array($responsive['tablet'] ?? null) ? $responsive['tablet'] : [];
                $mobile = is_array($responsive['mobile'] ?? null) ? $responsive['mobile'] : [];
                $sectionSpacingId = 'webu-section-' . (!empty($section['localId']) ? preg_replace('/[^a-z0-9_-]/', '-', $section['localId']) : (string) $loop->index);
            @endphp
            @php
                $tabletParts = array_filter([
                    trim((string) ($tablet['padding_top'] ?? '')) !== '' ? 'padding-top:' . e(trim((string) $tablet['padding_top'])) : null,
                    trim((string) ($tablet['padding_right'] ?? '')) !== '' ? 'padding-right:' . e(trim((string) $tablet['padding_right'])) : null,
                    trim((string) ($tablet['padding_bottom'] ?? '')) !== '' ? 'padding-bottom:' . e(trim((string) $tablet['padding_bottom'])) : null,
                    trim((string) ($tablet['padding_left'] ?? '')) !== '' ? 'padding-left:' . e(trim((string) $tablet['padding_left'])) : null,
                    trim((string) ($tablet['margin_top'] ?? '')) !== '' ? 'margin-top:' . e(trim((string) $tablet['margin_top'])) : null,
                    trim((string) ($tablet['margin_right'] ?? '')) !== '' ? 'margin-right:' . e(trim((string) $tablet['margin_right'])) : null,
                    trim((string) ($tablet['margin_bottom'] ?? '')) !== '' ? 'margin-bottom:' . e(trim((string) $tablet['margin_bottom'])) : null,
                    trim((string) ($tablet['margin_left'] ?? '')) !== '' ? 'margin-left:' . e(trim((string) $tablet['margin_left'])) : null,
                ]);
                $mobileParts = array_filter([
                    trim((string) ($mobile['padding_top'] ?? '')) !== '' ? 'padding-top:' . e(trim((string) $mobile['padding_top'])) : null,
                    trim((string) ($mobile['padding_right'] ?? '')) !== '' ? 'padding-right:' . e(trim((string) $mobile['padding_right'])) : null,
                    trim((string) ($mobile['padding_bottom'] ?? '')) !== '' ? 'padding-bottom:' . e(trim((string) $mobile['padding_bottom'])) : null,
                    trim((string) ($mobile['padding_left'] ?? '')) !== '' ? 'padding-left:' . e(trim((string) $mobile['padding_left'])) : null,
                    trim((string) ($mobile['margin_top'] ?? '')) !== '' ? 'margin-top:' . e(trim((string) $mobile['margin_top'])) : null,
                    trim((string) ($mobile['margin_right'] ?? '')) !== '' ? 'margin-right:' . e(trim((string) $mobile['margin_right'])) : null,
                    trim((string) ($mobile['margin_bottom'] ?? '')) !== '' ? 'margin-bottom:' . e(trim((string) $mobile['margin_bottom'])) : null,
                    trim((string) ($mobile['margin_left'] ?? '')) !== '' ? 'margin-left:' . e(trim((string) $mobile['margin_left'])) : null,
                ]);
            @endphp
            @if(count($tabletParts) > 0 || count($mobileParts) > 0)
                @push('section-spacing')
                <style>
                    @if(count($tabletParts) > 0)
                    @media (max-width: 1024px) {
                        #{{ $sectionSpacingId }} { {{ implode(' ', $tabletParts) }} }
                    }
                    @endif
                    @if(count($mobileParts) > 0)
                    @media (max-width: 640px) {
                        #{{ $sectionSpacingId }} { {{ implode(' ', $mobileParts) }} }
                    }
                    @endif
                </style>
                @endpush
            @endif
            <section id="{{ $sectionSpacingId }}" class="webu-section webu-{{ str_replace('_', '-', $normalizedKey) }} @if($variantLayout !== '') webu-variant-{{ preg_replace('/[^a-z0-9-]/', '-', strtolower($variantLayout)) }} @endif @if($variantStyle !== '') webu-variant-style-{{ preg_replace('/[^a-z0-9-]/', '-', strtolower($variantStyle)) }} @endif section {{ $isCardSection ? 'section-card' : '' }} @if($isProductSingleSection) product-single-section @endif @if($hideHeadOnProductPage && $normalizedKey === 'webu_ecom_product_tabs_01') product-single-tabs-section @endif{{ $heroVariantClass }} @if($customClass !== '') {{ $customClass }} @endif" @if($wrapperStyle !== '') style="{{ $wrapperStyle }}" @endif data-webu-section="{{ $normalizedKey }}" @if(!empty($section['localId'])) data-webu-section-local-id="{{ $section['localId'] }}" @endif @if($variantLayout !== '') data-variant-layout="{{ $variantLayout }}" @endif @if($variantStyle !== '') data-variant-style="{{ $variantStyle }}" @endif>
                <div class="webu-container">
                    @if(!$hideHeadOnProductPage)
                    <div class="section-head">
                        <h2 data-webu-field="title">{{ $label }}</h2>
                        @if($isEcommerceTemplate)
                            <span class="section-key" data-webu-field="label">{{ $normalizedKey }}</span>
                        @endif
                    </div>
                    @endif
                    @include('template-demos.partials.section-content', ['section' => $section])
                </div>
            </section>
            @if($closeProductSingle)</div>@endif
            @if($closeCardsRow)</div>@endif
                                            @endforeach
    </main>
    {{-- Responsive spacing: tablet/mobile padding and margin per section --}}
    @stack('section-spacing')

    {{-- Footer: same component as in Component Library (webu_footer_01 / footer variants); wrapper gets advanced spacing from section data --}}
    @php
        $footerData = is_array($resolvedFooterSection['data'] ?? null) ? $resolvedFooterSection['data'] : [];
        $footerResponsive = is_array($footerData['responsive'] ?? null) ? $footerData['responsive'] : [];
        $footerDesktop = is_array($footerResponsive['desktop'] ?? null) ? $footerResponsive['desktop'] : [];
        $footerAdvanced = is_array($footerData['advanced'] ?? null) ? $footerData['advanced'] : [];
        $fPadT = trim((string) ($footerDesktop['padding_top'] ?? $footerAdvanced['padding_top'] ?? ''));
        $fPadR = trim((string) ($footerDesktop['padding_right'] ?? $footerAdvanced['padding_right'] ?? ''));
        $fPadB = trim((string) ($footerDesktop['padding_bottom'] ?? $footerAdvanced['padding_bottom'] ?? ''));
        $fPadL = trim((string) ($footerDesktop['padding_left'] ?? $footerAdvanced['padding_left'] ?? ''));
        $fMarT = trim((string) ($footerDesktop['margin_top'] ?? $footerAdvanced['margin_top'] ?? ''));
        $fMarR = trim((string) ($footerDesktop['margin_right'] ?? $footerAdvanced['margin_right'] ?? ''));
        $fMarB = trim((string) ($footerDesktop['margin_bottom'] ?? $footerAdvanced['margin_bottom'] ?? ''));
        $fMarL = trim((string) ($footerDesktop['margin_left'] ?? $footerAdvanced['margin_left'] ?? ''));
        $fZIdx = isset($footerAdvanced['z_index']) && $footerAdvanced['z_index'] !== null && $footerAdvanced['z_index'] !== '' ? (int) $footerAdvanced['z_index'] : null;
        $fCustomClass = trim((string) ($footerAdvanced['custom_class'] ?? ''));
        $footerWrapperParts = array_filter([
            $fPadT !== '' ? 'padding-top:' . e($fPadT) : null,
            $fPadR !== '' ? 'padding-right:' . e($fPadR) : null,
            $fPadB !== '' ? 'padding-bottom:' . e($fPadB) : null,
            $fPadL !== '' ? 'padding-left:' . e($fPadL) : null,
            $fMarT !== '' ? 'margin-top:' . e($fMarT) : null,
            $fMarR !== '' ? 'margin-right:' . e($fMarR) : null,
            $fMarB !== '' ? 'margin-bottom:' . e($fMarB) : null,
            $fMarL !== '' ? 'margin-left:' . e($fMarL) : null,
            $fZIdx !== null ? 'z-index:' . (int) $fZIdx : null,
        ]);
        $footerWrapperStyle = count($footerWrapperParts) > 0 ? implode('; ', $footerWrapperParts) : '';
    @endphp
    <div @if($footerWrapperStyle !== '') style="{{ $footerWrapperStyle }}" @endif @if($fCustomClass !== '') class="{{ $fCustomClass }}" @endif>
        @include('template-demos.partials.section-content', [
            'section' => $resolvedFooterSection,
            'isEcommerceTemplate' => true,
            'templateSlug' => $templateSlug,
        'siteId' => $siteId,
        'draft' => $draft,
        'locale' => $locale,
        'thumb' => $thumb,
        'accountPreviewUrl' => $accountPreviewUrl,
        'selectedProductSlug' => $selectedProductSlug,
        'selectedOrderId' => $selectedOrderId,
        'sharedDemoQuery' => $sharedDemoQuery,
    ])
    </div>
</div>
@if($isEcommerceTemplate && $runtimeEcommerceEnabled && $runtimeAppConfigPayload !== null && $runtimeEcommerceScriptPayload !== null)
    <script>
        window.__APP_CONFIG__ = Object.assign({}, window.__APP_CONFIG__ || {}, @json($runtimeAppConfigPayload));
    </script>
    <script id="webby-ecommerce-runtime">{!! $runtimeEcommerceScriptPayload !!}</script>
    <script>
        (function () {
            'use strict';

            function asNodeList(value) {
                return Array.prototype.slice.call(value || []);
            }

            function money(value, currency) {
                const parsed = Number.parseFloat(String(value ?? 0));
                const safe = Number.isFinite(parsed) ? parsed : 0;
                const code = String(currency || 'GEL').toUpperCase();
                return safe.toFixed(2) + ' ' + code;
            }

            function cartItemCount(cart) {
                if (!cart || !Array.isArray(cart.items)) {
                    return 0;
                }
                return cart.items.reduce((sum, item) => {
                    const qty = Number.parseInt(String(item && item.quantity ? item.quantity : 0), 10);
                    return sum + (Number.isFinite(qty) ? qty : 0);
                }, 0);
            }

            function renderCartIconWidgets(cart) {
                asNodeList(document.querySelectorAll('[data-webby-ecommerce-cart-icon]')).forEach((node) => {
                    const count = cartItemCount(cart);
                    const total = cart && (cart.grand_total ?? cart.subtotal ?? 0);
                    const currency = cart && cart.currency ? cart.currency : 'GEL';
                    const badge = node.querySelector('[data-webu-role="ecom-cart-icon-badge"]');
                    const totalNode = node.querySelector('[data-webu-role="ecom-cart-icon-total"]');
                    if (badge) {
                        badge.textContent = String(count);
                    }
                    if (totalNode) {
                        totalNode.textContent = money(total, currency);
                    }
                });
            }

            function bindPaymentStartButtons() {
                asNodeList(document.querySelectorAll('[data-webu-role="ecom-demo-start-payment"]')).forEach((button) => {
                    if (!button || button.getAttribute('data-webu-bound') === '1') {
                        return;
                    }
                    button.setAttribute('data-webu-bound', '1');

                    button.addEventListener('click', () => {
                        const helper = window.WebbyEcommerce;
                        const status = button.parentElement ? button.parentElement.querySelector('[data-webu-role="ecom-demo-payment-status"]') : null;
                        if (!helper || typeof helper.startPayment !== 'function') {
                            if (status) status.textContent = 'Payment runtime unavailable.';
                            return;
                        }

                        const checkoutForm = document.querySelector('[data-webby-ecommerce-checkout-form][data-last-order-id]');
                        const orderId = checkoutForm ? Number.parseInt(String(checkoutForm.getAttribute('data-last-order-id') || ''), 10) : 0;
                        if (!Number.isFinite(orderId) || orderId <= 0) {
                            if (status) status.textContent = 'Place order first from checkout form.';
                            return;
                        }

                        const paymentContainer = button.closest('[data-webby-ecommerce-payment-selector]');
                        const provider = paymentContainer ? String(paymentContainer.getAttribute('data-selected-provider') || 'manual') : 'manual';
                        const method = paymentContainer ? String(paymentContainer.getAttribute('data-selected-method') || 'full') : 'full';

                        button.setAttribute('disabled', 'disabled');
                        if (status) status.textContent = 'Starting payment...';

                        helper.startPayment(orderId, {
                            provider,
                            method,
                            is_installment: method === 'installment',
                        }).then((response) => {
                            const session = response && response.payment_session ? response.payment_session : null;
                            const redirectUrl = session && session.redirect_url ? String(session.redirect_url) : '';
                            if (status) {
                                status.textContent = redirectUrl !== '' ? 'Payment session created. Redirect opened.' : 'Payment session created.';
                            }
                            if (redirectUrl !== '') {
                                window.open(redirectUrl, '_blank', 'noopener,noreferrer');
                            }
                        }).catch((error) => {
                            if (status) {
                                status.textContent = error && error.message ? error.message : 'Payment start failed.';
                            }
                        }).finally(() => {
                            button.removeAttribute('disabled');
                        });
                    });
                });
            }

            function bindManualProductAddToCart() {
                asNodeList(document.querySelectorAll('[data-webu-ecom-add-to-cart]')).forEach((container) => {
                    const button = container.querySelector('[data-webu-role="ecom-manual-add-to-cart"]');
                    if (!button || button.getAttribute('data-webu-bound') === '1') {
                        return;
                    }
                    button.setAttribute('data-webu-bound', '1');

                    button.addEventListener('click', () => {
                        const helper = window.WebbyEcommerce;
                        if (!helper || typeof helper.addCartItem !== 'function') {
                            return;
                        }

                        const productSlug = String(container.getAttribute('data-product-slug') || '');
                        if (productSlug === '') {
                            return;
                        }

                        button.setAttribute('disabled', 'disabled');
                        helper.addCartItem(null, { product_slug: productSlug, quantity: 1 })
                            .finally(() => {
                                button.removeAttribute('disabled');
                            });
                    });
                });
            }

            function onRuntimeReady() {
                bindPaymentStartButtons();
                bindManualProductAddToCart();

                const helper = window.WebbyEcommerce;
                if (!helper) {
                    return;
                }

                if (typeof helper.onCartUpdated === 'function') {
                    helper.onCartUpdated((cart) => {
                        renderCartIconWidgets(cart || null);
                    });
                }

                if (typeof helper.getCachedCart === 'function') {
                    renderCartIconWidgets(helper.getCachedCart() || null);
                }

                if (typeof helper.getCartId === 'function' && typeof helper.getCart === 'function') {
                    const cartId = helper.getCartId();
                    if (cartId) {
                        helper.getCart(cartId).then((payload) => {
                            renderCartIconWidgets(payload && payload.cart ? payload.cart : null);
                        }).catch(() => {});
                    }
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', onRuntimeReady);
            } else {
                onRuntimeReady();
            }
        })();
    </script>
@endif
@include('template-demos.partials.offcanvas-menu-script')
</body>
</html>
