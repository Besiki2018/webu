@php
    $v = $headerVariant ?? 'header-1';
    if (!preg_match('/^header-[1-7]$/', $v)) { $v = 'header-1'; }
    $headerIconSvg = static function (string $name, string $class = 'webu-header__icon'): string {
        $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $attrs = 'class="' . $safeClass . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

        return match ($name) {
            'instagram' => '<svg ' . $attrs . '><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.4" cy="6.6" r="1"></circle></svg>',
            'facebook' => '<svg ' . $attrs . '><path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v5h4v-5h3.2l.8-4H13V9c0-.8.2-1 1-1z"></path></svg>',
            'x-social' => '<svg ' . $attrs . '><path d="M5 4l14 16"></path><path d="M19 4L5 20"></path></svg>',
            'pinterest' => '<svg ' . $attrs . '><path d="M12 3a7 7 0 0 0-2.55 13.52l1.05-3.98-.44-.92c-.2-.42-.31-.9-.31-1.4 0-1.46.85-2.56 1.9-2.56.9 0 1.34.67 1.34 1.48 0 .9-.58 2.25-.88 3.5-.25 1.05.53 1.9 1.56 1.9 1.87 0 3.3-1.96 3.3-4.79 0-2.5-1.8-4.25-4.37-4.25-2.98 0-4.72 2.23-4.72 4.54 0 .9.35 1.87.79 2.39a.32.32 0 0 1 .07.31l-.31 1.27c-.05.2-.16.24-.36.15-1.33-.62-2.16-2.57-2.16-4.14 0-3.37 2.45-6.46 7.07-6.46 3.71 0 6.59 2.64 6.59 6.16 0 3.67-2.31 6.63-5.52 6.63-1.08 0-2.1-.56-2.44-1.22l-.66 2.5c-.24.92-.9 2.07-1.34 2.77A7 7 0 1 0 12 3z"></path></svg>',
            'chevron-down' => '<svg ' . $attrs . '><path d="M6 9l6 6l6-6"></path></svg>',
            'menu' => '<svg ' . $attrs . '><path d="M3 6h18"></path><path d="M3 12h18"></path><path d="M3 18h18"></path></svg>',
            'user' => '<svg ' . $attrs . '><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="8" r="4"></circle></svg>',
            'search' => '<svg ' . $attrs . '><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
            'map-pin' => '<svg ' . $attrs . '><path d="M12 21s6-5.33 6-11a6 6 0 1 0-12 0c0 5.67 6 11 6 11z"></path><circle cx="12" cy="10" r="2.2"></circle></svg>',
            'mail' => '<svg ' . $attrs . '><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6l9-6"></path></svg>',
            'phone-call' => '<svg ' . $attrs . '><path d="M22 16.92v3a2 2 0 0 1-2.18 2a19.86 19.86 0 0 1-8.63-3.07A19.5 19.5 0 0 1 5.15 12.8A19.86 19.86 0 0 1 2.08 4.18A2 2 0 0 1 4.06 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.79.63 2.64a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6.3 6.3l1.23-1.24a2 2 0 0 1 2.11-.45c.85.3 1.74.51 2.64.63A2 2 0 0 1 22 16.92z"></path><path d="M15 3a6 6 0 0 1 6 6"></path><path d="M15 7a2 2 0 0 1 2 2"></path></svg>',
            'heart' => '<svg ' . $attrs . '><path d="M12 20.5s-7-4.35-7-10.02C5 7.58 7.03 5.5 9.73 5.5c1.56 0 3 .75 3.87 1.94A4.8 4.8 0 0 1 17.47 5.5C20.17 5.5 22.2 7.58 22.2 10.48C22.2 16.15 15.2 20.5 15.2 20.5H12z"></path></svg>',
            'bag' => '<svg ' . $attrs . '><path d="M7 9V7a5 5 0 0 1 10 0v2"></path><path d="M5 9h14l-1 11H6L5 9z"></path></svg>',
            'cart' => '<svg ' . $attrs . '><circle cx="9" cy="20" r="1.3"></circle><circle cx="17" cy="20" r="1.3"></circle><path d="M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L21 7H7"></path></svg>',
            'smartphone' => '<svg ' . $attrs . '><rect x="7" y="2.5" width="10" height="19" rx="2"></rect><path d="M11 18h2"></path></svg>',
            'headphones' => '<svg ' . $attrs . '><path d="M4 13a8 8 0 0 1 16 0"></path><rect x="3" y="12" width="4" height="8" rx="1.5"></rect><rect x="17" y="12" width="4" height="8" rx="1.5"></rect><path d="M7 20a3 3 0 0 0 3 3h4"></path></svg>',
            'badge-percent' => '<svg ' . $attrs . '><path d="M9 8h.01"></path><path d="M15 16h.01"></path><path d="M8 16l8-8"></path><path d="M12.6 2.7l7.7 4.4v8.8l-7.7 4.4l-7.7-4.4V7.1l7.7-4.4z"></path></svg>',
            default => '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"></circle></svg>',
        };
    };
    $stripText = $headerStripText ?? '';
    if ($stripText === '' && $v === 'header-5') {
        $stripText = 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
    }
    if ($stripText === '' && $v === 'header-6') {
        $stripText = 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
    }
    if ($stripText === '' && $v === 'header-7') {
        $stripText = 'FREE SHIPPING ON ALL U.S. ORDERS $50+';
    }
    $stripRightLinks = $headerStripRightLinks ?? [['label' => 'SIGN IN', 'url' => '#'], ['label' => 'CONTACT US', 'url' => '#'], ['label' => 'FAQ', 'url' => '#']];
    $sectionKey = $sectionKey ?? 'webu_header_01';
    $resolveDemoHref = static function (?string $url) use ($templateSlug, $sharedDemoQuery): string {
        $target = trim((string) $url);
        if ($target === '' || $target === '#') { return '#'; }
        if (str_starts_with($target, 'http') || str_starts_with($target, 'mailto:') || str_starts_with($target, 'tel:')) {
            return $target;
        }
        $path = trim($target, '/');

        return route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => $path !== '' ? $path : null] + ($sharedDemoQuery ?? []) + ['slug' => $path !== '' ? $path : 'home']);
    };

    $headerLogoImageUrl = trim((string) ($headerLogoImageUrl ?? ''));
    $announcementText = trim((string) ($headerAnnouncementText ?? $stripText));
    $announcementCtaLabel = trim((string) ($headerAnnouncementCtaLabel ?? ''));
    $announcementCtaUrl = trim((string) ($headerAnnouncementCtaUrl ?? ''));
    $topBarLoginLabel = trim((string) ($headerTopBarLoginLabel ?? ''));
    $topBarLoginUrl = trim((string) ($headerTopBarLoginUrl ?? '/account/login'));
    $topBarSocialLinks = is_array($headerTopBarSocialLinks ?? null) ? $headerTopBarSocialLinks : [];
    $topBarLocationText = trim((string) ($headerTopBarLocationText ?? ''));
    $topBarLocationUrl = trim((string) ($headerTopBarLocationUrl ?? '#'));
    $topBarEmailText = trim((string) ($headerTopBarEmailText ?? ''));
    $topBarEmailUrl = trim((string) ($headerTopBarEmailUrl ?? ''));
    $headerHotlineEyebrow = trim((string) ($headerHotlineEyebrow ?? ''));
    $headerHotlineLabel = trim((string) ($headerHotlineLabel ?? ''));
    $headerHotlineUrl = trim((string) ($headerHotlineUrl ?? ''));
    $topBarLeftText = trim((string) ($headerTopBarLeftText ?? ''));
    $topBarLeftCta = trim((string) ($headerTopBarLeftCta ?? ''));
    $topBarLeftCtaUrl = trim((string) ($headerTopBarLeftCtaUrl ?? ''));
    $socialFollowers = trim((string) ($headerSocialFollowers ?? ''));
    $socialUrl = trim((string) ($headerSocialUrl ?? '#'));
    $topBarRightTracking = trim((string) ($headerTopBarRightTracking ?? ''));
    $topBarRightTrackingUrl = trim((string) ($headerTopBarRightTrackingUrl ?? '#'));
    $topBarRightLang = trim((string) ($headerTopBarRightLang ?? ''));
    $topBarRightCurrency = trim((string) ($headerTopBarRightCurrency ?? ''));
    $headerAccountUrl = trim((string) ($headerAccountUrl ?? '/account'));
    $headerSearchUrl = trim((string) ($headerSearchUrl ?? '/search'));
    $headerSearchPlaceholder = trim((string) ($headerSearchPlaceholder ?? ''));
    $headerSearchCategoryLabel = trim((string) ($headerSearchCategoryLabel ?? ''));
    $headerSearchButtonLabel = trim((string) ($headerSearchButtonLabel ?? ''));
    $headerWishlistUrl = trim((string) ($headerWishlistUrl ?? '/wishlist'));
    $headerCartUrl = trim((string) ($headerCartUrl ?? '/cart'));
    $headerDepartmentLabel = trim((string) ($headerDepartmentLabel ?? ''));
    $headerDepartmentMenu = is_array($headerDepartmentMenu ?? null) ? $headerDepartmentMenu : [];
    $headerPromoEyebrow = trim((string) ($headerPromoEyebrow ?? ''));
    $headerPromoLabel = trim((string) ($headerPromoLabel ?? ''));
    $headerPromoUrl = trim((string) ($headerPromoUrl ?? ''));
    $headerAccountEyebrow = trim((string) ($headerAccountEyebrow ?? ''));
    $headerAccountLabel = trim((string) ($headerAccountLabel ?? ''));
    $headerCartLabel = trim((string) ($headerCartLabel ?? ''));
    $menuDrawerSideRaw = strtolower(trim((string) ($headerMenuDrawerSide ?? 'left')));
    $menuDrawerSide = in_array($menuDrawerSideRaw, ['left', 'right'], true) ? $menuDrawerSideRaw : 'left';
    $menuDrawerTitle = trim((string) ($headerMenuDrawerTitle ?? ''));
    $menuDrawerSubtitle = trim((string) ($headerMenuDrawerSubtitle ?? ''));
    $wishlistCount = is_numeric($headerWishlistCount ?? null) ? (int) $headerWishlistCount : null;
    $cartCount = is_numeric($headerCartCount ?? null) ? (int) $headerCartCount : null;
    $cartTotal = trim((string) ($headerCartTotal ?? ''));

    if ($v === 'header-3') {
        if ($topBarLoginLabel === '') {
            $topBarLoginLabel = 'Log In';
        }
        if ($topBarSocialLinks === []) {
            $topBarSocialLinks = [
                ['label' => 'Facebook', 'url' => 'https://facebook.com', 'icon' => 'facebook'],
                ['label' => 'X', 'url' => 'https://x.com', 'icon' => 'x'],
                ['label' => 'Instagram', 'url' => 'https://instagram.com', 'icon' => 'instagram'],
                ['label' => 'Pinterest', 'url' => 'https://pinterest.com', 'icon' => 'pinterest'],
            ];
        }
        if ($topBarLocationText === '') {
            $topBarLocationText = 'Location: 57 Park Ave, New York';
        }
        if ($topBarLocationUrl === '#') {
            $topBarLocationUrl = '/contact';
        }
        if ($topBarEmailText === '') {
            $topBarEmailText = 'Mail: info@gmail.com';
        }
        if ($topBarEmailUrl === '') {
            $topBarEmailUrl = 'mailto:info@gmail.com';
        }
        if ($headerHotlineEyebrow === '') {
            $headerHotlineEyebrow = 'Hotline';
        }
        if ($headerHotlineLabel === '') {
            $headerHotlineLabel = '+123-7767-8989';
        }
        if ($headerHotlineUrl === '') {
            $headerHotlineUrl = 'tel:+12377678989';
        }
        if ($headerSearchUrl === '') {
            $headerSearchUrl = '/search';
        }
        if ($menuDrawerTitle === '') {
            $menuDrawerTitle = $headerLogo;
        }
        if ($menuDrawerSubtitle === '') {
            $menuDrawerSubtitle = 'Browse services, pages and utility links from the Finwave navigation.';
        }
    }

    if ($v === 'header-4') {
        if ($stripRightLinks === [['label' => 'SIGN IN', 'url' => '#'], ['label' => 'CONTACT US', 'url' => '#'], ['label' => 'FAQ', 'url' => '#']]) {
            $stripRightLinks = [
                ['label' => 'About Us', 'url' => '/about'],
                ['label' => 'My account', 'url' => '/account'],
                ['label' => 'Featured Products', 'url' => '/shop'],
                ['label' => 'Wishlist', 'url' => '/wishlist'],
            ];
        }
        if ($topBarRightTracking === '') {
            $topBarRightTracking = 'Order Tracking';
        }
        if ($topBarRightTrackingUrl === '#') {
            $topBarRightTrackingUrl = '/account/orders';
        }
        if ($topBarRightLang === '') {
            $topBarRightLang = 'English';
        }
        if ($topBarRightCurrency === '') {
            $topBarRightCurrency = 'USD';
        }
        if ($headerSearchPlaceholder === '') {
            $headerSearchPlaceholder = 'Search your favorite product...';
        }
        if ($headerSearchCategoryLabel === '') {
            $headerSearchCategoryLabel = 'All';
        }
        if ($headerSearchButtonLabel === '') {
            $headerSearchButtonLabel = 'Search';
        }
        if ($headerDepartmentLabel === '') {
            $headerDepartmentLabel = 'All Departments';
        }
        if ($headerPromoEyebrow === '') {
            $headerPromoEyebrow = 'Only this weekend';
        }
        if ($headerPromoLabel === '') {
            $headerPromoLabel = 'Super Discount';
        }
        if ($headerPromoUrl === '') {
            $headerPromoUrl = '/shop';
        }
        if ($headerAccountEyebrow === '') {
            $headerAccountEyebrow = 'Sign In';
        }
        if ($headerAccountLabel === '') {
            $headerAccountLabel = 'Account';
        }
        if ($headerCartLabel === '') {
            $headerCartLabel = 'Total';
        }
        if ($wishlistCount === null) {
            $wishlistCount = 16;
        }
        if ($cartCount === null) {
            $cartCount = 0;
        }
        if ($cartTotal === '') {
            $cartTotal = '$0.00';
        }
        if ($menuDrawerTitle === '') {
            $menuDrawerTitle = $headerDepartmentLabel;
        }
        if ($menuDrawerSubtitle === '') {
            $menuDrawerSubtitle = 'Browse departments, highlighted collections and key shopping destinations.';
        }
    }

    if ($v === 'header-5') {
        if ($announcementText === '') {
            $announcementText = $stripText !== '' ? $stripText : 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
        }
        if ($announcementCtaLabel === '') {
            $announcementCtaLabel = 'SHOP NOW';
        }
        if ($announcementCtaUrl === '') {
            $announcementCtaUrl = '/shop';
        }
        if ($wishlistCount === null) {
            $wishlistCount = 0;
        }
        if ($cartCount === null) {
            $cartCount = 0;
        }
        if ($cartTotal === '') {
            $cartTotal = '$0.00';
        }
        if ($menuDrawerTitle === '') {
            $menuDrawerTitle = $headerLogo;
        }
        if ($menuDrawerSubtitle === '') {
            $menuDrawerSubtitle = 'Browse featured departments, collections and main navigation links.';
        }
    }

    if ($v === 'header-6') {
        if ($announcementText === '') {
            $announcementText = 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
        }
        if ($announcementCtaLabel === '') {
            $announcementCtaLabel = 'SHOP NOW';
        }
        if ($announcementCtaUrl === '') {
            $announcementCtaUrl = '/shop';
        }
        if ($topBarLeftText === '') {
            $topBarLeftText = 'Free Shipping World wide for all orders over $199.';
        }
        if ($topBarLeftCta === '') {
            $topBarLeftCta = 'Click and Shop Now.';
        }
        if ($topBarLeftCtaUrl === '') {
            $topBarLeftCtaUrl = '/shop';
        }
        if ($socialFollowers === '') {
            $socialFollowers = '3.1M Followers';
        }
        if ($topBarRightTracking === '') {
            $topBarRightTracking = 'Order Tracking';
        }
        if ($topBarRightLang === '') {
            $topBarRightLang = 'English';
        }
        if ($topBarRightCurrency === '') {
            $topBarRightCurrency = 'USD';
        }
        if ($topBarRightTrackingUrl === '#') {
            $topBarRightTrackingUrl = '/account/orders';
        }
        if ($wishlistCount === null) {
            $wishlistCount = 4;
        }
        if ($cartCount === null) {
            $cartCount = 0;
        }
        if ($cartTotal === '') {
            $cartTotal = '$0.00';
        }
        if ($menuDrawerTitle === '') {
            $menuDrawerTitle = $headerLogo;
        }
        if ($menuDrawerSubtitle === '') {
            $menuDrawerSubtitle = 'Browse featured departments, collections and utility pages.';
        }
    }

    $menuDrawerItems = [];
    foreach ($headerMenu ?? [] as $item) {
        if (! is_array($item)) {
            continue;
        }

        $label = trim((string) ($item['label'] ?? 'Menu item'));
        $url = trim((string) ($item['url'] ?? '#'));
        $menuDrawerItems[] = [
            'label' => $label !== '' ? $label : 'Menu item',
            'href' => $resolveDemoHref($url !== '' ? $url : '#'),
            'description' => in_array(strtoupper($label), ['HOME', 'SHOP'], true)
                ? 'Highlighted navigation item'
                : 'Open page',
        ];
    }
    $departmentDrawerItems = [];
    foreach ($headerDepartmentMenu as $item) {
        if (! is_array($item)) {
            continue;
        }

        $label = trim((string) ($item['label'] ?? 'Menu item'));
        $url = trim((string) ($item['url'] ?? '#'));
        $description = trim((string) ($item['description'] ?? ''));
        if ($label === '') {
            continue;
        }

        $departmentDrawerItems[] = [
            'label' => $label,
            'href' => $resolveDemoHref($url !== '' ? $url : '#'),
            'description' => $description !== ''
                ? $description
                : 'Open page',
        ];
    }
    $headerOffcanvasSeedItems = $departmentDrawerItems !== [] ? $departmentDrawerItems : $menuDrawerItems;
    $headerOffcanvasId = 'webu-offcanvas-' . substr(md5($sectionKey . $v . json_encode($headerOffcanvasSeedItems)), 0, 10);
@endphp
{{-- header-1: Classic e-commerce — clean, logo left, nav center, CTA right --}}
@if($v === 'header-1')
<header class="webu-header webu-header--header-1" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__inner">
        <a href="{{ $headerLogoHref }}" class="webu-header__logo">@if($headerLogoImageUrl !== '')<img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />@else{{ $headerLogo }}@endif</a>
        <nav class="webu-nav" aria-label="Main">
            @foreach($headerMenu as $item)
                <a class="webu-nav__link" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $item['label'] ?? '' }}</a>
            @endforeach
        </nav>
        @if($headerCtaLabel !== '' && $headerCtaHref !== '')<a href="{{ $headerCtaHref }}" class="webu-header__cta">{{ $headerCtaLabel }}</a>@endif
    </div>
</header>
@endif

{{-- header-2: Compact — tighter padding, smaller type --}}
@if($v === 'header-2')
<header class="webu-header webu-header--header-2" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__inner">
        <a href="{{ $headerLogoHref }}" class="webu-header__logo">@if($headerLogoImageUrl !== '')<img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />@else{{ $headerLogo }}@endif</a>
        <nav class="webu-nav webu-nav--compact" aria-label="Main">
            @foreach($headerMenu as $item)
                <a class="webu-nav__link" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $item['label'] ?? '' }}</a>
            @endforeach
        </nav>
        @if($headerCtaLabel !== '' && $headerCtaHref !== '')<a href="{{ $headerCtaHref }}" class="webu-header__cta webu-header__cta--sm">{{ $headerCtaLabel }}</a>@endif
    </div>
</header>
@endif

{{-- header-3: Finwave-style utility strip + centered nav + hotline/search/menu actions --}}
@if($v === 'header-3')
<header class="webu-header webu-header--header-3 webu-header--finwave" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__finwave-topbar">
        <div class="webu-header__finwave-topbar-inner">
            <div class="webu-header__finwave-topbar-left">
                <a href="{{ $resolveDemoHref($topBarLoginUrl) }}" class="webu-header__finwave-login">
                    {!! $headerIconSvg('user', 'webu-header__icon') !!}
                    <span>{{ $topBarLoginLabel }}</span>
                </a>
                @foreach($topBarSocialLinks as $item)
                    @php
                        $socialLabel = trim((string) ($item['label'] ?? 'Social'));
                        $socialUrlItem = trim((string) ($item['url'] ?? '#'));
                        $socialIcon = strtolower(trim((string) ($item['icon'] ?? 'instagram')));
                        $socialIconName = match ($socialIcon) {
                            'facebook' => 'facebook',
                            'x', 'twitter', 'twitter-x' => 'x-social',
                            'pinterest' => 'pinterest',
                            default => 'instagram',
                        };
                    @endphp
                    <a href="{{ $resolveDemoHref($socialUrlItem) }}" class="webu-header__finwave-social-link" aria-label="{{ $socialLabel }}">
                        {!! $headerIconSvg($socialIconName, 'webu-header__icon') !!}
                    </a>
                @endforeach
            </div>
            <div class="webu-header__finwave-topbar-right">
                @if($topBarLocationText !== '')
                    <a href="{{ $resolveDemoHref($topBarLocationUrl) }}" class="webu-header__finwave-meta-link">
                        {!! $headerIconSvg('map-pin', 'webu-header__icon') !!}
                        <span>{{ $topBarLocationText }}</span>
                    </a>
                @endif
                @if($topBarLocationText !== '' && $topBarEmailText !== '')
                    <span class="webu-header__finwave-meta-separator" aria-hidden="true"></span>
                @endif
                @if($topBarEmailText !== '')
                    <a href="{{ $resolveDemoHref($topBarEmailUrl !== '' ? $topBarEmailUrl : '#') }}" class="webu-header__finwave-meta-link">
                        {!! $headerIconSvg('mail', 'webu-header__icon') !!}
                        <span>{{ $topBarEmailText }}</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="webu-header__finwave-main">
        <div class="webu-header__finwave-main-inner">
            <a href="{{ $headerLogoHref }}" class="webu-header__logo webu-header__logo--header-3" aria-label="{{ $headerLogo }}">
                @if($headerLogoImageUrl !== '')
                    <img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />
                @else
                    <span class="webu-header__finwave-mark" aria-hidden="true">
                        <span class="webu-header__finwave-mark-dot"></span>
                    </span>
                    <span class="webu-header__finwave-wordmark">{{ $headerLogo }}</span>
                @endif
            </a>
            <nav class="webu-nav webu-nav--header-3-finwave" aria-label="Main">
                @foreach($headerMenu as $item)
                    @php
                        $menuLabel = trim((string) ($item['label'] ?? ''));
                        $showDropdown = $loop->index < max(count($headerMenu) - 1, 0);
                    @endphp
                    <span class="webu-nav__item-wrap webu-nav__item-wrap--header-3">
                        <a class="webu-nav__link webu-nav__link--header-3 {{ $loop->first ? 'is-active' : '' }}" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $menuLabel }}</a>
                        @if($showDropdown)
                            {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--nav') !!}
                        @endif
                    </span>
                @endforeach
            </nav>
            <div class="webu-header__finwave-actions">
                <a href="{{ $resolveDemoHref($headerSearchUrl) }}" class="webu-header__finwave-search" aria-label="Search">
                    {!! $headerIconSvg('search', 'webu-header__icon') !!}
                </a>
                <a href="{{ $resolveDemoHref($headerHotlineUrl) }}" class="webu-header__finwave-hotline">
                    <span class="webu-header__finwave-hotline-icon">
                        {!! $headerIconSvg('phone-call', 'webu-header__icon') !!}
                    </span>
                    <span class="webu-header__finwave-hotline-copy">
                        <span class="webu-header__finwave-hotline-eyebrow">{{ $headerHotlineEyebrow }}</span>
                        <span class="webu-header__finwave-hotline-label">{{ $headerHotlineLabel }}</span>
                    </span>
                </a>
                <button
                    type="button"
                    class="webu-header__finwave-menu-btn"
                    aria-label="Open menu"
                    aria-controls="{{ $headerOffcanvasId }}"
                    aria-expanded="false"
                    data-webu-offcanvas-trigger="{{ $headerOffcanvasId }}"
                >
                    {!! $headerIconSvg('menu', 'webu-header__icon') !!}
                </button>
            </div>
        </div>
    </div>
</header>
@include('template-demos.partials.offcanvas-menu', [
    'panelId' => $headerOffcanvasId,
    'panelTitle' => $menuDrawerTitle,
    'panelSubtitle' => $menuDrawerSubtitle,
    'panelSide' => $menuDrawerSide,
    'panelItems' => $menuDrawerItems,
    'panelFooterLabel' => 'Contact us',
    'panelFooterHref' => $resolveDemoHref('/contact'),
    'showPanelClose' => true,
    'previewMode' => false,
    'openByDefault' => false,
])
@endif

{{-- header-4: Machic-style utility + search + category rail --}}
@if($v === 'header-4')
<header class="webu-header webu-header--header-4 webu-header--machic" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__utility webu-header__utility--header-4">
        <div class="webu-header__utility-left webu-header__utility-left--header-4">
            @foreach(($stripRightLinks ?? []) as $item)
                @php
                    $utilityLabel = trim((string) ($item['label'] ?? ''));
                    $utilityUrl = trim((string) ($item['url'] ?? '#'));
                @endphp
                @if($utilityLabel !== '')
                    <a href="{{ $resolveDemoHref($utilityUrl !== '' ? $utilityUrl : '#') }}" class="webu-header__utility-link webu-header__utility-link--header-4">{{ $utilityLabel }}</a>
                @endif
            @endforeach
        </div>
        <div class="webu-header__utility-right webu-header__utility-right--header-4">
            <a href="{{ $resolveDemoHref($topBarRightTrackingUrl) }}" class="webu-header__utility-item">{{ $topBarRightTracking }}</a>
            <span class="webu-header__utility-item webu-header__utility-item--dropdown">
                {{ $topBarRightLang }}
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </span>
            <span class="webu-header__utility-item webu-header__utility-item--dropdown">
                {{ $topBarRightCurrency }}
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </span>
        </div>
    </div>
    <div class="webu-header__search-row">
        <a href="{{ $headerLogoHref }}" class="webu-header__logo webu-header__logo--header-4" aria-label="{{ $headerLogo }}">
            @if($headerLogoImageUrl !== '')
                <img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />
            @else
                <span class="webu-header__machic-mark" aria-hidden="true"></span>
                <span class="webu-header__machic-wordmark">{{ $headerLogo }}</span>
            @endif
        </a>
        <form action="{{ $resolveDemoHref($headerSearchUrl) }}" class="webu-header__search-shell" method="get" role="search">
            <button type="button" class="webu-header__search-scope" aria-label="Select category">
                <span>{{ $headerSearchCategoryLabel }}</span>
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </button>
            <span class="webu-header__search-icon-wrap" aria-hidden="true">
                {!! $headerIconSvg('search', 'webu-header__icon') !!}
            </span>
            <input type="search" name="q" class="webu-header__search-input" placeholder="{{ $headerSearchPlaceholder }}" aria-label="{{ $headerSearchPlaceholder }}" />
            <button type="submit" class="webu-header__search-submit">{{ $headerSearchButtonLabel }}</button>
        </form>
        <div class="webu-header__actions webu-header__actions--header-4">
            <a href="{{ $resolveDemoHref($headerAccountUrl) }}" class="webu-header__account-link" aria-label="{{ $headerAccountLabel }}">
                <span class="webu-header__account-icon-wrap">
                    {!! $headerIconSvg('user', 'webu-header__icon') !!}
                </span>
                <span class="webu-header__account-copy">
                    <span class="webu-header__account-eyebrow">{{ $headerAccountEyebrow }}</span>
                    <span class="webu-header__account-label">{{ $headerAccountLabel }}</span>
                </span>
            </a>
            <a href="{{ $resolveDemoHref($headerWishlistUrl) }}" class="webu-header__action-icon-btn webu-header__action-icon-btn--badge" aria-label="Wishlist" data-badge="{{ $wishlistCount }}">
                {!! $headerIconSvg('heart', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerCartUrl) }}" class="webu-header__action-cart webu-header__action-cart--header-4" aria-label="Cart">
                <span class="webu-header__cart-icon-wrap">
                    {!! $headerIconSvg('cart', 'webu-header__icon') !!}
                    <span class="webu-header__cart-badge" data-badge="{{ $cartCount }}"></span>
                </span>
                <span class="webu-header__cart-copy">
                    <span class="webu-header__cart-label">{{ $headerCartLabel }}</span>
                    <span class="webu-header__cart-total">{{ $cartTotal }}</span>
                </span>
            </a>
        </div>
    </div>
    <div class="webu-header__nav-row">
        <button
            type="button"
            class="webu-header__department-trigger"
            aria-label="Open departments menu"
            aria-controls="{{ $headerOffcanvasId }}"
            aria-expanded="false"
            data-webu-offcanvas-trigger="{{ $headerOffcanvasId }}"
        >
            <span class="webu-header__department-trigger-icon">
                {!! $headerIconSvg('menu', 'webu-header__icon') !!}
            </span>
            <span class="webu-header__department-trigger-label">{{ $headerDepartmentLabel }}</span>
            {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
        </button>
        <nav class="webu-nav webu-nav--header-4" aria-label="Main">
            @foreach($headerMenu as $item)
                @php
                    $menuLabel = trim((string) ($item['label'] ?? ''));
                    $menuUpper = strtoupper($menuLabel);
                    $showDropdown = in_array($menuUpper, ['HOME', 'SHOP'], true) || $loop->index < 2;
                    $navIcon = match($menuUpper) {
                        'CELL PHONES' => 'smartphone',
                        'HEADPHONES' => 'headphones',
                        default => null,
                    };
                @endphp
                <span class="webu-nav__item-wrap webu-nav__item-wrap--header-4">
                    @if($navIcon)
                        {!! $headerIconSvg($navIcon, 'webu-header__icon webu-header__icon--menu-feature') !!}
                    @endif
                    <a class="webu-nav__link webu-nav__link--header-4 {{ $loop->first ? 'is-active' : '' }}" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $menuLabel }}</a>
                    @if($showDropdown)
                        {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--nav') !!}
                    @endif
                </span>
            @endforeach
        </nav>
        <a href="{{ $resolveDemoHref($headerPromoUrl) }}" class="webu-header__promo-link">
            <span class="webu-header__promo-icon">
                {!! $headerIconSvg('badge-percent', 'webu-header__icon') !!}
            </span>
            <span class="webu-header__promo-copy">
                <span class="webu-header__promo-eyebrow">{{ $headerPromoEyebrow }}</span>
                <span class="webu-header__promo-label">{{ $headerPromoLabel }}</span>
            </span>
            {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
        </a>
    </div>
</header>
@include('template-demos.partials.offcanvas-menu', [
    'panelId' => $headerOffcanvasId,
    'panelTitle' => $menuDrawerTitle,
    'panelSubtitle' => $menuDrawerSubtitle,
    'panelSide' => $menuDrawerSide,
    'panelItems' => $departmentDrawerItems !== [] ? $departmentDrawerItems : $menuDrawerItems,
    'panelFooterLabel' => $headerPromoLabel !== '' ? $headerPromoLabel : 'Shop all',
    'panelFooterHref' => $resolveDemoHref($headerPromoUrl !== '' ? $headerPromoUrl : '/shop'),
    'showPanelClose' => true,
    'previewMode' => false,
    'openByDefault' => false,
])
@endif

{{-- header-5: Clotya-style split header — announcement + centered logo + left nav/right icons --}}
@if($v === 'header-5')
<header class="webu-header webu-header--header-5 webu-header--clotya-minimal" data-webu-section="{{ $sectionKey }}">
    @if($announcementText !== '')
    <div class="webu-header__announcement">
        <span class="webu-header__announcement-text">
            {{ $announcementText }}
            @if($announcementCtaLabel !== '')
                <a href="{{ $resolveDemoHref($announcementCtaUrl) }}" class="webu-header__announcement-cta">{{ $announcementCtaLabel }}</a>
            @endif
        </span>
    </div>
    @endif
    <div class="webu-header__inner webu-header__inner--header-5">
        <div class="webu-header__main-left">
            <button
                type="button"
                class="webu-header__menu-btn"
                aria-label="Open menu"
                aria-controls="{{ $headerOffcanvasId }}"
                aria-expanded="false"
                data-webu-offcanvas-trigger="{{ $headerOffcanvasId }}"
            >
                {!! $headerIconSvg('menu', 'webu-header__icon') !!}
            </button>
            <nav class="webu-nav webu-nav--header-5" aria-label="Main">
                @foreach($headerMenu as $item)
                    @php
                        $menuLabel = trim((string) ($item['label'] ?? ''));
                        $showDropdown = in_array(strtoupper($menuLabel), ['HOME', 'SHOP'], true) || $loop->index < 2;
                    @endphp
                    <span class="webu-nav__item-wrap">
                        <a class="webu-nav__link webu-nav__link--header-5" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $menuLabel }}</a>
                        @if($showDropdown)
                            {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--nav') !!}
                        @endif
                    </span>
                @endforeach
            </nav>
        </div>
        <a href="{{ $headerLogoHref }}" class="webu-header__logo webu-header__logo--header-5">@if($headerLogoImageUrl !== '')<img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />@else{{ $headerLogo }}@endif</a>
        <div class="webu-header__actions webu-header__actions--header-5">
            <a href="{{ $resolveDemoHref($headerAccountUrl) }}" class="webu-header__action-icon-btn" aria-label="Account">
                {!! $headerIconSvg('user', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerSearchUrl) }}" class="webu-header__action-icon-btn" aria-label="Search">
                {!! $headerIconSvg('search', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerWishlistUrl) }}" class="webu-header__action-icon-btn webu-header__action-icon-btn--badge" aria-label="Wishlist" data-badge="{{ $wishlistCount }}">
                {!! $headerIconSvg('heart', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerCartUrl) }}" class="webu-header__action-cart" aria-label="Cart">
                <span class="webu-header__cart-total">{{ $cartTotal }}</span>
                <span class="webu-header__cart-icon-wrap">
                    {!! $headerIconSvg('bag', 'webu-header__icon') !!}
                    <span class="webu-header__cart-badge" data-badge="{{ $cartCount }}"></span>
                </span>
            </a>
        </div>
    </div>
</header>
@include('template-demos.partials.offcanvas-menu', [
    'panelId' => $headerOffcanvasId,
    'panelTitle' => $menuDrawerTitle,
    'panelSubtitle' => $menuDrawerSubtitle,
    'panelSide' => $menuDrawerSide,
    'panelItems' => $menuDrawerItems,
    'panelFooterLabel' => $announcementCtaLabel !== '' ? $announcementCtaLabel : 'SHOP NOW',
    'panelFooterHref' => $resolveDemoHref($announcementCtaUrl !== '' ? $announcementCtaUrl : '/shop'),
    'showPanelClose' => true,
    'previewMode' => false,
    'openByDefault' => false,
])
@endif

{{-- header-6: Clotya-style 3-bar header — announcement, utility, main nav --}}
@if($v === 'header-6')
<header class="webu-header webu-header--header-6 webu-header--clotya" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__announcement">
        <span class="webu-header__announcement-text">
            {{ $announcementText }}
            @if($announcementCtaLabel !== '')
                <a href="{{ $resolveDemoHref($announcementCtaUrl) }}" class="webu-header__announcement-cta">{{ $announcementCtaLabel }}</a>
            @endif
        </span>
    </div>
    <div class="webu-header__utility">
        <div class="webu-header__utility-left">
            <a href="{{ $resolveDemoHref($socialUrl) }}" class="webu-header__utility-social" aria-label="Instagram">
                {!! $headerIconSvg('instagram', 'webu-header__icon') !!}
            </a>
            <span class="webu-header__utility-followers">
                {{ $socialFollowers }}
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </span>
            @if($topBarLeftText !== '')
                <span class="webu-header__utility-shipping">{{ $topBarLeftText }}</span>
            @endif
            @if($topBarLeftCta !== '')
                <a href="{{ $resolveDemoHref($topBarLeftCtaUrl) }}" class="webu-header__utility-cta">{{ $topBarLeftCta }}</a>
            @endif
        </div>
        <div class="webu-header__utility-right">
            <a href="{{ $resolveDemoHref($topBarRightTrackingUrl) }}" class="webu-header__utility-item">{{ $topBarRightTracking }}</a>
            <span class="webu-header__utility-item webu-header__utility-item--dropdown">
                {{ $topBarRightLang }}
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </span>
            <span class="webu-header__utility-item webu-header__utility-item--dropdown">
                {{ $topBarRightCurrency }}
                {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--chevron') !!}
            </span>
        </div>
    </div>
    <div class="webu-header__inner webu-header__inner--header-6">
        <div class="webu-header__main-left">
            <button
                type="button"
                class="webu-header__menu-btn"
                aria-label="Open menu"
                aria-controls="{{ $headerOffcanvasId }}"
                aria-expanded="false"
                data-webu-offcanvas-trigger="{{ $headerOffcanvasId }}"
            >
                {!! $headerIconSvg('menu', 'webu-header__icon') !!}
            </button>
            <a href="{{ $headerLogoHref }}" class="webu-header__logo webu-header__logo--header-6">@if($headerLogoImageUrl !== '')<img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />@else{{ $headerLogo }}@endif</a>
            <nav class="webu-nav webu-nav--header-6" aria-label="Main">
                @foreach($headerMenu as $item)
                    @php
                        $menuLabel = trim((string) ($item['label'] ?? ''));
                        $showDropdown = in_array(strtoupper($menuLabel), ['HOME', 'SHOP'], true) || $loop->index < 2;
                    @endphp
                    <span class="webu-nav__item-wrap">
                        <a class="webu-nav__link webu-nav__link--header-6" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $menuLabel }}</a>
                        @if($showDropdown)
                            {!! $headerIconSvg('chevron-down', 'webu-header__icon webu-header__icon--nav') !!}
                        @endif
                    </span>
                @endforeach
            </nav>
        </div>
        <div class="webu-header__actions webu-header__actions--header-6">
            <a href="{{ $resolveDemoHref($headerAccountUrl) }}" class="webu-header__action-icon-btn" aria-label="Account">
                {!! $headerIconSvg('user', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerSearchUrl) }}" class="webu-header__action-icon-btn" aria-label="Search">
                {!! $headerIconSvg('search', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerWishlistUrl) }}" class="webu-header__action-icon-btn webu-header__action-icon-btn--badge" aria-label="Wishlist" data-badge="{{ $wishlistCount }}">
                {!! $headerIconSvg('heart', 'webu-header__icon') !!}
            </a>
            <a href="{{ $resolveDemoHref($headerCartUrl) }}" class="webu-header__action-cart" aria-label="Cart">
                <span class="webu-header__cart-icon-wrap">
                    {!! $headerIconSvg('bag', 'webu-header__icon') !!}
                    <span class="webu-header__cart-badge" data-badge="{{ $cartCount }}"></span>
                </span>
                <span class="webu-header__cart-total">{{ $cartTotal }}</span>
            </a>
        </div>
    </div>
</header>
@include('template-demos.partials.offcanvas-menu', [
    'panelId' => $headerOffcanvasId,
    'panelTitle' => $menuDrawerTitle,
    'panelSubtitle' => $menuDrawerSubtitle,
    'panelSide' => $menuDrawerSide,
    'panelItems' => $menuDrawerItems,
    'panelFooterLabel' => $announcementCtaLabel !== '' ? $announcementCtaLabel : 'SHOP NOW',
    'panelFooterHref' => $resolveDemoHref($announcementCtaUrl !== '' ? $announcementCtaUrl : '/shop'),
    'showPanelClose' => true,
    'previewMode' => false,
    'openByDefault' => false,
])
@endif

{{-- header-7: Light grey strip (social left, promo center, ENGLISH/USD right), white main — search left, centered logo with nav split, account/wishlist/cart right — GLOWING style --}}
@if($v === 'header-7')
<header class="webu-header webu-header--header-7" data-webu-section="{{ $sectionKey }}">
    <div class="webu-header__strip webu-header__strip--grey">
        <div class="webu-header__strip-left">
            <a href="#" class="webu-header__social-icon" aria-label="Facebook"><i class="ti ti-brand-facebook" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__social-icon" aria-label="Twitter"><i class="ti ti-brand-x" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__social-icon" aria-label="Vimeo"><i class="ti ti-brand-vimeo" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__social-icon" aria-label="YouTube"><i class="ti ti-brand-youtube" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__social-icon" aria-label="Pinterest"><i class="ti ti-brand-pinterest" aria-hidden="true"></i></a>
        </div>
        <span class="webu-header__strip-center">{{ $stripText }}</span>
        <div class="webu-header__strip-right webu-header__strip-right--dropdowns">
            <span class="webu-header__dropdown-label">ENGLISH</span>
            <span class="webu-header__dropdown-label">USD</span>
        </div>
    </div>
    <div class="webu-header__inner webu-header__inner--header-7">
        <div class="webu-header__search-placeholder">
            <span class="webu-header__action-icon"><i class="ti ti-search" aria-hidden="true"></i></span>
            <span class="webu-header__search-text">Search</span>
        </div>
        <div class="webu-header__center-block">
            <nav class="webu-nav webu-nav--header-7-left" aria-label="Main">
                @foreach(array_slice($headerMenu, 0, (int) ceil(count($headerMenu) / 2)) as $item)
                    <a class="webu-nav__link {{ $loop->first ? 'webu-nav__link--active' : '' }}" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $item['label'] ?? '' }}</a>
                @endforeach
            </nav>
            <a href="{{ $headerLogoHref }}" class="webu-header__logo webu-header__logo--header-7">@if($headerLogoImageUrl !== '')<img src="{{ $headerLogoImageUrl }}" alt="{{ $headerLogo }}" class="webu-header__logo-img" />@else{{ $headerLogo }}@endif</a>
            <nav class="webu-nav webu-nav--header-7-right" aria-label="Main">
                @foreach(array_slice($headerMenu, (int) ceil(count($headerMenu) / 2)) as $item)
                    <a class="webu-nav__link" href="{{ isset($item['url']) ? (str_starts_with($item['url'] ?? '', 'http') ? $item['url'] : route('template-demos.show', ['templateSlug' => $templateSlug, 'path' => trim($item['url'] ?? '', '/')] + ($sharedDemoQuery ?? []) + ['slug' => trim($item['url'] ?? '', '/') ?: 'home'])) : '#' }}">{{ $item['label'] ?? '' }}</a>
                @endforeach
            </nav>
        </div>
        <div class="webu-header__actions webu-header__actions--header-7">
            <a href="#" class="webu-header__action-icon-only" aria-label="Account"><i class="ti ti-user" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__action-icon-only webu-header__action-icon-only--badge" aria-label="Wishlist" data-badge="0"><i class="ti ti-heart" aria-hidden="true"></i></a>
            <a href="#" class="webu-header__action-icon-only webu-header__action-icon-only--badge" aria-label="Cart" data-badge="0"><i class="ti ti-shopping-cart" aria-hidden="true"></i></a>
        </div>
    </div>
</header>
@endif
